<?php
/**
 * Settings Manager - Handles addon settings registration.
 *
 * @package WP_Ultimo_Content_Sync
 * @since 1.0.0
 */

namespace WP_Ultimo_Content_Sync;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Settings Manager class.
 *
 * Registers and manages addon settings.
 */
class Settings_Manager {

	use \WP_Ultimo_Content_Sync\Traits\Singleton;

	/**
	 * Initialize the settings manager.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init() {

		add_action('init', [$this, 'register_settings']);
		add_filter('wu_pre_save_settings', [$this, 'save_excluded_sites'], 10, 3);
		add_filter('wu_get_setting', [$this, 'filter_excluded_sites_value'], 10, 4);
	}

	/**
	 * Register settings section and fields.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings() {

		// Register settings section
		wu_register_settings_section(
			'content_sync',
			[
				'title' => __('Content Sync', 'ultimate-multisite-content-sync'),
				'desc'  => __('Configure content synchronization between sites in your network.', 'ultimate-multisite-content-sync'),
				'icon'  => 'dashicons-wu-block-default',
				'order' => 999,
				'addon' => true,
			]
		);

		// General Settings Header
		wu_register_settings_field(
			'content_sync',
			'ets_header_general',
			[
				'type'  => 'header',
				'title' => __('General Settings', 'ultimate-multisite-content-sync'),
				'desc'  => __('Configure how content is synced between sites in your network.', 'ultimate-multisite-content-sync'),
			]
		);

		// Enable Media Sync
		wu_register_settings_field(
			'content_sync',
			'ets_sync_media',
			[
				'type'    => 'toggle',
				'title'   => __('Sync Media Files', 'ultimate-multisite-content-sync'),
				'desc'    => __('Automatically copy images and media files used in content to target sites.', 'ultimate-multisite-content-sync'),
				'tooltip' => __('When enabled, all media files (images, videos, etc.) referenced in content will be copied to target sites and IDs will be remapped. Disable this if you want to share media across sites.', 'ultimate-multisite-content-sync'),
				'default' => true,
			]
		);
		// Enable Dependent Sync
		wu_register_settings_field(
			'content_sync',
			'ets_sync_dependencies',
			[
				'type'    => 'toggle',
				'title'   => __('Sync Content Dependencies', 'ultimate-multisite-content-sync'),
				'desc'    => __('Automatically sync other content and objects used by the item when syncing.', 'ultimate-multisite-content-sync'),
				'tooltip' => __('When enabled, the content being synced is scanned for shortcodes to find other content that needs to be synced.', 'ultimate-multisite-content-sync'),
				'default' => true,
			]
		);

		// Conflict Resolution
		wu_register_settings_field(
			'content_sync',
			'ets_conflict_resolution',
			[
				'type'    => 'select',
				'title'   => __('Conflict Resolution', 'ultimate-multisite-content-sync'),
				'desc'    => __('How to handle content that already exists on target sites.', 'ultimate-multisite-content-sync'),
				'tooltip' => __('When content with the same name exists on a target site, choose whether to replace it with the source version or skip it.', 'ultimate-multisite-content-sync'),
				'options' => [
					'replace' => __('Replace Existing Content', 'ultimate-multisite-content-sync'),
					'skip'    => __('Skip Existing Content', 'ultimate-multisite-content-sync'),
				],
				'default' => 'replace',
			]
		);

		// Clear Elementor Cache
		wu_register_settings_field(
			'content_sync',
			'ets_clear_cache',
			[
				'type'    => 'toggle',
				'title'   => __('Clear Elementor Cache', 'ultimate-multisite-content-sync'),
				'desc'    => __('Automatically clear Elementor cache on target sites after syncing Elementor templates.', 'ultimate-multisite-content-sync'),
				'tooltip' => __('Recommended to ensure Elementor templates display correctly. Only applies when syncing Elementor content.', 'ultimate-multisite-content-sync'),
				'default' => true,
			]
		);

		// Excluded Sites Header
		wu_register_settings_field(
			'content_sync',
			'ets_header_exclusions',
			[
				'type'  => 'header',
				'title' => __('Site Exclusions', 'ultimate-multisite-content-sync'),
				'desc'  => __('Manage which sites should not receive content syncs.', 'ultimate-multisite-content-sync'),
			]
		);

		// Excluded Sites
		wu_register_settings_field(
			'content_sync',
			'ets_excluded_sites',
			[
				'type'    => 'html',
				'title'   => __('Excluded Sites', 'ultimate-multisite-content-sync'),
				'desc'    => __('Select sites that should be excluded from content syncs.', 'ultimate-multisite-content-sync'),
				'tooltip' => __('Content will not be synced to these sites. This is useful for custom sites that should maintain their own content.', 'ultimate-multisite-content-sync'),
				'content' => $this->render_excluded_sites_selector(),
			]
		);

		// Advanced Settings Header
		wu_register_settings_field(
			'content_sync',
			'ets_header_advanced',
			[
				'type'  => 'header',
				'title' => __('Advanced Settings', 'ultimate-multisite-content-sync'),
				'desc'  => __('Advanced configuration options.', 'ultimate-multisite-content-sync'),
			]
		);

		// Sync Taxonomies
		wu_register_settings_field(
			'content_sync',
			'ets_sync_taxonomies',
			[
				'type'    => 'toggle',
				'title'   => __('Sync Taxonomies', 'ultimate-multisite-content-sync'),
				'desc'    => __('Copy taxonomies (categories, tags, etc.) to target sites.', 'ultimate-multisite-content-sync'),
				'tooltip' => __('Enable this to preserve content organization on target sites.', 'ultimate-multisite-content-sync'),
				'default' => true,
			]
		);

		// Preserve Post IDs
		wu_register_settings_field(
			'content_sync',
			'ets_preserve_template_ids',
			[
				'type'    => 'toggle',
				'title'   => __('Attempt to Preserve Post IDs', 'ultimate-multisite-content-sync'),
				'desc'    => __('Try to use the same post IDs on target sites as the source site (not guaranteed).', 'ultimate-multisite-content-sync'),
				'tooltip' => __('This can help maintain consistency but may fail if the ID is already in use. Generally not needed.', 'ultimate-multisite-content-sync'),
				'default' => false,
			]
		);

		// Logging
		wu_register_settings_field(
			'content_sync',
			'ets_enable_logging',
			[
				'type'    => 'toggle',
				'title'   => __('Enable Detailed Logging', 'ultimate-multisite-content-sync'),
				'desc'    => __('Log content sync operations for debugging.', 'ultimate-multisite-content-sync'),
				'tooltip' => __('When enabled, sync operations will be logged to WP Ultimo logs. Useful for troubleshooting sync issues.', 'ultimate-multisite-content-sync'),
				'default' => false,
			]
		);
	}

	/**
	 * Render the excluded sites selector HTML.
	 *
	 * @since 1.0.0
	 * @return string HTML output for the site selector.
	 */
	public function render_excluded_sites_selector() {

		$excluded_sites = function_exists('wu_get_setting') ? wu_get_setting('ets_excluded_sites', []) : [];
		$all_sites      = function_exists('wu_get_sites') ? wu_get_sites(['number' => 9999]) : [];
		$main_site_id   = function_exists('wu_get_main_site_id') ? wu_get_main_site_id() : 1;

		// Ensure it's an array
		if (! is_array($excluded_sites)) {
			$excluded_sites = [];
		}

		ob_start();
		?>
		<select name="wu_settings[ets_excluded_sites][]" id="ets_excluded_sites" multiple="multiple" style="width: 100%; min-height: 200px;">
			<?php foreach ($all_sites as $site) : ?>
				<?php
				$site_id = $site->get_id();
				// Skip main site
				if ((int) $site_id === $main_site_id) {
					continue;
				}
				?>
				<option value="<?php echo esc_attr($site_id); ?>" <?php selected(in_array($site_id, $excluded_sites, true)); ?>>
					<?php echo esc_html(sprintf('%s (%s)', $site->get_title(), $site->get_domain())); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e('Hold Ctrl (Cmd on Mac) to select multiple sites. Selected sites will be excluded from content syncs.', 'ultimate-multisite-content-sync'); ?>
		</p>
		<?php
		return ob_get_clean();
	}

