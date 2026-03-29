<?php

namespace UniversalCPTMigrator\Admin;

use UniversalCPTMigrator\Infrastructure\DiagnosticsService;

class DiagnosticsPage {
	public function render() {
		$service  = new DiagnosticsService();
		$snapshot = $service->get_snapshot();
		$notice   = isset( $_GET['ucm_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['ucm_notice'] ) ) : '';
		$notice_args = [
			'stale_jobs'    => isset( $_GET['ucm_stale_jobs'] ) ? absint( wp_unslash( $_GET['ucm_stale_jobs'] ) ) : 0,
			'worker_events' => isset( $_GET['ucm_worker_events'] ) ? absint( wp_unslash( $_GET['ucm_worker_events'] ) ) : 0,
			'cleanup_hook'  => isset( $_GET['ucm_cleanup_hook'] ) ? absint( wp_unslash( $_GET['ucm_cleanup_hook'] ) ) : 0,
		];

		include UCM_PATH . 'templates/diagnostics.php';
	}
}
