<?php
/**
 * Domain Transfer Manager - Handles domain transfer operations
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Domain Transfer Manager Class
 */
class Domain_Transfer_Manager {

	/**
	 * Transfer status constants
	 */
	const STATUS_PENDING = 'pending';
	const STATUS_IN_PROGRESS = 'in_progress';
	const STATUS_COMPLETED = 'completed';
	const STATUS_FAILED = 'failed';
	const STATUS_CANCELLED = 'cancelled';
	const STATUS_REJECTED = 'rejected';

	/**
	 * Singleton instance
	 *
	 * @var Domain_Transfer_Manager|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Domain_Transfer_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - private for singleton
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	private function init_hooks() {
		// AJAX handlers
		add_action( 'wp_ajax_reseller_panel_initiate_domain_transfer_in', array( $this, 'ajax_initiate_transfer_in' ) );
		add_action( 'wp_ajax_reseller_panel_initiate_domain_transfer_out', array( $this, 'ajax_initiate_transfer_out' ) );
		add_action( 'wp_ajax_reseller_panel_cancel_domain_transfer', array( $this, 'ajax_cancel_transfer' ) );
		add_action( 'wp_ajax_reseller_panel_get_transfer_status', array( $this, 'ajax_get_transfer_status' ) );

		// Cron hooks
		add_action( 'reseller_panel_check_transfer_status', array( $this, 'check_pending_transfers' ) );
	}

	/**
	 * Safely log a message if Logger class is available
	 *
	 * @param string $level Log level (info or error)
	 * @param string $category Log category
	 * @param string $message Log message
	 * @param array  $context Log context data
	 *
	 * @return void
	 */
	private function safe_log( $level, $category, $message, $context = array() ) {
		if ( ! class_exists( 'Reseller_Panel\Logger' ) ) {
			return;
		}

		if ( 'error' === $level ) {
			Logger::log_error( $category, $message, $context );
		} else {
			Logger::log_info( $category, $message, $context );
		}
	}

	/**
	 * Initiate domain transfer in
	 *
	 * @param string $domain_name Domain name
	 * @param string $auth_code Authorization code
	 * @param int    $customer_id Customer ID
	 * @param string $provider_id Provider ID
	 * @param array  $options Additional options
	 *
	 * @return array Array with 'success' and 'data' or 'message' keys
	 */
	public function initiate_transfer_in( $domain_name, $auth_code, $customer_id, $provider_id, $options = array() ) {
		// Check if transfers are enabled
		if ( ! get_site_option( 'reseller_panel_enable_transfers', true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Domain transfers are currently disabled.', 'ultimate-multisite' ),
			);
		}

		// Validate inputs
		if ( empty( $domain_name ) || empty( $auth_code ) ) {
			return array(
				'success' => false,
				'message' => __( 'Domain name and authorization code are required.', 'ultimate-multisite' ),
			);
		}

		// Get provider
		$provider_manager = Provider_Manager::get_instance();
		$provider = $provider_manager->get_provider( $provider_id );

		if ( ! $provider || ! $provider->is_configured() ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid or unconfigured provider.', 'ultimate-multisite' ),
			);
		}

		// Check transfer eligibility
		$eligibility = $this->check_transfer_eligibility( $domain_name, $provider );
		if ( ! $eligibility['eligible'] ) {
			return array(
				'success' => false,
				'message' => $eligibility['message'],
			);
		}

		// Prepare registrant info from customer
		$registrant_info = isset( $options['registrant_info'] ) ? $options['registrant_info'] : array();

		// Initiate transfer via provider
		$result = $provider->transfer_domain( $domain_name, $auth_code, $registrant_info, $options );

