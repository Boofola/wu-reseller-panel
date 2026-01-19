<?php
/**
 * Logger Utility Class
 *
 * @package Reseller_Panel
 */

namespace Reseller_Panel;

/**
 * Logger Class for API errors and debugging
 */
class Logger {

	/**
	 * Log file path
	 *
	 * @var string
	 */
	private static $log_file;

	/**
	 * Initialize logger
	 */
	public static function init() {
		// Set log file path in WordPress uploads directory
		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/reseller-panel-logs';
		
		// Create log directory if it doesn't exist
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
			
			// Add .htaccess to protect log files
			$htaccess_file = $log_dir . '/.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				file_put_contents( $htaccess_file, "deny from all\n" );
			}
			
			// Add index.php to prevent directory listing
			$index_file = $log_dir . '/index.php';
			if ( ! file_exists( $index_file ) ) {
				file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
			}
		}
		
		self::$log_file = $log_dir . '/errors.log';
	}

	/**
	 * Log an error message
	 *
	 * @param string $provider Provider name
	 * @param string $message Error message
	 * @param array  $context Additional context data
	 *
	 * @return void
	 */
	public static function log_error( $provider, $message, $context = array() ) {
		if ( ! self::$log_file ) {
			self::init();
		}

		$timestamp = current_time( 'Y-m-d H:i:s' );
		$log_entry = sprintf(
			"[%s] Provider: %s\nError: %s\n",
			$timestamp,
			$provider,
			$message
		);

		// Add context data if provided
		if ( ! empty( $context ) ) {
			$log_entry .= "Context:\n";
			foreach ( $context as $key => $value ) {
				if ( is_array( $value ) || is_object( $value ) ) {
					$value = print_r( $value, true );
				}
				$log_entry .= sprintf( "  %s: %s\n", $key, $value );
			}
		}

		$log_entry .= str_repeat( '-', 80 ) . "\n";

		// Write to log file
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( self::$log_file, $log_entry, FILE_APPEND );

		// Also log to WordPress debug log if enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf( 'Reseller Panel [%s] - %s', $provider, $message ) );
		}
	}

	/**
	 * Get log file path
	 *
	 * @return string
	 */
	public static function get_log_file_path() {
		if ( ! self::$log_file ) {
			self::init();
		}
		return self::$log_file;
	}

	/**
	 * Clear log file
	 *
	 * @return bool
	 */
	public static function clear_log() {
		if ( ! self::$log_file ) {
			self::init();
		}
		
		if ( file_exists( self::$log_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			return unlink( self::$log_file );
		}
		
		return true;
	}

	/**
	 * Get recent log entries
	 *
	 * @param int $lines Number of lines to retrieve
	 *
	 * @return string
	 */
	public static function get_recent_logs( $lines = 50 ) {
		if ( ! self::$log_file ) {
			self::init();
		}
		
		if ( ! file_exists( self::$log_file ) ) {
			return '';
		}

		// Read last N lines efficiently
		$file = new \SplFileObject( self::$log_file, 'r' );
		
		// Count total lines by seeking to the end
		$file->seek( $file->getSize() );
		$file->rewind();
		
		// Seek to the end to get line count
		$line_count = 0;
		while ( ! $file->eof() ) {
			$file->fgets();
			$line_count++;
		}
		
		$start_line = max( 0, $line_count - $lines );
		
		$log_content = '';
		$file->rewind();
		$file->seek( $start_line );
		while ( ! $file->eof() ) {
			$log_content .= $file->fgets();
		}
		
		return $log_content;
	}
}
