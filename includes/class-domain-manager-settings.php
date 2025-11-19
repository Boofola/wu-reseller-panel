<?php
// Migration notice dismissed: no-op handler removed — notice disabled below.

/**
 * Domain Manager Settings
 *
 * Wrapper for plugin settings (provider selection, API credentials)
 */
class WU_OpenSRS_Settings {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Register network admin menu and pages for Domain Reseller Manager
		add_action( 'network_admin_menu', array( $this, 'register_admin_pages' ) );
		// Handle settings saves
		add_action( 'load-' . 'network_page_wu-domain-reseller-manager', array( $this, 'handle_settings_save' ) );
		add_action( 'load-' . 'network_page_wu-domain-reseller-manager-opensrs', array( $this, 'handle_opensrs_save' ) );
		add_action( 'load-' . 'network_page_wu-domain-reseller-manager-namecheap', array( $this, 'handle_namecheap_save' ) );
		add_action( 'load-' . 'network_page_wu-domain-reseller-manager-default', array( $this, 'handle_default_provider_save' ) );
	}

	/**
	 * Handle settings save for default provider
	 */
	public function handle_default_provider_save() {
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}
		if ( ! isset( $_POST['wu_domain_provider_default'] ) ) {
			return;
		}
		check_admin_referer( 'wu_domain_provider_default_nonce' );
		$provider = sanitize_text_field( wp_unslash( $_POST['wu_domain_provider_default'] ) );
		if ( in_array( $provider, array( 'opensrs', 'namecheap' ), true ) ) {
			update_site_option( 'wu_domain_provider_default', $provider );
			wp_safe_remote_post( add_query_arg( 'updated', '1' ) );
		}
	}

	/**
	 * Handle OpenSRS settings save
	 */
	public function handle_opensrs_save() {
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}
		if ( ! isset( $_POST['opensrs_api_key'] ) && ! isset( $_POST['opensrs_username'] ) && ! isset( $_POST['opensrs_mode'] ) && ! isset( $_POST['opensrs_enabled'] ) ) {
			return;
		}
		check_admin_referer( 'wu_opensrs_settings_nonce' );

		if ( isset( $_POST['opensrs_enabled'] ) ) {
			update_site_option( 'opensrs_enabled', '1' );
		} else {
			update_site_option( 'opensrs_enabled', '0' );
		}
		if ( isset( $_POST['opensrs_mode'] ) ) {
			$mode = sanitize_text_field( wp_unslash( $_POST['opensrs_mode'] ) );
			if ( in_array( $mode, array( 'test', 'live' ), true ) ) {
				update_site_option( 'opensrs_mode', $mode );
			}
		}
		if ( isset( $_POST['opensrs_username'] ) ) {
			$username = sanitize_text_field( wp_unslash( $_POST['opensrs_username'] ) );
			update_site_option( 'opensrs_username', $username );
		}
		if ( isset( $_POST['opensrs_api_key'] ) ) {
			$api_key = sanitize_text_field( wp_unslash( $_POST['opensrs_api_key'] ) );
			update_site_option( 'opensrs_api_key', $api_key );
		}
	}

	/**
	 * Handle NameCheap settings save
	 */
	public function handle_namecheap_save() {
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}
		if ( ! isset( $_POST['namecheap_enabled'] ) && ! isset( $_POST['namecheap_mode'] ) && ! isset( $_POST['namecheap_api_user'] ) && ! isset( $_POST['namecheap_api_key'] ) && ! isset( $_POST['namecheap_username'] ) && ! isset( $_POST['namecheap_client_ip'] ) ) {
			return;
		}
		check_admin_referer( 'wu_namecheap_settings_nonce' );

		if ( isset( $_POST['namecheap_enabled'] ) ) {
			update_site_option( 'namecheap_enabled', '1' );
		} else {
			update_site_option( 'namecheap_enabled', '0' );
		}
		if ( isset( $_POST['namecheap_mode'] ) ) {
			$mode = sanitize_text_field( wp_unslash( $_POST['namecheap_mode'] ) );
			if ( in_array( $mode, array( 'sandbox', 'live' ), true ) ) {
				update_site_option( 'namecheap_mode', $mode );
			}
		}
		if ( isset( $_POST['namecheap_api_user'] ) ) {
			$api_user = sanitize_text_field( wp_unslash( $_POST['namecheap_api_user'] ) );
			update_site_option( 'namecheap_api_user', $api_user );
		}
		if ( isset( $_POST['namecheap_api_key'] ) ) {
			$api_key = sanitize_text_field( wp_unslash( $_POST['namecheap_api_key'] ) );
			update_site_option( 'namecheap_api_key', $api_key );
		}
		if ( isset( $_POST['namecheap_username'] ) ) {
			$username = sanitize_text_field( wp_unslash( $_POST['namecheap_username'] ) );
			update_site_option( 'namecheap_username', $username );
		}
		if ( isset( $_POST['namecheap_client_ip'] ) ) {
			$client_ip = sanitize_text_field( wp_unslash( $_POST['namecheap_client_ip'] ) );
			update_site_option( 'namecheap_client_ip', $client_ip );
		}
	}

	/**
	 * Generic settings save handler (for future use)
	 */
	public function handle_settings_save() {
		// Placeholder for future settings save logic
		return;
	}

	public function register_admin_pages() {
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}

		$cap = 'manage_network';

		// Attach under Ultimate Multisite menu
		// The parent menu slug should be the Ultimate Multisite settings page
		$parent = 'wp-ultimo-settings';

		// Add submenu pages under UMS settings
		add_submenu_page(
			$parent,
			__( 'Domain Reseller Manager', 'ultimate-multisite' ),
			__( 'Domain Reseller Manager', 'ultimate-multisite' ),
			$cap,
			'wu-domain-reseller-manager',
			array( $this, 'page_overview' )
		);

		// Hidden/linked pages under the UMS parent
		add_submenu_page( $parent, __( 'Default API', 'ultimate-multisite' ), __( 'Default API', 'ultimate-multisite' ), $cap, 'wu-domain-reseller-manager-default', array( $this, 'page_default_api' ) );
		add_submenu_page( $parent, __( 'OpenSRS', 'ultimate-multisite' ), __( 'OpenSRS', 'ultimate-multisite' ), $cap, 'wu-domain-reseller-manager-opensrs', array( $this, 'page_opensrs' ) );
		add_submenu_page( $parent, __( 'NameCheap', 'ultimate-multisite' ), __( 'NameCheap', 'ultimate-multisite' ), $cap, 'wu-domain-reseller-manager-namecheap', array( $this, 'page_namecheap' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function enqueue_admin_assets( $hook ) {
		wp_enqueue_style( 'dashicons' );
	}

	public static function is_enabled() {
		$opensrs = get_site_option( 'opensrs_enabled', false );
		$namecheap = get_site_option( 'namecheap_enabled', false );
		return (bool) ( $opensrs || $namecheap );
	}

	/** Render connection test UI (button + JS) */
	public function render_connection_test() {
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}

		$opensrs_nonce   = wp_create_nonce( 'wu-opensrs-test' );
		$namecheap_nonce = wp_create_nonce( 'wu-namecheap-test' );
		?>
		<div>
			<h3><?php esc_html_e( 'Provider Connection Test', 'ultimate-multisite' ); ?></h3>
			<p><?php esc_html_e( 'Test connections to each configured provider.', 'ultimate-multisite' ); ?></p>
			<p>
				<button type="button" class="button" id="wu-domain-provider-test-opensrs" data-nonce="<?php echo esc_attr( $opensrs_nonce ); ?>" data-provider="opensrs"><?php echo esc_html( __( 'Test OpenSRS', 'ultimate-multisite' ) ); ?></button>
				<button type="button" class="button" id="wu-domain-provider-test-namecheap" data-nonce="<?php echo esc_attr( $namecheap_nonce ); ?>" data-provider="namecheap"><?php echo esc_html( __( 'Test NameCheap', 'ultimate-multisite' ) ); ?></button>
				<span id="wu-domain-provider-test-result" style="margin-left:12px"></span>
			</p>
		</div>
		<script>
		jQuery(function($){
			var ajaxurl = '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>';

			$('#wu-domain-provider-test-opensrs, #wu-domain-provider-test-namecheap').on('click', function(){
				var btn = $(this);
				var provider = btn.data('provider');
				var nonce = btn.data('nonce');
				var label = provider === 'namecheap' ? <?php echo json_encode( __( 'Test NameCheap', 'ultimate-multisite' ) ); ?> : <?php echo json_encode( __( 'Test OpenSRS', 'ultimate-multisite' ) ); ?>;
				btn.prop('disabled', true).text(<?php echo json_encode( __( 'Testing...', 'ultimate-multisite' ) ); ?>);
				$('#wu-domain-provider-test-result').text('');
				$.post(ajaxurl, {
					action: 'wu_domain_provider_test_connection',
					provider: provider,
					nonce: nonce
				}, function(res){
					if (res.success) {
						$('#wu-domain-provider-test-result').text(res.data.message).css('color','green');
					} else {
						$('#wu-domain-provider-test-result').text(res.data.message || <?php echo json_encode( __( 'Connection failed', 'ultimate-multisite' ) ); ?>).css('color','red');
					}
					btn.prop('disabled', false).text(label);
				}).fail(function(){
					$('#wu-domain-provider-test-result').text(<?php echo json_encode( __( 'Connection failed', 'ultimate-multisite' ) ); ?>).css('color','red');
					btn.prop('disabled', false).text(label);
				});
			});
		});
		</script>
		<?php
	}

	public function page_overview() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( __( 'Permission denied.', 'ultimate-multisite' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Domain Reseller Manager', 'ultimate-multisite' ) . '</h1>';
		echo '<p>' . esc_html__( 'Manage domain provider connections, import TLDs, and configure provider-specific settings.', 'ultimate-multisite' ) . '</p>';
		echo '</div>';
	}

	public function page_default_api() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( __( 'Permission denied.', 'ultimate-multisite' ) );
		}
		$current = get_site_option( 'wu_domain_provider_default', 'opensrs' );
		echo '<div class="wrap"><h1>' . esc_html__( 'Default Domain Provider', 'ultimate-multisite' ) . '</h1>';
		echo '<form method="post">';
		wp_nonce_field( 'wu_domain_provider_default_nonce' );
		echo '<p>' . esc_html__( 'Choose the default provider to use when products do not specify a provider.', 'ultimate-multisite' ) . '</p>';
		echo '<select name="wu_domain_provider_default">';
		echo '<option value="opensrs" ' . selected( $current, 'opensrs', false ) . '>OpenSRS</option>';
		echo '<option value="namecheap" ' . selected( $current, 'namecheap', false ) . '>NameCheap</option>';
		echo '</select>';
		submit_button( __( 'Save Default', 'ultimate-multisite' ) );
		echo '</form></div>';
	}

	public function page_opensrs() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( __( 'Permission denied.', 'ultimate-multisite' ) );
		}
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'OpenSRS Settings', 'ultimate-multisite' ) . '</h1>';

		if ( function_exists( 'wu_render_settings_section' ) ) {
			wu_render_settings_section( 'opensrs' );
		} elseif ( function_exists( 'wu_render_settings' ) ) {
			wu_render_settings( 'opensrs' );
		} else {
			echo '<form method="post">';
			wp_nonce_field( 'wu_opensrs_settings_nonce' );
			$fields = array(
				'opensrs_enabled'   => __( 'Enable OpenSRS', 'ultimate-multisite' ),
				'opensrs_mode'      => __( 'API Mode', 'ultimate-multisite' ),
				'opensrs_username'  => __( 'Reseller Username', 'ultimate-multisite' ),
				'opensrs_api_key'   => __( 'API Key', 'ultimate-multisite' ),
			);
			foreach ( $fields as $key => $label ) {
				$val = get_site_option( $key, '' );
				echo '<p>';
				echo '<label>' . esc_html( $label ) . '</label><br />';
				if ( $key === 'opensrs_enabled' ) {
					echo '<input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( $val, '1', false ) . '> ' . esc_html__( 'Enable', 'ultimate-multisite' );
				} elseif ( $key === 'opensrs_mode' ) {
					echo '<select name="' . esc_attr( $key ) . '"><option value="test"' . selected( $val, 'test', false ) . '>Test/Sandbox</option><option value="live"' . selected( $val, 'live', false ) . '>Live Production</option></select>';
				} else {
					echo '<input type="text" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" class="regular-text">';
				}
				echo '</p>';
			}
			submit_button( __( 'Save Settings', 'ultimate-multisite' ) );
			echo '</form>';
			if ( class_exists( 'WU_OpenSRS_Domain_Importer' ) ) {
				WU_OpenSRS_Domain_Importer::get_instance()->render_import_section();
			}
			$this->render_connection_test_provider('opensrs');
		}
		echo '</div>';
	}

	public function page_namecheap() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( __( 'Permission denied.', 'ultimate-multisite' ) );
		}
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'NameCheap Settings', 'ultimate-multisite' ) . '</h1>';
		echo '<form method="post">';
		wp_nonce_field( 'wu_namecheap_settings_nonce' );
		$fields = array(
			'namecheap_enabled'  => __( 'Enable NameCheap', 'ultimate-multisite' ),
			'namecheap_mode'     => __( 'NameCheap Mode', 'ultimate-multisite' ),
			'namecheap_api_user' => __( 'API User', 'ultimate-multisite' ),
			'namecheap_api_key'  => __( 'API Key', 'ultimate-multisite' ),
			'namecheap_username' => __( 'Username', 'ultimate-multisite' ),
			'namecheap_client_ip'=> __( 'Client IP', 'ultimate-multisite' ),
		);
		foreach ( $fields as $key => $label ) {
			$val = get_site_option( $key, '' );
			echo '<p>';
			echo '<label>' . esc_html( $label ) . '</label><br />';
			if ( $key === 'namecheap_enabled' ) {
				echo '<input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( $val, '1', false ) . '> ' . esc_html__( 'Enable', 'ultimate-multisite' );
			} elseif ( $key === 'namecheap_mode' ) {
				echo '<select name="' . esc_attr( $key ) . '"><option value="sandbox"' . selected( $val, 'sandbox', false ) . '>Sandbox</option><option value="live"' . selected( $val, 'live', false ) . '>Live</option></select>';
			} else {
				echo '<input type="text" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" class="regular-text">';
			}
			echo '</p>';
		}
		submit_button( __( 'Save Settings', 'ultimate-multisite' ) );
		echo '</form>';
		$this->render_connection_test_provider('namecheap');
		echo '</div>';
	}

	/** Render only the test button for a specific provider */
	public function render_connection_test_provider( $provider ) {
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}
		$nonce = $provider === 'namecheap' ? wp_create_nonce( 'wu-namecheap-test' ) : wp_create_nonce( 'wu-opensrs-test' );
		$label = $provider === 'namecheap' ? __( 'Test NameCheap', 'ultimate-multisite' ) : __( 'Test OpenSRS', 'ultimate-multisite' );
		?>
		<div>
			<h3><?php esc_html_e( 'Provider Connection Test', 'ultimate-multisite' ); ?></h3>
			<p><?php esc_html_e( 'Test connection to this provider.', 'ultimate-multisite' ); ?></p>
			<p>
				<button type="button" class="button" id="wu-domain-provider-test-<?php echo esc_attr( $provider ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-provider="<?php echo esc_attr( $provider ); ?>"><?php echo esc_html( $label ); ?></button>
				<span id="wu-domain-provider-test-result" style="margin-left:12px"></span>
			</p>
		</div>
		<script>
		jQuery(function($){
			var ajaxurl = '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>';
			$('#wu-domain-provider-test-<?php echo esc_js( $provider ); ?>').on('click', function(){
				var btn = $(this);
				var provider = btn.data('provider');
				var nonce = btn.data('nonce');
				var label = <?php echo json_encode( $label ); ?>;
				btn.prop('disabled', true).text(<?php echo json_encode( __( 'Testing...', 'ultimate-multisite' ) ); ?>);
				$('#wu-domain-provider-test-result').text('');
				$.post(ajaxurl, {
					action: 'wu_domain_provider_test_connection',
					provider: provider,
					nonce: nonce
				}, function(res){
					if (res.success) {
						$('#wu-domain-provider-test-result').text(res.data.message).css('color','green');
					} else {
						$('#wu-domain-provider-test-result').text(res.data.message || <?php echo json_encode( __( 'Connection failed', 'ultimate-multisite' ) ); ?>).css('color','red');
					}
					btn.prop('disabled', false).text(label);
				}).fail(function(){
					$('#wu-domain-provider-test-result').text(<?php echo json_encode( __( 'Connection failed', 'ultimate-multisite' ) ); ?>).css('color','red');
					btn.prop('disabled', false).text(label);
				});
			});
		});
		</script>
		<?php
	}

	/** Migration notice disabled. */
	public function render_migration_notice() {
		// Intentionally disabled — migration messaging removed per request.
		return;
	}

	public function register_settings() {
		// Disabled; settings are rendered via the Domain Reseller Manager pages.
		return;
	}
}
        
