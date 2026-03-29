<?php

namespace UniversalCPTMigrator\Admin;

use UniversalCPTMigrator\Application\AdminJobService;
use UniversalCPTMigrator\Application\ImportApplicationService;
use UniversalCPTMigrator\Application\ImportRequestService;
use UniversalCPTMigrator\Domain\Discovery\DiscoveryService;
use UniversalCPTMigrator\Domain\Schema\Analyzer;
use UniversalCPTMigrator\Domain\Schema\SampleGenerator;

class Controller {
	private $discovery;
	private $analyzer;
	private $sample_gen;
	private $import_app;
	private $import_requests;
	private $jobs;
	private $responses;
	private $downloads;
	private $guard;

	public function __construct( array $services = [] ) {
		$this->discovery       = isset( $services['discovery'] ) ? $services['discovery'] : null;
		$this->analyzer        = isset( $services['analyzer'] ) ? $services['analyzer'] : null;
		$this->sample_gen      = isset( $services['sample_gen'] ) ? $services['sample_gen'] : null;
		$this->import_app      = isset( $services['import_app'] ) ? $services['import_app'] : null;
		$this->import_requests = isset( $services['import_requests'] ) ? $services['import_requests'] : null;
		$this->jobs            = isset( $services['jobs'] ) ? $services['jobs'] : null;
		$this->responses       = isset( $services['responses'] ) ? $services['responses'] : null;
		$this->downloads       = isset( $services['downloads'] ) ? $services['downloads'] : null;
		$this->guard           = isset( $services['guard'] ) ? $services['guard'] : null;
	}

	public function init() {
		$this->discovery       = $this->discovery ?: new DiscoveryService();
		$this->analyzer        = $this->analyzer ?: new Analyzer();
		$this->sample_gen      = $this->sample_gen ?: new SampleGenerator();
		$this->import_app      = $this->import_app ?: new ImportApplicationService();
		$this->import_requests = $this->import_requests ?: new ImportRequestService( $this->import_app );
		$this->jobs            = $this->jobs ?: new AdminJobService( null, $this->import_requests );
		$this->responses       = $this->responses ?: new AjaxResponder();
		$this->downloads       = $this->downloads ?: new DownloadResponder();
		$this->guard           = $this->guard ?: new RequestGuard();

		add_action( 'wp_ajax_ucm_get_cpts', [ $this, 'ajax_get_cpts' ] );
		add_action( 'wp_ajax_ucm_analyze_schema', [ $this, 'ajax_analyze_schema' ] );
		add_action( 'wp_ajax_ucm_generate_sample', [ $this, 'ajax_generate_sample' ] );
		add_action( 'wp_ajax_ucm_trigger_export', [ $this, 'ajax_trigger_export' ] );
		add_action( 'wp_ajax_ucm_validate_import', [ $this, 'ajax_validate_import' ] );
		add_action( 'wp_ajax_ucm_run_import', [ $this, 'ajax_run_import' ] );
		add_action( 'wp_ajax_ucm_resume_import', [ $this, 'ajax_resume_import' ] );
		add_action( 'wp_ajax_ucm_get_job_status', [ $this, 'ajax_get_job_status' ] );
		add_action( 'admin_post_ucm_download_export', [ $this, 'handle_export_download' ] );
		add_action( 'admin_post_ucm_export_now', [ $this, 'handle_direct_export' ] );
	}

	public function ajax_get_cpts() {
		$this->authorize_request();
		$this->responses->success( $this->discovery->get_all_cpts_summary() );
	}

	public function ajax_analyze_schema() {
		$this->authorize_request();
		$post_type = $this->require_post_type();
		$schema    = $this->analyzer->analyze_cpt( $post_type );
		$this->responses->success( $schema );
	}

	public function ajax_generate_sample() {
		$this->authorize_request();
		$post_type = $this->require_post_type();
		$schema    = $this->analyzer->analyze_cpt( $post_type );
		$sample    = $this->sample_gen->generate_sample( $post_type, $schema );

		$this->responses->success( $sample );
	}

	public function ajax_trigger_export() {
		$this->authorize_request();
		$post_type = $this->require_post_type();
		$this->responses->success( $this->jobs->queue_export( $post_type ) );
	}

	public function ajax_validate_import() {
		$this->handle_import_request( true );
	}

