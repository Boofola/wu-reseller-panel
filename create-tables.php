<?php
/**
 * Manual Database Table Creation Script
 * 
 * If the plugin activation doesn't create tables automatically,
 * run this script once by accessing:
 * https://myboofola.us/wp-content/plugins/wu-reseller-panel/create-tables.php
 * 
 * Then delete this file for security.
 */

// Load WordPress
require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-load.php';

// Check permissions
if ( ! current_user_can( 'manage_network' ) ) {
	die( 'ERROR: Insufficient permissions. Please log in as a network administrator.' );
}

global $wpdb;

$charset_collate = $wpdb->get_charset_collate();

echo '<h1>Reseller Panel - Manual Table Creation</h1>';
echo '<p>Creating database tables...</p>';

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

// Require dbDelta function
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

// Create tables
$result1 = dbDelta( $services_sql );
$result2 = dbDelta( $providers_sql );
$result3 = dbDelta( $logs_sql );

echo '<h2>Results:</h2>';
echo '<pre>';
echo "Services table:\n";
print_r( $result1 );
echo "\n\nProviders table:\n";
print_r( $result2 );
echo "\n\nFallback logs table:\n";
print_r( $result3 );
echo '</pre>';

// Verify tables exist
echo '<h2>Verification:</h2>';
echo '<ul>';

$tables_to_check = array(
	$services_table => 'Services',
	$providers_table => 'Providers',
	$logs_table => 'Fallback Logs',
);

foreach ( $tables_to_check as $table => $name ) {
	$exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table;
	if ( $exists ) {
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
		echo "<li style='color: green;'>✓ <strong>$name</strong> table exists ($table) - $count rows</li>";
	} else {
		echo "<li style='color: red;'>✗ <strong>$name</strong> table does NOT exist ($table)</li>";
	}
}

echo '</ul>';

// Set version option
update_site_option( 'reseller_panel_version', '2.0.0' );
echo '<p style="color: green;"><strong>✓ Version option set.</strong></p>';

echo '<hr>';
echo '<p><strong>SUCCESS!</strong> Tables have been created. You can now:</p>';
echo '<ol>';
echo '<li><a href="' . network_admin_url( 'admin.php?page=reseller-panel' ) . '">Go to Reseller Panel</a></li>';
echo '<li><strong>DELETE THIS FILE (create-tables.php) for security</strong></li>';
echo '</ol>';
