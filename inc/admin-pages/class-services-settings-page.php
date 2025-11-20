<?php
/**
 * Services Settings Admin Page
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel\Admin_Pages;

use Reseller_Panel\Provider_Manager;

/**
 * Services Settings Page Class
 */
class Services_Settings_Page extends Admin_Page {

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
	protected $page_slug = 'reseller-panel-services';

	/**
	 * Page title
	 *
	 * @var string
	 */
	protected $page_title = 'Reseller Panel - Services Settings';

	/**
	 * Menu title
	 *
	 * @var string
	 */
	protected $menu_title = 'Services Settings';

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
		if ( ! isset( $_POST['reseller_panel_services_nonce'] ) ) {
			return;
		}

		check_admin_referer( 'reseller_panel_services_save', 'reseller_panel_services_nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'ultimate-multisite' ) );
		}

		// Process service settings
		global $wpdb;

		if ( isset( $_POST['services'] ) && is_array( $_POST['services'] ) ) {
			foreach ( $_POST['services'] as $service_key => $service_data ) {
				$service_data = array_map( 'sanitize_text_field', $service_data );

				$wpdb->update(
					$wpdb->prefix . 'reseller_panel_services',
					array(
						'enabled' => isset( $service_data['enabled'] ) ? 1 : 0,
						'default_provider' => $service_data['default_provider'] ?? '',
						'fallback_provider' => $service_data['fallback_provider'] ?? '',
					),
					array( 'service_key' => sanitize_key( $service_key ) )
				);
			}
		}

		// Show success message
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-success is-dismissible"><p>';
			esc_html_e( 'Services settings saved successfully!', 'ultimate-multisite' );
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

		// Handle form submission first
		$this->handle_form_submission();

		global $wpdb;

		$services = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}reseller_panel_services ORDER BY service_name ASC"
		);

		$provider_manager = Provider_Manager::get_instance();
		$all_providers = $provider_manager->get_all_providers();

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->page_title ); ?></h1>

			<form method="post" action="<?php echo esc_url( network_admin_url( 'admin.php?page=reseller-panel-services' ) ); ?>">
				<?php $this->render_nonce_field( 'reseller_panel_services_save', 'reseller_panel_services_nonce' ); ?>

				<table class="wp-list-table widefat fixed">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Service', 'ultimate-multisite' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Enabled', 'ultimate-multisite' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Default Provider', 'ultimate-multisite' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Fallback Provider', 'ultimate-multisite' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'ultimate-multisite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! empty( $services ) ) : ?>
							<?php foreach ( $services as $service ) : ?>
								<?php
									$default_providers = array();
									$fallback_providers = array();

									foreach ( $all_providers as $provider ) {
										if ( $provider->supports_service( $service->service_key ) ) {
											$default_providers[ $provider->get_key() ] = $provider->get_name();
											$fallback_providers[ $provider->get_key() ] = $provider->get_name();
										}
									}

									$has_providers = ! empty( $default_providers );
								?>
								<tr>
									<td><?php echo esc_html( $service->service_name ); ?></td>
									<td>
										<input type="checkbox" name="services[<?php echo esc_attr( $service->service_key ); ?>][enabled]" value="1" <?php checked( $service->enabled, 1 ); ?> />
									</td>
									<td>
										<?php if ( $has_providers ) : ?>
											<select name="services[<?php echo esc_attr( $service->service_key ); ?>][default_provider]">
												<option value="">-- <?php esc_html_e( 'Select', 'ultimate-multisite' ); ?> --</option>
												<?php foreach ( $default_providers as $key => $name ) : ?>
													<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $service->default_provider, $key ); ?>>
														<?php echo esc_html( $name ); ?>
													</option>
												<?php endforeach; ?>
											</select>
										<?php else : ?>
											<span class="description"><?php esc_html_e( 'No providers available', 'ultimate-multisite' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $has_providers ) : ?>
											<select name="services[<?php echo esc_attr( $service->service_key ); ?>][fallback_provider]">
												<option value="">-- <?php esc_html_e( 'None', 'ultimate-multisite' ); ?> --</option>
												<?php foreach ( $fallback_providers as $key => $name ) : ?>
													<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $service->fallback_provider, $key ); ?>>
														<?php echo esc_html( $name ); ?>
													</option>
												<?php endforeach; ?>
											</select>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( ! $has_providers ) : ?>
											<span class="dashicons dashicons-warning" title="<?php esc_attr_e( 'No compatible providers configured', 'ultimate-multisite' ); ?>"></span>
										<?php elseif ( ! $service->enabled ) : ?>
											<span class="dashicons dashicons-marker" title="<?php esc_attr_e( 'Disabled', 'ultimate-multisite' ); ?>"></span>
										<?php elseif ( empty( $service->default_provider ) ) : ?>
											<span class="dashicons dashicons-warning" title="<?php esc_attr_e( 'No default provider selected', 'ultimate-multisite' ); ?>"></span>
										<?php else : ?>
											<span class="dashicons dashicons-yes" title="<?php esc_attr_e( 'Configured', 'ultimate-multisite' ); ?>"></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="5">
									<?php esc_html_e( 'No services configured', 'ultimate-multisite' ); ?>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>

				<p class="submit">
					<?php submit_button( __( 'Save Services Settings', 'ultimate-multisite' ), 'primary large', 'submit', true ); ?>
				</p>
			</form>

			<div class="reseller-panel-help">
				<h3><?php esc_html_e( 'How Fallback Works', 'ultimate-multisite' ); ?></h3>
				<p><?php esc_html_e( 'When a service request is made, the system will first try to use the default provider. If that provider fails, it will automatically fall back to the fallback provider if one is configured. An email notification will be sent to the admin email address when a fallback occurs.', 'ultimate-multisite' ); ?></p>
			</div>
		</div>
		<?php
	}
}