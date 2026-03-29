<?php

namespace UniversalCPTMigrator\Admin;

class DownloadResponder {
	public function send_probe( array $state, $job_id ) {
		$zip_path = isset( $state['artifacts']['zip_path'] ) ? (string) $state['artifacts']['zip_path'] : '';
		$zip_file = isset( $state['artifacts']['zip_file'] ) ? (string) $state['artifacts']['zip_file'] : '';

		wp_send_json_success(
			[
				'job_id'        => (string) $job_id,
				'probe'         => true,
				'content_type'  => 'application/zip',
				'filename'      => basename( $zip_file ),
				'filesize'      => $zip_path && file_exists( $zip_path ) ? (int) filesize( $zip_path ) : 0,
			]
		);
	}

	public function send_zip( array $state ) {
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . basename( $state['artifacts']['zip_file'] ) . '"' );
		header( 'Content-Length: ' . filesize( $state['artifacts']['zip_path'] ) );
		readfile( $state['artifacts']['zip_path'] );
		exit;
	}

	public function error( $message, $status = 400 ) {
		wp_die( esc_html( (string) $message ), $status );
	}
}
