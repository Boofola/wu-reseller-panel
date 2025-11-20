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
		return ! empty( $this->config ) && ! empty( $this->get_config_value( 'api_key' ) );
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
}
