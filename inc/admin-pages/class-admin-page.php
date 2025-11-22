<?php
/**
 * Base Admin Page Class
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel\Admin_Pages;

/**
 * Base Admin Page Class
 */
abstract class Admin_Page {

	/**
	 * Page slug
	 *
	 * @var string
	 */
	protected $page_slug = '';

	/**
	 * Page title
	 *
	 * @var string
	 */
	protected $page_title = '';

	/**
	 * Menu title
	 *
	 * @var string
	 */
	protected $menu_title = '';

	/**
	 * Capability required
	 *
	 * @var string
	 */
	protected $capability = 'manage_network';

	/**
	 * Parent slug
	 *
	 * @var string
	 */
	protected $parent_slug = 'wp-ultimo';

	/**
	 * Constructor
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup WordPress hooks
	 *
	 * @return void
	 */
	protected function setup_hooks() {
		// Removed: Pages are registered by Reseller_Panel class instead
		// add_action( 'network_admin_menu', array( $this, 'register_admin_page' ) );
		// add_action( 'load-' . $this->get_page_hook(), array( $this, 'handle_form_submission' ) );
	}

	/**
	 * Register admin page
	 *
	 * @return void
	 */
	public function register_admin_page() {
		if ( ! current_user_can( $this->capability ) ) {
			return;
		}

		add_submenu_page(
			$this->parent_slug,
			$this->page_title,
			$this->menu_title,
			$this->capability,
			$this->page_slug,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Get page hook (hook suffix)
	 *
	 * @return string
	 */
	protected function get_page_hook() {
		return 'network_page_' . $this->page_slug;
	}

	/**
	 * Handle form submission
	 *
	 * @return void
	 */
	public function handle_form_submission() {
		// To be implemented by subclasses
	}

	/**
	 * Render page
	 *
	 * @return void
	 */
	abstract public function render_page();

	/**
	 * Render nonce field
	 *
	 * @param string $action Nonce action
	 * @param string $field_name Nonce field name
	 *
	 * @return void
	 */
	protected function render_nonce_field( $action, $field_name = '_wpnonce' ) {
		wp_nonce_field( $action, $field_name );
	}

	/**
	 * Render admin notice
	 *
	 * @param string $message Message to display
	 * @param string $type Notice type (info, success, warning, error)
	 *
	 * @return void
	 */
	protected function render_notice( $message, $type = 'info' ) {
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			wp_kses_post( $message )
		);
	}

	/**
	 * Render section header
	 *
	 * @param string $title Section title
	 * @param string $description Section description
	 *
	 * @return void
	 */
	protected function render_section_header( $title, $description = '' ) {
		?>
		<div class="reseller-panel-section-header">
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( ! empty( $description ) ) : ?>
				<p class="description"><?php echo wp_kses_post( $description ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render card
	 *
	 * @param string $title Card title
	 * @param string $content Card content (HTML)
	 * @param string $class Additional CSS classes
	 *
	 * @return void
	 */
	protected function render_card( $title, $content, $class = '' ) {
		?>
		<div class="reseller-panel-card <?php echo esc_attr( $class ); ?>">
			<h3><?php echo esc_html( $title ); ?></h3>
			<div class="card-content">
				<?php echo wp_kses_post( $content ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render form field
	 *
	 * @param string $name Field name
	 * @param array  $field Field configuration
	 * @param mixed  $value Field value
	 *
	 * @return void
	 */
	protected function render_form_field( $name, $field, $value = '' ) {
		$field_id = sanitize_key( $name );
		$label = isset( $field['label'] ) ? $field['label'] : $name;
		$type = isset( $field['type'] ) ? $field['type'] : 'text';
		$description = isset( $field['description'] ) ? $field['description'] : '';
		$options = isset( $field['options'] ) ? $field['options'] : array();

		?>
		<div class="reseller-panel-form-group">
			<label for="<?php echo esc_attr( $field_id ); ?>">
				<?php echo esc_html( $label ); ?>
			</label>

			<?php if ( 'select' === $type ) : ?>
				<select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>">
					<option value="">-- <?php esc_html_e( 'Select an option', 'ultimate-multisite' ); ?> --</option>
					<?php foreach ( $options as $opt_val => $opt_label ) : ?>
						<option value="<?php echo esc_attr( $opt_val ); ?>" <?php selected( $value, $opt_val ); ?>>
							<?php echo esc_html( $opt_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>

			<?php elseif ( 'textarea' === $type ) : ?>
				<textarea id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>"><?php echo esc_textarea( $value ); ?></textarea>

			<?php else : ?>
				<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
			<?php endif; ?>

			<?php if ( ! empty( $description ) ) : ?>
				<p class="description">
					<?php echo wp_kses_post( $description ); ?>
					<?php if ( isset( $field['link'] ) ) : ?>
						<a href="<?php echo esc_url( $field['link'] ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( isset( $field['link_text'] ) ? $field['link_text'] : $field['link'] ); ?>
						</a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
}