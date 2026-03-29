<?php

namespace UniversalCPTMigrator\Infrastructure;

class Logger {
	private $log_id;
	private $log_path;

	public function __construct( $log_id = null ) {
		$storage = new Storage();
		$this->log_id = $log_id ?: 'ucm-' . date( 'Ymd-His' );
		$this->log_path = $storage->get_path( 'logs/' . $this->log_id . '.txt' );
	}

	public function log( $message, $level = 'INFO' ) {
		$timestamp = date( '[Y-m-d H:i:s]' );
		$entry     = sprintf( "%s [%s] %s\r\n", $timestamp, strtoupper( $level ), $message );

		if ( $this->should_emit_php_error_log() ) {
			error_log( sprintf( 'Universal CPT Migrator [%s]: %s', $this->log_id, $message ) );
		}

		// Persist to file
		if ( ! file_exists( dirname( $this->log_path ) ) ) {
			wp_mkdir_p( dirname( $this->log_path ) );
		}

		file_put_contents( $this->log_path, $entry, FILE_APPEND | LOCK_EX );
	}

	public function info( $message )    { $this->log( $message, 'INFO' ); }
	public function error( $message )   { $this->log( $message, 'ERROR' ); }
	public function warning( $message ) { $this->log( $message, 'WARNING' ); }

	public function get_log_id()   { return $this->log_id; }
	public function get_log_path() { return $this->log_path; }

	public static function cleanup_retained_logs( $retention_days ) {
		$storage = new Storage();
		return $storage->purge_old_files( 'logs', $retention_days );
	}

	private function should_emit_php_error_log() {
		$default = defined( 'WP_DEBUG' ) && WP_DEBUG && ! $this->is_test_environment();

		if ( function_exists( 'apply_filters' ) ) {
			return (bool) apply_filters( 'ucm_enable_php_error_log', $default, $this->log_id );
		}

		return $default;
	}

	private function is_test_environment() {
		return ( defined( 'WP_RUN_CORE_TESTS' ) && WP_RUN_CORE_TESTS )
			|| ( defined( 'UCM_BROWSER_TEST_ENV' ) && UCM_BROWSER_TEST_ENV )
			|| false !== getenv( 'WP_PHPUNIT__DIR' );
	}
}
