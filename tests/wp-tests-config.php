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

define( 'ABSPATH', dirname( __DIR__ ) . '/vendor/johnpbloch/wordpress-core/' );
define( 'WP_CONTENT_DIR', __DIR__ . '/wp-content' );
define( 'DB_DIR', __DIR__ . '/tmp' );
define( 'DB_FILE', 'wordpress-tests.sqlite' );
define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_DEBUG', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_AUTO_UPDATE_CORE', false );
define( 'DB_NAME', 'wordpress-tests' );
define( 'DB_USER', 'wordpress-tests' );
define( 'DB_PASSWORD', 'wordpress-tests' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
define( 'AUTH_KEY', 'ucm-auth-key' );
define( 'SECURE_AUTH_KEY', 'ucm-secure-auth-key' );
define( 'LOGGED_IN_KEY', 'ucm-logged-in-key' );
define( 'NONCE_KEY', 'ucm-nonce-key' );
define( 'AUTH_SALT', 'ucm-auth-salt' );
define( 'SECURE_AUTH_SALT', 'ucm-secure-auth-salt' );
define( 'LOGGED_IN_SALT', 'ucm-logged-in-salt' );
define( 'NONCE_SALT', 'ucm-nonce-salt' );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Universal CPT Migrator Tests' );
define( 'WP_PHP_BINARY', PHP_BINARY );
define( 'FS_METHOD', 'direct' );

$table_prefix = 'wptests_';
