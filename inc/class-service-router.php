<?php
/**
 * Service Router - Intelligent service execution with fallback logic
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel;

/**
 * Service Router Class
 */
class Service_Router {

	/**
	 * Singleton instance
	 *
	 * @var Service_Router|null
	 */
	private static $instance = null;

	/**
	 * Provider manager instance
	 *
	 * @var Provider_Manager
	 */
	private $provider_manager;

	/**
	 * Get singleton instance
	 *
	 * @return Service_Router
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->provider_manager = Provider_Manager::get_instance();
	}

	/**
	 * Execute service with fallback logic
	 *
	 * @param string $service Service key
	 * @param string $action Action to perform
	 * @param array  $params Action parameters
	 *
	 * @return array|WP_Error
	 */
	public function execute_service( $service, $action, $params = array() ) {
		global $wpdb;

		// Get service configuration
		$service_config = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}reseller_panel_services WHERE service_key = %s",
				$service
			)
		);

		if ( ! $service_config || ! $service_config->enabled ) {
			return new \WP_Error( 'service_disabled', sprintf( __( 'Service %s is not enabled', 'ultimate-multisite' ), $service ) );
		}

		$default_provider = $service_config->default_provider;
		$fallback_provider = $service_config->fallback_provider;

		// Try primary provider first
		$primary = $this->provider_manager->get_provider( $default_provider );

		if ( $primary ) {
			$result = $this->execute_provider_action( $primary, $action, $params );

			if ( ! is_wp_error( $result ) ) {
				return $result;
			}

			// Primary failed, try fallback if available
			if ( $fallback_provider && $fallback_provider !== $default_provider ) {
				$this->log_fallback( $service, $default_provider, $fallback_provider, $result->get_error_message() );
				$this->send_admin_notification( $service, $default_provider, $fallback_provider, $result );

				$fallback = $this->provider_manager->get_provider( $fallback_provider );

				if ( $fallback ) {
					$result = $this->execute_provider_action( $fallback, $action, $params );

					if ( ! is_wp_error( $result ) ) {
						return $result;
					}
				}
			}

			return $result;
		}

		return new \WP_Error( 'no_provider', sprintf( __( 'No provider configured for service %s', 'ultimate-multisite' ), $service ) );
	}

	/**
	 * Execute provider action
	 *
	 * @param \Reseller_Panel\Interfaces\Service_Provider_Interface $provider Provider instance
	 * @param string $action Action to perform
	 * @param array  $params Action parameters
	 *
	 * @return mixed|WP_Error
	 */
	private function execute_provider_action( $provider, $action, $params ) {
		// Load provider config
		$provider->load_config();

		// Check if provider is configured
		if ( ! $provider->is_configured() ) {
			return new \WP_Error( 'provider_not_configured', sprintf( __( 'Provider %s is not configured', 'ultimate-multisite' ), $provider->get_name() ) );
		}

		// Test connection
		$connection = $provider->test_connection();

		if ( is_wp_error( $connection ) ) {
			return new \WP_Error( 'connection_failed', sprintf( __( 'Could not connect to %s: %s', 'ultimate-multisite' ), $provider->get_name(), $connection->get_error_message() ) );
		}

		// Execute the action
		// This will be expanded based on specific service/action combinations
		do_action( 'reseller_panel_execute_action', $provider, $action, $params );

		return true;
	}

	/**
	 * Log fallback event to database
	 *
	 * @param string $service Service key
	 * @param string $primary_provider Primary provider key
	 * @param string $fallback_provider Fallback provider key
	 * @param string $error_message Error message
	 *
	 * @return bool
	 */
	private function log_fallback( $service, $primary_provider, $fallback_provider, $error_message ) {
		global $wpdb;

		$log_data = array(
			'service_key' => $service,
			'primary_provider' => $primary_provider,
			'fallback_provider' => $fallback_provider,
			'error_message' => $error_message,
			'timestamp' => current_time( 'mysql' ),
		);

		// Check if fallback log table exists, create if needed
		$table_name = $wpdb->prefix . 'reseller_panel_fallback_logs';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
			$this->create_fallback_log_table();
		}

		return (bool) $wpdb->insert(
			$table_name,
			$log_data
		);
	}

	/**
	 * Create fallback log table
	 *
	 * @return void
	 */
	private function create_fallback_log_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name = $wpdb->prefix . 'reseller_panel_fallback_logs';

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			service_key varchar(50) NOT NULL,
			primary_provider varchar(50) NOT NULL,
			fallback_provider varchar(50) NOT NULL,
			error_message text,
			timestamp datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY service_key (service_key),
			KEY timestamp (timestamp)
		) {$wpdb->get_charset_collate()};";

		dbDelta( $sql );
	}

	/**
	 * Send admin notification of fallback
	 *
	 * @param string $service Service key
	 * @param string $primary_provider Primary provider key
	 * @param string $fallback_provider Fallback provider key
	 * @param WP_Error $error Error object
	 *
	 * @return bool
	 */
	private function send_admin_notification( $service, $primary_provider, $fallback_provider, $error ) {
		$admin_email = get_site_option( 'admin_email' );

		if ( ! $admin_email ) {
			return false;
		}

		$primary_name = $this->provider_manager->get_provider( $primary_provider )->get_name();
		$fallback_name = $this->provider_manager->get_provider( $fallback_provider )->get_name();
		$site_name = get_blog_option( 1, 'blogname' );

		$subject = sprintf(
			__( '[%s] Reseller Panel: Service Fallback Triggered', 'ultimate-multisite' ),
			$site_name
		);

		$message = sprintf(
			__(
				"A fallback event was triggered for the '%s' service.\n\n" .
				"Primary Provider: %s\n" .
				"Fallback Provider: %s\n" .
				"Error: %s\n\n" .
				"The request was successfully handled by the fallback provider.\n" .
				"Please investigate the primary provider to prevent future fallbacks.",
				'ultimate-multisite'
			),
			$service,
			$primary_name,
			$fallback_name,
			$error->get_error_message()
		);

		$message .= "\n\n" . __( 'Admin URL:', 'ultimate-multisite' ) . ' ' . network_admin_url( 'admin.php?page=reseller-panel-services' );

		return wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Get fallback logs
	 *
	 * @param int $limit Number of logs to retrieve
	 *
	 * @return array
	 */
	public function get_fallback_logs( $limit = 50 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'reseller_panel_fallback_logs';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
			return array();
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT %d",
				$limit
			)
		);
	}
}