	public function ajax_run_import() {
		$this->handle_async_import_request( false );
	}

	public function ajax_resume_import() {
		$this->authorize_request();

		$state = $this->jobs->get_resume_state_from_request( $_POST );
		if ( is_wp_error( $state ) ) {
			$this->responses->error_message( $state->get_error_message(), 'ucm_missing_job_id' === $state->get_error_code() ? 400 : 404 );
		}

		$this->responses->success( $state );
	}

	public function ajax_get_job_status() {
		$this->authorize_request();

		$state = $this->jobs->get_job_status_from_request( $_POST );
		if ( is_wp_error( $state ) ) {
			$this->responses->error_message( $state->get_error_message(), 'ucm_missing_job_id' === $state->get_error_code() ? 400 : 404 );
		}

		$this->responses->success( $state );
	}

	public function handle_export_download() {
		$authorized = $this->guard->authorize_capability( 'manage_options', __( 'You do not have permission to download this export.', 'universal-cpt-migrator' ) );
		if ( is_wp_error( $authorized ) ) {
			$this->downloads->error( $authorized->get_error_message(), 403 );
		}

		$download = $this->jobs->resolve_export_download( $_GET );
		if ( is_wp_error( $download ) ) {
			$status = 'ucm_missing_export_job_id' === $download->get_error_code() ? 400 : 404;
			$this->downloads->error( $download->get_error_message(), $status );
		}

		$this->guard->verify_admin_nonce( 'ucm_download_export_' . $download['job_id'] );

		if ( isset( $_GET['ucm_probe'] ) && '1' === (string) wp_unslash( $_GET['ucm_probe'] ) ) {
			$this->downloads->send_probe( $download['state'], $download['job_id'] );
			return;
		}

		$this->downloads->send_zip( $download['state'] );
	}

	public function handle_direct_export() {
		$authorized = $this->guard->authorize_capability( 'manage_options', __( 'You do not have permission to export content.', 'universal-cpt-migrator' ) );
		if ( is_wp_error( $authorized ) ) {
			$this->downloads->error( $authorized->get_error_message(), 403 );
		}

		$this->guard->verify_admin_nonce( 'ucm_export_now' );

		$post_type = $this->guard->resolve_post_type( $_POST );
		if ( is_wp_error( $post_type ) ) {
			$this->downloads->error( $post_type->get_error_message(), 400 );
		}

		$state = $this->jobs->build_export_download( $post_type );
		if ( is_wp_error( $state ) ) {
			$this->downloads->error( $state->get_error_message(), 500 );
		}

		$this->downloads->send_zip( $state );
	}

	private function handle_import_request( $dry_run ) {
		$this->authorize_request();

		$request = $this->import_requests->normalize_synchronous_request( $_POST, $_FILES );
		if ( is_wp_error( $request ) ) {
			$this->responses->error_message( $request->get_error_message(), 400 );
		}

		$result = $this->import_app->run_synchronous_import(
			$request['bundle'],
			$dry_run,
			$request['offset'],
			$request['limit'],
			$request['job_id']
		);

		$this->responses->import_result( $result );
	}

	private function handle_async_import_request( $dry_run ) {
		$this->authorize_request();

		$request = $this->import_requests->normalize_async_request( $_POST, $_FILES );
		if ( is_wp_error( $request ) ) {
			$this->responses->error_message( $request->get_error_message(), 400 );
		}

		if ( ! $request['validation']['is_valid'] ) {
			$this->responses->validation_failure( $request['validation'] );
		}

		$this->responses->success( $this->jobs->queue_import( $request['bundle'], $request['validation'], $dry_run ? 'dry-run' : 'import' ) );
	}

	private function authorize_request() {
		$result = $this->guard->authorize_ajax(
			'ucm_admin_nonce',
			'nonce',
			'manage_options',
			__( 'You do not have permission to perform this action.', 'universal-cpt-migrator' )
		);

		if ( is_wp_error( $result ) ) {
			$this->responses->error_message( $result->get_error_message(), 403 );
		}
	}

	private function require_post_type() {
		$post_type = $this->guard->resolve_post_type( $_POST );
		if ( is_wp_error( $post_type ) ) {
			$this->responses->error_message( $post_type->get_error_message(), 400 );
		}

		return $post_type;
	}
}
