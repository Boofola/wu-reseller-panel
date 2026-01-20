<?php
/**
 * Checkout Integration Class
 *
 * Enhances checkout process with domain-specific fields,
 * domain search integration, and auto-population of registrant information.
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkout Integration Class
 *
 * Handles checkout enhancements for domain purchases.
 */
class Checkout_Integration {

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
		$this->setup_hooks();
	}

	/**
	 * Setup WordPress hooks
	 */
	private function setup_hooks() {
		// Add domain fields to checkout
		add_action( 'wu_checkout_form_after_plan', array( $this, 'render_domain_fields' ), 10, 2 );

		// Enqueue checkout scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_assets' ) );

		// Register AJAX handlers
		add_action( 'wp_ajax_reseller_panel_search_domain', array( $this, 'ajax_search_domain' ) );
		add_action( 'wp_ajax_nopriv_reseller_panel_search_domain', array( $this, 'ajax_search_domain' ) );
		add_action( 'wp_ajax_reseller_panel_get_domain_price', array( $this, 'ajax_get_domain_price' ) );
		add_action( 'wp_ajax_nopriv_reseller_panel_get_domain_price', array( $this, 'ajax_get_domain_price' ) );

		// Process domain purchase on checkout
		add_action( 'wu_checkout_processed', array( $this, 'process_domain_purchase' ), 10, 2 );

		// Validate domain fields
		add_filter( 'wu_checkout_validation_errors', array( $this, 'validate_domain_fields' ), 10, 2 );
	}

	/**
	 * Enqueue checkout assets
	 */
	public function enqueue_checkout_assets() {
		// Only enqueue on checkout page
		if ( ! $this->is_checkout_page() ) {
			return;
		}

		wp_enqueue_style(
			'reseller-panel-checkout',
			RESELLER_PANEL_URL . 'assets/css/domain-checkout.css',
			array(),
			RESELLER_PANEL_VERSION
		);

		wp_enqueue_script(
			'reseller-panel-checkout',
			RESELLER_PANEL_URL . 'assets/js/domain-checkout.js',
			array( 'jquery' ),
			RESELLER_PANEL_VERSION,
			true
		);

		wp_localize_script(
			'reseller-panel-checkout',
			'resellerPanelCheckout',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'searchNonce'     => wp_create_nonce( 'reseller_panel_search_nonce' ),
				'searchingText'   => __( 'Searching...', 'ultimate-multisite' ),
				'availableText'   => __( 'Available', 'ultimate-multisite' ),
				'unavailableText' => __( 'Not Available', 'ultimate-multisite' ),
				'errorText'       => __( 'Error checking availability', 'ultimate-multisite' ),
			)
		);
	}

	/**
	 * Check if current page is checkout page
	 *
	 * @return bool
	 */
	private function is_checkout_page() {
		// This would integrate with Ultimate Multisite's checkout detection
		// For now, use a simple filter
		return apply_filters( 'reseller_panel_is_checkout_page', false );
	}

	/**
	 * Render domain fields in checkout
	 *
	 * @param int   $plan_id Plan ID
	 * @param array $checkout_data Checkout data
	 */
	public function render_domain_fields( $plan_id, $checkout_data ) {
		// Check if domain purchase is enabled for this plan
		if ( ! $this->is_domain_enabled_for_plan( $plan_id ) ) {
			return;
		}

		// Get customer data for auto-population
		$customer_data = $this->get_customer_data();

		// Include the domain fields template
		include RESELLER_PANEL_PATH . 'views/frontend/checkout-domain-fields.php';
	}

	/**
	 * Check if domain purchase is enabled for plan
	 *
	 * @param int $plan_id Plan ID
	 *
	 * @return bool
	 */
	private function is_domain_enabled_for_plan( $plan_id ) {
		// This would check plan settings
		// For now, use a filter
		return apply_filters( 'reseller_panel_domain_enabled_for_plan', true, $plan_id );
	}

	/**
	 * Get customer data for auto-population
	 *
	 * @return array
	 */
	private function get_customer_data() {
		$data = array(
			'first_name' => '',
			'last_name'  => '',
			'email'      => '',
			'phone'      => '',
			'address'    => '',
			'city'       => '',
			'state'      => '',
			'zip'        => '',
			'country'    => '',
		);

		// Try to get current user data
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();

			$data['first_name'] = $user->first_name;
			$data['last_name']  = $user->last_name;
			$data['email']      = $user->user_email;

			// Try to get additional data from user meta
			$data['phone']   = get_user_meta( $user->ID, 'billing_phone', true );
			$data['address'] = get_user_meta( $user->ID, 'billing_address_1', true );
			$data['city']    = get_user_meta( $user->ID, 'billing_city', true );
			$data['state']   = get_user_meta( $user->ID, 'billing_state', true );
			$data['zip']     = get_user_meta( $user->ID, 'billing_postcode', true );
			$data['country'] = get_user_meta( $user->ID, 'billing_country', true );
		}

		// Allow filtering of customer data
		return apply_filters( 'reseller_panel_checkout_customer_data', $data );
	}

	/**
	 * Validate domain fields
	 *
	 * @param array $errors Existing validation errors
	 * @param array $post_data Posted form data
	 *
	 * @return array Updated errors
	 */
	public function validate_domain_fields( $errors, $post_data ) {
		// Check if domain registration is requested
		if ( empty( $post_data['register_domain'] ) || '1' !== $post_data['register_domain'] ) {
			return $errors;
		}

		// Validate domain name
		if ( empty( $post_data['domain_name'] ) ) {
			$errors['domain_name'] = __( 'Domain name is required', 'ultimate-multisite' );
		} else {
			$domain = sanitize_text_field( $post_data['domain_name'] );
			if ( ! $this->validate_domain_name( $domain ) ) {
				$errors['domain_name'] = __( 'Invalid domain name format', 'ultimate-multisite' );
			}
		}

		// Validate registrant information
		$required_fields = array(
			'registrant_first_name' => __( 'First name is required', 'ultimate-multisite' ),
			'registrant_last_name'  => __( 'Last name is required', 'ultimate-multisite' ),
			'registrant_email'      => __( 'Email is required', 'ultimate-multisite' ),
			'registrant_phone'      => __( 'Phone number is required', 'ultimate-multisite' ),
			'registrant_address'    => __( 'Address is required', 'ultimate-multisite' ),
			'registrant_city'       => __( 'City is required', 'ultimate-multisite' ),
			'registrant_state'      => __( 'State is required', 'ultimate-multisite' ),
			'registrant_zip'        => __( 'Zip code is required', 'ultimate-multisite' ),
			'registrant_country'    => __( 'Country is required', 'ultimate-multisite' ),
		);

		foreach ( $required_fields as $field => $error_message ) {
			if ( empty( $post_data[ $field ] ) ) {
				$errors[ $field ] = $error_message;
			}
		}

		// Validate email format
		if ( ! empty( $post_data['registrant_email'] ) && ! is_email( $post_data['registrant_email'] ) ) {
			$errors['registrant_email'] = __( 'Invalid email format', 'ultimate-multisite' );
		}

		return $errors;
	}

	/**
	 * Validate domain name format
	 *
	 * @param string $domain Domain name
	 *
	 * @return bool
	 */
	private function validate_domain_name( $domain ) {
		// Basic domain name validation
		$pattern = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i';
		return (bool) preg_match( $pattern, $domain );
	}

	/**
	 * Process domain purchase after checkout
	 *
	 * @param int   $membership_id Membership ID
	 * @param array $post_data Posted form data
	 */
	public function process_domain_purchase( $membership_id, $post_data ) {
		// Check if domain registration is requested
		if ( empty( $post_data['register_domain'] ) || '1' !== $post_data['register_domain'] ) {
			return;
		}

		$domain = sanitize_text_field( $post_data['domain_name'] );

		// Build registrant information
		$registrant_info = array(
			'first_name'    => sanitize_text_field( $post_data['registrant_first_name'] ),
			'last_name'     => sanitize_text_field( $post_data['registrant_last_name'] ),
			'email'         => sanitize_email( $post_data['registrant_email'] ),
			'phone'         => sanitize_text_field( $post_data['registrant_phone'] ),
			'address'       => sanitize_text_field( $post_data['registrant_address'] ),
			'city'          => sanitize_text_field( $post_data['registrant_city'] ),
			'state'         => sanitize_text_field( $post_data['registrant_state'] ),
			'zip'           => sanitize_text_field( $post_data['registrant_zip'] ),
			'country'       => sanitize_text_field( $post_data['registrant_country'] ),
			'organization'  => isset( $post_data['registrant_organization'] ) ? sanitize_text_field( $post_data['registrant_organization'] ) : '',
		);

		// Get provider
		$provider_manager = Provider_Manager::get_instance();
		$providers        = $provider_manager->get_providers_for_service( 'domains' );

		if ( empty( $providers ) ) {
			Logger::log_error( 'checkout', 'No domain provider available for registration' );
			return;
		}

		$provider = reset( $providers );

		// Register domain via provider
		if ( method_exists( $provider, 'register_domain' ) ) {
			$result = $provider->register_domain( $domain, 1, $registrant_info );

			if ( is_wp_error( $result ) ) {
				Logger::log_error(
					$provider->get_key(),
					sprintf( 'Failed to register domain %s: %s', $domain, $result->get_error_message() ),
					array( 'membership_id' => $membership_id )
				);

				// Store error for display
				update_post_meta( $membership_id, '_domain_registration_error', $result->get_error_message() );
			} else {
				Logger::log_info(
					$provider->get_key(),
					sprintf( 'Registered domain %s for membership %d', $domain, $membership_id ),
					array( 'result' => $result )
				);

				// Store successful registration
				update_post_meta( $membership_id, '_registered_domain', $domain );
				update_post_meta( $membership_id, '_domain_registration_data', $result );
			}
		}
	}

	/**
	 * AJAX handler: Search domain availability
	 */
	public function ajax_search_domain() {
		check_ajax_referer( 'reseller_panel_search_nonce', 'nonce' );

		$domain = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';

		if ( empty( $domain ) ) {
			wp_send_json_error( array( 'message' => __( 'Domain name is required', 'ultimate-multisite' ) ) );
		}

		// Get provider
		$provider_manager = Provider_Manager::get_instance();
		$providers        = $provider_manager->get_providers_for_service( 'domains' );

		if ( empty( $providers ) ) {
			wp_send_json_error( array( 'message' => __( 'No domain provider configured', 'ultimate-multisite' ) ) );
		}

		$provider = reset( $providers );

		// Check availability via provider
		if ( ! method_exists( $provider, 'check_domain_availability' ) ) {
			wp_send_json_error( array( 'message' => __( 'Domain search is not supported', 'ultimate-multisite' ) ) );
		}

		$result = $provider->check_domain_availability( $domain );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX handler: Get domain price
	 */
	public function ajax_get_domain_price() {
		check_ajax_referer( 'reseller_panel_search_nonce', 'nonce' );

		$domain = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';

		if ( empty( $domain ) ) {
			wp_send_json_error( array( 'message' => __( 'Domain name is required', 'ultimate-multisite' ) ) );
		}

		// Get provider
		$provider_manager = Provider_Manager::get_instance();
		$providers        = $provider_manager->get_providers_for_service( 'domains' );

		if ( empty( $providers ) ) {
			wp_send_json_error( array( 'message' => __( 'No domain provider configured', 'ultimate-multisite' ) ) );
		}

		$provider = reset( $providers );

		// Get domain pricing
		if ( ! method_exists( $provider, 'get_domain_pricing' ) ) {
			wp_send_json_error( array( 'message' => __( 'Domain pricing is not supported', 'ultimate-multisite' ) ) );
		}

		$result = $provider->get_domain_pricing( $domain );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}
}
