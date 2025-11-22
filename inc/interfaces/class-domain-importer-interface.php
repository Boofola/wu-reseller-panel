<?php
/**
 * Domain Importer Interface
 *
 * Defines contract for any service provider that supports importing domains.
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel\Interfaces;

/**
 * Domain Importer Interface
 *
 * Any service provider that supports importing domains should implement this interface.
 */
interface Domain_Importer_Interface {

	/**
	 * Get available domains from the provider
	 *
	 * Returns array of domains with their pricing information.
	 *
	 * @return array|WP_Error Array of domains on success, WP_Error on failure.
	 *                        Array format:
	 *                        [
	 *                            'tld' => 'com',
	 *                            'name' => '.com',
	 *                            'price' => '8.95',
	 *                            'registration_price' => '8.95',
	 *                            'renewal_price' => '8.95',
	 *                            'transfer_price' => '8.95',
	 *                        ]
	 */
	public function get_domains();
}
