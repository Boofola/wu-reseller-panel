<?php
/**
 * DNS Manager Class
 *
 * Manages DNS records for domains including CRUD operations,
 * customer permission checking, and AJAX handlers.
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DNS Manager Class
 *
 * Handles DNS record management operations.
 */
class DNS_Manager {

	/**
	 * Singleton instance
	 *
	 * @var self
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
		add_action( 'wp_ajax_reseller_panel_get_dns_records', array( $this, 'ajax_get_dns_records' ) );
		add_action( 'wp_ajax_reseller_panel_add_dns_record', array( $this, 'ajax_add_dns_record' ) );
		add_action( 'wp_ajax_reseller_panel_update_dns_record', array( $this, 'ajax_update_dns_record' ) );
		add_action( 'wp_ajax_reseller_panel_delete_dns_record', array( $this, 'ajax_delete_dns_record' ) );
		add_action( 'wp_ajax_reseller_panel_reset_dns_records', array( $this, 'ajax_reset_dns_records' ) );
	}

	/**
	 * Get DNS records for a domain
	 *
	 * @param string $domain Domain name
	 * @param string $provider_key Provider key (optional)
	 *
	 * @return array|WP_Error Array of DNS records or WP_Error on failure
	 */
	public function get_dns_records( $domain, $provider_key = null ) {
		if ( empty( $domain ) ) {
			return new \WP_Error( 'invalid_domain', __( 'Domain name is required', 'ultimate-multisite' ) );
		}

		// Get provider
		$provider = $this->get_provider_for_domain( $domain, $provider_key );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		// Check if provider supports DNS management
		if ( ! method_exists( $provider, 'get_dns_records' ) ) {
			return new \WP_Error(
				'unsupported_operation',
				sprintf(
					/* translators: %s: Provider name */
					__( 'DNS management is not supported by %s', 'ultimate-multisite' ),
					$provider->get_name()
				)
			);
		}

		// Get DNS records from provider
		$result = $provider->get_dns_records( $domain );

		if ( is_wp_error( $result ) ) {
			Logger::log_error(
				$provider->get_key(),
				sprintf( 'Failed to get DNS records for %s: %s', $domain, $result->get_error_message() )
			);
			return $result;
		}

		Logger::log_info(
			$provider->get_key(),
			sprintf( 'Retrieved DNS records for %s', $domain )
		);

		return $result;
	}

