<?php

namespace UniversalCPTMigrator\Infrastructure;

class DiagnosticsService {
	const STALE_QUEUE_THRESHOLD = HOUR_IN_SECONDS;
	const STALE_RUNNING_THRESHOLD = 2 * HOUR_IN_SECONDS;

	private $jobs;
	private $storage;
	private $transport;

	public function __construct( JobStore $jobs = null, Storage $storage = null, PackageTransport $transport = null ) {
		$this->jobs      = $jobs ?: new JobStore();
		$this->storage   = $storage ?: new Storage();
		$this->transport = $transport ?: new PackageTransport( $this->storage );
	}

	public function get_snapshot() {
		$all_jobs           = $this->jobs->get_all_jobs();
		$queued             = [];
		$running            = [];
		$failed             = [];
		$completed          = [];
		$stale_queued       = [];
		$stale_running      = [];
		$unscheduled_queued = [];
		$queued_cutoff      = time() - self::STALE_QUEUE_THRESHOLD;
		$running_cutoff     = time() - self::STALE_RUNNING_THRESHOLD;

		foreach ( $all_jobs as $job ) {
			$state   = isset( $job['state'] ) && is_array( $job['state'] ) ? $job['state'] : [];
			$status  = ! empty( $state['status'] ) ? $state['status'] : '';
			$updated = ! empty( $state['updated_at'] ) ? strtotime( $state['updated_at'] ) : 0;

			if ( 'queued' === $status ) {
				$queued[] = $job;

				if ( $updated && $updated < $queued_cutoff ) {
					$stale_queued[] = $job;
				}

				if ( ! $this->has_worker_event( $job['job_id'], $state ) ) {
					$unscheduled_queued[] = $job;
				}
			}

			if ( 'running' === $status ) {
				$running[] = $job;
				if ( $updated && $updated < $running_cutoff ) {
					$stale_running[] = $job;
				}
			}

			if ( 'failed' === $status ) {
				$failed[] = $job;
			}

			if ( 'completed' === $status ) {
				$completed[] = $job;
			}
		}

		$job_rows = array_map(
			function( $job ) use ( $queued_cutoff, $running_cutoff ) {
				return $this->format_job_row( $job, $queued_cutoff, $running_cutoff );
			},
			$all_jobs
		);

		$attention_rows = array_values(
			array_filter(
				$job_rows,
				static function( $job ) {
					return ! empty( $job['needs_attention'] );
				}
			)
		);

		$snapshot = [
			'cron_disabled'         => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'next_cleanup'          => wp_next_scheduled( BackgroundWorker::CLEANUP_HOOK ),
			'next_export_worker'    => wp_next_scheduled( BackgroundWorker::EXPORT_HOOK ),
			'next_import_worker'    => wp_next_scheduled( BackgroundWorker::IMPORT_HOOK ),
			'total_jobs'            => count( $all_jobs ),
			'queued_jobs'           => count( $queued ),
			'running_jobs'          => count( $running ),
			'failed_jobs'           => count( $failed ),
			'completed_jobs'        => count( $completed ),
			'stale_queued_jobs'     => count( $stale_queued ),
			'stale_running_jobs'    => count( $stale_running ),
			'unscheduled_queued'    => count( $unscheduled_queued ),
			'job_rows'              => array_slice( $job_rows, 0, 20 ),
			'attention_rows'        => array_slice( $attention_rows, 0, 10 ),
			'failure_breakdown'     => $this->build_failure_breakdown( $job_rows ),
		];

		$snapshot['recommendations'] = $this->build_recommendations( $snapshot );

		return $snapshot;
	}

	public function cleanup_stale_queued_jobs() {
		$stale_jobs = $this->jobs->get_stale_queued_jobs( self::STALE_QUEUE_THRESHOLD );
		$deleted    = 0;

		foreach ( $stale_jobs as $job ) {
			$this->cleanup_job_resources( isset( $job['state'] ) && is_array( $job['state'] ) ? $job['state'] : [] );
			$this->jobs->delete( $job['job_id'] );
			$deleted++;
		}

		return $deleted;
	}

