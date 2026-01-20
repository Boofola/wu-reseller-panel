<?php
/**
 * Domain Renewal Manager - Handles automatic domain renewal operations
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Domain Renewal Manager Class
 */
class Domain_Renewal_Manager {

	/**
	 * Singleton instance
	 *
	 * @var Domain_Renewal_Manager|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Domain_Renewal_Manager
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
		// Custom action hooks
		add_action( 'reseller_panel_domain_renewal_check', array( $this, 'check_domain_renewal' ), 10, 2 );
		add_action( 'reseller_panel_domain_batch_renewal_check', array( $this, 'process_batch_renewals' ) );

		// WP Ultimo integration hooks (if WP Ultimo is available)
		if ( function_exists( 'wu_get_membership' ) ) {
			add_action( 'wu_membership_status_to_active', array( $this, 'maybe_renew_domains' ), 10, 2 );
			add_action( 'wu_membership_status_to_expired', array( $this, 'handle_expired_membership' ), 10, 2 );
			add_action( 'wu_payment_status_to_completed', array( $this, 'process_renewal_payment' ), 10, 2 );
		}
	}

	/**
	 * Check domain renewal
	 *
	 * @param int    $payment_id Payment ID
	 * @param string $domain_name Domain name
	 *
	 * @return void
	 */
	public function check_domain_renewal( $payment_id, $domain_name ) {
		// Get domain metadata
		$metadata = $this->get_domain_metadata( $domain_name );

		if ( empty( $metadata ) ) {
			Logger::log_error( 'Renewal Manager', 'Domain metadata not found', array(
				'domain' => $domain_name,
				'payment_id' => $payment_id,
			) );
			return;
		}

		// Check if auto-renewal is enabled
		$auto_renew = isset( $metadata['auto_renew'] ) ? $metadata['auto_renew'] : false;

		if ( ! $auto_renew ) {
			Logger::log_info( 'Renewal Manager', 'Auto-renewal not enabled', array(
				'domain' => $domain_name,
			) );
			return;
		}

		// Check expiry date
		$expiry_date = isset( $metadata['expiry_date'] ) ? $metadata['expiry_date'] : '';

		if ( empty( $expiry_date ) ) {
			Logger::log_error( 'Renewal Manager', 'Expiry date not found', array(
				'domain' => $domain_name,
			) );
			return;
		}

		// Calculate days until expiry
		$days_until_expiry = $this->calculate_days_until_expiry( $expiry_date );

		// Get renewal notice days
		$notice_days = get_site_option( 'reseller_panel_renewal_notice_days', 30 );

		if ( $days_until_expiry <= $notice_days ) {
			$this->process_domain_renewal( $domain_name, $metadata );
		}
	}

	/**
	 * Process batch renewals
	 *
	 * @return void
	 */
	public function process_batch_renewals() {
		Logger::log_info( 'Renewal Manager', 'Starting batch renewal process', array() );

		// Get all domains with auto-renewal enabled
		$domains = $this->get_auto_renew_domains();

		if ( empty( $domains ) ) {
			Logger::log_info( 'Renewal Manager', 'No domains found for auto-renewal', array() );
			return;
		}

		$renewed_count = 0;
		$failed_count = 0;

		foreach ( $domains as $domain ) {
			$result = $this->process_domain_renewal( $domain['domain_name'], $domain );

			if ( $result['success'] ) {
				$renewed_count++;
			} else {
				$failed_count++;
			}
		}

		Logger::log_info( 'Renewal Manager', 'Batch renewal process completed', array(
			'total' => count( $domains ),
			'renewed' => $renewed_count,
			'failed' => $failed_count,
		) );
	}

