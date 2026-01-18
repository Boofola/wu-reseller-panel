<?php
/**
 * Provider Settings Admin Page
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel\Admin_Pages;

use Reseller_Panel\Provider_Manager;

/**
 * Provider Settings Page Class
 */
class Provider_Settings_Page extends Admin_Page {

	/**
	 * Singleton instance
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Page slug
	 *
	 * @var string
	 */
	protected $page_slug = 'reseller-panel-providers';

	/**
	 * Page title
	 *
	 * @var string
	 */
	protected $page_title = 'Reseller Panel - Provider Settings';

	/**
	 * Menu title
	 *
	 * @var string
	 */
	protected $menu_title = 'Provider Settings';

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
	 * Handle form submission
	 *
	 * @return void
	 */
	public function handle_form_submission() {
		if ( ! isset( $_POST['reseller_panel_provider_nonce'] ) ) {
			return;
		}

		\check_admin_referer( 'reseller_panel_provider_save', 'reseller_panel_provider_nonce' );

		if ( ! \current_user_can( 'manage_network' ) ) {
			\wp_die( \esc_html__( 'Insufficient permissions', 'ultimate-multisite' ) );
		}

		$provider_key = isset( $_POST['provider_key'] ) ? \sanitize_key( $_POST['provider_key'] ) : '';

		if ( empty( $provider_key ) ) {
			return;
		}

		$provider_manager = Provider_Manager::get_instance();
		$provider = $provider_manager->get_provider( $provider_key );

		if ( ! $provider ) {
			return;
		}

		// Get provider config fields
		$config_fields = $provider->get_config_fields();
		$config = array();

		foreach ( $config_fields as $field_key => $field ) {
			if ( isset( $_POST[ $field_key ] ) ) {
				$config[ $field_key ] = \sanitize_text_field( \wp_unslash( $_POST[ $field_key ] ) );
			}
		}

		// Save configuration
		if ( method_exists( $provider, 'save_config' ) ) {
			$provider->save_config( $config );
		} else {
			// Fallback: Save directly to options
			\update_option( 'reseller_panel_provider_' . $provider_key, $config );
		}

		// Show success message
		\add_action( 'admin_notices', function() use ( $provider ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo \esc_html( \sprintf( \__( '%s settings saved successfully!', 'ultimate-multisite' ), $provider->get_name() ) );
			echo '</p></div>';
		});
	}

	/**
	 * Render page
	 *
	 * @return void
	 */
	public function render_page() {
		try {
			if ( ! \current_user_can( 'manage_network' ) ) {
				\wp_die( \esc_html__( 'Insufficient permissions', 'ultimate-multisite' ) );
			}

			// Handle form submission first
			$this->handle_form_submission();

			$provider_manager = Provider_Manager::get_instance();
			
			if ( ! $provider_manager ) {
				\error_log( 'Reseller Panel - Provider Manager is NULL' );
				\wp_die( \esc_html__( 'Provider manager could not be initialized', 'ultimate-multisite' ) );
			}
			
			$providers = $provider_manager->get_all_providers();
			
			if ( empty( $providers ) ) {
				\error_log( 'Reseller Panel - No providers registered' );
			}

			$selected_provider = isset( $_GET['provider'] ) ? \sanitize_key( $_GET['provider'] ) : '';

			if ( ! empty( $selected_provider ) ) {
				$selected_provider = $provider_manager->get_provider( $selected_provider );
				if ( ! $selected_provider ) {
					\error_log( 'Reseller Panel - Provider not found: ' . $selected_provider );
				}
			}
		} catch ( \Exception $e ) {
			\error_log( 'Reseller Panel - Provider Settings Page Error: ' . $e->getMessage() );
			\error_log( 'Reseller Panel - Stack trace: ' . $e->getTraceAsString() );
			\wp_die( \esc_html( \sprintf( \__( 'An error occurred: %s', 'ultimate-multisite' ), $e->getMessage() ) ) );
		}

		?>
<div class="wrap">
    <h1><?php echo esc_html( $this->page_title ); ?></h1>

    <div class="reseller-panel-providers-tabs">
        <?php foreach ( $providers as $provider ) : ?>
        <a href="<?php echo esc_url( add_query_arg( 'provider', $provider->get_key() ) ); ?>"
            class="nav-tab <?php echo $selected_provider && $selected_provider->get_key() === $provider->get_key() ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html( $provider->get_name() ); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ( $selected_provider ) : ?>
    <?php $this->render_provider_form( $selected_provider ); ?>
    <?php else : ?>
    <div class="notice notice-info">
        <p><?php esc_html_e( 'Please select a provider to configure.', 'ultimate-multisite' ); ?></p>
    </div>
    <?php endif; ?>
</div>
<?php
	}

