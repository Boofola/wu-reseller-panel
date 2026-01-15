<?php
/**
 * Reseller Panel Diagnostic Script
 * 
 * Access this file via:
 * https://myboofola.us/wp-content/plugins/wu-reseller-panel/diagnostic.php
 * 
 * This will help identify the HTTP 500 error cause
 */

// Load WordPress
require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-load.php';

// Check if user is logged in and has proper permissions
if ( ! current_user_can( 'manage_network' ) ) {
	die( 'Insufficient permissions. Please log in as a network administrator.' );
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>Reseller Panel Diagnostic</title>
	<style>
		body { font-family: Arial, sans-serif; margin: 20px; }
		h2 { color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 5px; }
		.success { color: #46b450; }
		.error { color: #dc3232; }
		.warning { color: #f56e28; }
		.info { background: #f0f0f1; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa; }
		pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
		table { border-collapse: collapse; width: 100%; margin: 10px 0; }
		th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
		th { background-color: #0073aa; color: white; }
	</style>
</head>
<body>
	<h1>Reseller Panel Diagnostic Report</h1>
	<p><em>Generated: <?php echo date( 'Y-m-d H:i:s' ); ?></em></p>

	<h2>1. WordPress Environment</h2>
	<table>
		<tr>
			<th>Check</th>
			<th>Status</th>
			<th>Details</th>
		</tr>
		<tr>
			<td>WordPress Version</td>
			<td class="success">✓</td>
			<td><?php echo get_bloginfo( 'version' ); ?></td>
		</tr>
		<tr>
			<td>Multisite Enabled</td>
			<td class="<?php echo is_multisite() ? 'success' : 'error'; ?>">
				<?php echo is_multisite() ? '✓' : '✗'; ?>
			</td>
			<td><?php echo is_multisite() ? 'Yes' : 'No - REQUIRED!'; ?></td>
		</tr>
		<tr>
			<td>PHP Version</td>
			<td class="success">✓</td>
			<td><?php echo phpversion(); ?> (Required: 7.8+)</td>
		</tr>
	<tr>
		<td>Ultimate Multisite Plugin</td>
		<td class="<?php echo reseller_panel_is_ultimo_active() ? 'success' : 'warning'; ?>">
			<?php echo reseller_panel_is_ultimo_active() ? '✓' : '⚠'; ?>
		</td>
		<td><?php 
			if ( reseller_panel_is_ultimo_active() ) {
				$version = defined( 'WP_ULTIMO_VERSION' ) ? WP_ULTIMO_VERSION : 'unknown';
				echo 'Found (v' . esc_html( $version ) . ') - <strong style="color: #46b450;">Active</strong>';
			} else {
				echo 'NOT FOUND - Recommended for full functionality.<br><strong>Download Ultimate Multisite (opensource):</strong> <a href="https://wordpress.org/plugins/ultimate-multisite/" target="_blank" rel="noopener noreferrer">WordPress.org</a>';
			}
		?></td>
	</tr>
</table>	<h2>2. Plugin Files</h2>
	<table>
		<tr>
			<th>File</th>
			<th>Status</th>
		</tr>
		<?php
		$required_files = array(
			'inc/class-reseller-panel.php',
			'inc/class-provider-manager.php',
			'inc/interfaces/class-service-provider-interface.php',
			'inc/abstract/class-base-service-provider.php',
			'inc/providers/class-opensrs-provider.php',
			'inc/providers/class-namecheap-provider.php',
			'inc/admin-pages/class-admin-page.php',
			'inc/admin-pages/class-provider-settings-page.php',
			'inc/admin-pages/class-services-settings-page.php',
		);
		
		$plugin_dir = __DIR__ . '/';
		foreach ( $required_files as $file ) {
			$exists = file_exists( $plugin_dir . $file );
			?>
			<tr>
				<td><?php echo esc_html( $file ); ?></td>
				<td class="<?php echo $exists ? 'success' : 'error'; ?>">
					<?php echo $exists ? '✓ Found' : '✗ MISSING'; ?>
				</td>
			</tr>
			<?php
		}
		?>
	</table>

	<h2>3. Class Loading Test</h2>
	<?php
	// Manually load classes to test
	require_once __DIR__ . '/inc/class-reseller-panel.php';
	?>
	<table>
		<tr>
			<th>Class</th>
			<th>Status</th>
		</tr>
		<?php
		$required_classes = array(
			'Reseller_Panel\Reseller_Panel',
			'Reseller_Panel\Provider_Manager',
			'Reseller_Panel\Abstract_Classes\Base_Service_Provider',
			'Reseller_Panel\Providers\OpenSRS_Provider',
			'Reseller_Panel\Providers\NameCheap_Provider',
			'Reseller_Panel\Admin_Pages\Admin_Page',
			'Reseller_Panel\Admin_Pages\Provider_Settings_Page',
			'Reseller_Panel\Admin_Pages\Services_Settings_Page',
		);
		
		// Test interfaces separately (use interface_exists instead of class_exists)
		$required_interfaces = array(
			'Reseller_Panel\Interfaces\Service_Provider_Interface',
		);
		
		foreach ( $required_classes as $class ) {
			$exists = class_exists( $class );
			?>
			<tr>
				<td><?php echo esc_html( $class ); ?></td>
				<td class="<?php echo $exists ? 'success' : 'error'; ?>">
					<?php echo $exists ? '✓ Loaded' : '✗ NOT LOADED'; ?>
				</td>
			</tr>
			<?php
		}
		
		// Test interfaces
		foreach ( $required_interfaces as $interface ) {
			$exists = interface_exists( $interface );
			?>
			<tr>
				<td><?php echo esc_html( $interface ); ?> <em>(interface)</em></td>
				<td class="<?php echo $exists ? 'success' : 'error'; ?>">
					<?php echo $exists ? '✓ Loaded' : '✗ NOT LOADED'; ?>
				</td>
			</tr>
			<?php
		}
		?>
	</table>

	<h2>4. Database Tables</h2>
	<?php
	global $wpdb;
	$tables_to_check = array(
		'reseller_panel_services',
		'reseller_panel_providers',
		'reseller_panel_fallback_logs',
	);
	?>
	<table>
		<tr>
			<th>Table</th>
			<th>Status</th>
			<th>Rows</th>
		</tr>
		<?php
		foreach ( $tables_to_check as $table ) {
			$full_table_name = $wpdb->prefix . $table;
			$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$full_table_name}'" ) === $full_table_name;
			$row_count = $exists ? $wpdb->get_var( "SELECT COUNT(*) FROM {$full_table_name}" ) : 0;
			?>
			<tr>
				<td><?php echo esc_html( $full_table_name ); ?></td>
				<td class="<?php echo $exists ? 'success' : 'warning'; ?>">
					<?php echo $exists ? '✓ Exists' : '⚠ Not Created'; ?>
				</td>
				<td><?php echo $exists ? $row_count : 'N/A'; ?></td>
			</tr>
			<?php
		}
		?>
	</table>

	<h2>5. Provider Manager Test</h2>
	<?php
	try {
		$provider_manager = \Reseller_Panel\Provider_Manager::get_instance();
		$providers = $provider_manager->get_all_providers();
		?>
		<div class="info success">
			<strong>✓ Provider Manager initialized successfully!</strong><br>
			Registered Providers: <?php echo count( $providers ); ?>
		</div>
		
		<table>
			<tr>
				<th>Provider Key</th>
				<th>Provider Name</th>
				<th>Supported Services</th>
			</tr>
			<?php foreach ( $providers as $key => $provider ) : ?>
				<tr>
					<td><?php echo esc_html( $provider->get_key() ); ?></td>
					<td><?php echo esc_html( $provider->get_name() ); ?></td>
					<td><?php echo esc_html( implode( ', ', $provider->get_supported_services() ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	} catch ( Exception $e ) {
		?>
		<div class="info error">
			<strong>✗ Provider Manager Error:</strong><br>
			<?php echo esc_html( $e->getMessage() ); ?>
			<pre><?php echo esc_html( $e->getTraceAsString() ); ?></pre>
		</div>
		<?php
	}
	?>

	<h2>6. Admin Page Test</h2>
	<?php
	try {
		$provider_page = \Reseller_Panel\Admin_Pages\Provider_Settings_Page::get_instance();
		?>
		<div class="info success">
			<strong>✓ Provider Settings Page initialized successfully!</strong>
		</div>
		<?php
	} catch ( Exception $e ) {
		?>
		<div class="info error">
			<strong>✗ Provider Settings Page Error:</strong><br>
			<?php echo esc_html( $e->getMessage() ); ?>
			<pre><?php echo esc_html( $e->getTraceAsString() ); ?></pre>
		</div>
		<?php
	}
	?>

	<h2>7. Error Log (Last 50 lines)</h2>
	<?php
	$error_log = ini_get( 'error_log' );
	if ( $error_log && file_exists( $error_log ) ) {
		$log_lines = file( $error_log );
		$recent_lines = array_slice( $log_lines, -50 );
		?>
		<pre><?php echo esc_html( implode( '', $recent_lines ) ); ?></pre>
		<?php
	} else {
		?>
		<div class="info warning">
			Error log location: <?php echo esc_html( $error_log ?: 'Not configured' ); ?>
		</div>
		<?php
	}
	?>

	<h2>8. Recommendations</h2>
	<div class="info">
		<?php
		$issues = array();
		$critical = false;
		
		if ( ! is_multisite() ) {
			$issues[] = '<strong class="error">✗ WordPress Multisite is not enabled.</strong> This plugin requires WordPress Multisite.';
			$critical = true;
		}
		
		if ( ! class_exists( 'WP_Ultimo\WP_Ultimo' ) ) {
			$issues[] = '<strong class="warning">⚠ Ultimate Multisite plugin not found.</strong> This plugin is designed to work with Ultimate Multisite v2.0.0+ (opensource, formerly WP Ultimo). You can still use it standalone, but some features may not work optimally. Download Ultimate Multisite: <a href="https://wordpress.org/plugins/ultimate-multisite/" target="_blank">WordPress.org Plugin Directory</a>';
		}
		
		global $wpdb;
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}reseller_panel_services'" ) !== $wpdb->prefix . 'reseller_panel_services' ) {
			$issues[] = '<strong class="error">✗ Database tables not created.</strong> <a href="create-tables.php" style="color: #dc3232; text-decoration: underline;">Click here to create tables manually</a>, or try deactivating and reactivating the plugin.';
			$critical = true;
		}
		
		if ( empty( $issues ) ) {
			echo '<strong class="success">✓ All checks passed!</strong> The plugin should be working correctly.';
		} else {
			echo '<ul>';
			foreach ( $issues as $issue ) {
				echo '<li>' . $issue . '</li>';
			}
			echo '</ul>';
			
			if ( $critical ) {
				echo '<p style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 15px 0;"><strong>⚠ CRITICAL ISSUE DETECTED</strong><br>';
				echo 'The plugin cannot function without database tables. Please run the <a href="create-tables.php"><strong>manual table creation script</strong></a> immediately.</p>';
			}
		}
		?>
	</div>

	<h2>9. Quick Fix</h2>
	<div class="info" style="background: #d1ecf1; border-left: 4px solid #0c5460; padding: 15px;">
		<h3 style="margin-top: 0;">If tables are not created:</h3>
		<ol>
			<li><strong><a href="create-tables.php" style="font-size: 16px; color: #0c5460;">Run the Manual Table Creation Script</a></strong></li>
			<li>Refresh this diagnostic page to verify tables exist</li>
			<li>Delete <code>create-tables.php</code> after tables are created</li>
			<li><a href="<?php echo network_admin_url( 'admin.php?page=reseller-panel' ); ?>">Go to Reseller Panel</a></li>
		</ol>
	</div>

	<p><strong>Links:</strong></p>
	<ul>
		<li><a href="create-tables.php">→ Manual Table Creation Script</a></li>
		<li><a href="<?php echo network_admin_url( 'admin.php?page=reseller-panel' ); ?>">→ Go to Reseller Panel</a></li>
		<li><a href="<?php echo network_admin_url( 'plugins.php' ); ?>">→ Network Plugins</a></li>
	</ul>
</body>
</html>