	/**
	 * Process domain renewal
	 *
	 * @param string $domain_name Domain name
	 * @param array  $metadata Domain metadata
	 *
	 * @return array Result array with 'success' and 'message' keys
	 */
	private function process_domain_renewal( $domain_name, $metadata ) {
		// Get provider
		$provider = $this->get_domain_provider( $domain_name );

		if ( ! $provider ) {
			Logger::log_error( 'Renewal Manager', 'No provider found', array(
				'domain' => $domain_name,
			) );

			return array(
				'success' => false,
				'message' => __( 'No configured provider found for this domain.', 'ultimate-multisite' ),
			);
		}

		// Renew domain
		$result = $provider->renew_domain( $domain_name, 1 );

		if ( is_wp_error( $result ) ) {
			Logger::log_error( 'Renewal Manager', 'Domain renewal failed', array(
				'domain' => $domain_name,
				'error' => $result->get_error_message(),
			) );

			// Send notification
			$this->send_renewal_failed_notification( $domain_name, $metadata );

			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		// Update expiry date
		$new_expiry = $this->calculate_new_expiry( $metadata['expiry_date'], 1 );
		$this->update_domain_metadata( $domain_name, array(
			'expiry_date' => $new_expiry,
			'last_renewed' => current_time( 'mysql' ),
		) );

		Logger::log_info( 'Renewal Manager', 'Domain renewed successfully', array(
			'domain' => $domain_name,
			'new_expiry' => $new_expiry,
		) );

		// Send success notification
		$this->send_renewal_success_notification( $domain_name, $metadata );

		return array(
			'success' => true,
			'message' => __( 'Domain renewed successfully.', 'ultimate-multisite' ),
		);
	}

	/**
	 * Maybe renew domains when membership becomes active
	 *
	 * @param object $membership Membership object
	 * @param string $old_status Old status
	 *
	 * @return void
	 */
	public function maybe_renew_domains( $membership, $old_status ) {
		if ( ! is_object( $membership ) || ! method_exists( $membership, 'get_id' ) ) {
			return;
		}

		// Get customer ID
		$customer_id = method_exists( $membership, 'get_customer_id' ) ? $membership->get_customer_id() : 0;

		if ( ! $customer_id ) {
			return;
		}

		// Get domains for this customer
		$domains = $this->get_customer_domains( $customer_id );

		foreach ( $domains as $domain ) {
			$metadata = $this->get_domain_metadata( $domain['domain_name'] );

			if ( isset( $metadata['auto_renew'] ) && $metadata['auto_renew'] ) {
				$this->process_domain_renewal( $domain['domain_name'], $metadata );
			}
		}
	}

	/**
	 * Handle expired membership
	 *
	 * @param object $membership Membership object
	 * @param string $old_status Old status
	 *
	 * @return void
	 */
	public function handle_expired_membership( $membership, $old_status ) {
		if ( ! is_object( $membership ) || ! method_exists( $membership, 'get_id' ) ) {
			return;
		}

		// Get customer ID
		$customer_id = method_exists( $membership, 'get_customer_id' ) ? $membership->get_customer_id() : 0;

		if ( ! $customer_id ) {
			return;
		}

		Logger::log_info( 'Renewal Manager', 'Membership expired', array(
			'customer_id' => $customer_id,
		) );

		// Send notification about domain renewal status
		$this->send_membership_expired_notification( $customer_id );
	}

	/**
	 * Process renewal payment
	 *
	 * @param object $payment Payment object
	 * @param string $old_status Old status
	 *
	 * @return void
	 */
	public function process_renewal_payment( $payment, $old_status ) {
		if ( ! is_object( $payment ) || ! method_exists( $payment, 'get_id' ) ) {
			return;
		}

		// Check if this is a domain renewal payment
		$payment_meta = method_exists( $payment, 'get_meta' ) ? $payment->get_meta( 'domain_renewal' ) : null;

		if ( ! $payment_meta ) {
			return;
		}

		// Get domain name
		$domain_name = isset( $payment_meta['domain_name'] ) ? $payment_meta['domain_name'] : '';

		if ( empty( $domain_name ) ) {
			return;
		}

		// Process renewal
		$metadata = $this->get_domain_metadata( $domain_name );
		$this->process_domain_renewal( $domain_name, $metadata );
	}

	/**
	 * Get auto-renew domains
	 *
	 * @return array Array of domains with auto-renewal enabled
	 */
	private function get_auto_renew_domains() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'reseller_panel_domain_meta';

		// Get all domains with auto_renew enabled
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value FROM {$table_name} WHERE meta_key = %s AND meta_value LIKE %s",
				'domain_data',
				'%"auto_renew":true%'
			),
			ARRAY_A
		);

		$domains = array();

		foreach ( $results as $result ) {
			$data = maybe_unserialize( $result['meta_value'] );

			if ( is_array( $data ) && ! empty( $data['domain_name'] ) ) {
				// Check if renewal is due
				$expiry_date = isset( $data['expiry_date'] ) ? $data['expiry_date'] : '';

				if ( ! empty( $expiry_date ) ) {
					$days_until_expiry = $this->calculate_days_until_expiry( $expiry_date );
					$notice_days = get_site_option( 'reseller_panel_renewal_notice_days', 30 );

					if ( $days_until_expiry <= $notice_days ) {
						$domains[] = $data;
					}
				}
			}
		}

		return $domains;
	}

	/**
	 * Get customer domains
	 *
	 * @param int $customer_id Customer ID
	 *
	 * @return array Array of domains
	 */
	private function get_customer_domains( $customer_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'reseller_panel_domain_meta';

		// Properly escape for LIKE query
		$search_pattern = '%' . $wpdb->esc_like( '"customer_id":' . intval( $customer_id ) ) . '%';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value FROM {$table_name} WHERE meta_key = %s AND meta_value LIKE %s",
				'domain_data',
				$search_pattern
			),
			ARRAY_A
		);

		$domains = array();

		foreach ( $results as $result ) {
			$data = maybe_unserialize( $result['meta_value'] );

			if ( is_array( $data ) && ! empty( $data['domain_name'] ) ) {
				$domains[] = $data;
			}
		}

		return $domains;
	}

	/**
	 * Get domain metadata
	 *
	 * @param string $domain_name Domain name
	 *
	 * @return array Domain metadata
	 */
	private function get_domain_metadata( $domain_name ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'reseller_panel_domain_meta';

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$table_name} WHERE meta_key = %s AND meta_value LIKE %s LIMIT 1",
				'domain_data',
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
	 * Update domain metadata
	 *
	 * @param string $domain_name Domain name
	 * @param array  $data Metadata to update
	 *
	 * @return void
	 */
	private function update_domain_metadata( $domain_name, $data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'reseller_panel_domain_meta';

		// Get existing metadata
		$existing = $this->get_domain_metadata( $domain_name );

		// Merge with new data
		$data = array_merge( $existing, $data );
		$data['domain_name'] = $domain_name;

		// Store as serialized data
		$wpdb->replace(
			$table_name,
			array(
				'domain_id' => 0,
				'meta_key' => 'domain_data',
				'meta_value' => maybe_serialize( $data ),
			),
			array( '%d', '%s', '%s' )
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
	 * Calculate days until expiry
	 *
	 * @param string $expiry_date Expiry date (Y-m-d H:i:s)
	 *
	 * @return int Days until expiry
	 */
	private function calculate_days_until_expiry( $expiry_date ) {
		$expiry = strtotime( $expiry_date );
		$now = current_time( 'timestamp' );

		return floor( ( $expiry - $now ) / DAY_IN_SECONDS );
	}

	/**
	 * Calculate new expiry date
	 *
	 * @param string $current_expiry Current expiry date
	 * @param int    $years Years to add
	 *
	 * @return string New expiry date (Y-m-d H:i:s)
	 */
	private function calculate_new_expiry( $current_expiry, $years ) {
		$expiry = strtotime( $current_expiry );
		$new_expiry = strtotime( "+{$years} year", $expiry );

		return gmdate( 'Y-m-d H:i:s', $new_expiry );
	}

	/**
	 * Send renewal success notification
	 *
	 * @param string $domain_name Domain name
	 * @param array  $metadata Domain metadata
	 *
	 * @return void
	 */
	private function send_renewal_success_notification( $domain_name, $metadata ) {
		// This would integrate with notification system
		// For now, just log
		Logger::log_info( 'Renewal Manager', 'Renewal success notification sent', array(
			'domain' => $domain_name,
		) );
	}

	/**
	 * Send renewal failed notification
	 *
	 * @param string $domain_name Domain name
	 * @param array  $metadata Domain metadata
	 *
	 * @return void
	 */
	private function send_renewal_failed_notification( $domain_name, $metadata ) {
		// This would integrate with notification system
		// For now, just log
		Logger::log_error( 'Renewal Manager', 'Renewal failed notification sent', array(
			'domain' => $domain_name,
		) );
	}

	/**
	 * Send membership expired notification
	 *
	 * @param int $customer_id Customer ID
	 *
	 * @return void
	 */
	private function send_membership_expired_notification( $customer_id ) {
		// This would integrate with notification system
		// For now, just log
		Logger::log_info( 'Renewal Manager', 'Membership expired notification sent', array(
			'customer_id' => $customer_id,
		) );
	}
}
