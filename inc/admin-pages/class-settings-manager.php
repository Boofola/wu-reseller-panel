<?php
/**
 * Settings Manager - Handles addon settings registration.
 *
 * @package Reseller_Panel
 * @since 1.0.0
 */

namespace Reseller_Panel\Admin_Pages;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Settings Manager class.
 *
 * Registers and manages addon settings.
 */
class Settings_Manager {

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
	 * Initialize the settings manager.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init() {
		add_action('init', [$this, 'register_settings']);
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
			'reseller_panel',
			[
				'title' => __('Reseller Panel', 'ultimate-multisite'),
				'desc'  => __('Sell and manage domains, SSL certificates, hosting, and email services.', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-globe',
				'order' => 100,
				'addon' => true,
			]
		);

		// General Settings Header
		wu_register_settings_field(
			'reseller_panel',
			'rp_header_general',
			[
				'type'  => 'header',
				'title' => __('General Settings', 'ultimate-multisite'),
				'desc'  => __('Configure general reseller panel options.', 'ultimate-multisite'),
			]
		);

		// Enable Reseller Panel
		wu_register_settings_field(
			'reseller_panel',
			'rp_enable',
			[
				'type'    => 'toggle',
				'title'   => __('Enable Reseller Panel', 'ultimate-multisite'),
				'desc'    => __('Enable or disable the reseller panel functionality.', 'ultimate-multisite'),
				'tooltip' => __('When enabled, you can sell and manage domains, SSL certificates, hosting, and email services through your Ultimate Multisite platform.', 'ultimate-multisite'),
				'default' => true,
			]
		);

		// Default Provider
		wu_register_settings_field(
			'reseller_panel',
			'rp_default_provider',
			[
				'type'    => 'select',
				'title'   => __('Default Provider', 'ultimate-multisite'),
				'desc'    => __('Select the default provider for reseller services.', 'ultimate-multisite'),
				'tooltip' => __('This provider will be used by default for all services unless a specific provider is configured for a service.', 'ultimate-multisite'),
				'options' => [
					'opensrs'    => __('OpenSRS', 'ultimate-multisite'),
					'namecheap'  => __('Namecheap', 'ultimate-multisite'),
				],
				'default' => 'opensrs',
				'require' => ['rp_enable' => true],
			]
		);

		// Domain Management Header
		wu_register_settings_field(
			'reseller_panel',
			'rp_header_domains',
			[
				'type'  => 'header',
				'title' => __('Domain Management', 'ultimate-multisite'),
				'desc'  => __('Configure domain-related settings.', 'ultimate-multisite'),
			]
		);

		// Auto-Renewal
		wu_register_settings_field(
			'reseller_panel',
			'rp_domain_auto_renew',
			[
				'type'    => 'toggle',
				'title'   => __('Enable Auto-Renewal by Default', 'ultimate-multisite'),
				'desc'    => __('Automatically enable auto-renewal for new domain registrations.', 'ultimate-multisite'),
				'tooltip' => __('When enabled, all new domain registrations will have auto-renewal enabled by default. Customers can still disable it for individual domains.', 'ultimate-multisite'),
				'default' => true,
				'require' => ['rp_enable' => true],
			]
		);

		// Domain Privacy
		wu_register_settings_field(
			'reseller_panel',
			'rp_domain_privacy',
			[
				'type'    => 'toggle',
				'title'   => __('Enable WHOIS Privacy by Default', 'ultimate-multisite'),
				'desc'    => __('Automatically enable WHOIS privacy protection for new domain registrations.', 'ultimate-multisite'),
				'tooltip' => __('When enabled, domain registrant information will be hidden from public WHOIS lookups by default.', 'ultimate-multisite'),
				'default' => true,
				'require' => ['rp_enable' => true],
			]
		);

		// DNS Management
		wu_register_settings_field(
			'reseller_panel',
			'rp_enable_dns_management',
			[
				'type'    => 'toggle',
				'title'   => __('Enable DNS Management', 'ultimate-multisite'),
				'desc'    => __('Allow customers to manage DNS records for their domains.', 'ultimate-multisite'),
				'tooltip' => __('When enabled, customers can add, edit, and delete DNS records through the control panel.', 'ultimate-multisite'),
				'default' => true,
				'require' => ['rp_enable' => true],
			]
		);

		// Domain Transfers
		wu_register_settings_field(
			'reseller_panel',
			'reseller_panel_enable_transfers',
			[
				'type'    => 'toggle',
				'title'   => __('Enable Domain Transfers', 'ultimate-multisite'),
				'desc'    => __('Allow customers to transfer domains in and out.', 'ultimate-multisite'),
				'default' => true,
				'require' => ['rp_enable' => true],
			]
		);

		// Renewal Notice Days
		wu_register_settings_field(
			'reseller_panel',
			'reseller_panel_renewal_notice_days',
			[
				'type'    => 'number',
				'title'   => __('Renewal Notice Days', 'ultimate-multisite'),
				'desc'    => __('Send renewal notifications this many days before expiration.', 'ultimate-multisite'),
				'default' => 30,
				'min'     => 1,
				'max'     => 90,
			]
		);

		// Provider Settings Header
		wu_register_settings_field(
			'reseller_panel',
			'rp_header_providers',
			[
				'type'  => 'header',
				'title' => __('Provider Configuration', 'ultimate-multisite'),
				'desc'  => __('Links to configure your service providers.', 'ultimate-multisite'),
			]
		);

		// Provider Configuration Links
		wu_register_settings_field(
			'reseller_panel',
			'rp_provider_links',
			[
				'type'    => 'html',
				'title'   => __('Provider Settings', 'ultimate-multisite'),
				'desc'    => __('Configure your service provider API credentials and settings.', 'ultimate-multisite'),
				'content' => $this->render_provider_links(),
			]
		);

		// Advanced Settings Header
		wu_register_settings_field(
			'reseller_panel',
			'rp_header_advanced',
			[
				'type'  => 'header',
				'title' => __('Advanced Settings', 'ultimate-multisite'),
				'desc'  => __('Advanced configuration options.', 'ultimate-multisite'),
			]
		);

		// Enable Logging
		wu_register_settings_field(
			'reseller_panel',
			'rp_enable_logging',
			[
				'type'    => 'toggle',
				'title'   => __('Enable Detailed Logging', 'ultimate-multisite'),
				'desc'    => __('Log reseller panel operations for debugging.', 'ultimate-multisite'),
				'tooltip' => __('When enabled, reseller panel operations will be logged to WP Ultimo logs. Useful for troubleshooting API issues.', 'ultimate-multisite'),
				'default' => false,
			]
		);

		// Domain Sync Frequency
		wu_register_settings_field(
			'reseller_panel',
			'rp_domain_sync_frequency',
			[
				'type'    => 'select',
				'title'   => __('Domain Status Sync Frequency', 'ultimate-multisite'),
				'desc'    => __('How often to check domain statuses with providers.', 'ultimate-multisite'),
				'tooltip' => __('Determines how frequently domain information is synchronized from your service providers.', 'ultimate-multisite'),
				'options' => [
					'hourly'     => __('Hourly', 'ultimate-multisite'),
					'twicedaily' => __('Twice Daily', 'ultimate-multisite'),
					'daily'      => __('Daily', 'ultimate-multisite'),
					'weekly'     => __('Weekly', 'ultimate-multisite'),
				],
				'default' => 'daily',
				'require' => ['rp_enable' => true],
			]
		);

		// Email Notifications
		wu_register_settings_field(
			'reseller_panel',
			'rp_expiry_notifications',
			[
				'type'    => 'toggle',
				'title'   => __('Send Domain Expiry Notifications', 'ultimate-multisite'),
				'desc'    => __('Send email notifications to customers when domains are about to expire.', 'ultimate-multisite'),
				'tooltip' => __('When enabled, customers will receive email notifications 30 days before their domains expire.', 'ultimate-multisite'),
				'default' => true,
				'require' => ['rp_enable' => true],
			]
		);

		// Expiry Notification Days
		wu_register_settings_field(
			'reseller_panel',
			'rp_expiry_notification_days',
			[
				'type'    => 'text',
				'title'   => __('Expiry Notification Days', 'ultimate-multisite'),
				'desc'    => __('Number of days before expiry to send notifications (default: 30).', 'ultimate-multisite'),
				'tooltip' => __('Customers will receive notifications this many days before their domains expire.', 'ultimate-multisite'),
				'default' => '30',
				'require' => ['rp_enable' => true, 'rp_expiry_notifications' => true],
			]
		);
	}