	/**
	 * Add a DNS record
	 *
	 * @param string $domain Domain name
	 * @param array  $record_data Record data (type, name, value, ttl, priority)
	 * @param string $provider_key Provider key (optional)
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function add_dns_record( $domain, $record_data, $provider_key = null ) {
		// Validate inputs
		$validation = $this->validate_dns_record_data( $record_data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Get provider
		$provider = $this->get_provider_for_domain( $domain, $provider_key );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		// Check if provider supports DNS management
		if ( ! method_exists( $provider, 'add_dns_record' ) ) {
			return new \WP_Error(
				'unsupported_operation',
				sprintf(
					/* translators: %s: Provider name */
					__( 'DNS management is not supported by %s', 'ultimate-multisite' ),
					$provider->get_name()
				)
			);
		}

		// Add DNS record via provider
		$result = $provider->add_dns_record( $domain, $record_data );

		if ( is_wp_error( $result ) ) {
			Logger::log_error(
				$provider->get_key(),
				sprintf( 'Failed to add DNS record for %s: %s', $domain, $result->get_error_message() ),
				array( 'record_data' => $record_data )
			);
			return $result;
		}

		Logger::log_info(
			$provider->get_key(),
			sprintf( 'Added DNS record for %s', $domain ),
			array( 'record_data' => $record_data )
		);

		return $result;
	}

	/**
	 * Update a DNS record
	 *
	 * @param string $domain Domain name
	 * @param string $record_id Record ID
	 * @param array  $record_data Updated record data
	 * @param string $provider_key Provider key (optional)
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function update_dns_record( $domain, $record_id, $record_data, $provider_key = null ) {
		// Validate inputs
		$validation = $this->validate_dns_record_data( $record_data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Get provider
		$provider = $this->get_provider_for_domain( $domain, $provider_key );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		// Check if provider supports DNS management
		if ( ! method_exists( $provider, 'update_dns_record' ) ) {
			return new \WP_Error(
				'unsupported_operation',
				sprintf(
					/* translators: %s: Provider name */
					__( 'DNS management is not supported by %s', 'ultimate-multisite' ),
					$provider->get_name()
				)
			);
		}

		// Update DNS record via provider
		$result = $provider->update_dns_record( $domain, $record_id, $record_data );

		if ( is_wp_error( $result ) ) {
			Logger::log_error(
				$provider->get_key(),
				sprintf( 'Failed to update DNS record for %s: %s', $domain, $result->get_error_message() ),
				array(
					'record_id'   => $record_id,
					'record_data' => $record_data,
				)
			);
			return $result;
		}

		Logger::log_info(
			$provider->get_key(),
			sprintf( 'Updated DNS record for %s', $domain ),
			array(
				'record_id'   => $record_id,
				'record_data' => $record_data,
			)
		);

		return $result;
	}

	/**
	 * Delete a DNS record
	 *
	 * @param string $domain Domain name
	 * @param string $record_id Record ID
	 * @param string $provider_key Provider key (optional)
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function delete_dns_record( $domain, $record_id, $provider_key = null ) {
		if ( empty( $record_id ) ) {
			return new \WP_Error( 'invalid_record', __( 'Record ID is required', 'ultimate-multisite' ) );
		}

		// Get provider
		$provider = $this->get_provider_for_domain( $domain, $provider_key );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		// Check if provider supports DNS management
		if ( ! method_exists( $provider, 'delete_dns_record' ) ) {
			return new \WP_Error(
				'unsupported_operation',
				sprintf(
					/* translators: %s: Provider name */
					__( 'DNS management is not supported by %s', 'ultimate-multisite' ),
					$provider->get_name()
				)
			);
		}

		// Delete DNS record via provider
		$result = $provider->delete_dns_record( $domain, $record_id );

		if ( is_wp_error( $result ) ) {
			Logger::log_error(
				$provider->get_key(),
				sprintf( 'Failed to delete DNS record for %s: %s', $domain, $result->get_error_message() ),
				array( 'record_id' => $record_id )
			);
			return $result;
		}

		Logger::log_info(
			$provider->get_key(),
			sprintf( 'Deleted DNS record for %s', $domain ),
			array( 'record_id' => $record_id )
		);

		return $result;
	}

	/**
	 * Reset DNS records to default
	 *
	 * @param string $domain Domain name
	 * @param string $provider_key Provider key (optional)
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function reset_dns_records( $domain, $provider_key = null ) {
		if ( empty( $domain ) ) {
			return new \WP_Error( 'invalid_domain', __( 'Domain name is required', 'ultimate-multisite' ) );
		}

		// Get provider
		$provider = $this->get_provider_for_domain( $domain, $provider_key );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		// Check if provider supports DNS management
		if ( ! method_exists( $provider, 'reset_dns_records' ) ) {
			return new \WP_Error(
				'unsupported_operation',
				sprintf(
					/* translators: %s: Provider name */
					__( 'DNS management is not supported by %s', 'ultimate-multisite' ),
					$provider->get_name()
				)
			);
		}

		// Reset DNS records via provider
		$result = $provider->reset_dns_records( $domain );

		if ( is_wp_error( $result ) ) {
			Logger::log_error(
				$provider->get_key(),
				sprintf( 'Failed to reset DNS records for %s: %s', $domain, $result->get_error_message() )
			);
			return $result;
		}

		Logger::log_info(
			$provider->get_key(),
			sprintf( 'Reset DNS records for %s', $domain )
		);

		return $result;
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
	 * Validate DNS record data
	 *
	 * @param array $record_data Record data to validate
	 *
	 * @return true|WP_Error True if valid, WP_Error otherwise
	 */
	private function validate_dns_record_data( $record_data ) {
		if ( empty( $record_data['type'] ) ) {
			return new \WP_Error( 'missing_type', __( 'DNS record type is required', 'ultimate-multisite' ) );
		}

		if ( ! in_array( $record_data['type'], $this->supported_record_types, true ) ) {
			return new \WP_Error(
				'invalid_type',
				sprintf(
					/* translators: %s: Record type */
					__( 'Invalid DNS record type: %s', 'ultimate-multisite' ),
					$record_data['type']
				)
			);
		}

		if ( empty( $record_data['name'] ) && 'A' !== $record_data['type'] && 'AAAA' !== $record_data['type'] ) {
			return new \WP_Error( 'missing_name', __( 'DNS record name is required', 'ultimate-multisite' ) );
		}

		if ( empty( $record_data['value'] ) ) {
			return new \WP_Error( 'missing_value', __( 'DNS record value is required', 'ultimate-multisite' ) );
		}

		// Validate MX priority
		if ( 'MX' === $record_data['type'] && empty( $record_data['priority'] ) ) {
			return new \WP_Error( 'missing_priority', __( 'MX record requires a priority value', 'ultimate-multisite' ) );
		}

		return true;
	}

	/**
	 * Check if user can manage DNS for a domain
	 *
	 * @param string $domain Domain name
	 * @param int    $user_id User ID (optional, defaults to current user)
	 *
	 * @return bool True if user can manage DNS
	 */
	public function user_can_manage_dns( $domain, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// Super admins can always manage DNS
		if ( is_super_admin( $user_id ) ) {
			return true;
		}

		// Check if user owns the domain
		// This would integrate with Ultimate Multisite's customer/domain ownership
		// For now, we'll use a simple filter to allow customization
		$can_manage = apply_filters( 'reseller_panel_user_can_manage_dns', false, $domain, $user_id );

		return $can_manage;
	}

	/**
	 * AJAX handler: Get DNS records
	 */
	public function ajax_get_dns_records() {
		check_ajax_referer( 'reseller_panel_dns_nonce', 'nonce' );

		$domain       = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
		$provider_key = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : null;

		if ( ! $this->user_can_manage_dns( $domain ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ultimate-multisite' ) ) );
		}

		$result = $this->get_dns_records( $domain, $provider_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'records' => $result ) );
	}

	/**
	 * AJAX handler: Add DNS record
	 */
	public function ajax_add_dns_record() {
		check_ajax_referer( 'reseller_panel_dns_nonce', 'nonce' );

		$domain       = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
		$provider_key = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : null;

		if ( ! $this->user_can_manage_dns( $domain ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ultimate-multisite' ) ) );
		}

		$record_data = array(
			'type'     => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '',
			'name'     => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'value'    => isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '',
			'ttl'      => isset( $_POST['ttl'] ) ? absint( $_POST['ttl'] ) : 3600,
			'priority' => isset( $_POST['priority'] ) ? absint( $_POST['priority'] ) : 10,
		);

		$result = $this->add_dns_record( $domain, $record_data, $provider_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'DNS record added successfully', 'ultimate-multisite' ) ) );
	}

	/**
	 * AJAX handler: Update DNS record
	 */
	public function ajax_update_dns_record() {
		check_ajax_referer( 'reseller_panel_dns_nonce', 'nonce' );

		$domain       = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
		$record_id    = isset( $_POST['record_id'] ) ? sanitize_text_field( wp_unslash( $_POST['record_id'] ) ) : '';
		$provider_key = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : null;

		if ( ! $this->user_can_manage_dns( $domain ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ultimate-multisite' ) ) );
		}

		$record_data = array(
			'type'     => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '',
			'name'     => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'value'    => isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '',
			'ttl'      => isset( $_POST['ttl'] ) ? absint( $_POST['ttl'] ) : 3600,
			'priority' => isset( $_POST['priority'] ) ? absint( $_POST['priority'] ) : 10,
		);

		$result = $this->update_dns_record( $domain, $record_id, $record_data, $provider_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'DNS record updated successfully', 'ultimate-multisite' ) ) );
	}

	/**
	 * AJAX handler: Delete DNS record
	 */
	public function ajax_delete_dns_record() {
		check_ajax_referer( 'reseller_panel_dns_nonce', 'nonce' );

		$domain       = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
		$record_id    = isset( $_POST['record_id'] ) ? sanitize_text_field( wp_unslash( $_POST['record_id'] ) ) : '';
		$provider_key = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : null;

		if ( ! $this->user_can_manage_dns( $domain ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ultimate-multisite' ) ) );
		}

		$result = $this->delete_dns_record( $domain, $record_id, $provider_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'DNS record deleted successfully', 'ultimate-multisite' ) ) );
	}

	/**
	 * AJAX handler: Reset DNS records
	 */
	public function ajax_reset_dns_records() {
		check_ajax_referer( 'reseller_panel_dns_nonce', 'nonce' );

		$domain       = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
		$provider_key = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : null;

		if ( ! $this->user_can_manage_dns( $domain ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'ultimate-multisite' ) ) );
		}

		$result = $this->reset_dns_records( $domain, $provider_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'DNS records reset successfully', 'ultimate-multisite' ) ) );
	}

	/**
	 * Get supported DNS record types
	 *
	 * @return array
	 */
	public function get_supported_record_types() {
		return $this->supported_record_types;
	}
}
