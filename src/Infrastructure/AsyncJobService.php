<?php

namespace UniversalCPTMigrator\Infrastructure;

class AsyncJobService {
	private $worker;
	private $jobs;

	public function __construct( BackgroundWorker $worker = null, JobStore $jobs = null ) {
		$this->worker = $worker ?: new BackgroundWorker();
		$this->jobs   = $jobs ?: new JobStore();
	}

	public function queue_export( $post_type ) {
		return [
			'job_id'  => $this->worker->queue_export( $post_type ),
			'status'  => 'queued',
			'message' => __( 'Export job queued.', 'universal-cpt-migrator' ),
		];
	}

	public function queue_import( array $bundle, array $validation, $mode ) {
		$job_id = $this->worker->queue_import( $bundle['package'], $validation, $mode );
		$this->jobs->update(
			$job_id,
			[
				'extract_dir' => ! empty( $bundle['extract_dir'] ) ? $bundle['extract_dir'] : '',
			]
		);

		return [
			'job_id'     => $job_id,
			'status'     => 'queued',
			'validation' => $validation,
			'resume_url' => admin_url( 'admin.php?page=u-cpt-migrator-import&ucm_job_id=' . rawurlencode( $job_id ) ),
		];
	}

	public function get_job_status( $job_id ) {
		$state = $this->jobs->get( $job_id );
		if ( empty( $state ) ) {
			return new \WP_Error( 'ucm_job_not_found', __( 'Job not found.', 'universal-cpt-migrator' ) );
		}

		$state['job_id']     = $job_id;
		$state['resume_url'] = admin_url( 'admin.php?page=u-cpt-migrator-import&ucm_job_id=' . rawurlencode( $job_id ) );

		return $state;
	}

	public function get_resume_state( $job_id ) {
		$state = $this->jobs->get( $job_id );
		if ( empty( $state['package'] ) || empty( $state['mode'] ) ) {
			return new \WP_Error( 'ucm_resume_unavailable', __( 'Import job could not be resumed.', 'universal-cpt-migrator' ) );
		}

		return $state;
	}

	public function get_export_download( $job_id ) {
		$state = $this->jobs->get( $job_id );
		if ( empty( $state['artifacts']['zip_path'] ) || ! file_exists( $state['artifacts']['zip_path'] ) ) {
			return new \WP_Error( 'ucm_export_missing', __( 'Export bundle is no longer available.', 'universal-cpt-migrator' ) );
		}

		return $state;
	}
}
