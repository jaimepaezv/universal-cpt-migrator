<?php

error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );
set_error_handler(
	static function( $severity, $message, $file = '', $line = 0 ) {
		$normalized = str_replace( '\\', '/', (string) $file );
		if ( in_array( $severity, [ E_DEPRECATED, E_USER_DEPRECATED ], true ) && false !== strpos( $normalized, '/wp-content/wp-sqlite-db/src/db.php' ) ) {
			return true;
		}

		if ( E_USER_NOTICE === $severity && false !== strpos( (string) $message, 'Function wp_is_block_theme was called' ) ) {
			return true;
		}

		return false;
	},
	E_DEPRECATED | E_USER_DEPRECATED | E_USER_NOTICE
);

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__, 2 ) . '/vendor/johnpbloch/wordpress-core/' );
defined( 'WP_CONTENT_DIR' ) || define( 'WP_CONTENT_DIR', dirname( __DIR__ ) . '/wp-content' );
defined( 'DB_DIR' ) || define( 'DB_DIR', dirname( __DIR__ ) . '/tmp' );
$browser_db_name_file = dirname( __DIR__ ) . '/tmp/browser-db-file.txt';
$browser_db_name      = file_exists( $browser_db_name_file ) ? trim( (string) file_get_contents( $browser_db_name_file ) ) : 'wordpress-tests.sqlite';
defined( 'DB_FILE' ) || define( 'DB_FILE', $browser_db_name ? $browser_db_name : 'wordpress-tests.sqlite' );
defined( 'WP_DEFAULT_THEME' ) || define( 'WP_DEFAULT_THEME', 'default' );
defined( 'WP_DEBUG' ) || define( 'WP_DEBUG', true );
defined( 'WP_DEBUG_DISPLAY' ) || define( 'WP_DEBUG_DISPLAY', false );
defined( 'UCM_BROWSER_TEST_ENV' ) || define( 'UCM_BROWSER_TEST_ENV', true );
defined( 'AUTOMATIC_UPDATER_DISABLED' ) || define( 'AUTOMATIC_UPDATER_DISABLED', true );
defined( 'WP_AUTO_UPDATE_CORE' ) || define( 'WP_AUTO_UPDATE_CORE', false );
defined( 'WP_HOME' ) || define( 'WP_HOME', 'http://127.0.0.1:8889' );
defined( 'WP_SITEURL' ) || define( 'WP_SITEURL', 'http://127.0.0.1:8889' );
defined( 'DB_NAME' ) || define( 'DB_NAME', 'wordpress-tests' );
defined( 'DB_USER' ) || define( 'DB_USER', 'wordpress-tests' );
defined( 'DB_PASSWORD' ) || define( 'DB_PASSWORD', 'wordpress-tests' );
defined( 'DB_HOST' ) || define( 'DB_HOST', 'localhost' );
defined( 'DB_CHARSET' ) || define( 'DB_CHARSET', 'utf8' );
defined( 'DB_COLLATE' ) || define( 'DB_COLLATE', '' );
defined( 'AUTH_KEY' ) || define( 'AUTH_KEY', 'ucm-auth-key' );
defined( 'SECURE_AUTH_KEY' ) || define( 'SECURE_AUTH_KEY', 'ucm-secure-auth-key' );
defined( 'LOGGED_IN_KEY' ) || define( 'LOGGED_IN_KEY', 'ucm-logged-in-key' );
defined( 'NONCE_KEY' ) || define( 'NONCE_KEY', 'ucm-nonce-key' );
defined( 'AUTH_SALT' ) || define( 'AUTH_SALT', 'ucm-auth-salt' );
defined( 'SECURE_AUTH_SALT' ) || define( 'SECURE_AUTH_SALT', 'ucm-secure-auth-salt' );
defined( 'LOGGED_IN_SALT' ) || define( 'LOGGED_IN_SALT', 'ucm-logged-in-salt' );
defined( 'NONCE_SALT' ) || define( 'NONCE_SALT', 'ucm-nonce-salt' );
defined( 'FS_METHOD' ) || define( 'FS_METHOD', 'direct' );

$table_prefix = 'wptests_';

require_once ABSPATH . 'wp-settings.php';
