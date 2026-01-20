<?php
/**
 * Domain Renewal Manager Class
 *
 * Manages domain renewals including auto-renewal scheduling,
 * batch processing, and renewal notifications.
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Domain Renewal Manager Class
 *
 * Handles domain renewal operations and scheduling.
 */
class Domain_Renewal_Manager {

	/**
	 * Singleton instance
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Days before expiry to send renewal notice
	 *
	 * @var int
	 */
	private $renewal_notice_days = 30;

	/**
	 * Days to retry failed renewals
	 *
	 * @var int
	 */
	private $renewal_retry_days = 7;

	/**
	 * Cron hooks
	 *
	 * @var array
	 */
	const CRON_DAILY_BATCH = 'reseller_panel_daily_renewal_batch';
	const CRON_CHECK_DOMAINS = 'reseller_panel_check_domain_expiry';

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
		$this->load_settings();
		$this->setup_hooks();
	}

	/**
	 * Load renewal settings
	 */
	private function load_settings() {
		$this->renewal_notice_days = (int) get_site_option( 'reseller_panel_renewal_notice_days', 30 );
		$this->renewal_retry_days  = (int) get_site_option( 'reseller_panel_renewal_retry_days', 7 );
	}

	/**
	 * Setup WordPress hooks
	 */
	private function setup_hooks() {
		// Register AJAX handlers
		add_action( 'wp_ajax_reseller_panel_renew_domain', array( $this, 'ajax_renew_domain' ) );
		add_action( 'wp_ajax_reseller_panel_toggle_auto_renew', array( $this, 'ajax_toggle_auto_renew' ) );
		add_action( 'wp_ajax_reseller_panel_get_renewal_info', array( $this, 'ajax_get_renewal_info' ) );

		// Register cron jobs
		add_action( self::CRON_DAILY_BATCH, array( $this, 'process_daily_renewals' ) );
		add_action( self::CRON_CHECK_DOMAINS, array( $this, 'check_expiring_domains' ) );

		// Schedule cron jobs if not already scheduled
		if ( ! wp_next_scheduled( self::CRON_DAILY_BATCH ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_DAILY_BATCH );
		}

		if ( ! wp_next_scheduled( self::CRON_CHECK_DOMAINS ) ) {
			wp_schedule_event( time(), 'twicedaily', self::CRON_CHECK_DOMAINS );
		}
	}

	/**
	 * Renew a domain
	 *
	 * @param string $domain Domain name
	 * @param int    $years Number of years to renew
	 * @param string $provider_key Provider key (optional)
	 *
	 * @return array|WP_Error Renewal result or WP_Error on failure
	 */
	public function renew_domain( $domain, $years = 1, $provider_key = null ) {
		if ( empty( $domain ) ) {
			return new \WP_Error( 'invalid_domain', __( 'Domain name is required', 'ultimate-multisite' ) );
		}

		if ( $years < 1 || $years > 10 ) {
			return new \WP_Error( 'invalid_years', __( 'Years must be between 1 and 10', 'ultimate-multisite' ) );
		}

		// Get provider
		$provider = $this->get_provider_for_domain( $domain, $provider_key );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		// Check if provider supports domain renewals
		if ( ! method_exists( $provider, 'renew_domain' ) ) {
			return new \WP_Error(
				'unsupported_operation',
				sprintf(
					/* translators: %s: Provider name */
					__( 'Domain renewals are not supported by %s', 'ultimate-multisite' ),
					$provider->get_name()
				)
			);
		}

		// Renew domain via provider
		$result = $provider->renew_domain( $domain, $years );

		if ( is_wp_error( $result ) ) {
			Logger::log_error(
				$provider->get_key(),
				sprintf( 'Failed to renew domain %s: %s', $domain, $result->get_error_message() ),
				array( 'years' => $years )
			);

			// Record failed renewal
			$this->record_renewal_attempt( $domain, 'failed', $result->get_error_message() );

			return $result;
		}

		// Record successful renewal
		$this->record_renewal_attempt( $domain, 'success', null, $result );

		Logger::log_info(
			$provider->get_key(),
			sprintf( 'Renewed domain %s for %d year(s)', $domain, $years ),
			array( 'result' => $result )
		);

		return $result;
	}

	/**
	 * Enable or disable auto-renewal for a domain
	 *
	 * @param string $domain Domain name
	 * @param bool   $enabled Whether to enable auto-renewal
	 * @param string $provider_key Provider key (optional)
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function toggle_auto_renewal( $domain, $enabled, $provider_key = null ) {
		if ( empty( $domain ) ) {
			return new \WP_Error( 'invalid_domain', __( 'Domain name is required', 'ultimate-multisite' ) );
		}

		// Store auto-renewal preference
		$auto_renewals = get_site_option( 'reseller_panel_auto_renewals', array() );

		if ( $enabled ) {
			$auto_renewals[ $domain ] = array(
				'enabled'    => true,
				'provider'   => $provider_key,
				'created_at' => current_time( 'mysql' ),
			);
		} else {
			unset( $auto_renewals[ $domain ] );
		}

		update_site_option( 'reseller_panel_auto_renewals', $auto_renewals );

		Logger::log_info(
			'renewal_manager',
			sprintf( 'Auto-renewal %s for domain %s', $enabled ? 'enabled' : 'disabled', $domain )
		);

		return true;
	}

	/**
	 * Get renewal information for a domain
	 *
	 * @param string $domain Domain name
	 * @param string $provider_key Provider key (optional)
	 *
	 * @return array|WP_Error Renewal info or WP_Error on failure
	 */
	public function get_renewal_info( $domain, $provider_key = null ) {
		if ( empty( $domain ) ) {
			return new \WP_Error( 'invalid_domain', __( 'Domain name is required', 'ultimate-multisite' ) );
		}

		// Get provider
		$provider = $this->get_provider_for_domain( $domain, $provider_key );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		// Check if provider supports getting domain info
		if ( ! method_exists( $provider, 'get_domain_info' ) ) {
			return new \WP_Error(
				'unsupported_operation',
				sprintf(
					/* translators: %s: Provider name */
					__( 'Getting domain information is not supported by %s', 'ultimate-multisite' ),
					$provider->get_name()
				)
			);
		}

		// Get domain info via provider
		$result = $provider->get_domain_info( $domain );

		if ( is_wp_error( $result ) ) {
			Logger::log_error(
				$provider->get_key(),
				sprintf( 'Failed to get renewal info for %s: %s', $domain, $result->get_error_message() )
			);
			return $result;
		}

		// Add auto-renewal status
		$auto_renewals = get_site_option( 'reseller_panel_auto_renewals', array() );
		$result['auto_renew_enabled'] = isset( $auto_renewals[ $domain ] );

		// Add renewal history
		$result['renewal_history'] = $this->get_renewal_history( $domain );

		return $result;
	}

	/**
	 * Process daily renewal batch (called by cron)
	 */
	public function process_daily_renewals() {
		$auto_renewals = get_site_option( 'reseller_panel_auto_renewals', array() );

		if ( empty( $auto_renewals ) ) {
			return;
		}

		foreach ( $auto_renewals as $domain => $config ) {
			// Get domain info to check expiry
			$info = $this->get_renewal_info( $domain, isset( $config['provider'] ) ? $config['provider'] : null );

			if ( is_wp_error( $info ) ) {
				Logger::log_error(
					'renewal_manager',
					sprintf( 'Failed to get info for auto-renewal domain %s: %s', $domain, $info->get_error_message() )
				);
				continue;
			}

			// Check if domain is expiring soon
			if ( isset( $info['expiry_date'] ) ) {
				$expiry_timestamp = strtotime( $info['expiry_date'] );
				$days_until_expiry = ( $expiry_timestamp - time() ) / DAY_IN_SECONDS;

				// Renew if within renewal window
				if ( $days_until_expiry <= $this->renewal_notice_days && $days_until_expiry > 0 ) {
					$result = $this->renew_domain( $domain, 1, isset( $config['provider'] ) ? $config['provider'] : null );

					if ( is_wp_error( $result ) ) {
						// Send notification about failed auto-renewal
						$this->send_renewal_notification( $domain, 'failed', $result->get_error_message() );
					} else {
						// Send notification about successful auto-renewal
						$this->send_renewal_notification( $domain, 'success' );
					}
				}
			}
		}
	}

	/**
	 * Check for expiring domains and send notifications (called by cron)
	 */
	public function check_expiring_domains() {
		// Get all domains (this would integrate with Ultimate Multisite's domain models)
		// For now, we'll check domains with auto-renewal enabled
		$auto_renewals = get_site_option( 'reseller_panel_auto_renewals', array() );

		foreach ( $auto_renewals as $domain => $config ) {
			$info = $this->get_renewal_info( $domain, isset( $config['provider'] ) ? $config['provider'] : null );

			if ( ! is_wp_error( $info ) && isset( $info['expiry_date'] ) ) {
				$expiry_timestamp = strtotime( $info['expiry_date'] );
				$days_until_expiry = ( $expiry_timestamp - time() ) / DAY_IN_SECONDS;

				// Send notification at 30, 14, and 7 days before expiry
				if ( in_array( (int) $days_until_expiry, array( 30, 14, 7 ), true ) ) {
					$this->send_expiry_notification( $domain, $days_until_expiry, $info );
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
	 * Record renewal attempt
	 *
	 * @param string $domain Domain name
	 * @param string $status Status (success/failed)
	 * @param string $error_message Error message (if failed)
	 * @param array  $result Renewal result data
	 */
	private function record_renewal_attempt( $domain, $status, $error_message = null, $result = array() ) {
		$history = get_site_option( 'reseller_panel_renewal_history', array() );

		if ( ! isset( $history[ $domain ] ) ) {
			$history[ $domain ] = array();
		}

		$history[ $domain ][] = array(
			'status'        => $status,
			'error_message' => $error_message,
			'timestamp'     => current_time( 'mysql' ),
			'result'        => $result,
		);

		// Keep only last 50 attempts per domain
		$history[ $domain ] = array_slice( $history[ $domain ], -50 );

		update_site_option( 'reseller_panel_renewal_history', $history );
	}

	/**
	 * Get renewal history for a domain
	 *
	 * @param string $domain Domain name
	 * @param int    $limit Number of records to return
	 *
	 * @return array
	 */
	private function get_renewal_history( $domain, $limit = 10 ) {
		$history = get_site_option( 'reseller_panel_renewal_history', array() );

		if ( ! isset( $history[ $domain ] ) ) {
			return array();
		}

		return array_slice( $history[ $domain ], -$limit );
	}

	/**
	 * Send renewal notification
	 *
	 * @param string $domain Domain name
	 * @param string $status Status (success/failed)
	 * @param string $error_message Error message (if failed)
	 */
	private function send_renewal_notification( $domain, $status, $error_message = null ) {
		$admin_email = get_site_option( 'admin_email' );

		if ( 'success' === $status ) {
			$subject = sprintf(
				/* translators: %s: Domain name */
				__( 'Domain Renewal Successful: %s', 'ultimate-multisite' ),
				$domain
			);

			$message = sprintf(
				/* translators: %s: Domain name */
				__( 'The domain %s has been successfully renewed.', 'ultimate-multisite' ),
				$domain
			);
		} else {
			$subject = sprintf(
				/* translators: %s: Domain name */
				__( 'Domain Renewal Failed: %s', 'ultimate-multisite' ),
				$domain
			);

			$message = sprintf(
				/* translators: %s: Domain name */
				__( 'The automatic renewal for domain %s has failed.', 'ultimate-multisite' ),
				$domain
			);

			if ( $error_message ) {
				$message .= "\n\n" . sprintf(
					/* translators: %s: Error message */
					__( 'Error: %s', 'ultimate-multisite' ),
					$error_message
				);
			}

			$message .= "\n\n" . __( 'Please renew this domain manually to avoid expiration.', 'ultimate-multisite' );
		}

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Send expiry notification
	 *
	 * @param string $domain Domain name
	 * @param int    $days_until_expiry Days until expiry
	 * @param array  $domain_info Domain information
	 */
	private function send_expiry_notification( $domain, $days_until_expiry, $domain_info ) {
		$admin_email = get_site_option( 'admin_email' );

		$subject = sprintf(
			/* translators: 1: Domain name, 2: Number of days */
			__( 'Domain Expiring Soon: %1$s (%2$d days)', 'ultimate-multisite' ),
			$domain,
			$days_until_expiry
		);

		$message = sprintf(
			/* translators: 1: Domain name, 2: Number of days, 3: Expiry date */
			__( 'The domain %1$s will expire in %2$d days on %3$s.', 'ultimate-multisite' ),
			$domain,
			$days_until_expiry,
			isset( $domain_info['expiry_date'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $domain_info['expiry_date'] ) ) : 'N/A'
		);

		// Check if auto-renewal is enabled
		$auto_renewals = get_site_option( 'reseller_panel_auto_renewals', array() );
		if ( isset( $auto_renewals[ $domain ] ) ) {
			$message .= "\n\n" . __( 'Auto-renewal is enabled for this domain and will be processed automatically.', 'ultimate-multisite' );
		} else {
			$message .= "\n\n" . __( 'Auto-renewal is not enabled. Please renew this domain manually to avoid expiration.', 'ultimate-multisite' );
		}

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Check if user can manage renewals for a domain
	 *
	 * @param string $domain Domain name
	 * @param int    $user_id User ID (optional, defaults to current user)
	 *
	 * @return bool True if user can manage renewals
	 */
	public function user_can_manage_renewal( $domain, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// Super admins can always manage renewals
		if ( is_super_admin( $user_id ) ) {
			return true;
		}

		// Check if user owns the domain
		// This would integrate with Ultimate Multisite's customer/domain ownership
		// For now, we'll use a simple filter to allow customization
		$can_manage = apply_filters( 'reseller_panel_user_can_manage_renewal', false, $domain, $user_id );

		return $can_manage;
	}

	/**
	 * AJAX handler: Renew domain
	 */
	public function ajax_renew_domain() {
		check_ajax_referer( 'reseller_panel_renewal_nonce', 'nonce' );

		$domain       = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
		$years        = isset( $_POST['years'] ) ? absint( $_POST['years'] ) : 1;
		$provider_key = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : null;

		if ( ! $this->user_can_manage_renewal( $domain ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ultimate-multisite' ) ) );
		}

		$result = $this->renew_domain( $domain, $years, $provider_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Domain renewed successfully', 'ultimate-multisite' ),
				'data'    => $result,
			)
		);
	}

	/**
	 * AJAX handler: Toggle auto-renewal
	 */
	public function ajax_toggle_auto_renew() {
		check_ajax_referer( 'reseller_panel_renewal_nonce', 'nonce' );

		$domain       = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
		$enabled      = isset( $_POST['enabled'] ) && '1' === $_POST['enabled'];
		$provider_key = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : null;

		if ( ! $this->user_can_manage_renewal( $domain ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ultimate-multisite' ) ) );
		}

		$result = $this->toggle_auto_renewal( $domain, $enabled, $provider_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => $enabled
					? __( 'Auto-renewal enabled', 'ultimate-multisite' )
					: __( 'Auto-renewal disabled', 'ultimate-multisite' ),
			)
		);
	}

	/**
	 * AJAX handler: Get renewal info
	 */
	public function ajax_get_renewal_info() {
		check_ajax_referer( 'reseller_panel_renewal_nonce', 'nonce' );

		$domain       = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
		$provider_key = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : null;

		if ( ! $this->user_can_manage_renewal( $domain ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ultimate-multisite' ) ) );
		}

		$result = $this->get_renewal_info( $domain, $provider_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'info' => $result ) );
	}
}