	/**
	 * Save excluded sites setting.
	 *
	 * @since 1.0.0
	 * @param mixed  $value The value being saved.
	 * @param string $key The setting key.
	 * @param array  $settings All settings being saved.
	 * @return mixed The processed value.
	 */
	public function save_excluded_sites($value, $key, $settings) {

		if ($key !== 'ets_excluded_sites') {
			return $value;
		}

		// Get the value from POST data
		if (isset($_POST['wu_settings']['ets_excluded_sites'])) {
			$excluded_sites = array_map('intval', (array) $_POST['wu_settings']['ets_excluded_sites']);
			return $excluded_sites;
		}

		// If not in POST, return empty array (field was cleared)
		return [];
	}

	/**
	 * Filter excluded sites value to ensure it's always an array.
	 *
	 * @since 1.0.0
	 * @param mixed  $value The setting value.
	 * @param string $key The setting key.
	 * @param mixed  $default The default value.
	 * @param array  $settings All settings.
	 * @return mixed The filtered value.
	 */
	public function filter_excluded_sites_value($value, $key, $default, $settings) {

		if ($key !== 'ets_excluded_sites') {
			return $value;
		}

		// Ensure it's always an array
		if (! is_array($value)) {
			return [];
		}

		// Ensure all values are integers
		return array_map('intval', $value);
	}
}
