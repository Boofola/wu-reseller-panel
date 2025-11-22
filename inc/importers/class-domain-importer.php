<?php
/**
 * Domain Importer Service
 *
 * Orchestrates the import of domains from a provider into Ultimate Multisite products.
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel\Importers;

use Reseller_Panel\Product_Types\Domain_Product_Type;
use Reseller_Panel\Interfaces\Domain_Importer_Interface;

/**
 * Domain Importer Service Class
 */
class Domain_Importer {

	/**
	 * Provider instance
	 *
	 * @var Domain_Importer_Interface
	 */
	private $provider;

	/**
	 * Product type instance
	 *
	 * @var Domain_Product_Type
	 */
	private $product_type;

	/**
	 * Import results
	 *
	 * @var array
	 */
	private $results = array(
		'imported'  => 0,
		'updated'   => 0,
		'skipped'   => 0,
		'errors'    => array(),
		'details'   => array(),
	);

	/**
	 * Constructor
	 *
	 * @param Domain_Importer_Interface $provider Provider instance
	 */
	public function __construct( Domain_Importer_Interface $provider ) {
		$this->provider = $provider;
		$this->product_type = Domain_Product_Type::get_instance();
	}

	/**
	 * Import domains from the provider
	 *
	 * @return array|WP_Error Array of import results, WP_Error on failure
	 */
	public function import() {
		// Reset results
		$this->results = array(
			'imported'  => 0,
			'updated'   => 0,
			'skipped'   => 0,
			'errors'    => array(),
			'details'   => array(),
		);

		// Get domains from provider
		$domains = $this->provider->get_domains();

		if ( is_wp_error( $domains ) ) {
			return $domains;
		}

		if ( empty( $domains ) ) {
			return new \WP_Error(
				'no_domains',
				__( 'No domains found from provider', 'ultimate-multisite' )
			);
		}

		// Import each domain
		foreach ( $domains as $domain_data ) {
			$this->import_domain( $domain_data );
		}

		return $this->get_results();
	}

	/**
	 * Import a single domain
	 *
	 * @param array $domain_data Domain data
	 *
	 * @return void
	 */
	private function import_domain( $domain_data ) {
		// Validate domain data
		if ( empty( $domain_data['tld'] ) ) {
			$this->results['errors'][] = __( 'Domain data missing TLD', 'ultimate-multisite' );
			$this->results['skipped']++;
			return;
		}

		$tld = sanitize_key( $domain_data['tld'] );
		$provider_key = $this->provider instanceof \Reseller_Panel\Providers\OpenSRS_Provider ? 'opensrs' : 'unknown';

		// Prepare pricing data
		$pricing = array(
			'registration_price' => isset( $domain_data['registration_price'] ) ? floatval( $domain_data['registration_price'] ) : 0,
			'renewal_price'      => isset( $domain_data['renewal_price'] ) ? floatval( $domain_data['renewal_price'] ) : 0,
			'transfer_price'     => isset( $domain_data['transfer_price'] ) ? floatval( $domain_data['transfer_price'] ) : 0,
		);

		// Get or create product
		$product_id = $this->product_type->get_or_create_product( $tld, $provider_key, $pricing );

		if ( is_wp_error( $product_id ) ) {
			$this->results['errors'][] = sprintf(
				__( 'Failed to import domain %s: %s', 'ultimate-multisite' ),
				$tld,
				$product_id->get_error_message()
			);
			$this->results['skipped']++;
			return;
		}

		// Check if this is a new product or an update
		$existing_id = $this->product_type->get_product_by_tld( $tld, $provider_key );

		if ( $existing_id ) {
			$this->results['updated']++;
			$this->results['details'][] = sprintf(
				__( 'Updated domain product: .%s', 'ultimate-multisite' ),
				$tld
			);
		} else {
			$this->results['imported']++;
			$this->results['details'][] = sprintf(
				__( 'Imported domain product: .%s', 'ultimate-multisite' ),
				$tld
			);
		}
	}

	/**
	 * Get import results
	 *
	 * @return array
	 */
	public function get_results() {
		return $this->results;
	}

	/**
	 * Get result summary
	 *
	 * @return string
	 */
	public function get_summary() {
		$summary_parts = array();

		if ( $this->results['imported'] > 0 ) {
			$summary_parts[] = sprintf(
				_n(
					'Imported %d domain',
					'Imported %d domains',
					$this->results['imported'],
					'ultimate-multisite'
				),
				$this->results['imported']
			);
		}

		if ( $this->results['updated'] > 0 ) {
			$summary_parts[] = sprintf(
				_n(
					'Updated %d domain',
					'Updated %d domains',
					$this->results['updated'],
					'ultimate-multisite'
				),
				$this->results['updated']
			);
		}

		if ( $this->results['skipped'] > 0 ) {
			$summary_parts[] = sprintf(
				_n(
					'Skipped %d domain',
					'Skipped %d domains',
					$this->results['skipped'],
					'ultimate-multisite'
				),
				$this->results['skipped']
			);
		}

		if ( empty( $summary_parts ) ) {
			return __( 'No changes made', 'ultimate-multisite' );
		}

		return implode( ', ', $summary_parts );
	}
}
