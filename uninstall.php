<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'ucm_settings', [] );
if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	delete_option( 'ucm_settings' );
	return;
}

$upload_dir = wp_upload_dir();
$base_path  = trailingslashit( $upload_dir['basedir'] ) . 'u-cpt-mgr';

if ( ! function_exists( 'ucm_delete_dir' ) ) {
	function ucm_delete_dir( $dir ) {
		if ( ! file_exists( $dir ) ) {
			return;
		}

		foreach ( scandir( $dir ) as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $dir . DIRECTORY_SEPARATOR . $item;
			if ( is_dir( $path ) ) {
				ucm_delete_dir( $path );
			} else {
				@unlink( $path );
			}
		}

		@rmdir( $dir );
	}
}

ucm_delete_dir( $base_path );
delete_option( 'ucm_settings' );
