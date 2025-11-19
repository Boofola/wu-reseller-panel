<?php
/**
 * Service Provider Interface
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel\Interfaces;

/**
 * Service Provider Interface
 */
interface Service_Provider_Interface {

	/**
	 * Get provider unique key
	 *
	 * @return string
	 */
	public function get_key();

	/**
	 * Get provider name
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Get supported services
	 *
	 * @return array
	 */
	public function get_supported_services();

	/**
	 * Check if provider is configured
	 *
	 * @return bool
	 */
	public function is_configured();

	/**
	 * Test connection
	 *
	 * @return bool|WP_Error
	 */
	public function test_connection();

	/**
	 * Get configuration fields
	 *
	 * @return array
	 */
	public function get_config_fields();
}
