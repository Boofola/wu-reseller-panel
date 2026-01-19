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

				// Handle providers_order (ranked list of providers)
				$providers_order = array();
				if ( isset( $service_data['providers_order'] ) && is_array( $service_data['providers_order'] ) ) {
					$providers_order = array_filter( $service_data['providers_order'] );
				}

				// For backward compatibility, also save default_provider and fallback_provider
				$default_provider = ! empty( $providers_order ) ? reset( $providers_order ) : '';
				$fallback_provider = isset( $providers_order[1] ) ? $providers_order[1] : '';

				$wpdb->update(
					$wpdb->prefix . 'reseller_panel_services',
					array(
						'enabled' => isset( $service_data['enabled'] ) ? 1 : 0,
						'default_provider' => $default_provider,
						'fallback_provider' => $fallback_provider,
						'providers_order' => ! empty( $providers_order ) ? wp_json_encode( $providers_order ) : null,
					),
					array( 'service_key' => sanitize_key( $service_key ) )
				);

				\Reseller_Panel\Logger::log_info(
					'Services_Settings',
					sprintf( 'Service %s updated', $service_key ),
					array(
						'enabled' => isset( $service_data['enabled'] ) ? '1' : '0',
						'providers_count' => count( $providers_order ),
						'providers' => implode( ', ', $providers_order ),
					)
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
		$configured_providers = $provider_manager->get_configured_providers();

		?>
<div class="wrap">
    <h1><?php echo esc_html( $this->page_title ); ?></h1>

    <?php if ( empty( $configured_providers ) ) : ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php esc_html_e( 'No Providers Configured', 'ultimate-multisite' ); ?></strong>
        </p>
        <p>
            <?php
						printf(
							/* translators: %s: link to provider settings page */
							esc_html__( 'You need to first configure some providers in %s before they will be available to configure here.', 'ultimate-multisite' ),
							'<a href="' . esc_url( network_admin_url( 'admin.php?page=reseller-panel-providers' ) ) . '">' . esc_html__( 'Provider Settings', 'ultimate-multisite' ) . '</a>'
						);
						?>
        </p>
        <p>
            <?php esc_html_e( 'After configuring at least one provider and testing the connection, return to this page to enable and configure services.', 'ultimate-multisite' ); ?>
        </p>
    </div>
    <?php endif; ?>

    <form method="post"
        action="<?php echo esc_url( network_admin_url( 'admin.php?page=reseller-panel-services' ) ); ?>">
        <?php $this->render_nonce_field( 'reseller_panel_services_save', 'reseller_panel_services_nonce' ); ?>

        <table class="wp-list-table widefat fixed">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e( 'Service', 'ultimate-multisite' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Enabled', 'ultimate-multisite' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Ranked Providers', 'ultimate-multisite' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Status', 'ultimate-multisite' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $services ) ) : ?>
                <?php foreach ( $services as $service ) : ?>
                <?php
									$available_providers = array();

									// Get available providers that support this service
									foreach ( $configured_providers as $provider ) {
										if ( $provider->supports_service( $service->service_key ) ) {
											$available_providers[ $provider->get_key() ] = $provider->get_name();
										}
									}

									// Get current ranked providers
									$providers_order = ! empty( $service->providers_order ) ? json_decode( $service->providers_order, true ) : array();
									if ( ! is_array( $providers_order ) ) {
										$providers_order = array();
									}

									// For backward compatibility, use default_provider and fallback_provider if providers_order is empty
									if ( empty( $providers_order ) && ! empty( $service->default_provider ) ) {
										$providers_order[] = $service->default_provider;
										if ( ! empty( $service->fallback_provider ) && $service->fallback_provider !== $service->default_provider ) {
											$providers_order[] = $service->fallback_provider;
										}
									}

									$has_providers = ! empty( $available_providers );
								?>
                <tr>
                    <td><?php echo esc_html( $service->service_name ); ?></td>
                    <td>
                        <input type="checkbox"
                            name="services[<?php echo esc_attr( $service->service_key ); ?>][enabled]" value="1"
                            <?php checked( $service->enabled, 1 ); ?> />
                    </td>
                    <td>
                        <?php if ( $has_providers ) : ?>
                        <div class="reseller-panel-provider-ranking"
                            data-service-key="<?php echo esc_attr( $service->service_key ); ?>">
                            <ul class="provider-list sortable" style="list-style: none; padding: 0; margin: 0;">
                                <?php
														$rank = 1;
														foreach ( $providers_order as $provider_key ) {
															if ( isset( $available_providers[ $provider_key ] ) ) {
																echo '<li class="provider-item" data-provider-key="' . esc_attr( $provider_key ) . '" style="padding: 8px; margin-bottom: 5px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; cursor: move; display: flex; align-items: center;">';
																echo '<span class="rank-number" style="display: inline-block; width: 24px; margin-right: 8px; font-weight: bold; color: #0073aa;">' . esc_html( $rank ) . '.</span>';
																echo '<span class="dashicons dashicons-move" style="margin-right: 8px; color: #999;"></span>';
																echo '<span class="provider-name">' . esc_html( $available_providers[ $provider_key ] ) . '</span>';
																echo '<input type="hidden" name="services[' . esc_attr( $service->service_key ) . '][providers_order][]" value="' . esc_attr( $provider_key ) . '" />';
																echo '<button type="button" class="button button-link-delete" style="margin-left: auto;" title="' . esc_attr__( 'Remove provider', 'ultimate-multisite' ) . '">Remove</button>';
																echo '</li>';
																$rank++;
															}
														}
													?>
                            </ul>

                            <div style="margin-top: 10px;">
                                <label for="add-provider-<?php echo esc_attr( $service->service_key ); ?>"
                                    style="display: block; margin-bottom: 5px; font-weight: bold;">
                                    <?php esc_html_e( 'Add Provider:', 'ultimate-multisite' ); ?>
                                </label>
                                <div style="display: flex; gap: 5px;">
                                    <select id="add-provider-<?php echo esc_attr( $service->service_key ); ?>"
                                        class="add-provider-select"
                                        data-service-key="<?php echo esc_attr( $service->service_key ); ?>"
                                        style="flex: 1;">
                                        <option value="">--
                                            <?php esc_html_e( 'Select a provider', 'ultimate-multisite' ); ?> --
                                        </option>
                                        <?php foreach ( $available_providers as $key => $name ) : ?>
                                        <?php if ( ! in_array( $key, $providers_order, true ) ) : ?>
                                        <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $name ); ?>
                                        </option>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="button add-provider-btn"
                                        data-service-key="<?php echo esc_attr( $service->service_key ); ?>">
                                        <?php esc_html_e( 'Add', 'ultimate-multisite' ); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php else : ?>
                        <span
                            class="description"><?php esc_html_e( 'No providers available', 'ultimate-multisite' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( ! $has_providers ) : ?>
                        <span class="dashicons dashicons-warning"
                            title="<?php esc_attr_e( 'No compatible providers configured', 'ultimate-multisite' ); ?>"></span>
                        <?php elseif ( ! $service->enabled ) : ?>
                        <span class="dashicons dashicons-marker"
                            title="<?php esc_attr_e( 'Disabled', 'ultimate-multisite' ); ?>"></span>
                        <?php elseif ( empty( $providers_order ) ) : ?>
                        <span class="dashicons dashicons-warning"
                            title="<?php esc_attr_e( 'No providers selected', 'ultimate-multisite' ); ?>"></span>
                        <?php else : ?>
                        <span class="dashicons dashicons-yes"
                            title="<?php esc_attr_e( 'Configured', 'ultimate-multisite' ); ?>"></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else : ?>
                <tr>
                    <td colspan="4">
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
        <h3><?php esc_html_e( 'Multi-Provider Ranking System', 'ultimate-multisite' ); ?></h3>
        <p><?php esc_html_e( 'Drag and drop providers to rank them in the order you want them to be tried. The first provider in the list is the default provider. If it fails, the system will automatically try the next provider in the list as a fallback. This continues until all providers have been exhausted or one succeeds.', 'ultimate-multisite' ); ?>
        </p>
        <p><?php esc_html_e( 'An email notification will be sent to the admin email address when a fallback provider is used, including details about which provider failed and which fallback provider was used.', 'ultimate-multisite' ); ?>
        </p>
    </div>

    <script>
    jQuery(function($) {
        // Initialize sortable lists for provider ranking
        $('.provider-list.sortable').sortable({
            placeholder: 'sortable-placeholder',
            items: '> .provider-item',
            update: function() {
                updateRankNumbers($(this));
            }
        });

        // Function to update rank numbers after sorting
        function updateRankNumbers($list) {
            $list.find('.rank-number').each(function(index) {
                $(this).text((index + 1) + '.');
            });
        }

        // Handle adding a provider
        $('.add-provider-btn').on('click', function(e) {
            e.preventDefault();
            var serviceKey = $(this).data('service-key');
            var selectBox = $('#add-provider-' + serviceKey);
            var providerKey = selectBox.val();

            if (!providerKey) {
                alert('<?php esc_attr_e( 'Please select a provider', 'ultimate-multisite' ); ?>');
                return;
            }

            var providerName = selectBox.find('option:selected').text();
            var $list = selectBox.closest('.reseller-panel-provider-ranking[data-service-key="' +
                serviceKey + '"]').find('.provider-list');

            // Check if provider is already in the list
            if ($list.find('[data-provider-key="' + providerKey + '"]').length > 0) {
                alert(
                    '<?php esc_attr_e( 'This provider is already in the list', 'ultimate-multisite' ); ?>'
                );
                return;
            }

            // Create new list item
            var $newItem = $('<li class="provider-item" data-provider-key="' + providerKey +
                '" style="padding: 8px; margin-bottom: 5px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; cursor: move; display: flex; align-items: center;">' +
                '<span class="rank-number" style="display: inline-block; width: 24px; margin-right: 8px; font-weight: bold; color: #0073aa;"></span>' +
                '<span class="dashicons dashicons-move" style="margin-right: 8px; color: #999;"></span>' +
                '<span class="provider-name"></span>' +
                '<input type="hidden" name="services[' + serviceKey +
                '][providers_order][]" value="' + providerKey + '" />' +
                '<button type="button" class="button button-link-delete" style="margin-left: auto;" title="<?php esc_attr_e( 'Remove provider', 'ultimate-multisite' ); ?>">Remove</button>' +
                '</li>');
            $newItem.find('.provider-name').text(providerName);

            $list.append($newItem);
            updateRankNumbers($list);

            // Remove option from select and reset
            selectBox.find('option[value="' + providerKey + '"]').remove();
            selectBox.val('').trigger('change');
        });

        // Handle removing a provider
        $(document).on('click', '.provider-item .button-link-delete', function(e) {
            e.preventDefault();
            var $item = $(this).closest('.provider-item');
            var $list = $item.closest('.provider-list');
            var serviceKey = $item.closest('[data-service-key]').data('service-key');
            var providerKey = $item.data('provider-key');
            var providerName = $item.find('.provider-name').text();

            $item.remove();
            updateRankNumbers($list);

            // Add option back to select
            var selectBox = $('#add-provider-' + serviceKey);
            selectBox.append('<option value="' + providerKey + '">' + providerName + '</option>');
            selectBox.find('option').sort(function(a, b) {
                return $(a).text() > $(b).text() ? 1 : -1;
            }).appendTo(selectBox);
        });

        // Update hidden inputs when list is reordered
        $('.provider-list.sortable').on('sortupdate', function() {
            var serviceKey = $(this).closest('.reseller-panel-provider-ranking').data('service-key');
            var $list = $(this);
            var providers = [];

            $list.find('.provider-item').each(function() {
                providers.push($(this).data('provider-key'));
            });

            $list.closest('.reseller-panel-provider-ranking').find('input[name*="providers_order"]')
                .remove();

            providers.forEach(function(providerKey) {
                $list.find('[data-provider-key="' + providerKey + '"]').append(
                    '<input type="hidden" name="services[' + serviceKey +
                    '][providers_order][]" value="' + providerKey + '" />'
                );
            });
        });
    });
    </script>

    <style>
    .reseller-panel-provider-ranking {
        padding: 10px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .provider-list {
        margin-bottom: 10px;
    }

    .provider-item {
        transition: opacity 0.2s;
    }

    .provider-item:hover {
        background-color: #efefef !important;
    }

    .provider-item.ui-sortable-helper {
        opacity: 0.7;
    }

    .sortable-placeholder {
        background: #e8e8e8;
        border: 1px dashed #999;
        margin-bottom: 5px;
        height: 36px;
        border-radius: 4px;
    }

    .provider-item .button-link-delete {
        padding: 0;
        height: auto;
        font-size: 12px;
        color: #dc3545;
    }

    .provider-item .button-link-delete:hover {
        color: #c82333;
    }
    </style>
</div>
<?php
	}
}