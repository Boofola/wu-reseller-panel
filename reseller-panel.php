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
