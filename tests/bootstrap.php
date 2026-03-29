<?php

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
error_reporting( E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED );

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );
}

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! file_exists( __DIR__ . '/tmp' ) ) {
	mkdir( __DIR__ . '/tmp', 0777, true );
}

$db_file = __DIR__ . '/tmp/wordpress-tests.sqlite';
if ( file_exists( $db_file ) ) {
	unlink( $db_file );
}
touch( $db_file );

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function() {
		if ( ! defined( 'UCM_VERSION' ) ) {
			require dirname( __DIR__ ) . '/universal-cpt-migrator.php';
		}

		add_action(
			'init',
			static function() {
				remove_action( 'wp_version_check', 'wp_version_check' );
				remove_action( 'wp_update_plugins', 'wp_update_plugins' );
				remove_action( 'wp_update_themes', 'wp_update_themes' );
				remove_action( 'delete_expired_transients', 'delete_expired_transients' );
				remove_action( 'admin_init', '_maybe_update_core' );
				remove_action( 'admin_init', '_maybe_update_plugins' );
				remove_action( 'admin_init', '_maybe_update_themes' );
			},
			0
		);
	}
);

require $_tests_dir . '/includes/bootstrap.php';
