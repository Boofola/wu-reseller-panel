<?php
/**
 * WordPress Function Stubs for Static Analysis
 * 
 * This file provides type hints for WordPress core functions to prevent
 * false positives in static analysis tools like Intelephense or PHPStan.
 * 
 * DO NOT include this file in your actual plugin - it's only for IDE/analyzer.
 */

// Core WordPress functions
function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {}
function do_action( $hook_name, ...$args ) {}
function apply_filters( $hook_name, $value, ...$args ) { return $value; }
function remove_action( $hook_name, $callback, $priority = 10 ) {}
function remove_filter( $hook_name, $callback, $priority = 10 ) {}

// Admin functions
function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {}
function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {}
function get_current_screen() {}
function admin_url( $path = '', $scheme = 'admin' ) { return ''; }
function network_admin_url( $path = '', $scheme = 'admin' ) { return ''; }

// Enqueue functions
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {}
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {}
function wp_register_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {}
function wp_register_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {}
function wp_localize_script( $handle, $object_name, $l10n ) {}
function wp_dequeue_script( $handle ) {}
function wp_dequeue_style( $handle ) {}

// Security functions
function wp_create_nonce( $action = -1 ) { return ''; }
function wp_verify_nonce( $nonce, $action = -1 ) { return false; }
function check_ajax_referer( $action = -1, $query_arg = '_wpnonce', $die = true ) {}
function current_user_can( $capability, ...$args ) { return false; }
function is_user_logged_in() { return false; }
function wp_get_current_user() {}

// Sanitization functions
function sanitize_text_field( $str ) { return ''; }
function sanitize_email( $email ) { return ''; }
function sanitize_key( $key ) { return ''; }
function sanitize_title( $title, $fallback_title = '', $context = 'save' ) { return ''; }
function esc_html( $text ) { return ''; }
function esc_attr( $text ) { return ''; }
function esc_url( $url, $protocols = null, $_context = 'display' ) { return ''; }
function esc_js( $text ) { return ''; }
function esc_textarea( $text ) { return ''; }
function esc_sql( $data ) { return ''; }

// Translation functions
function __( $text, $domain = 'default' ) { return ''; }
function _e( $text, $domain = 'default' ) {}
function esc_html__( $text, $domain = 'default' ) { return ''; }
function esc_html_e( $text, $domain = 'default' ) {}
function esc_attr__( $text, $domain = 'default' ) { return ''; }
function esc_attr_e( $text, $domain = 'default' ) {}
function _x( $text, $context, $domain = 'default' ) { return ''; }
function _ex( $text, $context, $domain = 'default' ) {}
function esc_html_x( $text, $context, $domain = 'default' ) { return ''; }
function _n( $single, $plural, $number, $domain = 'default' ) { return ''; }

// AJAX functions
function wp_send_json( $response, $status_code = null ) {}
function wp_send_json_success( $data = null, $status_code = null ) {}
function wp_send_json_error( $data = null, $status_code = null ) {}

// Error handling
function is_wp_error( $thing ) { return false; }
function wp_die( $message = '', $title = '', $args = array() ) {}

// Options API
function get_option( $option, $default = false ) { return $default; }
function update_option( $option, $value, $autoload = null ) { return false; }
function delete_option( $option ) { return false; }
function add_option( $option, $value = '', $deprecated = '', $autoload = 'yes' ) { return false; }
function get_site_option( $option, $default = false, $deprecated = true ) { return $default; }
function update_site_option( $option, $value ) { return false; }
function delete_site_option( $option ) { return false; }
function add_site_option( $option, $value ) { return false; }

// Database
function get_blog_option( $blog_id, $option, $default = false ) { return $default; }
function update_blog_option( $blog_id, $option, $value ) { return false; }
function delete_blog_option( $blog_id, $option ) { return false; }

// Multisite functions
function is_multisite() { return false; }
function is_network_admin() { return false; }
function switch_to_blog( $new_blog_id ) {}
function restore_current_blog() {}
function get_current_blog_id() { return 1; }
function get_sites( $args = array() ) { return array(); }
function get_blog_details( $fields = null, $get_all = true ) {}

// Plugin/Theme functions
function plugin_dir_path( $file ) { return ''; }
function plugin_dir_url( $file ) { return ''; }
function plugins_url( $path = '', $plugin = '' ) { return ''; }
function register_activation_hook( $file, $callback ) {}
function register_deactivation_hook( $file, $callback ) {}

// Misc
function wp_parse_args( $args, $defaults = array() ) { return array(); }
function wp_mkdir_p( $target ) { return false; }
function wp_upload_dir( $time = null, $create_dir = true, $refresh_cache = false ) { return array(); }
function home_url( $path = '', $scheme = null ) { return ''; }
function site_url( $path = '', $scheme = null ) { return ''; }
function get_bloginfo( $show = '', $filter = 'raw' ) { return ''; }
function wp_redirect( $location, $status = 302, $x_redirect_by = 'WordPress' ) {}
function wp_safe_redirect( $location, $status = 302, $x_redirect_by = 'WordPress' ) {}

// Constants
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}
if ( ! defined( 'WP_INSTALLING' ) ) {
	define( 'WP_INSTALLING', false );
}
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}