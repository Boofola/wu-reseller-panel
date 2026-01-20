<?php
/**
 * DNS Manager - Handles DNS record management operations
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DNS Manager Class
 */
class DNS_Manager {

	/**
	 * Singleton instance
	 *
	 * @var DNS_Manager|null
	 */
	private static $instance = null;

	/**
	 * Supported DNS record types
	 *
	 * @var array
	 */
	private $supported_record_types = array(
		'A',
		'AAAA',
		'CNAME',
		'MX',
		'TXT',
		'NS',
		'SRV',
		'CAA',
		'PTR',
	);

	/**
	 * Get singleton instance
	 *
	 * @return DNS_Manager
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
		// Admin AJAX handlers
		add_action( 'wp_ajax_reseller_panel_get_dns_records', array( $this, 'ajax_get_dns_records' ) );
		add_action( 'wp_ajax_reseller_panel_add_dns_record', array( $this, 'ajax_add_dns_record' ) );
		add_action( 'wp_ajax_reseller_panel_update_dns_record', array( $this, 'ajax_update_dns_record' ) );
		add_action( 'wp_ajax_reseller_panel_delete_dns_record', array( $this, 'ajax_delete_dns_record' ) );

		// Customer AJAX handlers
		add_action( 'wp_ajax_reseller_panel_customer_get_dns_records', array( $this, 'ajax_customer_get_dns_records' ) );
		add_action( 'wp_ajax_reseller_panel_customer_add_dns_record', array( $this, 'ajax_customer_add_dns_record' ) );
		add_action( 'wp_ajax_reseller_panel_customer_update_dns_record', array( $this, 'ajax_customer_update_dns_record' ) );
		add_action( 'wp_ajax_reseller_panel_customer_delete_dns_record', array( $this, 'ajax_customer_delete_dns_record' ) );
	}

	/**
	 * Get DNS records for a domain
	 *
	 * @param string $domain_name Domain name
	 * @param int    $customer_id Customer ID (0 for admin)
	 *
	 * @return array Array with 'success' and 'data' or 'message' keys
	 */
	public function get_dns_records( $domain_name, $customer_id = 0 ) {
		// Check permissions
		if ( $customer_id > 0 && ! $this->can_manage_domain_dns( $domain_name, $customer_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to manage DNS for this domain.', 'ultimate-multisite' ),
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

		// Get DNS records from provider
		$result = $provider->get_dns_records( $domain_name );

		if ( is_wp_error( $result ) ) {
			Logger::log_error( 'DNS Manager', 'Failed to get DNS records', array(
				'domain' => $domain_name,
				'error' => $result->get_error_message(),
			) );

			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		Logger::log_info( 'DNS Manager', 'Retrieved DNS records', array(
			'domain' => $domain_name,
			'count' => count( $result ),
		) );

		return array(
			'success' => true,
			'data' => $result,
		);
	}

	/**
	 * Add a DNS record
	 *
	 * @param string $domain_name Domain name
	 * @param array  $record_data Record data (type, name, value, ttl, priority)
	 * @param int    $customer_id Customer ID (0 for admin)
	 *
	 * @return array Array with 'success' and 'data' or 'message' keys
	 */
	public function add_dns_record( $domain_name, $record_data, $customer_id = 0 ) {
		// Check permissions
		if ( $customer_id > 0 && ! $this->can_manage_domain_dns( $domain_name, $customer_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to manage DNS for this domain.', 'ultimate-multisite' ),
			);
		}

		// Validate record data
		$validation = $this->validate_dns_record( $record_data );
		if ( ! $validation['valid'] ) {
			return array(
				'success' => false,
				'message' => $validation['message'],
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

		// Add DNS record via provider
		$result = $provider->add_dns_record( $domain_name, $record_data );

		if ( is_wp_error( $result ) ) {
			Logger::log_error( 'DNS Manager', 'Failed to add DNS record', array(
				'domain' => $domain_name,
				'record_type' => $record_data['type'],
				'error' => $result->get_error_message(),
			) );

			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		Logger::log_info( 'DNS Manager', 'Added DNS record', array(
			'domain' => $domain_name,
			'record_type' => $record_data['type'],
		) );

		return array(
			'success' => true,
			'data' => $result,
			'message' => __( 'DNS record added successfully.', 'ultimate-multisite' ),
		);
	}

	/**
	 * Update a DNS record
	 *
	 * @param string $domain_name Domain name
	 * @param string $record_id Record ID
	 * @param array  $record_data Record data
	 * @param int    $customer_id Customer ID (0 for admin)
	 *
	 * @return array Array with 'success' and 'data' or 'message' keys
	 */
	public function update_dns_record( $domain_name, $record_id, $record_data, $customer_id = 0 ) {
		// Check permissions
		if ( $customer_id > 0 && ! $this->can_manage_domain_dns( $domain_name, $customer_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to manage DNS for this domain.', 'ultimate-multisite' ),
			);
		}

		// Validate record data
		$validation = $this->validate_dns_record( $record_data );
		if ( ! $validation['valid'] ) {
			return array(
				'success' => false,
				'message' => $validation['message'],
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

		// Update DNS record via provider
		$result = $provider->update_dns_record( $domain_name, $record_id, $record_data );

		if ( is_wp_error( $result ) ) {
			Logger::log_error( 'DNS Manager', 'Failed to update DNS record', array(
				'domain' => $domain_name,
				'record_id' => $record_id,
				'error' => $result->get_error_message(),
			) );

			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		Logger::log_info( 'DNS Manager', 'Updated DNS record', array(
			'domain' => $domain_name,
			'record_id' => $record_id,
		) );

		return array(
			'success' => true,
			'data' => $result,
			'message' => __( 'DNS record updated successfully.', 'ultimate-multisite' ),
		);
	}

	/**
	 * Delete a DNS record
	 *
	 * @param string $domain_name Domain name
	 * @param string $record_id Record ID
	 * @param int    $customer_id Customer ID (0 for admin)
	 *
	 * @return array Array with 'success' and 'data' or 'message' keys
	 */
	public function delete_dns_record( $domain_name, $record_id, $customer_id = 0 ) {
		// Check permissions
		if ( $customer_id > 0 && ! $this->can_manage_domain_dns( $domain_name, $customer_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to manage DNS for this domain.', 'ultimate-multisite' ),
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

		// Delete DNS record via provider
		$result = $provider->delete_dns_record( $domain_name, $record_id );

		if ( is_wp_error( $result ) ) {
			Logger::log_error( 'DNS Manager', 'Failed to delete DNS record', array(
				'domain' => $domain_name,
				'record_id' => $record_id,
				'error' => $result->get_error_message(),
			) );

			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		Logger::log_info( 'DNS Manager', 'Deleted DNS record', array(
			'domain' => $domain_name,
			'record_id' => $record_id,
		) );

		return array(
			'success' => true,
			'message' => __( 'DNS record deleted successfully.', 'ultimate-multisite' ),
		);
	}

	/**
	 * Get zone file for a domain
	 *
	 * @param string $domain_name Domain name
	 * @param int    $customer_id Customer ID (0 for admin)
	 *
	 * @return array Array with 'success' and 'data' or 'message' keys
	 */
	public function get_zone_file( $domain_name, $customer_id = 0 ) {
		// Check permissions
		if ( $customer_id > 0 && ! $this->can_manage_domain_dns( $domain_name, $customer_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'You do not have permission to manage DNS for this domain.', 'ultimate-multisite' ),
			);
		}

		// Get DNS records
		$records_result = $this->get_dns_records( $domain_name, $customer_id );
		if ( ! $records_result['success'] ) {
			return $records_result;
		}

		// Generate zone file
		$zone_file = $this->generate_zone_file( $domain_name, $records_result['data'] );

		return array(
			'success' => true,
			'data' => $zone_file,
		);
	}

	/**
	 * Validate DNS record data
	 *
	 * @param array $record_data Record data
	 *
	 * @return array Validation result with 'valid' and 'message' keys
	 */
	public function validate_dns_record( $record_data ) {
		// Check required fields
		if ( empty( $record_data['type'] ) ) {
			return array(
				'valid' => false,
				'message' => __( 'DNS record type is required.', 'ultimate-multisite' ),
			);
		}

		if ( empty( $record_data['name'] ) ) {
			return array(
				'valid' => false,
				'message' => __( 'DNS record name is required.', 'ultimate-multisite' ),
			);
		}

		if ( empty( $record_data['value'] ) ) {
			return array(
				'valid' => false,
				'message' => __( 'DNS record value is required.', 'ultimate-multisite' ),
			);
		}

		// Check if record type is supported
		if ( ! in_array( $record_data['type'], $this->supported_record_types, true ) ) {
			return array(
				'valid' => false,
				'message' => sprintf(
					/* translators: %s: record type */
					__( 'DNS record type "%s" is not supported.', 'ultimate-multisite' ),
					$record_data['type']
				),
			);
		}

		// Type-specific validation
		switch ( $record_data['type'] ) {
			case 'A':
				if ( ! filter_var( $record_data['value'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
					return array(
						'valid' => false,
						'message' => __( 'Invalid IPv4 address for A record.', 'ultimate-multisite' ),
					);
				}
				break;

			case 'AAAA':
				if ( ! filter_var( $record_data['value'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
					return array(
						'valid' => false,
						'message' => __( 'Invalid IPv6 address for AAAA record.', 'ultimate-multisite' ),
					);
				}
				break;

			case 'MX':
				if ( empty( $record_data['priority'] ) ) {
					return array(
						'valid' => false,
						'message' => __( 'Priority is required for MX records.', 'ultimate-multisite' ),
					);
				}
				break;
		}

		return array(
			'valid' => true,
			'message' => '',
		);
	}

	/**
	 * Check if customer can manage DNS for a domain
	 *
	 * @param string $domain_name Domain name
	 * @param int    $customer_id Customer ID
	 *
	 * @return bool
	 */
	private function can_manage_domain_dns( $domain_name, $customer_id ) {
		// Allow admins
		if ( current_user_can( 'manage_network' ) ) {
			return true;
		}

		// Check if customer DNS management is enabled
		$enabled = get_site_option( 'reseller_panel_enable_customer_dns', true );
		if ( ! $enabled ) {
			return false;
		}

		// Check if customer owns the domain
		// This would integrate with WP Ultimo's domain model
		// For now, return true if user is logged in and customer ID matches
		if ( is_user_logged_in() && get_current_user_id() === $customer_id ) {
			return true;
		}

		return false;
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
	 * Generate zone file from DNS records
	 *
	 * @param string $domain_name Domain name
	 * @param array  $records DNS records
	 *
	 * @return string Zone file content
	 */
	private function generate_zone_file( $domain_name, $records ) {
		$zone_file = "; Zone file for {$domain_name}\n";
		$zone_file .= "; Generated on " . current_time( 'mysql' ) . "\n\n";

		foreach ( $records as $record ) {
			$name = isset( $record['name'] ) ? $record['name'] : '';
			$ttl = isset( $record['ttl'] ) ? $record['ttl'] : '3600';
			$type = isset( $record['type'] ) ? $record['type'] : '';
			$value = isset( $record['value'] ) ? $record['value'] : '';
			$priority = isset( $record['priority'] ) ? $record['priority'] . ' ' : '';

			$zone_file .= "{$name}\t{$ttl}\tIN\t{$type}\t{$priority}{$value}\n";
		}

		return $zone_file;
	}

	/**
	 * AJAX handler: Get DNS records
	 *
	 * @return void
	 */
	public function ajax_get_dns_records() {
		check_ajax_referer( 'reseller-panel-admin', 'nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ultimate-multisite' ) ) );
		}

		$domain_name = isset( $_POST['domain_name'] ) ? sanitize_text_field( wp_unslash( $_POST['domain_name'] ) ) : '';

		if ( empty( $domain_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Domain name is required.', 'ultimate-multisite' ) ) );
		}

		$result = $this->get_dns_records( $domain_name );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler: Add DNS record
	 *
	 * @return void
	 */
	public function ajax_add_dns_record() {
		check_ajax_referer( 'reseller-panel-admin', 'nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ultimate-multisite' ) ) );
		}

		$domain_name = isset( $_POST['domain_name'] ) ? sanitize_text_field( wp_unslash( $_POST['domain_name'] ) ) : '';
		$record_data = isset( $_POST['record_data'] ) ? wp_unslash( $_POST['record_data'] ) : array();

		if ( empty( $domain_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Domain name is required.', 'ultimate-multisite' ) ) );
		}

		// Sanitize record data
		$record_data = array(
			'type' => isset( $record_data['type'] ) ? sanitize_text_field( $record_data['type'] ) : '',
			'name' => isset( $record_data['name'] ) ? sanitize_text_field( $record_data['name'] ) : '',
			'value' => isset( $record_data['value'] ) ? sanitize_text_field( $record_data['value'] ) : '',
			'ttl' => isset( $record_data['ttl'] ) ? absint( $record_data['ttl'] ) : 3600,
			'priority' => isset( $record_data['priority'] ) ? absint( $record_data['priority'] ) : 0,
		);

		$result = $this->add_dns_record( $domain_name, $record_data );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler: Update DNS record
	 *
	 * @return void
	 */
	public function ajax_update_dns_record() {
		check_ajax_referer( 'reseller-panel-admin', 'nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ultimate-multisite' ) ) );
		}

		$domain_name = isset( $_POST['domain_name'] ) ? sanitize_text_field( wp_unslash( $_POST['domain_name'] ) ) : '';
		$record_id = isset( $_POST['record_id'] ) ? sanitize_text_field( wp_unslash( $_POST['record_id'] ) ) : '';
		$record_data = isset( $_POST['record_data'] ) ? wp_unslash( $_POST['record_data'] ) : array();

		if ( empty( $domain_name ) || empty( $record_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Domain name and record ID are required.', 'ultimate-multisite' ) ) );
		}

		// Sanitize record data
		$record_data = array(
			'type' => isset( $record_data['type'] ) ? sanitize_text_field( $record_data['type'] ) : '',
			'name' => isset( $record_data['name'] ) ? sanitize_text_field( $record_data['name'] ) : '',
			'value' => isset( $record_data['value'] ) ? sanitize_text_field( $record_data['value'] ) : '',
			'ttl' => isset( $record_data['ttl'] ) ? absint( $record_data['ttl'] ) : 3600,
			'priority' => isset( $record_data['priority'] ) ? absint( $record_data['priority'] ) : 0,
		);

		$result = $this->update_dns_record( $domain_name, $record_id, $record_data );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler: Delete DNS record
	 *
	 * @return void
	 */
	public function ajax_delete_dns_record() {
		check_ajax_referer( 'reseller-panel-admin', 'nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ultimate-multisite' ) ) );
		}

		$domain_name = isset( $_POST['domain_name'] ) ? sanitize_text_field( wp_unslash( $_POST['domain_name'] ) ) : '';
		$record_id = isset( $_POST['record_id'] ) ? sanitize_text_field( wp_unslash( $_POST['record_id'] ) ) : '';

		if ( empty( $domain_name ) || empty( $record_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Domain name and record ID are required.', 'ultimate-multisite' ) ) );
		}

		$result = $this->delete_dns_record( $domain_name, $record_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler: Customer get DNS records
	 *
	 * @return void
	 */
	public function ajax_customer_get_dns_records() {
		check_ajax_referer( 'reseller-panel-customer', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ultimate-multisite' ) ) );
		}

		$domain_name = isset( $_POST['domain_name'] ) ? sanitize_text_field( wp_unslash( $_POST['domain_name'] ) ) : '';
		$customer_id = get_current_user_id();

		if ( empty( $domain_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Domain name is required.', 'ultimate-multisite' ) ) );
		}

		$result = $this->get_dns_records( $domain_name, $customer_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler: Customer add DNS record
	 *
	 * @return void
	 */
	public function ajax_customer_add_dns_record() {
		check_ajax_referer( 'reseller-panel-customer', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ultimate-multisite' ) ) );
		}

		$domain_name = isset( $_POST['domain_name'] ) ? sanitize_text_field( wp_unslash( $_POST['domain_name'] ) ) : '';
		$record_data = isset( $_POST['record_data'] ) ? wp_unslash( $_POST['record_data'] ) : array();
		$customer_id = get_current_user_id();

		if ( empty( $domain_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Domain name is required.', 'ultimate-multisite' ) ) );
		}

		// Sanitize record data
		$record_data = array(
			'type' => isset( $record_data['type'] ) ? sanitize_text_field( $record_data['type'] ) : '',
			'name' => isset( $record_data['name'] ) ? sanitize_text_field( $record_data['name'] ) : '',
			'value' => isset( $record_data['value'] ) ? sanitize_text_field( $record_data['value'] ) : '',
			'ttl' => isset( $record_data['ttl'] ) ? absint( $record_data['ttl'] ) : 3600,
			'priority' => isset( $record_data['priority'] ) ? absint( $record_data['priority'] ) : 0,
		);

		$result = $this->add_dns_record( $domain_name, $record_data, $customer_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler: Customer update DNS record
	 *
	 * @return void
	 */
	public function ajax_customer_update_dns_record() {
		check_ajax_referer( 'reseller-panel-customer', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ultimate-multisite' ) ) );
		}

		$domain_name = isset( $_POST['domain_name'] ) ? sanitize_text_field( wp_unslash( $_POST['domain_name'] ) ) : '';
		$record_id = isset( $_POST['record_id'] ) ? sanitize_text_field( wp_unslash( $_POST['record_id'] ) ) : '';
		$record_data = isset( $_POST['record_data'] ) ? wp_unslash( $_POST['record_data'] ) : array();
		$customer_id = get_current_user_id();

		if ( empty( $domain_name ) || empty( $record_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Domain name and record ID are required.', 'ultimate-multisite' ) ) );
		}

		// Sanitize record data
		$record_data = array(
			'type' => isset( $record_data['type'] ) ? sanitize_text_field( $record_data['type'] ) : '',
			'name' => isset( $record_data['name'] ) ? sanitize_text_field( $record_data['name'] ) : '',
			'value' => isset( $record_data['value'] ) ? sanitize_text_field( $record_data['value'] ) : '',
			'ttl' => isset( $record_data['ttl'] ) ? absint( $record_data['ttl'] ) : 3600,
			'priority' => isset( $record_data['priority'] ) ? absint( $record_data['priority'] ) : 0,
		);

		$result = $this->update_dns_record( $domain_name, $record_id, $record_data, $customer_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler: Customer delete DNS record
	 *
	 * @return void
	 */
	public function ajax_customer_delete_dns_record() {
		check_ajax_referer( 'reseller-panel-customer', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'ultimate-multisite' ) ) );
		}

		$domain_name = isset( $_POST['domain_name'] ) ? sanitize_text_field( wp_unslash( $_POST['domain_name'] ) ) : '';
		$record_id = isset( $_POST['record_id'] ) ? sanitize_text_field( wp_unslash( $_POST['record_id'] ) ) : '';
		$customer_id = get_current_user_id();

		if ( empty( $domain_name ) || empty( $record_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Domain name and record ID are required.', 'ultimate-multisite' ) ) );
		}

		$result = $this->delete_dns_record( $domain_name, $record_id, $customer_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Get supported record types
	 *
	 * @return array
	 */
	public function get_supported_record_types() {
		return $this->supported_record_types;
	}
}
