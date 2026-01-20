<?php
/**
 * Customer Portal Class
 *
 * Handles customer-facing domain management features including
 * shortcode registration and rendering.
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer Portal Class
 *
 * Provides customer-facing domain management interface.
 */
class Customer_Portal {

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
		// Register shortcodes
		add_shortcode( 'reseller_panel_domains', array( $this, 'render_domain_manager_shortcode' ) );

		// Enqueue frontend scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		// Only enqueue on pages with shortcode or specific templates
		if ( ! $this->should_load_assets() ) {
			return;
		}

		wp_enqueue_style(
			'reseller-panel-customer-portal',
			RESELLER_PANEL_URL . 'assets/css/customer-domain-manager.css',
			array(),
			RESELLER_PANEL_VERSION
		);

		wp_register_script(
			'reseller-panel-customer-portal',
			RESELLER_PANEL_URL . 'assets/js/customer-domain-manager.js',
			array( 'jquery' ),
			RESELLER_PANEL_VERSION,
			true
		);
	}

	/**
	 * Check if assets should be loaded
	 *
	 * @return bool
	 */
	private function should_load_assets() {
		global $post;

		// Check if shortcode is present
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'reseller_panel_domains' ) ) {
			return true;
		}

		// Allow filtering
		return apply_filters( 'reseller_panel_load_customer_assets', false );
	}

	/**
	 * Render domain manager shortcode
	 *
	 * @param array $atts Shortcode attributes
	 *
	 * @return string Rendered HTML
	 */
	public function render_domain_manager_shortcode( $atts ) {
		// Parse attributes
		$atts = shortcode_atts(
			array(
				'limit' => 50,
			),
			$atts,
			'reseller_panel_domains'
		);

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			return $this->render_login_message();
		}

		// Get customer domains
		$domains = $this->get_customer_domains( get_current_user_id(), $atts['limit'] );

		// Start output buffering
		ob_start();

		// Include template
		include RESELLER_PANEL_PATH . 'views/frontend/customer-domain-manager.php';

		// Return buffered content
		return ob_get_clean();
	}

	/**
	 * Render login message
	 *
	 * @return string HTML message
	 */
	private function render_login_message() {
		ob_start();
		?>
		<div class="reseller-panel-login-message">
			<p><?php esc_html_e( 'Please log in to manage your domains.', 'ultimate-multisite' ); ?></p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="btn btn-primary">
				<?php esc_html_e( 'Log In', 'ultimate-multisite' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get customer domains
	 *
	 * @param int $user_id User ID
	 * @param int $limit   Number of domains to retrieve
	 *
	 * @return array Array of domain data
	 */
	private function get_customer_domains( $user_id, $limit = 50 ) {
		// This would integrate with Ultimate Multisite's customer/domain models
		// For now, get from user meta or custom table
		$domains = get_user_meta( $user_id, '_reseller_panel_domains', true );

		if ( ! is_array( $domains ) ) {
			$domains = array();
		}

		// Limit results
		$domains = array_slice( $domains, 0, $limit );

		// Add auto-renewal status
		$auto_renewals = get_site_option( 'reseller_panel_auto_renewals', array() );

		foreach ( $domains as &$domain ) {
			$domain['auto_renew'] = isset( $auto_renewals[ $domain['name'] ] );
		}

		// Allow filtering
		return apply_filters( 'reseller_panel_customer_domains', $domains, $user_id );
	}

	/**
	 * Get customer by user ID
	 *
	 * @param int $user_id User ID
	 *
	 * @return object|null Customer object or null
	 */
	private function get_customer_by_user_id( $user_id ) {
		// This would integrate with Ultimate Multisite's customer model
		// For now, use a filter to allow customization
		return apply_filters( 'reseller_panel_get_customer', null, $user_id );
	}
}
