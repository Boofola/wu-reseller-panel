#!/usr/bin/env php
<?php
/**
 * Activation Status Checker
 * 
 * Run this from command line to check activation status:
 * php check-activation.php
 */

// Try to find wp-load.php
$wp_load_paths = array(
	__DIR__ . '/../../../wp-load.php',
	__DIR__ . '/../../../../wp-load.php',
);

$wp_loaded = false;
foreach ( $wp_load_paths as $path ) {
	if ( file_exists( $path ) ) {
		require_once $path;
		$wp_loaded = true;
		break;
	}
}

if ( ! $wp_loaded ) {
	echo "ERROR: Could not find wp-load.php\n";
	echo "Please run this from the plugin directory.\n";
	exit( 1 );
}

echo "===========================================\n";
echo "Reseller Panel - Activation Status Check\n";
echo "===========================================\n\n";

// Check if multisite
echo "1. Multisite: ";
if ( is_multisite() ) {
	echo "✓ YES\n";
} else {
	echo "✗ NO (REQUIRED!)\n";
}

// Check WP Ultimo
echo "2. WP Ultimo: ";
if ( class_exists( 'WP_Ultimo\WP_Ultimo' ) ) {
	echo "✓ Found\n";
} else {
	echo "⚠ Not found (recommended but not required)\n";
}

// Check version option
echo "3. Version Option: ";
$version = get_site_option( 'reseller_panel_version' );
if ( $version ) {
	echo "✓ $version\n";
} else {
	echo "✗ Not set (activation may not have run)\n";
}

// Check tables
echo "\n4. Database Tables:\n";
global $wpdb;
$tables = array(
	'reseller_panel_services',
	'reseller_panel_providers',
	'reseller_panel_fallback_logs',
);

$all_exist = true;
foreach ( $tables as $table ) {
	$full_name = $wpdb->prefix . $table;
	$exists = $wpdb->get_var( "SHOW TABLES LIKE '$full_name'" ) === $full_name;
	echo "   - $table: ";
	if ( $exists ) {
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $full_name" );
		echo "✓ Exists ($count rows)\n";
	} else {
		echo "✗ MISSING\n";
		$all_exist = false;
	}
}

// Summary
echo "\n===========================================\n";
if ( $all_exist ) {
	echo "STATUS: ✓ Plugin is properly activated!\n";
	echo "You can access it at:\n";
	echo network_admin_url( 'admin.php?page=reseller-panel' ) . "\n";
} else {
	echo "STATUS: ✗ Tables are missing!\n";
	echo "\nTO FIX:\n";
	echo "1. Access: https://your-site.com/wp-content/plugins/wu-reseller-panel/create-tables.php\n";
	echo "2. Wait for SUCCESS message\n";
	echo "3. Delete create-tables.php file\n";
	echo "4. Run this script again to verify\n";
}
echo "===========================================\n";
