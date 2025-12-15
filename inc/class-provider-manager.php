<?php
/**
 * Provider Manager - Factory for managing service providers
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel;

use Reseller_Panel\Interfaces\Service_Provider_Interface;
use Reseller_Panel\Providers\OpenSRS_Provider;
use Reseller_Panel\Providers\NameCheap_Provider;

/**
 * Provider Manager Class
 */
class Provider_Manager {

	/**
	 * Singleton instance
	 *
	 * @var Provider_Manager|null
	 */
	private static $instance = null;

	/**
	 * Registered providers
	 *
	 * @var array
	 */
	private $providers = array();

	/**
	 * Get singleton instance
	 *
	 * @return Provider_Manager
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
		$this->register_default_providers();
	}

	/**
	 * Register built-in providers
	 *
	 * @return void
	 */
	private function register_default_providers() {
		try {
			if ( class_exists( 'Reseller_Panel\Providers\OpenSRS_Provider' ) ) {
				$this->register_provider( new OpenSRS_Provider() );
			} else {
				\error_log( 'Reseller Panel - OpenSRS_Provider class not found' );
			}
			
			if ( class_exists( 'Reseller_Panel\Providers\NameCheap_Provider' ) ) {
				$this->register_provider( new NameCheap_Provider() );
			} else {
				\error_log( 'Reseller Panel - NameCheap_Provider class not found' );
			}
		} catch ( \Exception $e ) {
			\error_log( 'Reseller Panel - Error registering providers: ' . $e->getMessage() );
		}
	}

	/**
	 * Register a provider
	 *
	 * @param Service_Provider_Interface $provider Provider instance
	 *
	 * @return void
	 */
	public function register_provider( Service_Provider_Interface $provider ) {
		$this->providers[ $provider->get_key() ] = $provider;
	}

	/**
	 * Get provider by key
	 *
	 * @param string $key Provider key
	 *
	 * @return Service_Provider_Interface|null
	 */
	public function get_provider( $key ) {
		return isset( $this->providers[ $key ] ) ? $this->providers[ $key ] : null;
	}

	/**
	 * Get all providers
	 *
	 * @return array
	 */
	public function get_all_providers() {
		return $this->providers;
	}

	/**
	 * Get providers supporting a specific service
	 *
	 * @param string $service Service key
	 *
	 * @return array
	 */
	public function get_providers_by_service( $service ) {
		$matching_providers = array();

		foreach ( $this->providers as $key => $provider ) {
			if ( $provider->supports_service( $service ) ) {
				$matching_providers[ $key ] = $provider;
			}
		}

		return $matching_providers;
	}

	/**
	 * Get configured providers
	 *
	 * @return array
	 */
	public function get_configured_providers() {
		$configured = array();

		foreach ( $this->providers as $key => $provider ) {
			$provider->load_config();
			if ( $provider->is_configured() ) {
				$configured[ $key ] = $provider;
			}
		}

		return $configured;
	}

	/**
	 * Get providers that support a service and are configured
	 *
	 * @param string $service Service key
	 *
	 * @return array
	 */
	public function get_available_providers( $service ) {
		$available = array();

		foreach ( $this->get_providers_by_service( $service ) as $key => $provider ) {
			$provider->load_config();
			if ( $provider->is_configured() ) {
				$available[ $key ] = $provider;
			}
		}

		return $available;
	}
}