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

		// Get ranked providers (new system)
		$providers_order = ! empty( $service_config->providers_order ) ? json_decode( $service_config->providers_order, true ) : array();

		// Fallback to old default/fallback columns if no providers_order set
		if ( empty( $providers_order ) ) {
			if ( ! empty( $service_config->default_provider ) ) {
				$providers_order[] = $service_config->default_provider;
			}
			if ( ! empty( $service_config->fallback_provider ) && $service_config->fallback_provider !== $service_config->default_provider ) {
				$providers_order[] = $service_config->fallback_provider;
			}
		}

		if ( empty( $providers_order ) ) {
			return new \WP_Error( 'no_provider', sprintf( __( 'No provider configured for service %s', 'ultimate-multisite' ), $service ) );
		}

		$last_error = null;
		$failed_providers = array();

		// Try each provider in ranked order
		foreach ( $providers_order as $rank => $provider_key ) {
			$provider = $this->provider_manager->get_provider( $provider_key );

			if ( ! $provider ) {
				$last_error = new \WP_Error( 'provider_not_found', sprintf( __( 'Provider %s not found', 'ultimate-multisite' ), $provider_key ) );
				$failed_providers[] = array(
					'key' => $provider_key,
					'rank' => $rank + 1,
					'error' => 'not_found',
				);
				continue;
			}

			// Log provider attempt
			\Reseller_Panel\Logger::log_info(
				'Service_Router',
				sprintf( 'Attempting provider %s (rank %d)', $provider_key, $rank + 1 ),
				array(
					'service' => $service,
					'action' => $action,
					'rank' => $rank + 1,
				)
			);

			$result = $this->execute_provider_action( $provider, $action, $params );

			if ( ! is_wp_error( $result ) ) {
				// Success! Log if fallback was used
				if ( $rank > 0 ) {
					$this->log_fallback_event( $service, $providers_order[0], $provider_key, $last_error ? $last_error->get_error_message() : 'Primary provider failed' );
					$this->send_fallback_notification( $service, $providers_order[0], $provider_key, $rank, $last_error );
				}

				return $result;
			}

			$last_error = $result;
			$failed_providers[] = array(
				'key' => $provider_key,
				'rank' => $rank + 1,
				'error' => $result->get_error_code(),
			);

			\Reseller_Panel\Logger::log_error(
				'Service_Router',
				sprintf( 'Provider %s (rank %d) failed', $provider_key, $rank + 1 ),
				array(
					'service' => $service,
					'error' => $result->get_error_code(),
					'message' => $result->get_error_message(),
				)
			);
		}

		// All providers exhausted
		$error_message = sprintf(
			__( 'All providers exhausted for service %s after %d attempts', 'ultimate-multisite' ),
			$service,
			count( $providers_order )
		);

		\Reseller_Panel\Logger::log_error(
			'Service_Router',
			$error_message,
			array(
				'service' => $service,
				'providers_attempted' => count( $providers_order ),
				'failed_providers' => wp_json_encode( $failed_providers ),
			)
		);

		return $last_error ?: new \WP_Error( 'all_providers_failed', $error_message );
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
		$result = apply_filters( 'reseller_panel_execute_action', null, $provider, $action, $params );

		// Check if a handler was registered
		if ( null === $result ) {
			return new \WP_Error( 'no_handler', __( 'No handler registered for reseller_panel_execute_action', 'ultimate-multisite' ) );
		}

		return $result;
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
	private function log_fallback_event( $service, $primary_provider, $fallback_provider, $error_message ) {
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
		$like_pattern = $wpdb->esc_like( $table_name );

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like_pattern ) ) !== $table_name ) {
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
	 * @param int $fallback_rank Rank of the fallback provider
	 * @param WP_Error $error Error object
	 *
	 * @return bool
	 */
	private function send_fallback_notification( $service, $primary_provider, $fallback_provider, $fallback_rank, $error ) {
		$admin_email = get_site_option( 'admin_email' );

		if ( ! $admin_email ) {
			return false;
		}

		$primary_provider_obj = $this->provider_manager->get_provider( $primary_provider );
		$primary_name = $primary_provider_obj ? $primary_provider_obj->get_name() : ( $primary_provider ?: __( 'Unknown provider', 'ultimate-multisite' ) );
		
		$fallback_provider_obj = $this->provider_manager->get_provider( $fallback_provider );
		$fallback_name = $fallback_provider_obj ? $fallback_provider_obj->get_name() : ( $fallback_provider ?: __( 'Unknown provider', 'ultimate-multisite' ) );
		
		$main_site_id = is_multisite() ? get_main_site_id() : 1;
		$site_name = get_blog_option( $main_site_id, 'blogname' );
		if ( ! $site_name && ! is_multisite() ) {
			$site_name = get_option( 'blogname' );
		}
		if ( ! $site_name ) {
			$site_name = __( 'Site', 'ultimate-multisite' );
		}

		$subject = sprintf(
			__( '[%s] Reseller Panel: Service Fallback Triggered', 'ultimate-multisite' ),
			$site_name
		);

		$rank_text = sprintf(
			__( 'rank #%d', 'ultimate-multisite' ),
			$fallback_rank + 1
		);

		$message = sprintf(
			__(
				"A fallback event was triggered for the '%s' service.\n\n" .
				"Primary Provider: %s\n" .
				"Fallback Provider: %s (%s)\n" .
				"Error: %s\n\n" .
				"The request was successfully handled by the fallback provider.\n" .
				"Please investigate the primary provider to prevent future fallbacks.",
				'ultimate-multisite'
			),
			$service,
			$primary_name,
			$fallback_name,
			$rank_text,
			$error ? $error->get_error_message() : __( 'Primary provider failed', 'ultimate-multisite' )
		);

		$message .= "\n\n" . __( 'Admin URL:', 'ultimate-multisite' ) . ' ' . network_admin_url( 'admin.php?page=reseller-panel-services' );

		\Reseller_Panel\Logger::log_info(
			'Service_Router',
			'Sending fallback notification',
			array(
				'notification_sent' => true,
				'service' => $service,
				'primary' => $primary_provider,
				'fallback' => $fallback_provider,
			)
		);
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
		$like_pattern = $wpdb->esc_like( $table_name );

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like_pattern ) ) !== $table_name ) {
			return array();
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $wpdb->esc_sql( $table_name ) . ' ORDER BY timestamp DESC LIMIT %d',
				$limit
			)
		);
	}
}