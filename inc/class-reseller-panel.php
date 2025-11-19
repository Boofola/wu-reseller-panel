<?php
/**
 * Main Reseller Panel Addon Class
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel;

/**
 * Reseller Panel Addon
 */
class Reseller_Panel {

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
		$this->load_dependencies();
		$this->setup_hooks();
		$this->init_components();
	}

	/**
	 * Load required files
	 */
	private function load_dependencies() {
		// Interfaces
		require_once RESELLER_PANEL_PATH . 'inc/interfaces/class-service-provider-interface.php';

		// Base classes
		require_once RESELLER_PANEL_PATH . 'inc/abstract/class-base-service-provider.php';
		require_once RESELLER_PANEL_PATH . 'inc/class-service-router.php';

		// Providers
		require_once RESELLER_PANEL_PATH . 'inc/providers/class-opensrs-provider.php';
		require_once RESELLER_PANEL_PATH . 'inc/providers/class-namecheap-provider.php';
		require_once RESELLER_PANEL_PATH . 'inc/class-provider-manager.php';

		// Admin pages
		require_once RESELLER_PANEL_PATH . 'inc/admin-pages/class-admin-page.php';
		require_once RESELLER_PANEL_PATH . 'inc/admin-pages/class-services-settings-page.php';
		require_once RESELLER_PANEL_PATH . 'inc/admin-pages/class-provider-settings-page.php';
	}

	/**
	 * Setup WordPress hooks
	 */
	private function setup_hooks() {
		// Register admin pages
		add_action( 'network_admin_menu', array( $this, 'register_admin_pages' ) );

		// Load admin styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Initialize components
	 */
	private function init_components() {
		// Initialize provider manager
		Provider_Manager::get_instance();

		// Initialize service router
		Service_Router::get_instance();

		// Initialize admin pages
		Admin_Pages\Services_Settings_Page::get_instance();
		Admin_Pages\Provider_Settings_Page::get_instance();
	}

	/**
	 * Register admin pages with Ultimate Multisite
	 *
	 * @return void
	 */
	public function register_admin_pages() {
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}

		// Register main Reseller Panel menu under Ultimate Multisite
		add_submenu_page(
			'wp-ultimo',
			__( 'Reseller Panel', 'ultimate-multisite' ),
			__( 'Reseller Panel', 'ultimate-multisite' ),
			'manage_network',
			'reseller-panel',
			array( $this, 'render_overview_page' )
		);

		// Services Settings and Provider Settings pages are registered by their own classes
		Admin_Pages\Services_Settings_Page::get_instance();
		Admin_Pages\Provider_Settings_Page::get_instance();
	}

	/**
	 * Enqueue admin assets
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
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

	/**
	 * Render overview page
	 *
	 * @return void
	 */
	public function render_overview_page() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'ultimate-multisite' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Reseller Panel', 'ultimate-multisite' ); ?></h1>
			<p><?php esc_html_e( 'Manage domains, SSL certificates, email services, hosting, and more through your Ultimate Multisite platform.', 'ultimate-multisite' ); ?></p>

			<div class="wu-container">
				<div class="wu-row">
					<div class="wu-col-md-4">
						<div class="wu-card">
							<h3><?php esc_html_e( 'Services', 'ultimate-multisite' ); ?></h3>
							<p><?php esc_html_e( 'Configure which services each provider offers and set defaults.', 'ultimate-multisite' ); ?></p>
							<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=reseller-panel-services' ) ); ?>" class="button button-primary">
								<?php esc_html_e( 'Manage', 'ultimate-multisite' ); ?>
							</a>
						</div>
					</div>

					<div class="wu-col-md-4">
						<div class="wu-card">
							<h3><?php esc_html_e( 'Providers', 'ultimate-multisite' ); ?></h3>
							<p><?php esc_html_e( 'Setup API credentials and configure providers.', 'ultimate-multisite' ); ?></p>
							<a href="<?php echo esc_url( network_admin_url( 'admin.php?page=reseller-panel-providers' ) ); ?>" class="button button-primary">
								<?php esc_html_e( 'Configure', 'ultimate-multisite' ); ?>
							</a>
						</div>
					</div>

					<div class="wu-col-md-4">
						<div class="wu-card">
							<h3><?php esc_html_e( 'Documentation', 'ultimate-multisite' ); ?></h3>
							<p><?php esc_html_e( 'Learn how to set up and manage reseller services.', 'ultimate-multisite' ); ?></p>
							<a href="https://ultimatemultisite.com/docs/" target="_blank" class="button button-secondary">
								<?php esc_html_e( 'View Docs', 'ultimate-multisite' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Plugin activation
	 *
	 * @return void
	 */
	public function activate() {
		// Create database tables if needed
		$this->create_tables();

		// Set version
		update_site_option( 'reseller_panel_version', RESELLER_PANEL_VERSION );
	}

	/**
	 * Plugin deactivation
	 *
	 * @return void
	 */
	public function deactivate() {
		// Clean up scheduled events if any
		wp_clear_scheduled_hook( 'reseller_panel_sync_pricing' );
	}

	/**
	 * Create database tables
	 *
	 * @return void
	 */
	private function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Services table
		$services_table = $wpdb->prefix . 'reseller_panel_services';
		$services_sql = "CREATE TABLE IF NOT EXISTS $services_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			service_key varchar(50) NOT NULL,
			service_name varchar(255) NOT NULL,
			description text,
			enabled tinyint(1) DEFAULT 0,
			default_provider varchar(100),
			fallback_provider varchar(100),
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY service_key (service_key)
		) $charset_collate;";

		// Providers table
		$providers_table = $wpdb->prefix . 'reseller_panel_providers';
		$providers_sql = "CREATE TABLE IF NOT EXISTS $providers_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			provider_key varchar(50) NOT NULL,
			provider_name varchar(255) NOT NULL,
			status varchar(20) DEFAULT 'inactive',
			config longtext,
			supported_services longtext,
			priority int DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY provider_key (provider_key)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $services_sql );
		dbDelta( $providers_sql );
	}
}
