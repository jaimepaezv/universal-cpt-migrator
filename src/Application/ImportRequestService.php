<?php

namespace UniversalCPTMigrator\Application;

class ImportRequestService {
	private $import_app;

	public function __construct( ImportApplicationService $import_app = null ) {
		$this->import_app = $import_app ?: new ImportApplicationService();
	}

	public function normalize_synchronous_request( array $post, array $files ) {
		$bundle = $this->extract_uploaded_bundle( $files );
		if ( is_wp_error( $bundle ) ) {
			return $bundle;
		}

		return [
			'bundle' => $bundle,
			'offset' => isset( $post['offset'] ) ? absint( wp_unslash( $post['offset'] ) ) : 0,
			'limit'  => isset( $post['limit'] ) ? absint( wp_unslash( $post['limit'] ) ) : 0,
			'job_id' => isset( $post['job_id'] ) ? sanitize_text_field( wp_unslash( $post['job_id'] ) ) : '',
		];
	}

	public function normalize_async_request( array $post, array $files ) {
		$bundle = $this->extract_uploaded_bundle( $files );
		if ( is_wp_error( $bundle ) ) {
			return $bundle;
		}

		return [
			'bundle'     => $bundle,
			'validation' => $this->import_app->validate_bundle( $bundle ),
		];
	}

	public function normalize_job_lookup_request( array $post ) {
		$job_id = isset( $post['job_id'] ) ? sanitize_text_field( wp_unslash( $post['job_id'] ) ) : '';
		if ( ! $job_id ) {
			return new \WP_Error( 'ucm_missing_job_id', __( 'Missing job ID.', 'universal-cpt-migrator' ) );
		}

		return [
			'job_id' => $job_id,
		];
	}

	private function extract_uploaded_bundle( array $files ) {
		if ( empty( $files['package'] ) || ! is_array( $files['package'] ) ) {
			return new \WP_Error( 'ucm_missing_upload', __( 'No import package was uploaded.', 'universal-cpt-migrator' ) );
		}

		$file = $files['package'];
		if ( ! empty( $file['error'] ) ) {
			return new \WP_Error( 'ucm_upload_error', __( 'The uploaded package could not be processed.', 'universal-cpt-migrator' ) );
		}

		return $this->import_app->extract_uploaded_bundle( $file );
	}
}
