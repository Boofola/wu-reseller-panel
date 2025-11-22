<?php
/**
 * Domain Product Type
 *
 * Defines the Domain product type that can be used to sell domains through Ultimate Multisite.
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel\Product_Types;

/**
 * Domain Product Type Class
 */
class Domain_Product_Type {

	/**
	 * Product type key
	 *
	 * @var string
	 */
	const TYPE_KEY = 'domain';

	/**
	 * Product type name
	 *
	 * @var string
	 */
	const TYPE_NAME = 'Domain';

	/**
	 * Singleton instance
	 *
	 * @var self
	 */
	private static $instance = null;

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
		$this->init();
	}

	/**
	 * Initialize the product type
	 *
	 * @return void
	 */
	private function init() {
		// Register product type with Ultimate Multisite
		add_action( 'wu_register_product_types', array( $this, 'register_product_type' ) );
		
		// Add meta fields for domain pricing
		add_action( 'wu_product_meta_fields', array( $this, 'add_meta_fields' ) );
	}

	/**
	 * Register the domain product type
	 *
	 * @return void
	 */
	public function register_product_type() {
		// Check if Ultimate Multisite is available and has product type registration
		if ( ! function_exists( 'wu_register_product_type' ) ) {
			return;
		}

		wu_register_product_type(
			self::TYPE_KEY,
			array(
				'name'        => self::TYPE_NAME,
				'description' => __( 'Domain registration, renewal, and transfer products', 'ultimate-multisite' ),
				'icon'        => 'dashicons-admin-network',
			)
		);
	}

	/**
	 * Add meta fields for domain pricing
	 *
	 * @return array
	 */
	public function add_meta_fields() {
		return array(
			'domain_tld'           => array(
				'label'       => __( 'Top-Level Domain (TLD)', 'ultimate-multisite' ),
				'description' => __( 'e.g., com, net, org', 'ultimate-multisite' ),
				'type'        => 'text',
			),
			'registration_price'   => array(
				'label'       => __( 'Registration Price', 'ultimate-multisite' ),
				'description' => __( 'Price for registering a new domain', 'ultimate-multisite' ),
				'type'        => 'number',
			),
			'renewal_price'        => array(
				'label'       => __( 'Renewal Price', 'ultimate-multisite' ),
				'description' => __( 'Price for renewing an existing domain', 'ultimate-multisite' ),
				'type'        => 'number',
			),
			'transfer_price'       => array(
				'label'       => __( 'Transfer Price', 'ultimate-multisite' ),
				'description' => __( 'Price for transferring a domain to this account', 'ultimate-multisite' ),
				'type'        => 'number',
			),
			'provider_key'         => array(
				'label'       => __( 'Provider Key', 'ultimate-multisite' ),
				'description' => __( 'Which provider this domain is from (e.g., opensrs, namecheap)', 'ultimate-multisite' ),
				'type'        => 'text',
			),
		);
	}

	/**
	 * Create a domain product
	 *
	 * @param string $tld TLD (e.g., 'com', 'net')
	 * @param string $provider_key Provider key (e.g., 'opensrs')
	 * @param array  $pricing Pricing data (registration_price, renewal_price, transfer_price)
	 *
	 * @return int|WP_Error Product ID on success, WP_Error on failure
	 */
	public function create_product( $tld, $provider_key, $pricing = array() ) {
		// Ensure we have Ultimate Multisite functions
		if ( ! function_exists( 'wu_create_product' ) ) {
			return new \WP_Error(
				'ums_not_available',
				__( 'Ultimate Multisite is not available', 'ultimate-multisite' )
			);
		}

		// Default pricing structure
		$default_pricing = array(
			'registration_price' => 0,
			'renewal_price'      => 0,
			'transfer_price'     => 0,
		);

		$pricing = wp_parse_args( $pricing, $default_pricing );

		// Create product
		$product = wu_create_product(
			array(
				'name'       => '.' . sanitize_text_field( $tld ),
				'type'       => self::TYPE_KEY,
				'price'      => isset( $pricing['registration_price'] ) ? floatval( $pricing['registration_price'] ) : 0,
				'status'     => 'publish',
				'is_visible' => true,
			)
		);

		if ( is_wp_error( $product ) ) {
			return $product;
		}

		// Set meta fields
		if ( function_exists( 'wu_update_product_meta' ) ) {
			wu_update_product_meta( $product->get_id(), 'domain_tld', sanitize_key( $tld ) );
			wu_update_product_meta( $product->get_id(), 'registration_price', floatval( $pricing['registration_price'] ) );
			wu_update_product_meta( $product->get_id(), 'renewal_price', floatval( $pricing['renewal_price'] ) );
			wu_update_product_meta( $product->get_id(), 'transfer_price', floatval( $pricing['transfer_price'] ) );
			wu_update_product_meta( $product->get_id(), 'provider_key', sanitize_key( $provider_key ) );
		}

		return $product->get_id();
	}

	/**
	 * Update a domain product
	 *
	 * @param int   $product_id Product ID
	 * @param array $pricing Pricing data
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function update_product( $product_id, $pricing = array() ) {
		if ( ! function_exists( 'wu_update_product' ) ) {
			return new \WP_Error(
				'ums_not_available',
				__( 'Ultimate Multisite is not available', 'ultimate-multisite' )
			);
		}

		// Update main pricing
		if ( isset( $pricing['registration_price'] ) ) {
			wu_update_product(
				$product_id,
				array(
					'price' => floatval( $pricing['registration_price'] ),
				)
			);
		}

		// Update meta fields
		if ( function_exists( 'wu_update_product_meta' ) ) {
			if ( isset( $pricing['registration_price'] ) ) {
				wu_update_product_meta( $product_id, 'registration_price', floatval( $pricing['registration_price'] ) );
			}
			if ( isset( $pricing['renewal_price'] ) ) {
				wu_update_product_meta( $product_id, 'renewal_price', floatval( $pricing['renewal_price'] ) );
			}
			if ( isset( $pricing['transfer_price'] ) ) {
				wu_update_product_meta( $product_id, 'transfer_price', floatval( $pricing['transfer_price'] ) );
			}
		}

		return true;
	}

	/**
	 * Get or create a domain product
	 *
	 * If product exists, update it. Otherwise, create it.
	 *
	 * @param string $tld TLD
	 * @param string $provider_key Provider key
	 * @param array  $pricing Pricing data
	 *
	 * @return int|WP_Error Product ID on success, WP_Error on failure
	 */
	public function get_or_create_product( $tld, $provider_key, $pricing = array() ) {
		$product_id = $this->get_product_by_tld( $tld, $provider_key );

		if ( $product_id ) {
			// Product exists, update it
			$this->update_product( $product_id, $pricing );
			return $product_id;
		}

		// Product doesn't exist, create it
		return $this->create_product( $tld, $provider_key, $pricing );
	}

	/**
	 * Get product ID by TLD
	 *
	 * @param string $tld TLD
	 * @param string $provider_key Provider key
	 *
	 * @return int|false Product ID on success, false on failure
	 */
	public function get_product_by_tld( $tld, $provider_key ) {
		if ( ! function_exists( 'wu_get_products' ) ) {
			return false;
		}

		$products = wu_get_products(
			array(
				'type'     => self::TYPE_KEY,
				'meta'     => array(
					array(
						'key'   => 'domain_tld',
						'value' => sanitize_key( $tld ),
					),
					array(
						'key'   => 'provider_key',
						'value' => sanitize_key( $provider_key ),
					),
				),
				'per_page' => 1,
			)
		);

		if ( empty( $products ) ) {
			return false;
		}

		return $products[0]->get_id();
	}
}