	/**
	 * Render provider configuration form
	 *
	 * @param object $provider Provider instance
	 *
	 * @return void
	 */
	private function render_provider_form( $provider ) {
		$provider->load_config();
		$config_fields = $provider->get_config_fields();

		// Build form action URL
		$action_url = network_admin_url( 'admin.php?page=reseller-panel-providers' );
		if ( ! empty( $_GET['provider'] ) ) {
			$action_url = add_query_arg( 'provider', sanitize_key( $_GET['provider'] ), $action_url );
		}

		?>
<form method="post" action="<?php echo esc_url( $action_url ); ?>">
    <?php $this->render_nonce_field( 'reseller_panel_provider_save', 'reseller_panel_provider_nonce' ); ?>

    <input type="hidden" name="provider_key" value="<?php echo esc_attr( $provider->get_key() ); ?>" />

			<div class="reseller-panel-form-container">
				<h2><?php echo esc_html( $provider->get_name() ); ?></h2>
				
				<?php
				// Get provider-specific help link
				$help_links = array(
					'opensrs' => array(
						'url' => 'https://www.tucowsdomains.com/resource-center/reseller-support/',
						'text' => __( 'OpenSRS Reseller Support & Documentation', 'ultimate-multisite' ),
					),
					'namecheap' => array(
						'url' => 'https://www.namecheap.com/support/api/intro/',
						'text' => __( 'NameCheap API Getting Started Guide', 'ultimate-multisite' ),
					),
				);
				
				$provider_key = $provider->get_key();
				if ( isset( $help_links[ $provider_key ] ) ) :
				?>
					<div class="notice notice-info inline" style="margin: 15px 0; padding: 12px;">
						<p>
							<strong><?php esc_html_e( 'Need Help?', 'ultimate-multisite' ); ?></strong>
							<?php
							printf(
								/* translators: %1$s: provider name, %2$s: link opening tag, %3$s: link closing tag */
								esc_html__( 'Setting up your %1$s API account? %2$sVisit the %1$s Knowledge Base%3$s for detailed setup instructions.', 'ultimate-multisite' ),
								esc_html( $provider->get_name() ),
								'<a href="' . esc_url( $help_links[ $provider_key ]['url'] ) . '" target="_blank" rel="noopener noreferrer">',
								'</a>'
							);
							?>
						</p>
					</div>
				<?php endif; ?>
    <div class="reseller-panel-form-container">
        <h2><?php echo esc_html( $provider->get_name() ); ?></h2>

        <?php foreach ( $config_fields as $field_key => $field ) : ?>
        <?php
						$value = '';
						if ( method_exists( $provider, 'get_config_value' ) ) {
							$value = $provider->get_config_value( $field_key, '' );
						}
					?>
					<div class="reseller-panel-form-group">
						<label for="<?php echo esc_attr( $field_key ); ?>">
							<?php echo esc_html( $field['label'] ?? $field_key ); ?>
						</label>

						<?php if ( 'select' === ( $field['type'] ?? 'text' ) ) : ?>
							<select id="<?php echo esc_attr( $field_key ); ?>" name="<?php echo esc_attr( $field_key ); ?>">
								<?php if ( isset( $field['options'] ) ) : ?>
									<?php foreach ( $field['options'] as $opt_val => $opt_label ) : ?>
										<option value="<?php echo esc_attr( $opt_val ); ?>" <?php selected( $value, $opt_val ); ?>>
											<?php echo esc_html( $opt_label ); ?>
										</option>
									<?php endforeach; ?>
								<?php endif; ?>
							</select>

						<?php elseif ( 'textarea' === ( $field['type'] ?? 'text' ) ) : ?>
							<textarea id="<?php echo esc_attr( $field_key ); ?>" name="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_textarea( $value ); ?></textarea>

						<?php else : ?>
							<input type="<?php echo esc_attr( $field['type'] ?? 'text' ); ?>" 
							       id="<?php echo esc_attr( $field_key ); ?>" 
							       name="<?php echo esc_attr( $field_key ); ?>" 
							       value="<?php echo esc_attr( $value ); ?>" />
						<?php endif; ?>

						<?php if ( ! empty( $field['description'] ?? '' ) ) : ?>
							<p class="description">
								<?php echo wp_kses_post( $field['description'] ); ?>
								<?php if ( isset( $field['link'] ) ) : ?>
									<br/>
									<a href="<?php echo esc_url( $field['link'] ); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo esc_html( $field['link_text'] ?? $field['link'] ); ?>
									</a>
								<?php endif; ?>
							</p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>

				<p class="submit">
					<?php submit_button( sprintf( __( 'Save %s Settings', 'ultimate-multisite' ), $provider->get_name() ), 'primary large', 'submit', true ); ?>
					<button type="button" class="button button-secondary reseller-panel-test-connection" data-provider="<?php echo esc_attr( $provider->get_key() ); ?>">
						<?php esc_html_e( 'Test Connection', 'ultimate-multisite' ); ?>
					</button>
					<span class="reseller-panel-test-message" style="display: none; margin-left: 10px; font-weight: bold; vertical-align: middle;"></span>
					
					<?php if ( $provider->supports_service( 'domains' ) ) : ?>
						<button type="button" class="button button-secondary reseller-panel-import-domains" data-provider="<?php echo esc_attr( $provider->get_key() ); ?>" disabled>
							<?php esc_html_e( 'Import Domains', 'ultimate-multisite' ); ?>
						</button>
						<span class="reseller-panel-import-message" style="display: none; margin-left: 10px; font-weight: bold; vertical-align: middle;"></span>
					<?php endif; ?>
					
					<?php wp_nonce_field( 'reseller_panel_provider_nonce', '_wpnonce', false ); ?>
				</p>

				<!-- Error Details Section -->
				<div id="reseller-panel-error-details" class="reseller-panel-error-details" style="display: none;">
					<h3><?php esc_html_e( 'Connection Error Details', 'ultimate-multisite' ); ?></h3>
					<div id="reseller-panel-error-content" class="reseller-panel-error-content"></div>
					<p class="description" style="margin-top: 10px;">
						<?php esc_html_e( 'Please verify your API credentials and ensure your server IP is whitelisted (if required by the provider). If the issue persists, contact the provider\'s support with the error code above.', 'ultimate-multisite' ); ?>
					</p>
				</div>
			</div>
		</form>
		<?php
	}
}