	/**
	 * Render the provider configuration links HTML.
	 *
	 * @since 1.0.0
	 * @return string HTML output for the provider links.
	 */
	public function render_provider_links() {
		ob_start();
		?>
		<div class="wu-styling">
			<p><?php esc_html_e('Configure your service providers to enable domain registration, SSL certificates, and other reseller services:', 'ultimate-multisite'); ?></p>
			<ul style="list-style: disc; margin-left: 20px;">
				<li>
					<a href="<?php echo esc_url(network_admin_url('admin.php?page=reseller-panel-providers')); ?>">
						<?php esc_html_e('OpenSRS Configuration', 'ultimate-multisite'); ?>
					</a> - 
					<?php esc_html_e('Configure OpenSRS API credentials and settings', 'ultimate-multisite'); ?>
				</li>
				<li>
					<a href="<?php echo esc_url(network_admin_url('admin.php?page=reseller-panel-providers')); ?>">
						<?php esc_html_e('Namecheap Configuration', 'ultimate-multisite'); ?>
					</a> - 
					<?php esc_html_e('Configure Namecheap API credentials and settings', 'ultimate-multisite'); ?>
				</li>
			</ul>
			<p class="description">
				<?php esc_html_e('Visit the Provider Settings page to configure API credentials, test connections, and manage provider-specific options.', 'ultimate-multisite'); ?>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}
}
