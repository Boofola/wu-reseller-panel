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

		check_admin_referer( 'reseller_panel_provider_save', 'reseller_panel_provider_nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'ultimate-multisite' ) );
		}

		$provider_key = isset( $_POST['provider_key'] ) ? sanitize_key( $_POST['provider_key'] ) : '';

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
				$config[ $field_key ] = sanitize_text_field( wp_unslash( $_POST[ $field_key ] ) );
			}
		}

		// Save configuration
		$provider->save_config( $config );

		// Show success message
		add_action( 'admin_notices', function() use ( $provider ) {
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html( sprintf( __( '%s settings saved successfully!', 'ultimate-multisite' ), $provider->get_name() ) );
			echo '</p></div>';
		});
	}

	/**
	 * Render page
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'ultimate-multisite' ) );
		}

		$provider_manager = Provider_Manager::get_instance();
		$providers = $provider_manager->get_all_providers();

		$selected_provider = isset( $_GET['provider'] ) ? sanitize_key( $_GET['provider'] ) : '';

		if ( ! empty( $selected_provider ) ) {
			$selected_provider = $provider_manager->get_provider( $selected_provider );
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
		$config = $provider->load_config();

		?>
		<form method="post" action="">
			<?php $this->render_nonce_field( 'reseller_panel_provider_save', 'reseller_panel_provider_nonce' ); ?>

			<input type="hidden" name="provider_key" value="<?php echo esc_attr( $provider->get_key() ); ?>" />

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
					<a href="<?php echo esc_url( $this->get_test_connection_url( $provider ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Test Connection', 'ultimate-multisite' ); ?>
					</a>
				</p>
			</div>
		</form>
		<?php
	}

	/**
	 * Get test connection URL
	 *
	 * @param object $provider Provider instance
	 *
	 * @return string
	 */
	private function get_test_connection_url( $provider ) {
		return add_query_arg(
			array(
				'action' => 'reseller_panel_test_connection',
				'provider' => $provider->get_key(),
				'_wpnonce' => wp_create_nonce( 'reseller_panel_test_' . $provider->get_key() ),
			),
			network_admin_url( 'admin-ajax.php' )
		);
	}
}