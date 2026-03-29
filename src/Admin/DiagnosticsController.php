<?php

namespace UniversalCPTMigrator\Admin;

use UniversalCPTMigrator\Infrastructure\BackgroundWorker;
use UniversalCPTMigrator\Infrastructure\DiagnosticsService;

class DiagnosticsController {
	public function init() {
		add_action( 'admin_post_ucm_run_cleanup_now', [ $this, 'handle_cleanup_now' ] );
		add_action( 'admin_post_ucm_run_cron_sanity', [ $this, 'handle_worker_sanity' ] );
		add_action( 'admin_post_ucm_cleanup_stale_jobs', [ $this, 'handle_stale_job_cleanup' ] );
	}

	public function handle_cleanup_now() {
		$this->authorize();
		check_admin_referer( 'ucm_run_cleanup_now' );

		$worker = new BackgroundWorker();
		$worker->cleanup_retained_data();

		$this->redirect_with_notice( 'cleanup-run' );
	}

	public function handle_worker_sanity() {
		$this->authorize();
		check_admin_referer( 'ucm_run_cron_sanity' );

		$service = new DiagnosticsService();
		$result  = $service->run_worker_sanity_check();

		$this->redirect_with_notice(
			'worker-checked',
			[
				'ucm_worker_events' => (int) $result['worker_events'],
				'ucm_cleanup_hook'  => ! empty( $result['cleanup_scheduled'] ) ? 1 : 0,
			]
		);
	}

	public function handle_stale_job_cleanup() {
		$this->authorize();
		check_admin_referer( 'ucm_cleanup_stale_jobs' );

		$service = new DiagnosticsService();
		$count   = $service->cleanup_stale_queued_jobs();

		$this->redirect_with_notice(
			'stale-cleaned',
			[
				'ucm_stale_jobs' => (int) $count,
			]
		);
	}

	private function authorize() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run diagnostics actions.', 'universal-cpt-migrator' ), 403 );
		}
	}

	private function redirect_with_notice( $notice, array $args = [] ) {
		$args['page']       = 'u-cpt-migrator-diagnostics';
		$args['ucm_notice'] = $notice;
		wp_safe_redirect( admin_url( 'admin.php?' . http_build_query( $args, '', '&' ) ) );
		exit;
	}
}