	public function run_worker_sanity_check() {
		$repaired = [
			'cleanup_scheduled' => false,
			'worker_events'     => 0,
		];

		if ( ! wp_next_scheduled( BackgroundWorker::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', BackgroundWorker::CLEANUP_HOOK );
			$repaired['cleanup_scheduled'] = true;
		}

		foreach ( $this->jobs->get_all_jobs() as $job ) {
			$state  = isset( $job['state'] ) && is_array( $job['state'] ) ? $job['state'] : [];
			$status = ! empty( $state['status'] ) ? $state['status'] : '';
			if ( 'queued' !== $status || $this->has_worker_event( $job['job_id'], $state ) ) {
				continue;
			}

			$hook = $this->get_worker_hook( $state );
			if ( ! $hook ) {
				continue;
			}

			wp_schedule_single_event( time() + 1, $hook, [ $job['job_id'] ] );
			$repaired['worker_events']++;
		}

		return $repaired;
	}

	private function cleanup_job_resources( array $state ) {
		if ( ! empty( $state['extract_dir'] ) ) {
			$this->transport->cleanup_extracted_bundle( $state['extract_dir'] );
		}

		if ( ! empty( $state['artifacts']['zip_file'] ) ) {
			$this->storage->delete_relative( 'exports/' . sanitize_file_name( $state['artifacts']['zip_file'] ) );
		}

		if ( ! empty( $state['artifacts']['json_file'] ) ) {
			$this->storage->delete_relative( 'exports/' . sanitize_file_name( $state['artifacts']['json_file'] ) );
		}
	}

	private function format_job_row( array $job, $queued_cutoff, $running_cutoff ) {
		$state           = isset( $job['state'] ) && is_array( $job['state'] ) ? $job['state'] : [];
		$job_id          = ! empty( $job['job_id'] ) ? $job['job_id'] : '';
		$type            = ! empty( $state['type'] ) ? $state['type'] : 'job';
		$status          = ! empty( $state['status'] ) ? $state['status'] : 'unknown';
		$stage           = ! empty( $state['failed_stage'] ) ? (string) $state['failed_stage'] : ( ! empty( $state['stage'] ) ? (string) $state['stage'] : '' );
		$updated_at      = ! empty( $state['updated_at'] ) ? (string) $state['updated_at'] : '';
		$updated_ts      = $updated_at ? strtotime( $updated_at ) : 0;
		$progress        = isset( $state['progress'] ) ? (int) $state['progress'] : 0;
		$failed_items    = ! empty( $state['results']['failed'] ) ? (int) $state['results']['failed'] : 0;
		$warning_items   = $this->count_warning_items( $state );
		$missing_worker  = 'queued' === $status && ! $this->has_worker_event( $job_id, $state );
		$stale_queue     = 'queued' === $status && $updated_ts && $updated_ts < $queued_cutoff;
		$stale_running   = 'running' === $status && $updated_ts && $updated_ts < $running_cutoff;
		$completed_with_failures = 'completed' === $status && ( $failed_items > 0 || $warning_items > 0 );
		$needs_attention = 'failed' === $status || $missing_worker || $stale_queue || $stale_running || $completed_with_failures;
		$failure_category = $this->classify_failure_category( $type, $status, $stage, $state, $failed_items, $warning_items );
		$failure_subsystem = ! empty( $state['failure_subsystem'] ) ? (string) $state['failure_subsystem'] : $this->infer_item_warning_subsystem( $state );

		return [
			'job_id'             => $job_id,
			'type'               => $type,
			'status'             => $status,
			'stage'              => $stage,
			'progress'           => $progress,
			'updated_at'         => $updated_at,
			'age_label'          => $this->format_age_label( $updated_ts ),
			'error'              => ! empty( $state['error'] ) ? (string) $state['error'] : '',
			'error_code'         => ! empty( $state['error_code'] ) ? (string) $state['error_code'] : '',
			'error_context'      => ! empty( $state['error_context'] ) && is_array( $state['error_context'] ) ? $state['error_context'] : [],
			'error_data'         => ! empty( $state['error_data'] ) && is_array( $state['error_data'] ) ? $state['error_data'] : [],
			'log_path'           => ! empty( $state['log_path'] ) ? (string) $state['log_path'] : '',
			'log_url'            => $this->build_log_url(
				! empty( $state['log_path'] ) ? (string) $state['log_path'] : '',
				$job_id,
				! empty( $state['failure_subsystem'] ) ? (string) $state['failure_subsystem'] : ''
			),
			'offset'             => isset( $state['offset'] ) ? (int) $state['offset'] : 0,
			'items_total'        => ! empty( $state['validation']['summary']['items'] ) ? (int) $state['validation']['summary']['items'] : 0,
			'failed_items'       => $failed_items,
			'warning_items'      => $warning_items,
			'resume_url'         => ( 'import' === $type && ! empty( $state['package'] ) && ! empty( $state['mode'] ) ) ? admin_url( 'admin.php?page=u-cpt-migrator-import&ucm_job_id=' . rawurlencode( $job_id ) ) : '',
			'download_url'       => ! empty( $state['download_url'] ) ? (string) $state['download_url'] : '',
			'missing_worker'     => $missing_worker,
			'stale_queue'        => $stale_queue,
			'stale_running'      => $stale_running,
			'completed_with_failures' => $completed_with_failures,
			'failure_category'   => $failure_category,
			'failure_subsystem'  => $failure_subsystem,
			'remediation_key'    => ! empty( $state['remediation_key'] ) ? (string) $state['remediation_key'] : '',
			'retryable'          => isset( $state['retryable'] ) ? (bool) $state['retryable'] : false,
			'needs_attention'    => $needs_attention,
			'recommended_action' => $this->build_job_recommendation( $status, $type, $stage, $missing_worker, $stale_queue, $stale_running, $state, $failed_items, $warning_items, $failure_category, $failure_subsystem ),
		];
	}

	private function build_recommendations( array $snapshot ) {
		$items = [];

		if ( ! empty( $snapshot['cron_disabled'] ) ) {
			$items[] = [
				'severity' => 'error',
				'title'    => __( 'WP-Cron is disabled', 'universal-cpt-migrator' ),
				'message'  => __( 'Background imports and exports will stall unless a server cron triggers WordPress regularly. Re-enable scheduling or configure a system cron before relying on long-running jobs.', 'universal-cpt-migrator' ),
			];
		}

		if ( ! empty( $snapshot['unscheduled_queued'] ) ) {
			$items[] = [
				'severity' => 'warning',
				'title'    => __( 'Queued jobs are missing worker events', 'universal-cpt-migrator' ),
				'message'  => __( 'Run the worker sanity check to re-schedule missing background workers, then confirm queued jobs begin progressing again.', 'universal-cpt-migrator' ),
			];
		}

		if ( ! empty( $snapshot['stale_running_jobs'] ) ) {
			$items[] = [
				'severity' => 'warning',
				'title'    => __( 'Running jobs appear stalled', 'universal-cpt-migrator' ),
				'message'  => __( 'Review the job log path below, confirm cron execution, and re-run the migration if the worker stopped mid-run. Stalled running jobs are not auto-retried.', 'universal-cpt-migrator' ),
			];
		}

		if ( ! empty( $snapshot['failed_jobs'] ) ) {
			$items[] = [
				'severity' => 'warning',
				'title'    => __( 'Failed jobs need operator review', 'universal-cpt-migrator' ),
				'message'  => __( 'Use the attention table below to inspect error messages, open the related log file, and decide whether to re-export, re-upload, or resume from the last known import state.', 'universal-cpt-migrator' ),
			];
		}

		if ( empty( $items ) ) {
			$items[] = [
				'severity' => 'success',
				'title'    => __( 'No blocking diagnostics signals detected', 'universal-cpt-migrator' ),
				'message'  => __( 'Background workers, retention cleanup, and recent job activity look healthy from the stored job state.', 'universal-cpt-migrator' ),
			];
		}

		return $items;
	}

	private function build_job_recommendation( $status, $type, $stage, $missing_event, $stale_queue, $stale_running, array $state, $failed_items = 0, $warning_items = 0, $failure_category = '', $failure_subsystem = '' ) {
		if ( $missing_event ) {
			return __( 'Run Worker Sanity Check to schedule a missing worker event for this queued job.', 'universal-cpt-migrator' );
		}

		if ( $stale_queue ) {
			return __( 'Queued too long. Verify cron health, then clear stale queued jobs if this work is no longer expected to run.', 'universal-cpt-migrator' );
		}

		if ( $stale_running ) {
			return __( 'Worker appears stuck. Review the log, confirm cron execution, and restart the migration if the job cannot progress.', 'universal-cpt-migrator' );
		}

		if ( 'completed' === $status && ( $failed_items > 0 || $warning_items > 0 ) ) {
			if ( 'media' === $failure_category ) {
				return __( 'The job completed, but one or more media records failed. Review the log and item results, then verify packaged files, MIME validation, and remote media policy before retrying.', 'universal-cpt-migrator' );
			}

			if ( false !== strpos( $failure_subsystem, 'acf' ) ) {
				return __( 'The job completed with ACF field warnings. Review nested field mappings and item-level warning details before re-running.', 'universal-cpt-migrator' );
			}

			return __( 'The job completed with item-level failures. Review the log and failed item messages before deciding whether to re-run the migration.', 'universal-cpt-migrator' );
		}

		if ( 'failed' === $status ) {
			if ( false !== strpos( $failure_subsystem, 'media_manifest' ) ) {
				return __( 'Import media validation failed against packaged files. Verify bundled filenames, MIME declarations, and image contents before retrying.', 'universal-cpt-migrator' );
			}

			if ( false !== strpos( $failure_subsystem, 'media_remote' ) ) {
				return __( 'Remote media retrieval failed. Review allowlisted hosts, remote media settings, and the original source URL before retrying.', 'universal-cpt-migrator' );
			}

			if ( 'processing_chunk' === $stage && 'import' === $type ) {
				return __( 'Import processing failed while applying a chunk. Review the error code, offset, and log path before resuming or re-running the package.', 'universal-cpt-migrator' );
			}

			if ( 'package_transport' === $stage && 'export' === $type ) {
				return __( 'Export packaging failed while creating the ZIP artifact. Review storage permissions, retention paths, and the related log before retrying.', 'universal-cpt-migrator' );
			}

			if ( 'build_package' === $stage && 'export' === $type ) {
				return __( 'Export failed while analyzing or serializing the source content. Review schema and media access on the source site before retrying.', 'universal-cpt-migrator' );
			}

			if ( 'bootstrap' === $stage ) {
				return __( 'The job state was incomplete before work began. Review the queued job payload and related logs before retrying.', 'universal-cpt-migrator' );
			}

			if ( 'import' === $type && ! empty( $state['package'] ) ) {
				return __( 'Review the log and package validation output, then re-upload or resume the import from the Import screen if appropriate.', 'universal-cpt-migrator' );
			}

			if ( 'export' === $type ) {
				return __( 'Review the log and retry the export after confirming schema and media access on the source site.', 'universal-cpt-migrator' );
			}

			return __( 'Review the related log and retry the job after correcting the underlying issue.', 'universal-cpt-migrator' );
		}

		if ( 'completed' === $status && 'export' === $type && ! empty( $state['download_url'] ) ) {
			return __( 'Download the export artifact or adjust retention settings if you need to keep artifacts longer.', 'universal-cpt-migrator' );
		}

		return __( 'No operator action required.', 'universal-cpt-migrator' );
	}

	private function classify_failure_category( $type, $status, $stage, array $state, $failed_items, $warning_items ) {
		$error_code = ! empty( $state['error_code'] ) ? (string) $state['error_code'] : '';
		$subsystem  = ! empty( $state['failure_subsystem'] ) ? (string) $state['failure_subsystem'] : '';

		if ( 'completed' === $status && ( $failed_items > 0 || $warning_items > 0 ) ) {
			$item_messages = ! empty( $state['results']['items'] ) && is_array( $state['results']['items'] ) ? wp_list_pluck( $state['results']['items'], 'message' ) : [];
			$haystack      = strtolower( implode( ' ', array_filter( array_map( 'strval', $item_messages ) ) ) );
			$warning_subsystems = strtolower( implode( ' ', $this->collect_item_warning_subsystems( $state ) ) );

			if ( false !== strpos( $haystack, 'media' ) || false !== strpos( $haystack, 'thumbnail' ) || false !== strpos( $haystack, 'image' ) || false !== strpos( $warning_subsystems, 'media' ) ) {
				return 'media';
			}

			if ( false !== strpos( $warning_subsystems, 'acf' ) ) {
				return 'acf';
			}

			return 'item_data';
		}

		if ( 'failed' !== $status ) {
			return '';
		}

		if ( false !== strpos( $error_code, 'media' ) || 'media' === $stage || false !== strpos( $subsystem, 'media' ) ) {
			return 'media';
		}

		if ( 'package_transport' === $stage || false !== strpos( $error_code, 'zip' ) || false !== strpos( $error_code, 'package' ) ) {
			return 'transport';
		}

		if ( 'build_package' === $stage || 'export' === $type ) {
			return 'export';
		}

		if ( 'processing_chunk' === $stage || 'import' === $type ) {
			return 'import';
		}

		return 'state';
	}

	private function count_warning_items( array $state ) {
		if ( empty( $state['results']['items'] ) || ! is_array( $state['results']['items'] ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $state['results']['items'] as $item ) {
			if ( ! empty( $item['warnings'] ) && is_array( $item['warnings'] ) ) {
				$count += count( $item['warnings'] );
			}
		}

		return $count;
	}

	private function collect_item_warning_subsystems( array $state ) {
		$subsystems = [];

		if ( empty( $state['results']['items'] ) || ! is_array( $state['results']['items'] ) ) {
			return $subsystems;
		}

		foreach ( $state['results']['items'] as $item ) {
			if ( empty( $item['warnings'] ) || ! is_array( $item['warnings'] ) ) {
				continue;
			}

			foreach ( $item['warnings'] as $warning ) {
				if ( ! empty( $warning['subsystem'] ) ) {
					$subsystems[] = (string) $warning['subsystem'];
				}
			}
		}

		return array_values( array_unique( $subsystems ) );
	}

	private function infer_item_warning_subsystem( array $state ) {
		$subsystems = $this->collect_item_warning_subsystems( $state );
		return ! empty( $subsystems ) ? $subsystems[0] : '';
	}

	private function build_failure_breakdown( array $job_rows ) {
		$breakdown = [];

		foreach ( $job_rows as $row ) {
			if ( empty( $row['failure_category'] ) || empty( $row['needs_attention'] ) ) {
				continue;
			}

			if ( empty( $breakdown[ $row['failure_category'] ] ) ) {
				$breakdown[ $row['failure_category'] ] = 0;
			}

			$breakdown[ $row['failure_category'] ]++;
		}

		arsort( $breakdown );

		return $breakdown;
	}

	private function format_age_label( $timestamp ) {
		if ( ! $timestamp ) {
			return __( 'Unknown', 'universal-cpt-migrator' );
		}

		$delta = max( 0, time() - $timestamp );
		if ( $delta < MINUTE_IN_SECONDS ) {
			return __( 'Just now', 'universal-cpt-migrator' );
		}

		if ( $delta < HOUR_IN_SECONDS ) {
			return sprintf( _n( '%d minute ago', '%d minutes ago', (int) floor( $delta / MINUTE_IN_SECONDS ), 'universal-cpt-migrator' ), (int) floor( $delta / MINUTE_IN_SECONDS ) );
		}

		if ( $delta < DAY_IN_SECONDS ) {
			return sprintf( _n( '%d hour ago', '%d hours ago', (int) floor( $delta / HOUR_IN_SECONDS ), 'universal-cpt-migrator' ), (int) floor( $delta / HOUR_IN_SECONDS ) );
		}

		return sprintf( _n( '%d day ago', '%d days ago', (int) floor( $delta / DAY_IN_SECONDS ), 'universal-cpt-migrator' ), (int) floor( $delta / DAY_IN_SECONDS ) );
	}

	private function build_log_url( $log_path, $job_id = '', $subsystem = '' ) {
		if ( empty( $log_path ) ) {
			return '';
		}

		$log_name  = sanitize_file_name( wp_basename( $log_path ) );
		$logs_root = wp_normalize_path( $this->storage->get_path( 'logs' ) );
		$real_path = wp_normalize_path( (string) $log_path );

		if ( empty( $log_name ) || false === strpos( $real_path, $logs_root ) ) {
			return '';
		}

		$url = admin_url( 'admin.php?page=u-cpt-migrator-logs&log=' . rawurlencode( $log_name ) );

		if ( $job_id ) {
			$url .= '&job_id=' . rawurlencode( $job_id );
		}

		if ( $subsystem ) {
			$url .= '&trace_subsystem=' . rawurlencode( $subsystem );
		}

		return $url;
	}

	private function has_worker_event( $job_id, array $state ) {
		$hook = $this->get_worker_hook( $state );
		if ( ! $hook ) {
			return true;
		}

		return (bool) wp_next_scheduled( $hook, [ $job_id ] );
	}

	private function get_worker_hook( array $state ) {
		if ( empty( $state['type'] ) ) {
			return '';
		}

		if ( 'export' === $state['type'] ) {
			return BackgroundWorker::EXPORT_HOOK;
		}

		if ( 'import' === $state['type'] ) {
			return BackgroundWorker::IMPORT_HOOK;
		}

		return '';
	}
}
