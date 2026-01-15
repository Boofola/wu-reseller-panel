<?php
/**
 * Plugin Name: Ultimate Multisite - Reseller Panel
 * Plugin URI: https://ultimatemultisite.com/
 * Description: Sell and manage domains, SSL certificates, hosting, and email services through Ultimate Multisite
 * Version: 2.0.1
 * Author: Ultimate Multisite Community
 * Author URI: https://ultimatemultisite.com/
 * Text Domain: ultimate-multisite
 * Domain Path: /languages
 * Network: true
 * Requires at least: 6.2
 * Requires PHP: 7.4
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
define( 'RESELLER_PANEL_VERSION', '2.0.1' );
define( 'RESELLER_PANEL_FILE', __FILE__ );
define( 'RESELLER_PANEL_PATH', plugin_dir_path( __FILE__ ) );
define( 'RESELLER_PANEL_URL', plugin_dir_url( __FILE__ ) );
define( 'RESELLER_PANEL_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if Ultimate Multisite is active and loaded
 */
function reseller_panel_is_ultimo_active() {
	if ( ! is_multisite() ) {
		return false;
	}

	// Check if plugin file exists and is active
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	// Check both possible plugin paths
	$plugin_paths = array(
		'ultimate-multisite/ultimate-multisite.php',
		'wp-ultimo/wp-ultimo.php',
	);

	foreach ( $plugin_paths as $plugin_path ) {
		if ( is_plugin_active_for_network( $plugin_path ) ) {
			return true;
		}
	}

	// Fallback to class check
	return class_exists( 'WP_Ultimo\WP_Ultimo' );
}