		if ( is_wp_error( $result ) ) {
			$this->safe_log( 'error', 'Transfer Manager', 'Failed to initiate transfer in', array(
				'domain' => $domain_name,
				'provider' => $provider_id,
				'error' => $result->get_error_message(),
			) );

			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		// Store transfer metadata
		$this->update_transfer_metadata( $domain_name, array(
			'status' => self::STATUS_PENDING,
			'transfer_id' => isset( $result['transfer_id'] ) ? $result['transfer_id'] : '',
			'provider' => $provider_id,
			'customer_id' => $customer_id,
			'direction' => 'in',
			'initiated_at' => current_time( 'mysql' ),
		) );

		$this->safe_log( 'info', 'Transfer Manager', 'Transfer in initiated', array(
			'domain' => $domain_name,
			'provider' => $provider_id,
			'customer_id' => $customer_id,
		) );

		return array(
			'success' => true,
			'data' => $result,
			'message' => __( 'Domain transfer initiated successfully.', 'ultimate-multisite' ),
		);
	}

	/**
	 * Initiate domain transfer out
	 *
	 * @param string $domain_name Domain name
	 * @param int    $customer_id Customer ID
	 * @param string $new_registrar New registrar name
	 * @param array  $options Additional options
	 *
	 * @return array Array with 'success' and 'data' or 'message' keys
	 */
	public function initiate_transfer_out( $domain_name, $customer_id, $new_registrar, $options = array() ) {
		// Check if transfers are enabled
		if ( ! get_site_option( 'reseller_panel_enable_transfers', true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Domain transfers are currently disabled.', 'ultimate-multisite' ),
			);
		}

		// Validate inputs
		if ( empty( $domain_name ) || empty( $new_registrar ) ) {
			return array(
				'success' => false,
				'message' => __( 'Domain name and new registrar are required.', 'ultimate-multisite' ),
			);
		}

		// Get provider for this domain
		$provider = $this->get_domain_provider( $domain_name );
		if ( ! $provider ) {
			return array(
				'success' => false,
				'message' => __( 'No configured provider found for this domain.', 'ultimate-multisite' ),
			);
		}

		// Get auth code from provider
		$auth_code_result = $provider->get_auth_code( $domain_name );

		if ( is_wp_error( $auth_code_result ) ) {
			$this->safe_log( 'error', 'Transfer Manager', 'Failed to get auth code', array(
				'domain' => $domain_name,
				'error' => $auth_code_result->get_error_message(),
			) );

			return array(
				'success' => false,
				'message' => $auth_code_result->get_error_message(),
			);
		}

		// Store transfer metadata
		$this->update_transfer_metadata( $domain_name, array(
			'status' => self::STATUS_IN_PROGRESS,
			'auth_code' => isset( $auth_code_result['auth_code'] ) ? $auth_code_result['auth_code'] : '',
			'new_registrar' => $new_registrar,
			'customer_id' => $customer_id,
			'direction' => 'out',
			'initiated_at' => current_time( 'mysql' ),
		) );

		$this->safe_log( 'info', 'Transfer Manager', 'Transfer out initiated', array(
			'domain' => $domain_name,
			'new_registrar' => $new_registrar,
			'customer_id' => $customer_id,
		) );

		return array(
			'success' => true,
			'data' => $auth_code_result,
			'message' => __( 'Authorization code generated successfully.', 'ultimate-multisite' ),
		);
	}

	/**
	 * Cancel domain transfer
	 *
	 * @param string $domain_name Domain name
	 * @param int    $customer_id Customer ID
	 *
	 * @return array Array with 'success' and 'message' keys
	 */
	public function cancel_transfer( $domain_name, $customer_id ) {
		// Get transfer metadata
		$metadata = $this->get_transfer_metadata( $domain_name );

		if ( empty( $metadata ) || $metadata['customer_id'] !== $customer_id ) {
			return array(
				'success' => false,
				'message' => __( 'Transfer not found or permission denied.', 'ultimate-multisite' ),
			);
		}

		// Update status
		$this->update_transfer_metadata( $domain_name, array(
			'status' => self::STATUS_CANCELLED,
			'cancelled_at' => current_time( 'mysql' ),
		), true );

		$this->safe_log( 'info', 'Transfer Manager', 'Transfer cancelled', array(
			'domain' => $domain_name,
			'customer_id' => $customer_id,
		) );

		return array(
			'success' => true,
			'message' => __( 'Domain transfer cancelled successfully.', 'ultimate-multisite' ),
		);
	}

