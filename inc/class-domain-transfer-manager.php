<?php
/**
 * Domain Transfer Manager Class
 *
 * Manages domain transfer operations including transfer in/out,
 * status tracking, and authorization code management.
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Domain Transfer Manager Class
 *
 * Handles domain transfer operations and monitoring.
 */
class Domain_Transfer_Manager {

	/**
	 * Singleton instance
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Transfer statuses
	 *
	 * @var array
	 */
	private $transfer_statuses = array(
		'pending',
		'in_progress',
		'completed',
		'failed',
		'cancelled',
		'rejected',
	);

	/**
	 * Cron hook name
	 *
	 * @var string
	 */
	const CRON_HOOK = 'reseller_panel_check_transfer_status';

	/**
	 * Get singleton instance
	 *
	 * @return self
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
		$this->setup_hooks();
	}

	/**
	 * Setup WordPress hooks
	 */
	private function setup_hooks() {
		// Register AJAX handlers
		add_action( 'wp_ajax_reseller_panel_transfer_domain', array( $this, 'ajax_transfer_domain' ) );
		add_action( 'wp_ajax_reseller_panel_check_transfer_status', array( $this, 'ajax_check_transfer_status' ) );
		add_action( 'wp_ajax_reseller_panel_get_auth_code', array( $this, 'ajax_get_auth_code' ) );
		add_action( 'wp_ajax_reseller_panel_cancel_transfer', array( $this, 'ajax_cancel_transfer' ) );

		// Register cron job
		add_action( self::CRON_HOOK, array( $this, 'monitor_transfers' ) );

		// Schedule cron if not already scheduled
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Transfer a domain in
	 *
	 * @param string $domain Domain name
	 * @param string $auth_code Authorization code
	 * @param array  $registrant_info Registrant information
	 * @param string $provider_key Provider key (optional)
	 *
	 * @return array|WP_Error Transfer result or WP_Error on failure
	 */
	public function transfer_domain( $domain, $auth_code, $registrant_info = array(), $provider_key = null ) {
		if ( empty( $domain ) ) {
			return new \WP_Error( 'invalid_domain', __( 'Domain name is required', 'ultimate-multisite' ) );
		}

		if ( empty( $auth_code ) ) {
			return new \WP_Error( 'invalid_auth_code', __( 'Authorization code is required', 'ultimate-multisite' ) );
		}

		// Get provider
		$provider = $this->get_provider_for_domain( $domain, $provider_key );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		// Check if provider supports domain transfers
		if ( ! method_exists( $provider, 'transfer_domain' ) ) {
			return new \WP_Error(
				'unsupported_operation',
				sprintf(
					/* translators: %s: Provider name */
					__( 'Domain transfers are not supported by %s', 'ultimate-multisite' ),
					$provider->get_name()
				)
			);
		}

		// Initiate transfer via provider
		$result = $provider->transfer_domain( $domain, $auth_code, $registrant_info );

		if ( is_wp_error( $result ) ) {
			Logger::log_error(
				$provider->get_key(),
				sprintf( 'Failed to transfer domain %s: %s', $domain, $result->get_error_message() )
			);
			return $result;
		}

		// Store transfer record
		$this->save_transfer_record( $domain, $provider->get_key(), 'pending', $result );

		Logger::log_info(
			$provider->get_key(),
			sprintf( 'Initiated transfer for domain %s', $domain ),
			array( 'transfer_id' => isset( $result['transfer_id'] ) ? $result['transfer_id'] : 'N/A' )
		);

		return $result;
	}

	/**
	 * Check transfer status
	 *
	 * @param string $domain Domain name
	 * @param string $transfer_id Transfer ID (optional)
	 * @param string $provider_key Provider key (optional)
	 *
	 * @return array|WP_Error Transfer status or WP_Error on failure
	 */
	public function check_transfer_status( $domain, $transfer_id = null, $provider_key = null ) {
		if ( empty( $domain ) ) {
			return new \WP_Error( 'invalid_domain', __( 'Domain name is required', 'ultimate-multisite' ) );
		}

		// Get provider
		$provider = $this->get_provider_for_domain( $domain, $provider_key );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		// Check if provider supports transfer status checking
		if ( ! method_exists( $provider, 'check_transfer_status' ) ) {
			return new \WP_Error(
				'unsupported_operation',
				sprintf(
					/* translators: %s: Provider name */
					__( 'Transfer status checking is not supported by %s', 'ultimate-multisite' ),
					$provider->get_name()
				)
			);
		}

		// Check status via provider
		$result = $provider->check_transfer_status( $domain, $transfer_id );

		if ( is_wp_error( $result ) ) {
			Logger::log_error(
				$provider->get_key(),
				sprintf( 'Failed to check transfer status for %s: %s', $domain, $result->get_error_message() )
			);
			return $result;
		}

		// Update transfer record if status changed
		if ( isset( $result['status'] ) ) {
			$this->update_transfer_status( $domain, $result['status'], $result );
		}

		Logger::log_info(
			$provider->get_key(),
			sprintf( 'Checked transfer status for domain %s', $domain ),
			array( 'status' => isset( $result['status'] ) ? $result['status'] : 'unknown' )
		);

		return $result;
	}

	/**
	 * Get authorization code for domain transfer out
	 *
	 * @param string $domain Domain name
	 * @param string $provider_key Provider key (optional)
	 *
	 * @return array|WP_Error Authorization code data or WP_Error on failure
	 */
	public function get_auth_code( $domain, $provider_key = null ) {
		if ( empty( $domain ) ) {
			return new \WP_Error( 'invalid_domain', __( 'Domain name is required', 'ultimate-multisite' ) );
		}

		// Get provider
		$provider = $this->get_provider_for_domain( $domain, $provider_key );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		// Check if provider supports auth code retrieval
		if ( ! method_exists( $provider, 'get_auth_code' ) ) {
			return new \WP_Error(
				'unsupported_operation',
				sprintf(
					/* translators: %s: Provider name */
					__( 'Authorization code retrieval is not supported by %s', 'ultimate-multisite' ),
					$provider->get_name()
				)
			);
		}

		// Get auth code via provider
		$result = $provider->get_auth_code( $domain );

		if ( is_wp_error( $result ) ) {
			Logger::log_error(
				$provider->get_key(),
				sprintf( 'Failed to get auth code for %s: %s', $domain, $result->get_error_message() )
			);
			return $result;
		}

		Logger::log_info(
			$provider->get_key(),
			sprintf( 'Retrieved auth code for domain %s', $domain )
		);

		return $result;
	}

	/**
	 * Cancel a pending transfer
	 *
	 * @param string $domain Domain name
	 * @param string $transfer_id Transfer ID (optional)
	 * @param string $provider_key Provider key (optional)
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function cancel_transfer( $domain, $transfer_id = null, $provider_key = null ) {
		if ( empty( $domain ) ) {
			return new \WP_Error( 'invalid_domain', __( 'Domain name is required', 'ultimate-multisite' ) );
		}

		// Get provider
		$provider = $this->get_provider_for_domain( $domain, $provider_key );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		// Check if provider supports transfer cancellation
		if ( ! method_exists( $provider, 'cancel_transfer' ) ) {
			return new \WP_Error(
				'unsupported_operation',
				sprintf(
					/* translators: %s: Provider name */
					__( 'Transfer cancellation is not supported by %s', 'ultimate-multisite' ),
					$provider->get_name()
				)
			);
		}

		// Cancel transfer via provider
		$result = $provider->cancel_transfer( $domain, $transfer_id );

		if ( is_wp_error( $result ) ) {
			Logger::log_error(
				$provider->get_key(),
				sprintf( 'Failed to cancel transfer for %s: %s', $domain, $result->get_error_message() )
			);
			return $result;
		}

		// Update transfer record
		$this->update_transfer_status( $domain, 'cancelled' );

		Logger::log_info(
			$provider->get_key(),
			sprintf( 'Cancelled transfer for domain %s', $domain )
		);

		return $result;
	}

	/**
	 * Monitor active transfers (called by cron)
	 */
	public function monitor_transfers() {
		// Get all pending/in_progress transfers
		$transfers = $this->get_active_transfers();

		if ( empty( $transfers ) ) {
			return;
		}

		foreach ( $transfers as $transfer ) {
			// Check status for each transfer
			$status = $this->check_transfer_status(
				$transfer['domain'],
				isset( $transfer['transfer_id'] ) ? $transfer['transfer_id'] : null,
				isset( $transfer['provider'] ) ? $transfer['provider'] : null
			);

			// If completed or failed, send notification
			if ( ! is_wp_error( $status ) && isset( $status['status'] ) ) {
				if ( in_array( $status['status'], array( 'completed', 'failed', 'rejected' ), true ) ) {
					$this->send_transfer_notification( $transfer['domain'], $status );
				}
			}
		}
	}

	/**
	 * Get provider for a domain
	 *
	 * @param string $domain Domain name
	 * @param string $provider_key Provider key (optional)
	 *
	 * @return object|WP_Error Provider instance or WP_Error
	 */
	private function get_provider_for_domain( $domain, $provider_key = null ) {
		$provider_manager = Provider_Manager::get_instance();

		// If provider key specified, use it
		if ( $provider_key ) {
			$provider = $provider_manager->get_provider( $provider_key );
			if ( ! $provider ) {
				return new \WP_Error(
					'invalid_provider',
					sprintf(
						/* translators: %s: Provider key */
						__( 'Provider %s not found', 'ultimate-multisite' ),
						$provider_key
					)
				);
			}
			return $provider;
		}

		// Otherwise, get the primary provider for domains service
		$providers = $provider_manager->get_providers_for_service( 'domains' );
		if ( empty( $providers ) ) {
			return new \WP_Error(
				'no_provider',
				__( 'No domain provider configured', 'ultimate-multisite' )
			);
		}

		return reset( $providers );
	}

	/**
	 * Save transfer record
	 *
	 * @param string $domain Domain name
	 * @param string $provider Provider key
	 * @param string $status Transfer status
	 * @param array  $data Additional data
	 */
	private function save_transfer_record( $domain, $provider, $status, $data = array() ) {
		$transfers = get_site_option( 'reseller_panel_transfers', array() );

		$transfers[ $domain ] = array(
			'domain'       => $domain,
			'provider'     => $provider,
			'status'       => $status,
			'transfer_id'  => isset( $data['transfer_id'] ) ? $data['transfer_id'] : '',
			'created_at'   => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
			'data'         => $data,
		);

		update_site_option( 'reseller_panel_transfers', $transfers );
	}

	/**
	 * Update transfer status
	 *
	 * @param string $domain Domain name
	 * @param string $status New status
	 * @param array  $data Additional data
	 */
	private function update_transfer_status( $domain, $status, $data = array() ) {
		$transfers = get_site_option( 'reseller_panel_transfers', array() );

		if ( isset( $transfers[ $domain ] ) ) {
			$transfers[ $domain ]['status']     = $status;
			$transfers[ $domain ]['updated_at'] = current_time( 'mysql' );

			if ( ! empty( $data ) ) {
				$transfers[ $domain ]['data'] = array_merge(
					isset( $transfers[ $domain ]['data'] ) ? $transfers[ $domain ]['data'] : array(),
					$data
				);
			}

			update_site_option( 'reseller_panel_transfers', $transfers );
		}
	}

	/**
	 * Get active transfers
	 *
	 * @return array
	 */
	private function get_active_transfers() {
		$transfers = get_site_option( 'reseller_panel_transfers', array() );

		// Filter for active statuses
		return array_filter(
			$transfers,
			function ( $transfer ) {
				return in_array( $transfer['status'], array( 'pending', 'in_progress' ), true );
			}
		);
	}

	/**
	 * Send transfer notification
	 *
	 * @param string $domain Domain name
	 * @param array  $status_data Transfer status data
	 */
	private function send_transfer_notification( $domain, $status_data ) {
		// Get admin email
		$admin_email = get_site_option( 'admin_email' );

		$status = isset( $status_data['status'] ) ? $status_data['status'] : 'unknown';

		$subject = sprintf(
			/* translators: 1: Domain name, 2: Transfer status */
			__( 'Domain Transfer Update: %1$s - %2$s', 'ultimate-multisite' ),
			$domain,
			ucfirst( $status )
		);

		$message = sprintf(
			/* translators: 1: Domain name, 2: Transfer status */
			__( 'The transfer for domain %1$s has been updated to: %2$s', 'ultimate-multisite' ),
			$domain,
			ucfirst( $status )
		);

		if ( isset( $status_data['message'] ) ) {
			$message .= "\n\n" . $status_data['message'];
		}

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Check if user can manage transfers for a domain
	 *
	 * @param string $domain Domain name
	 * @param int    $user_id User ID (optional, defaults to current user)
	 *
	 * @return bool True if user can manage transfers
	 */
	public function user_can_manage_transfer( $domain, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// Super admins can always manage transfers
		if ( is_super_admin( $user_id ) ) {
			return true;
		}

		// Check if user owns the domain
		// This would integrate with Ultimate Multisite's customer/domain ownership
		// For now, we'll use a simple filter to allow customization
		$can_manage = apply_filters( 'reseller_panel_user_can_manage_transfer', false, $domain, $user_id );

		return $can_manage;
	}

	/**
	 * AJAX handler: Transfer domain
	 */
	public function ajax_transfer_domain() {
		check_ajax_referer( 'reseller_panel_transfer_nonce', 'nonce' );

		$domain       = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
		$auth_code    = isset( $_POST['auth_code'] ) ? sanitize_text_field( wp_unslash( $_POST['auth_code'] ) ) : '';
		$provider_key = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : null;

		if ( ! $this->user_can_manage_transfer( $domain ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ultimate-multisite' ) ) );
		}

		// Get registrant info from POST data
		$registrant_info = array();
		if ( isset( $_POST['registrant'] ) && is_array( $_POST['registrant'] ) ) {
			foreach ( $_POST['registrant'] as $key => $value ) {
				$registrant_info[ sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( $value ) );
			}
		}

		$result = $this->transfer_domain( $domain, $auth_code, $registrant_info, $provider_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Domain transfer initiated successfully', 'ultimate-multisite' ),
				'data'    => $result,
			)
		);
	}

	/**
	 * AJAX handler: Check transfer status
	 */
	public function ajax_check_transfer_status() {
		check_ajax_referer( 'reseller_panel_transfer_nonce', 'nonce' );

		$domain       = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
		$transfer_id  = isset( $_POST['transfer_id'] ) ? sanitize_text_field( wp_unslash( $_POST['transfer_id'] ) ) : null;
		$provider_key = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : null;

		if ( ! $this->user_can_manage_transfer( $domain ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ultimate-multisite' ) ) );
		}

		$result = $this->check_transfer_status( $domain, $transfer_id, $provider_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'status' => $result ) );
	}

	/**
	 * AJAX handler: Get authorization code
	 */
	public function ajax_get_auth_code() {
		check_ajax_referer( 'reseller_panel_transfer_nonce', 'nonce' );

		$domain       = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
		$provider_key = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : null;

		if ( ! $this->user_can_manage_transfer( $domain ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ultimate-multisite' ) ) );
		}

		$result = $this->get_auth_code( $domain, $provider_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'auth_code' => $result ) );
	}

	/**
	 * AJAX handler: Cancel transfer
	 */
	public function ajax_cancel_transfer() {
		check_ajax_referer( 'reseller_panel_transfer_nonce', 'nonce' );

		$domain       = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
		$transfer_id  = isset( $_POST['transfer_id'] ) ? sanitize_text_field( wp_unslash( $_POST['transfer_id'] ) ) : null;
		$provider_key = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : null;

		if ( ! $this->user_can_manage_transfer( $domain ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ultimate-multisite' ) ) );
		}

		$result = $this->cancel_transfer( $domain, $transfer_id, $provider_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Transfer cancelled successfully', 'ultimate-multisite' ) ) );
	}

	/**
	 * Get supported transfer statuses
	 *
	 * @return array
	 */
	public function get_transfer_statuses() {
		return $this->transfer_statuses;
	}
}
