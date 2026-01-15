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
		
		// Don't initialize components during installation
		if ( ! defined( 'WP_INSTALLING' ) || ! WP_INSTALLING ) {
			$this->init_components();
		}
	}

	/**
	 * Load required files
	 */
	private function load_dependencies() {
		// Interfaces
		require_once RESELLER_PANEL_PATH . 'inc/interfaces/class-service-provider-interface.php';
		require_once RESELLER_PANEL_PATH . 'inc/interfaces/class-domain-importer-interface.php';

		// Base classes
		require_once RESELLER_PANEL_PATH . 'inc/abstract/class-base-service-provider.php';

		// Providers
		require_once RESELLER_PANEL_PATH . 'inc/providers/class-opensrs-provider.php';
		require_once RESELLER_PANEL_PATH . 'inc/providers/class-namecheap-provider.php';
		require_once RESELLER_PANEL_PATH . 'inc/class-provider-manager.php';

		// Product types
		require_once RESELLER_PANEL_PATH . 'inc/product-types/class-domain-product-type.php';

		// Importers
		require_once RESELLER_PANEL_PATH . 'inc/importers/class-domain-importer.php';

		// Admin pages
		require_once RESELLER_PANEL_PATH . 'inc/admin-pages/class-admin-page.php';
		require_once RESELLER_PANEL_PATH . 'inc/admin-pages/class-services-settings-page.php';
		require_once RESELLER_PANEL_PATH . 'inc/admin-pages/class-provider-settings-page.php';
	}

	/**
	 * Setup WordPress hooks
	 */
	private function setup_hooks() {
		// Register admin pages - only on network admin menu for multisite
		add_action( 'network_admin_menu', array( $this, 'register_admin_pages' ), 10 );

		// Add admin notice for non-multisite installations - only if not multisite
		if ( ! is_multisite() ) {
			add_action( 'admin_notices', array( $this, 'show_multisite_required_notice' ) );
		}

		// Load admin styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Register AJAX handlers
		add_action( 'wp_ajax_reseller_panel_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'wp_ajax_nopriv_reseller_panel_test_connection', array( $this, 'handle_test_connection' ) );

		// Handle form submissions for admin pages
		add_action( 'load-reseller-panel_page_reseller-panel-services', function() {
			$page = Admin_Pages\Services_Settings_Page::get_instance();
			$page->handle_form_submission();
		});
		add_action( 'load-reseller-panel_page_reseller-panel-providers', function() {
			$page = Admin_Pages\Provider_Settings_Page::get_instance();
			$page->handle_form_submission();
		});
	}

	/**
	 * Initialize components
	 */
	private function init_components() {
		// Initialize provider manager
		Provider_Manager::get_instance();

		// Initialize admin pages
		Admin_Pages\Services_Settings_Page::get_instance();
		Admin_Pages\Provider_Settings_Page::get_instance();
	}

	/**
	 * Show multisite required notice
	 *
	 * @return void
	 */
	public function show_multisite_required_notice() {
		// Only show to users who can manage options
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Ultimate Multisite - Reseller Panel', 'ultimate-multisite' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'This plugin requires WordPress Multisite to function. Please activate WordPress Multisite before using this plugin.', 'ultimate-multisite' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( 'https://wordpress.org/documentation/article/create-a-network/' ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Learn how to enable WordPress Multisite', 'ultimate-multisite' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Register admin pages and main menu
	 *
	 * @return void
	 */
	public function register_admin_pages() {
		// Only register menu in multisite network admin
		if ( ! is_multisite() ) {
			return;
		}

		// Register main Reseller Panel menu as top-level menu
		add_menu_page(
			__( 'Reseller Panel', 'ultimate-multisite' ),
			__( 'Reseller Panel', 'ultimate-multisite' ),
			'manage_network',
			'reseller-panel',
			array( $this, 'render_overview_page' ),
			'dashicons-shopping-cart',
			25  // Position below Ultimate Multisite menu
		);

		// Initialize admin page instances first
		$services_page = Admin_Pages\Services_Settings_Page::get_instance();
		$provider_page = Admin_Pages\Provider_Settings_Page::get_instance();

		// Register Services Settings as submenu
		add_submenu_page(
			'reseller-panel',
			__( 'Services Settings', 'ultimate-multisite' ),
			__( 'Services Settings', 'ultimate-multisite' ),
			'manage_network',
			'reseller-panel-services',
			array( $services_page, 'render_page' )
		);

		// Register Provider Settings as submenu
		add_submenu_page(
			'reseller-panel',
			__( 'Provider Settings', 'ultimate-multisite' ),
			__( 'Provider Settings', 'ultimate-multisite' ),
			'manage_network',
			'reseller-panel-providers',
			array( $provider_page, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		// Only load on reseller panel pages
		$screen = \get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'reseller-panel' ) === false ) {
			return;
		}

		\wp_enqueue_style(
			'reseller-panel-admin',
			RESELLER_PANEL_URL . 'assets/css/admin.css',
			array(),
			RESELLER_PANEL_VERSION
		);

		\wp_enqueue_script(
			'reseller-panel-admin',
			RESELLER_PANEL_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			RESELLER_PANEL_VERSION,
			true
		);
		
		// Localize script with AJAX URL and nonce
		\wp_localize_script(
			'reseller-panel-admin',
			'resellerPanelAdmin',
			array(
				'ajaxUrl' => \admin_url( 'admin-ajax.php' ),
				'nonce'   => \wp_create_nonce( 'reseller_panel_admin' ),
			)
		);
	}

	/**
	 * Handle AJAX test connection request
	 *
	 * @return void
	 */
	public function handle_test_connection() {
		if ( ! \current_user_can( 'manage_network' ) ) {
			\wp_send_json_error( 'Insufficient permissions' );
		}

		\check_ajax_referer( 'reseller_panel_provider_save', '_wpnonce' );

		$provider_key = isset( $_POST['provider'] ) ? \sanitize_key( $_POST['provider'] ) : '';

		if ( empty( $provider_key ) ) {
			\wp_send_json_error( 'No provider specified' );
		}

		// Load required classes
		require_once RESELLER_PANEL_PATH . 'inc/interfaces/class-service-provider-interface.php';
		require_once RESELLER_PANEL_PATH . 'inc/abstract/class-base-service-provider.php';
		require_once RESELLER_PANEL_PATH . 'inc/providers/class-opensrs-provider.php';
		require_once RESELLER_PANEL_PATH . 'inc/providers/class-namecheap-provider.php';
		require_once RESELLER_PANEL_PATH . 'inc/class-provider-manager.php';

		$provider_manager = Provider_Manager::get_instance();
		$provider = $provider_manager->get_provider( $provider_key );

		if ( ! $provider ) {
			\wp_send_json_error( 'Provider not found' );
		}

		// Test the connection
		$result = $provider->test_connection();

		if ( \is_wp_error( $result ) ) {
			\wp_send_json_error( $result->get_error_message() );
		}

		\wp_send_json_success( 'Connection successful!' );
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
		\error_log( 'Reseller Panel - Activation hook called' );
		
		// Create database tables if needed
		$this->create_tables();

		// Set version
		\update_site_option( 'reseller_panel_version', RESELLER_PANEL_VERSION );
		
		\error_log( 'Reseller Panel - Activation completed' );
	}

	/**
	 * Plugin deactivation
	 *
	 * @return void
	 */
	public function deactivate() {
		// Clean up scheduled events if any
		\wp_clear_scheduled_hook( 'reseller_panel_sync_pricing' );
		
		\error_log( 'Reseller Panel - Deactivation completed' );
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

		// Fallback logs table
		$logs_table = $wpdb->prefix . 'reseller_panel_fallback_logs';
		$logs_sql = "CREATE TABLE IF NOT EXISTS $logs_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			service_key varchar(50) NOT NULL,
			primary_provider varchar(100) NOT NULL,
			fallback_provider varchar(100) NOT NULL,
			error_message text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY service_key (service_key),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once \ABSPATH . 'wp-admin/includes/upgrade.php';
		\dbDelta( $services_sql );
		\dbDelta( $providers_sql );
		\dbDelta( $logs_sql );
		
		// Log table creation for debugging
		\error_log( 'Reseller Panel - Database tables created/verified' );
	}
}
