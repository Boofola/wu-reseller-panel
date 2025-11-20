<?php
/**
 * Plugin Name: Ultimate Multisite - Reseller Panel
 * Plugin URI: https://ultimatemultisite.com/
 * Description: Sell and manage domains, SSL certificates, hosting, and email services through Ultimate Multisite
 * Version: 2.0.0
 * Author: Ultimate Multisite Community
 * Author URI: https://ultimatemultisite.com/
 * Text Domain: ultimate-multisite
 * Domain Path: /languages
 * Network: true
 * Requires WordPress: 6.2
 * Requires PHP: 7.8
 *
 * This plugin is an addon for Ultimate Multisite and requires it to be installed and activated.
 *
 * @package Reseller_Panel
 * @subpackage Addons
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'RESELLER_PANEL_VERSION', '2.0.0' );
define( 'RESELLER_PANEL_FILE', __FILE__ );
define( 'RESELLER_PANEL_PATH', plugin_dir_path( __FILE__ ) );
define( 'RESELLER_PANEL_URL', plugin_dir_url( __FILE__ ) );
define( 'RESELLER_PANEL_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if Ultimate Multisite is active and loaded
 */
add_action(
	'plugins_loaded',
	function() {
		if ( ! class_exists( 'WP_Ultimo\WP_Ultimo' ) ) {
			add_action( 'admin_notices', function() {
				?>
				<div class="notice notice-error">
					<p>
						<strong><?php esc_html_e( 'Ultimate Multisite - Reseller Panel', 'ultimate-multisite' ); ?></strong>
						<?php esc_html_e( 'requires Ultimate Multisite to be installed and activated.', 'ultimate-multisite' ); ?>
					</p>
				</div>
				<?php
			} );
			return;
		}

		// Load text domain
		load_plugin_textdomain(
			'ultimate-multisite',
			false,
			dirname( RESELLER_PANEL_BASENAME ) . '/languages'
		);

		// Load the addon
		require_once RESELLER_PANEL_PATH . 'inc/class-reseller-panel.php';

		// Initialize the addon
		\Reseller_Panel\Reseller_Panel::get_instance();

		// Register AJAX handlers directly to ensure they're available
		add_action( 'wp_ajax_reseller_panel_test_connection', function() {
			if ( ! current_user_can( 'manage_network' ) ) {
				wp_send_json_error( 'Insufficient permissions' );
			}

			check_ajax_referer( 'reseller_panel_provider_save', '_wpnonce' );

			$provider_key = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : '';

			if ( empty( $provider_key ) ) {
				wp_send_json_error( 'No provider specified' );
			}

			// Load required classes
			require_once RESELLER_PANEL_PATH . 'inc/interfaces/class-service-provider-interface.php';
			require_once RESELLER_PANEL_PATH . 'inc/abstract/class-base-service-provider.php';
			require_once RESELLER_PANEL_PATH . 'inc/providers/class-opensrs-provider.php';
			require_once RESELLER_PANEL_PATH . 'inc/providers/class-namecheap-provider.php';
			require_once RESELLER_PANEL_PATH . 'inc/class-provider-manager.php';

			$provider_manager = \Reseller_Panel\Provider_Manager::get_instance();
			$provider = $provider_manager->get_provider( $provider_key );

			if ( ! $provider ) {
				wp_send_json_error( 'Provider not found' );
			}

			// Test the connection
			$result = $provider->test_connection();

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( $result->get_error_message() );
			}

			wp_send_json_success( 'Connection successful!' );
		} );
	}
);

/**
 * Register admin menu on network_admin_menu hook
 */
add_action(
	'network_admin_menu',
	function() {
		// Add top-level menu with icon
		add_menu_page(
			'Reseller Panel',
			'Reseller Panel',
			'manage_network',
			'reseller-panel',
			function() {
				require_once RESELLER_PANEL_PATH . 'inc/class-reseller-panel.php';
				$addon = \Reseller_Panel\Reseller_Panel::get_instance();
				$addon->render_overview_page();
			},
			'dashicons-shopping-cart'
		);

		// Add Services Settings submenu
		add_submenu_page(
			'reseller-panel',
			'Services Settings',
			'Services Settings',
			'manage_network',
			'reseller-panel-services',
			function() {
				// Load all dependencies
				require_once RESELLER_PANEL_PATH . 'inc/interfaces/class-service-provider-interface.php';
				require_once RESELLER_PANEL_PATH . 'inc/abstract/class-base-service-provider.php';
				require_once RESELLER_PANEL_PATH . 'inc/providers/class-opensrs-provider.php';
				require_once RESELLER_PANEL_PATH . 'inc/providers/class-namecheap-provider.php';
				require_once RESELLER_PANEL_PATH . 'inc/class-provider-manager.php';
				require_once RESELLER_PANEL_PATH . 'inc/admin-pages/class-admin-page.php';
				require_once RESELLER_PANEL_PATH . 'inc/admin-pages/class-services-settings-page.php';
				
				$page = \Reseller_Panel\Admin_Pages\Services_Settings_Page::get_instance();
				$page->render_page();
			}
		);

		// Add Provider Settings submenu
		add_submenu_page(
			'reseller-panel',
			'Provider Settings',
			'Provider Settings',
			'manage_network',
			'reseller-panel-providers',
			function() {
				// Load all dependencies
				require_once RESELLER_PANEL_PATH . 'inc/interfaces/class-service-provider-interface.php';
				require_once RESELLER_PANEL_PATH . 'inc/abstract/class-base-service-provider.php';
				require_once RESELLER_PANEL_PATH . 'inc/providers/class-opensrs-provider.php';
				require_once RESELLER_PANEL_PATH . 'inc/providers/class-namecheap-provider.php';
				require_once RESELLER_PANEL_PATH . 'inc/class-provider-manager.php';
				require_once RESELLER_PANEL_PATH . 'inc/class-service-router.php';
				require_once RESELLER_PANEL_PATH . 'inc/admin-pages/class-admin-page.php';
				require_once RESELLER_PANEL_PATH . 'inc/admin-pages/class-provider-settings-page.php';
				
				$page = \Reseller_Panel\Admin_Pages\Provider_Settings_Page::get_instance();
				$page->render_page();
			}
		);
	},
	5
);

/**
 * Enqueue admin styles and scripts
 */
add_action(
	'admin_enqueue_scripts',
	function() {
		if ( ! is_network_admin() ) {
			return;
		}

		wp_enqueue_style(
			'reseller-panel-admin',
			RESELLER_PANEL_URL . 'assets/css/admin.css',
			array(),
			RESELLER_PANEL_VERSION
		);

		wp_enqueue_script(
			'reseller-panel-admin',
			RESELLER_PANEL_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			RESELLER_PANEL_VERSION,
			true
		);
	}
);

/**
 * Activation hook
 */
register_activation_hook( RESELLER_PANEL_FILE, function() {
	if ( class_exists( 'WP_Ultimo\WP_Ultimo' ) ) {
		\Reseller_Panel\Reseller_Panel::get_instance()->activate();
	}
} );

/**
 * Deactivation hook
 */
register_deactivation_hook( RESELLER_PANEL_FILE, function() {
	if ( class_exists( 'WP_Ultimo\WP_Ultimo' ) ) {
		\Reseller_Panel\Reseller_Panel::get_instance()->deactivate();
	}
} );
