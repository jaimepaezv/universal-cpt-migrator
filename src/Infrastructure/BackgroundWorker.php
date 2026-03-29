<?php

namespace UniversalCPTMigrator\Infrastructure;

use UniversalCPTMigrator\Domain\Export\Exporter;
use UniversalCPTMigrator\Domain\Import\Processor;

class BackgroundWorker {
	const EXPORT_HOOK = 'ucm_process_export_job';
	const IMPORT_HOOK = 'ucm_process_import_job';
	const CLEANUP_HOOK = 'ucm_cleanup_jobs_and_artifacts';

	public function init() {
		add_action( self::EXPORT_HOOK, [ $this, 'process_export_job' ], 10, 1 );
		add_action( self::IMPORT_HOOK, [ $this, 'process_import_job' ], 10, 1 );
		add_action( self::CLEANUP_HOOK, [ $this, 'cleanup_retained_data' ] );

		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	public function queue_export( $post_type ) {
		$jobs   = new JobStore();
		$job_id = $jobs->create(
			[
				'type'   => 'export',
				'status' => 'queued',
				'stage'  => 'queued',
				'post_type' => $post_type,
				'progress' => 0,
			]
		);

		wp_schedule_single_event( time() + 1, self::EXPORT_HOOK, [ $job_id ] );
		return $job_id;
	}

	public function queue_import( array $package, array $validation, $mode = 'import' ) {
		$jobs   = new JobStore();
		$job_id = $jobs->create(
			[
				'type'       => 'import',
				'status'     => 'queued',
				'stage'      => 'queued',
				'package'    => $package,
				'validation' => $validation,
				'mode'       => $mode,
				'offset'     => 0,
				'progress'   => 0,
				'results'    => [
					'imported' => 0,
					'updated'  => 0,
					'failed'   => 0,
					'items'    => [],
				],
			]
		);

		wp_schedule_single_event( time() + 1, self::IMPORT_HOOK, [ $job_id ] );
		return $job_id;
	}

	public function process_export_job( $job_id ) {
		$jobs      = new JobStore();
		$state     = $jobs->get( $job_id );
		$logger    = new Logger();
		$exporter  = new Exporter( $logger );
		$transport = new PackageTransport();

		if ( empty( $state['post_type'] ) ) {
			$this->fail_job( $jobs, $job_id, $state, 'bootstrap', new \WP_Error( 'ucm_missing_post_type', __( 'Export job is missing the source post type.', 'universal-cpt-migrator' ) ) );
			return;
		}

		$state['status']   = 'running';
		$state['stage']    = 'analyzing_source';
		$state['progress'] = 10;
		$jobs->save( $job_id, $state );

		$data = $exporter->build_package( $state['post_type'] );
		if ( is_wp_error( $data ) ) {
			$this->fail_job( $jobs, $job_id, $state, 'build_package', $data );
			return;
		}

		$state['stage']    = 'packaging_bundle';
		$state['progress'] = 80;
		$jobs->save( $job_id, $state );

		$bundle = $transport->create_export_bundle( $data['package'], $state['post_type'] );
		if ( is_wp_error( $bundle ) ) {
			$this->fail_job( $jobs, $job_id, $state, 'package_transport', $bundle );
			return;
		}

		$state['status']   = 'completed';
		$state['stage']    = 'completed';
		$state['progress'] = 100;
		$state['package']  = $data['package'];
		$state['artifacts'] = $bundle;
		$state['download_url'] = add_query_arg(
			[
				'action'   => 'ucm_download_export',
				'job_id'   => $job_id,
				'_wpnonce' => wp_create_nonce( 'ucm_download_export_' . $job_id ),
			],
			admin_url( 'admin-post.php' )
		);
		$state['log_path'] = $logger->get_log_path();
		$jobs->save( $job_id, $state );
	}

	public function process_import_job( $job_id ) {
		$jobs      = new JobStore();
		$state     = $jobs->get( $job_id );
		$logger    = new Logger();
		$processor = new Processor( $logger );
		$settings  = new Settings();
		$transport = new PackageTransport();

		if ( empty( $state['package'] ) || ! is_array( $state['package'] ) ) {
			$this->fail_job( $jobs, $job_id, $state, 'bootstrap', new \WP_Error( 'ucm_missing_package', __( 'Import job is missing the package payload.', 'universal-cpt-migrator' ) ) );
			return;
		}

		$state['status'] = 'running';
		$state['stage']  = 'processing_chunk';
		$jobs->save( $job_id, $state );

		$result = $processor->import_package(
			$state['package'],
			'dry-run' === $state['mode'],
			isset( $state['offset'] ) ? (int) $state['offset'] : 0,
			(int) $settings->get( 'chunk_size' )
		);

		if ( is_wp_error( $result ) ) {
			$this->fail_job(
				$jobs,
				$job_id,
				$state,
				'processing_chunk',
				$result,
				[
					'offset' => isset( $state['offset'] ) ? (int) $state['offset'] : 0,
					'mode'   => isset( $state['mode'] ) ? $state['mode'] : 'import',
				]
			);
			return;
		}

		$state['results']['imported'] += (int) $result['imported'];
		$state['results']['updated']  += (int) $result['updated'];
		$state['results']['failed']   += (int) $result['failed'];
		$state['results']['items']     = array_merge( $state['results']['items'], $result['items'] );
		$state['offset']               = (int) $result['next_offset'];
		$total                         = ! empty( $state['validation']['summary']['items'] ) ? (int) $state['validation']['summary']['items'] : max( 1, count( $state['package']['items'] ) );
		$state['progress']             = min( 100, (int) round( ( $state['offset'] / $total ) * 100 ) );
		$state['log_path']             = $logger->get_log_path();

		if ( ! empty( $result['has_more'] ) ) {
			$jobs->save( $job_id, $state );
			wp_schedule_single_event( time() + 1, self::IMPORT_HOOK, [ $job_id ] );
			return;
		}

		$state['stage']    = 'cleanup_bundle';
		$state['status']   = 'completed';
		$state['progress'] = 100;
		$jobs->save( $job_id, $state );

		if ( ! empty( $state['extract_dir'] ) ) {
			$transport->cleanup_extracted_bundle( $state['extract_dir'] );
			$jobs->update( $job_id, [ 'extract_dir' => '' ] );
		}

		$jobs->update( $job_id, [ 'stage' => 'completed' ] );
	}

	public function cleanup_retained_data() {
		$settings = new Settings();
		$storage  = new Storage();
		$jobs     = new JobStore();
		$transport = new PackageTransport( $storage );

		$storage->purge_old_files( 'exports', (int) $settings->get( 'artifact_retention_days' ) );
		$storage->purge_old_files( 'imports', (int) $settings->get( 'artifact_retention_days' ) );
		$storage->purge_old_directories( 'temp', (int) $settings->get( 'temp_retention_days' ) );
		$storage->purge_old_files( 'logs', (int) $settings->get( 'log_retention_days' ) );

		foreach ( $jobs->get_stale_jobs( (int) $settings->get( 'job_retention_days' ) ) as $stale_job ) {
			$state = $stale_job['state'];

			if ( ! empty( $state['extract_dir'] ) ) {
				$transport->cleanup_extracted_bundle( $state['extract_dir'] );
			}

			if ( ! empty( $state['artifacts']['zip_file'] ) ) {
				$storage->delete_relative( 'exports/' . sanitize_file_name( $state['artifacts']['zip_file'] ) );
			}

			if ( ! empty( $state['artifacts']['json_file'] ) ) {
				$storage->delete_relative( 'exports/' . sanitize_file_name( $state['artifacts']['json_file'] ) );
			}

			delete_option( $stale_job['option_name'] );
		}
	}

	private function fail_job( JobStore $jobs, $job_id, array $state, $stage, \WP_Error $error, array $context = [] ) {
		$error_data = $error->get_error_data();
		$error_data = is_array( $error_data ) ? $error_data : [];
		$forensics  = $this->build_failure_forensics( $state, $stage, $error->get_error_code(), $error_data, $context );

		$state['status']         = 'failed';
		$state['stage']          = 'failed';
		$state['failed_stage']   = sanitize_key( $stage );
		$state['error']          = $error->get_error_message();
		$state['error_code']     = $error->get_error_code();
		$state['error_context']  = $forensics['error_context'];
		$state['error_data']     = $error_data;
		$state['failure_category'] = $forensics['failure_category'];
		$state['failure_subsystem'] = $forensics['failure_subsystem'];
		$state['remediation_key'] = $forensics['remediation_key'];
		$state['retryable']       = $forensics['retryable'];
		$jobs->save( $job_id, $state );
	}

	private function build_failure_forensics( array $state, $stage, $error_code, array $error_data, array $context ) {
		$type      = ! empty( $state['type'] ) ? (string) $state['type'] : 'job';
		$subsystem = ! empty( $error_data['subsystem'] ) ? sanitize_key( (string) $error_data['subsystem'] ) : $this->infer_failure_subsystem( $type, $stage, (string) $error_code );
		$category  = $this->infer_failure_category( $subsystem, $stage, (string) $error_code );
		$merged    = array_merge( $error_data, $context );

		return [
			'failure_category'  => $category,
			'failure_subsystem' => $subsystem,
			'remediation_key'   => $this->infer_remediation_key( $subsystem, $stage, (string) $error_code ),
			'retryable'         => $this->is_retryable_failure( $subsystem, $stage, (string) $error_code ),
			'error_context'     => $merged,
		];
	}

	private function infer_failure_subsystem( $type, $stage, $error_code ) {
		if ( false !== strpos( $error_code, 'media_' ) || 'media' === $stage ) {
			return 'media_pipeline';
		}

		if ( false !== strpos( $error_code, 'zip' ) || false !== strpos( $error_code, 'package' ) ) {
			return 'package_transport';
		}

		if ( 'build_package' === $stage ) {
			return 'export_serializer';
		}

		if ( 'processing_chunk' === $stage ) {
			return 'import_chunk_processor';
		}

		if ( 'bootstrap' === $stage ) {
			return 'job_bootstrap';
		}

		return 'job_runtime';
	}

	private function infer_failure_category( $subsystem, $stage, $error_code ) {
		if ( false !== strpos( $subsystem, 'media' ) ) {
			return 'media';
		}

		if ( false !== strpos( $subsystem, 'transport' ) || false !== strpos( $error_code, 'zip' ) || false !== strpos( $error_code, 'package' ) ) {
			return 'transport';
		}

		if ( false !== strpos( $subsystem, 'export' ) || 'build_package' === $stage ) {
			return 'export';
		}

		if ( false !== strpos( $subsystem, 'import' ) || 'processing_chunk' === $stage ) {
			return 'import';
		}

		return 'state';
	}

	private function infer_remediation_key( $subsystem, $stage, $error_code ) {
		if ( false !== strpos( $subsystem, 'media_manifest' ) ) {
			return 'check_packaged_media_manifest';
		}

		if ( false !== strpos( $subsystem, 'media_remote' ) ) {
			return 'review_remote_media_policy';
		}

		if ( 'package_transport' === $subsystem ) {
			return 'inspect_package_transport';
		}

		if ( 'import_chunk_processor' === $subsystem ) {
			return 'resume_or_reupload_import';
		}

		if ( 'export_serializer' === $subsystem ) {
			return 'review_source_schema_and_media';
		}

		if ( 'job_bootstrap' === $subsystem ) {
			return 'requeue_job_payload';
		}

		return 'review_job_log';
	}

	private function is_retryable_failure( $subsystem, $stage, $error_code ) {
		if ( 'bootstrap' === $stage || false !== strpos( $error_code, 'invalid_uuid' ) ) {
			return false;
		}

		if ( false !== strpos( $subsystem, 'transport' ) || false !== strpos( $subsystem, 'media_remote' ) ) {
			return true;
		}

		if ( 'import_chunk_processor' === $subsystem ) {
			return true;
		}

		return false;
	}
}
