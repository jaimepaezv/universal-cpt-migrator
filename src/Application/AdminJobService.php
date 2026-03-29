<?php

namespace UniversalCPTMigrator\Application;

use UniversalCPTMigrator\Domain\Export\Exporter;
use UniversalCPTMigrator\Infrastructure\AsyncJobService;
use UniversalCPTMigrator\Infrastructure\Logger;
use UniversalCPTMigrator\Infrastructure\PackageTransport;

class AdminJobService {
	private $async_jobs;
	private $import_requests;

	public function __construct( AsyncJobService $async_jobs = null, ImportRequestService $import_requests = null ) {
		$this->async_jobs       = $async_jobs ?: new AsyncJobService();
		$this->import_requests  = $import_requests ?: new ImportRequestService();
	}

	public function queue_export( $post_type ) {
		return $this->async_jobs->queue_export( $post_type );
	}

	public function build_export_download( $post_type ) {
		$logger    = new Logger();
		$exporter  = new Exporter( $logger );
		$transport = new PackageTransport();

		$data = $exporter->build_package( $post_type );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$bundle = $transport->create_export_bundle( $data['package'], $post_type );
		if ( is_wp_error( $bundle ) ) {
			return $bundle;
		}

		return [
			'status'    => 'completed',
			'stage'     => 'completed',
			'package'   => $data['package'],
			'artifacts' => $bundle,
			'log_path'  => $logger->get_log_path(),
		];
	}

	public function queue_import( array $bundle, array $validation, $mode ) {
		return $this->async_jobs->queue_import( $bundle, $validation, $mode );
	}

	public function get_resume_state_from_request( array $post ) {
		$request = $this->import_requests->normalize_job_lookup_request( $post );
		if ( is_wp_error( $request ) ) {
			return $request;
		}

		return $this->async_jobs->get_resume_state( $request['job_id'] );
	}

	public function get_job_status_from_request( array $post ) {
		$request = $this->import_requests->normalize_job_lookup_request( $post );
		if ( is_wp_error( $request ) ) {
			return $request;
		}

		return $this->async_jobs->get_job_status( $request['job_id'] );
	}

	public function resolve_export_download( array $query ) {
		$job_id = isset( $query['job_id'] ) ? sanitize_text_field( wp_unslash( $query['job_id'] ) ) : '';
		if ( ! $job_id ) {
			return new \WP_Error( 'ucm_missing_export_job_id', __( 'Missing export job ID.', 'universal-cpt-migrator' ) );
		}

		$state = $this->async_jobs->get_export_download( $job_id );
		if ( is_wp_error( $state ) ) {
			return $state;
		}

		return [
			'job_id' => $job_id,
			'state'  => $state,
		];
	}
}
