<?php
/**
 * Plugin Name: Universal CPT Migrator
 * Plugin URI:  https://github.com/universal-cpt-migrator
 * Description: Enterprise-grade tool to discover, analyze, export, import, and generate sample import payloads for any CPT.
 * Version:     1.0.0
 * Author:      Principal WordPress Architect
 * Text Domain: universal-cpt-migrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Constants
defined( 'UCM_VERSION' ) || define( 'UCM_VERSION', '1.0.0' );
defined( 'UCM_PATH' ) || define( 'UCM_PATH', plugin_dir_path( __FILE__ ) );
defined( 'UCM_URL' ) || define( 'UCM_URL', plugin_dir_url( __FILE__ ) );
defined( 'UCM_BASENAME' ) || define( 'UCM_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Basic PSR-4 Autoloader
 */
spl_autoload_register( function ( $class ) {
	$prefix = 'UniversalCPTMigrator\\';
	$base_dir = UCM_PATH . 'src/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, $len );
	$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Initialize the Plugin
 */
add_action( 'plugins_loaded', function() {
	$plugin = UniversalCPTMigrator\Plugin::get_instance();
	$plugin->init();
} );

/**
 * Activation/Deactivation
 */
register_activation_hook( __FILE__, [ 'UniversalCPTMigrator\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'UniversalCPTMigrator\Plugin', 'deactivate' ] );
