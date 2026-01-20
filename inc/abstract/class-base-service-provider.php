<?php
/**
 * Base Service Provider Class
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel\Abstract_Classes;

use Reseller_Panel\Interfaces\Service_Provider_Interface;

/**
 * Base Service Provider Abstract Class
 */
abstract class Base_Service_Provider implements Service_Provider_Interface {

	/**
	 * Provider key
	 *
	 * @var string
	 */
	protected $key = '';

	/**
	 * Provider name
	 *
	 * @var string
	 */
	protected $name = '';

	/**
	 * Supported services
	 *
	 * @var array
	 */
	protected $supported_services = array();

	/**
	 * Configuration
	 *
	 * @var array
	 */
	protected $config = array();

	/**
	 * Get provider key
	 *
	 * @return string
	 */
	public function get_key() {
		return $this->key;
	}

	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get supported services
	 *
	 * @return array
	 */
	public function get_supported_services() {
		return $this->supported_services;
	}

	/**
	 * Check if provider supports a service
	 *
	 * @param string $service Service key
	 *
	 * @return bool
	 */
	public function supports_service( $service ) {
		return in_array( $service, $this->supported_services, true );
	}

	/**
	 * Load configuration from database
	 *
	 * @return void
	 */
	public function load_config() {
		$this->config = get_site_option( 'reseller_panel_' . $this->key . '_config', array() );
	}

	/**
	 * Save configuration to database
	 *
	 * @param array $config Configuration array
	 *
	 * @return void
	 */
	public function save_config( $config ) {
		$this->config = $config;
		update_site_option( 'reseller_panel_' . $this->key . '_config', $config );
	}

	/**
	 * Get configuration value
	 *
	 * @param string $key Configuration key
	 * @param mixed  $default Default value
	 *
	 * @return mixed
	 */
	public function get_config_value( $key, $default = null ) {
		if ( empty( $this->config ) ) {
			$this->load_config();
		}
		return isset( $this->config[ $key ] ) ? $this->config[ $key ] : $default;
	}

	/**
	 * Check if provider is configured
	 *
	 * @return bool
	 */
	public function is_configured() {
		$has_api_key = ! empty( $this->get_config_value( 'api_key' ) );
		$has_config = ! empty( $this->config );
		$result = $has_config && $has_api_key;

		\Reseller_Panel\Logger::log_info(
			$this->name,
			'is_configured() check',
			array(
				'has_config' => $has_config ? 'yes' : 'no',
				'has_api_key' => $has_api_key ? 'yes' : 'no',
				'result' => $result ? 'yes' : 'no',
			)
		);

		return $result;
	}

	/**
	 * Check if provider is enabled
	 *
	 * @return bool
	 */
	public function is_enabled() {
		// Default to true for backward compatibility
		$enabled = (bool) $this->get_config_value( 'enabled', true );

		\Reseller_Panel\Logger::log_info(
			$this->name,
			'is_enabled() check',
			array(
				'enabled_value' => $this->get_config_value( 'enabled', 'not_set' ),
				'result' => $enabled ? 'yes' : 'no',
			)
		);

		return $enabled;
	}

	/**
	 * Get configuration fields
	 *
	 * @return array
	 */
	public function get_config_fields() {
		return array();
	}

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error
	 */
	abstract public function test_connection();

	/**
	 * Get DNS records for a domain
	 *
	 * @param string $domain_name Domain name
	 *
	 * @return array|WP_Error DNS records or error
	 */
	public function get_dns_records( $domain_name ) {
		return new \WP_Error( 'not_implemented', __( 'DNS record retrieval not implemented for this provider.', 'ultimate-multisite' ) );
	}

	/**
	 * Add DNS record
	 *
	 * @param string $domain_name Domain name
	 * @param array  $record_data Record data
	 *
	 * @return array|WP_Error Result or error
	 */
	public function add_dns_record( $domain_name, $record_data ) {
		return new \WP_Error( 'not_implemented', __( 'DNS record creation not implemented for this provider.', 'ultimate-multisite' ) );
	}

	/**
	 * Update DNS record
	 *
	 * @param string $domain_name Domain name
	 * @param string $record_id Record ID
	 * @param array  $record_data Record data
	 *
	 * @return array|WP_Error Result or error
	 */
	public function update_dns_record( $domain_name, $record_id, $record_data ) {
		return new \WP_Error( 'not_implemented', __( 'DNS record update not implemented for this provider.', 'ultimate-multisite' ) );
	}

	/**
	 * Delete DNS record
	 *
	 * @param string $domain_name Domain name
	 * @param string $record_id Record ID
	 *
	 * @return array|WP_Error Result or error
	 */
	public function delete_dns_record( $domain_name, $record_id ) {
		return new \WP_Error( 'not_implemented', __( 'DNS record deletion not implemented for this provider.', 'ultimate-multisite' ) );
	}

	/**
	 * Transfer domain
	 *
	 * @param string $domain_name Domain name
	 * @param string $auth_code Authorization code
	 * @param array  $registrant_info Registrant information
	 * @param array  $options Additional options
	 *
	 * @return array|WP_Error Result or error
	 */
	public function transfer_domain( $domain_name, $auth_code, $registrant_info, $options = array() ) {
		return new \WP_Error( 'not_implemented', __( 'Domain transfer not implemented for this provider.', 'ultimate-multisite' ) );
	}

	/**
	 * Check transfer status
	 *
	 * @param string $domain_name Domain name
	 * @param string $transfer_id Transfer ID
	 *
	 * @return array|WP_Error Status or error
	 */
	public function check_transfer_status( $domain_name, $transfer_id ) {
		return new \WP_Error( 'not_implemented', __( 'Transfer status check not implemented for this provider.', 'ultimate-multisite' ) );
	}

	/**
	 * Get authorization code for domain transfer
	 *
	 * @param string $domain_name Domain name
	 *
	 * @return array|WP_Error Auth code or error
	 */
	public function get_auth_code( $domain_name ) {
		return new \WP_Error( 'not_implemented', __( 'Authorization code retrieval not implemented for this provider.', 'ultimate-multisite' ) );
	}

	/**
	 * Renew domain
	 *
	 * @param string $domain_name Domain name
	 * @param int    $years Number of years
	 *
	 * @return array|WP_Error Result or error
	 */
	public function renew_domain( $domain_name, $years = 1 ) {
		return new \WP_Error( 'not_implemented', __( 'Domain renewal not implemented for this provider.', 'ultimate-multisite' ) );
	}
}