	/**
	 * Check pending transfers
	 *
	 * @return void
	 */
	public function check_pending_transfers() {
		global $wpdb;

		// Get all domains with pending transfers
		$table_name     = $wpdb->prefix . 'reseller_panel_domain_meta';
		$status_pattern = '"status":"' . self::STATUS_PENDING . '"';
		$status_like    = '%' . $wpdb->esc_like( $status_pattern ) . '%';
		$pending_transfers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT domain_id, meta_value FROM {$table_name} WHERE meta_key = %s AND meta_value LIKE %s",
				'transfer_data',
				$status_like
			)
		);

		foreach ( $pending_transfers as $transfer ) {
			$metadata = maybe_unserialize( $transfer->meta_value );
			if ( ! is_array( $metadata ) || empty( $metadata['domain_name'] ) ) {
				continue;
			}

			$this->update_transfer_status_for_domain( $metadata['domain_name'], $metadata );
		}

		$this->safe_log( 'info', 'Transfer Manager', 'Checked pending transfers', array(
			'count' => count( $pending_transfers ),
		) );
	}

	/**
	 * Update transfer status for a domain
	 *
	 * @param string $domain_name Domain name
	 * @param array  $metadata Transfer metadata
	 *
	 * @return void
	 */
	private function update_transfer_status_for_domain( $domain_name, $metadata ) {
		if ( empty( $metadata['provider'] ) || empty( $metadata['transfer_id'] ) ) {
			return;
		}

		// Get provider
		$provider_manager = Provider_Manager::get_instance();
		$provider = $provider_manager->get_provider( $metadata['provider'] );

		if ( ! $provider ) {
			return;
		}

		// Check transfer status
		$status_result = $provider->check_transfer_status( $domain_name, $metadata['transfer_id'] );

		if ( is_wp_error( $status_result ) ) {
			$this->safe_log( 'error', 'Transfer Manager', 'Failed to check transfer status', array(
				'domain' => $domain_name,
				'error' => $status_result->get_error_message(),
			) );
			return;
		}

		// Update metadata if status changed
		if ( isset( $status_result['status'] ) && $status_result['status'] !== $metadata['status'] ) {
			$this->update_transfer_metadata( $domain_name, array(
				'status' => $status_result['status'],
				'updated_at' => current_time( 'mysql' ),
			), true );

			$this->safe_log( 'info', 'Transfer Manager', 'Transfer status updated', array(
				'domain' => $domain_name,
				'old_status' => $metadata['status'],
				'new_status' => $status_result['status'],
			) );
		}
	}

	/**
	 * Check transfer eligibility
	 *
	 * @param string                                                          $domain_name Domain name
	 * @param \Reseller_Panel\Interfaces\Service_Provider_Interface $provider Provider instance
	 *
	 * @return array Eligibility result with 'eligible' and 'message' keys
	 */
	private function check_transfer_eligibility( $domain_name, $provider ) {
		// Check transfer lock period
		$lock_days = get_site_option( 'reseller_panel_transfer_lock_days', 60 );

		// This would check domain registration date
		// For now, return eligible
		return array(
			'eligible' => true,
			'message' => '',
		);
	}

	/**
	 * Get provider for a domain
	 *
	 * @param string $domain_name Domain name
	 *
	 * @return \Reseller_Panel\Interfaces\Service_Provider_Interface|null
	 */
	private function get_domain_provider( $domain_name ) {
		// Get the first available configured provider that supports domains
		$provider_manager = Provider_Manager::get_instance();
		$providers = $provider_manager->get_available_providers( 'domains' );

		if ( empty( $providers ) ) {
			return null;
		}

		// Return the first provider
		return reset( $providers );
	}

	/**
	 * Update transfer metadata
	 *
	 * @param string $domain_name Domain name
	 * @param array  $data Metadata to update
	 * @param bool   $merge Whether to merge with existing data
	 *
	 * @return void
	 */
	private function update_transfer_metadata( $domain_name, $data, $merge = true ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'reseller_panel_domain_meta';

		// Get existing metadata if merging
		if ( $merge ) {
			$existing = $this->get_transfer_metadata( $domain_name );
			$data = array_merge( $existing, $data );
		}

		// Add domain name to data
		$data['domain_name'] = $domain_name;

		// Store as serialized data
		$wpdb->replace(
			$table_name,
			array(
				'domain_id' => 0, // We're using domain_name in meta_value for simplicity
				'meta_key' => 'transfer_data',
				'meta_value' => maybe_serialize( $data ),
			),
			array( '%d', '%s', '%s' )
		);
	}

	/**
	 * Get transfer metadata
	 *
	 * @param string $domain_name Domain name
	 *
	 * @return array Transfer metadata
	 */
	public function get_transfer_metadata( $domain_name ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'reseller_panel_domain_meta';

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$table_name} WHERE meta_key = %s AND meta_value LIKE %s LIMIT 1",
				'transfer_data',
				'%"domain_name":"' . $wpdb->esc_like( $domain_name ) . '"%'
			)
		);

		if ( ! $result ) {
			return array();
		}

		$data = maybe_unserialize( $result );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * AJAX handler: Initiate transfer in
	 *
	 * @return void
	 */
	public function ajax_initiate_transfer_in() {
		check_ajax_referer( 'reseller-panel-customer', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ultimate-multisite' ) ) );
		}

		$domain_name = isset( $_POST['domain_name'] ) ? sanitize_text_field( wp_unslash( $_POST['domain_name'] ) ) : '';
		$auth_code = isset( $_POST['auth_code'] ) ? sanitize_text_field( wp_unslash( $_POST['auth_code'] ) ) : '';
		$provider_id = isset( $_POST['provider_id'] ) ? sanitize_text_field( wp_unslash( $_POST['provider_id'] ) ) : '';
		$customer_id = get_current_user_id();

		if ( empty( $domain_name ) || empty( $auth_code ) || empty( $provider_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Domain name, authorization code, and provider are required.', 'ultimate-multisite' ) ) );
		}

		$result = $this->initiate_transfer_in( $domain_name, $auth_code, $customer_id, $provider_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler: Initiate transfer out
	 *
	 * @return void
	 */
	public function ajax_initiate_transfer_out() {
		check_ajax_referer( 'reseller-panel-customer', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ultimate-multisite' ) ) );
		}

		$domain_name = isset( $_POST['domain_name'] ) ? sanitize_text_field( wp_unslash( $_POST['domain_name'] ) ) : '';
		$new_registrar = isset( $_POST['new_registrar'] ) ? sanitize_text_field( wp_unslash( $_POST['new_registrar'] ) ) : '';
		$customer_id = get_current_user_id();

		if ( empty( $domain_name ) || empty( $new_registrar ) ) {
			wp_send_json_error( array( 'message' => __( 'Domain name and new registrar are required.', 'ultimate-multisite' ) ) );
		}

		$result = $this->initiate_transfer_out( $domain_name, $customer_id, $new_registrar );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler: Cancel transfer
	 *
	 * @return void
	 */
	public function ajax_cancel_transfer() {
		check_ajax_referer( 'reseller-panel-customer', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ultimate-multisite' ) ) );
		}

		$domain_name = isset( $_POST['domain_name'] ) ? sanitize_text_field( wp_unslash( $_POST['domain_name'] ) ) : '';
		$customer_id = get_current_user_id();

		if ( empty( $domain_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Domain name is required.', 'ultimate-multisite' ) ) );
		}

		$result = $this->cancel_transfer( $domain_name, $customer_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler: Get transfer status
	 *
	 * @return void
	 */
	public function ajax_get_transfer_status() {
		check_ajax_referer( 'reseller-panel-customer', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ultimate-multisite' ) ) );
		}

		$domain_name = isset( $_POST['domain_name'] ) ? sanitize_text_field( wp_unslash( $_POST['domain_name'] ) ) : '';

		if ( empty( $domain_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Domain name is required.', 'ultimate-multisite' ) ) );
		}

		$metadata = $this->get_transfer_metadata( $domain_name );

		if ( empty( $metadata ) ) {
			wp_send_json_error( array( 'message' => __( 'Transfer not found.', 'ultimate-multisite' ) ) );
		}

		wp_send_json_success( array(
			'data' => $metadata,
		) );
	}
}