add_action(
	'plugins_loaded',
	function() {
		if ( ! reseller_panel_is_ultimo_active() ) {
			add_action( 'network_admin_notices', function() {
				?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Ultimate Multisite - Reseller Panel', 'ultimate-multisite' ); ?></strong>
						<?php esc_html_e( 'works best with Ultimate Multisite installed and activated.', 'ultimate-multisite' ); ?>
					</p>
					<p>
						<?php
						printf(
							/* translators: %s: Link to plugin */
							esc_html__( 'Download Ultimate Multisite (opensource): %s', 'ultimate-multisite' ),
							'<a href="https://wordpress.org/plugins/ultimate-multisite/" target="_blank" rel="noopener noreferrer">WordPress.org</a>'
						);
						?>
					</p>
					<p>
						<em><?php esc_html_e( 'Note: Ultimate Multisite uses the WP_Ultimo namespace for backwards compatibility.', 'ultimate-multisite' ); ?></em>
					</p>
				</div>
				<?php
			} );
			// Continue loading even without Ultimate Multisite
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
 * Register AJAX handlers on admin_init hook (fires after init)
 */
add_action( 'admin_init', function() {
	// Register AJAX handler for testing provider connection
	$handler = function() {
		$debug = array();
		try {
			$debug[] = 'Handler called';
			
			// Check nonce
			if ( ! isset( $_POST['_wpnonce'] ) ) {
				$debug[] = 'No nonce provided';
				wp_send_json_error( array( 'message' => 'Security check failed: no nonce', 'debug' => $debug ) );
			}
			
			if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'reseller_panel_provider_nonce' ) ) {
				$debug[] = 'Invalid nonce';
				wp_send_json_error( array( 'message' => 'Security check failed: invalid nonce', 'debug' => $debug ) );
			}
			
			// Check capabilities
			if ( ! current_user_can( 'manage_network' ) ) {
				$debug[] = 'Insufficient permissions';
				wp_send_json_error( array( 'message' => 'Insufficient permissions', 'debug' => $debug ) );
			}

			// Get provider key
			$provider_key = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : null;
			$debug[] = 'Provider key: ' . $provider_key;
			
			if ( ! $provider_key ) {
				$debug[] = 'No provider key provided';
				wp_send_json_error( array( 'message' => 'Provider key not specified', 'debug' => $debug ) );
			}

			// Load dependencies
			$debug[] = 'Loading dependencies...';
			require_once RESELLER_PANEL_PATH . 'inc/interfaces/class-service-provider-interface.php';
			require_once RESELLER_PANEL_PATH . 'inc/abstract/class-base-service-provider.php';
			require_once RESELLER_PANEL_PATH . 'inc/providers/class-opensrs-provider.php';
			require_once RESELLER_PANEL_PATH . 'inc/providers/class-namecheap-provider.php';
			require_once RESELLER_PANEL_PATH . 'inc/class-provider-manager.php';
			$debug[] = 'Dependencies loaded';

			// Get provider
			$debug[] = 'Getting provider manager...';
			$provider_manager = \Reseller_Panel\Provider_Manager::get_instance();
			$provider = $provider_manager->get_provider( $provider_key );
			$debug[] = 'Got provider: ' . ( $provider ? get_class( $provider ) : 'null' );

			if ( ! $provider ) {
				$debug[] = 'Provider not found: ' . $provider_key;
				wp_send_json_error( array( 'message' => 'Provider not found: ' . $provider_key, 'debug' => $debug ) );
			}

			// Test connection
			$debug[] = 'Testing connection...';
			$result = $provider->test_connection();
			$debug[] = 'Connection test completed';

			if ( is_wp_error( $result ) ) {
				$debug[] = 'Error returned: ' . $result->get_error_message();
				wp_send_json_error( array( 'message' => $result->get_error_message(), 'debug' => $debug ) );
			}

			if ( $result === true ) {
				$debug[] = 'Connection successful!';
				wp_send_json_success( array( 'message' => 'Connection successful!', 'debug' => $debug ) );
			} else {
				$debug[] = 'Connection returned: ' . var_export( $result, true );
				wp_send_json_error( array( 'message' => 'Connection test returned false', 'debug' => $debug ) );
			}
		} catch ( \Exception $e ) {
			$debug[] = 'EXCEPTION: ' . $e->getMessage();
			$debug[] = 'File: ' . $e->getFile() . ' Line: ' . $e->getLine();
			wp_send_json_error( array( 'message' => $e->getMessage(), 'debug' => $debug ) );
		} catch ( \Throwable $e ) {
			$debug[] = 'ERROR: ' . $e->getMessage();
			$debug[] = 'File: ' . $e->getFile() . ' Line: ' . $e->getLine();
			wp_send_json_error( array( 'message' => $e->getMessage(), 'debug' => $debug ) );
		}
	};
	
	add_action( 'wp_ajax_reseller_panel_test_connection', $handler );
	add_action( 'wp_ajax_nopriv_reseller_panel_test_connection', $handler );
}, 999 );

/**
 * Register AJAX handler for importing domains
 */
add_action( 'admin_init', function() {
	// Register AJAX handler for importing domains
	$handler = function() {
		$debug = array();
		try {
			$debug[] = 'Import handler called';
			
			// Check nonce
			if ( ! isset( $_POST['_wpnonce'] ) ) {
				$debug[] = 'No nonce provided';
				wp_send_json_error( array( 'message' => 'Security check failed: no nonce', 'debug' => $debug ) );
			}
			
			if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'reseller_panel_provider_nonce' ) ) {
				$debug[] = 'Invalid nonce';
				wp_send_json_error( array( 'message' => 'Security check failed: invalid nonce', 'debug' => $debug ) );
			}
			
			// Check capabilities
			if ( ! current_user_can( 'manage_network' ) ) {
				$debug[] = 'Insufficient permissions';
				wp_send_json_error( array( 'message' => 'Insufficient permissions', 'debug' => $debug ) );
			}

			// Get provider key
			$provider_key = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : null;
			$debug[] = 'Provider key: ' . $provider_key;
			
			if ( ! $provider_key ) {
				$debug[] = 'No provider key provided';
				wp_send_json_error( array( 'message' => 'Provider key not specified', 'debug' => $debug ) );
			}

			// Load dependencies
			require_once RESELLER_PANEL_PATH . 'inc/interfaces/class-service-provider-interface.php';
			require_once RESELLER_PANEL_PATH . 'inc/interfaces/class-domain-importer-interface.php';
			require_once RESELLER_PANEL_PATH . 'inc/abstract/class-base-service-provider.php';
			require_once RESELLER_PANEL_PATH . 'inc/providers/class-opensrs-provider.php';
			require_once RESELLER_PANEL_PATH . 'inc/providers/class-namecheap-provider.php';
			require_once RESELLER_PANEL_PATH . 'inc/class-provider-manager.php';
			require_once RESELLER_PANEL_PATH . 'inc/product-types/class-domain-product-type.php';
			require_once RESELLER_PANEL_PATH . 'inc/importers/class-domain-importer.php';

			$debug[] = 'Dependencies loaded';

			// Get provider manager
			$provider_manager = \Reseller_Panel\Provider_Manager::get_instance();
			$debug[] = 'Got provider manager';

			// Get provider
			$provider = $provider_manager->get_provider( $provider_key );
			if ( ! $provider ) {
				$debug[] = 'Provider not found: ' . $provider_key;
				wp_send_json_error( array( 'message' => 'Provider not found', 'debug' => $debug ) );
			}

			$debug[] = 'Got provider: ' . get_class( $provider );

			// Check if provider implements Domain_Importer_Interface
			if ( ! $provider instanceof \Reseller_Panel\Interfaces\Domain_Importer_Interface ) {
				$debug[] = 'Provider does not support domain import';
				wp_send_json_error( array( 'message' => 'Provider does not support domain import', 'debug' => $debug ) );
			}

			$debug[] = 'Starting domain import...';

			// Import domains
			$importer = new \Reseller_Panel\Importers\Domain_Importer( $provider );
			$result = $importer->import();

			if ( is_wp_error( $result ) ) {
				$debug[] = 'Import error: ' . $result->get_error_message();
				wp_send_json_error( array( 
					'message' => $result->get_error_message(),
					'debug' => $debug,
				) );
			}

			$debug[] = 'Import completed successfully';

			// Return success response
			wp_send_json_success( array(
				'message' => $importer->get_summary(),
				'summary' => $importer->get_summary(),
				'details' => $result['details'],
				'imported' => $result['imported'],
				'updated' => $result['updated'],
				'skipped' => $result['skipped'],
				'errors' => $result['errors'],
				'debug' => $debug,
			) );

		} catch ( \Throwable $e ) {
			$debug[] = 'ERROR: ' . $e->getMessage();
			$debug[] = 'File: ' . $e->getFile() . ' Line: ' . $e->getLine();
			wp_send_json_error( array( 
				'message' => $e->getMessage(),
				'debug' => $debug,
			) );
		}
	};
	
	add_action( 'wp_ajax_reseller_panel_import_domains', $handler );
	add_action( 'wp_ajax_nopriv_reseller_panel_import_domains', $handler );
}, 999 );

// Admin menu registration is now handled by the Reseller_Panel class
// to avoid duplicate registrations and conflicts

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
	// Load the main class first
	require_once RESELLER_PANEL_PATH . 'inc/class-reseller-panel.php';
	
	// Run activation (creates database tables)
	\Reseller_Panel\Reseller_Panel::get_instance()->activate();
} );

/**
 * Deactivation hook
 */
register_deactivation_hook( RESELLER_PANEL_FILE, function() {
	// Load the main class first
	require_once RESELLER_PANEL_PATH . 'inc/class-reseller-panel.php';
	
	// Run deactivation
	\Reseller_Panel\Reseller_Panel::get_instance()->deactivate();
} );
