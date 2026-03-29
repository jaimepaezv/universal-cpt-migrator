<?php

class UCM_Test_Redirect_Exception extends Exception {
	private $location;

	public function __construct( $location ) {
		parent::__construct( 'Redirect intercepted for testing.' );
		$this->location = $location;
	}

	public function get_location() {
		return $this->location;
	}
}

class UCM_Diagnostics_Actions_Test extends WP_UnitTestCase {
	private $admin_id;

	public function setUp(): void {
		parent::setUp();
		$this->admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_id );

		$storage = new UniversalCPTMigrator\Infrastructure\Storage();
		$storage->setup_directories();
	}

	public function tearDown(): void {
		wp_clear_scheduled_hook( UniversalCPTMigrator\Infrastructure\BackgroundWorker::CLEANUP_HOOK );
		wp_clear_scheduled_hook( UniversalCPTMigrator\Infrastructure\BackgroundWorker::IMPORT_HOOK );
		wp_clear_scheduled_hook( UniversalCPTMigrator\Infrastructure\BackgroundWorker::EXPORT_HOOK );

		$storage = new UniversalCPTMigrator\Infrastructure\Storage();
		$storage->delete_all();

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ucm_job_%'" );

		$_REQUEST = [];
		parent::tearDown();
	}

	public function test_worker_sanity_action_repairs_missing_worker_events_and_redirects_with_notice() {
		$jobs   = new UniversalCPTMigrator\Infrastructure\JobStore();
		$job_id = $jobs->create(
			[
				'type'   => 'import',
				'status' => 'queued',
				'mode'   => 'import',
				'package' => [
					'metadata' => [ 'post_type' => 'post' ],
					'items'    => [],
				],
			]
		);

		wp_clear_scheduled_hook( UniversalCPTMigrator\Infrastructure\BackgroundWorker::CLEANUP_HOOK );
		wp_clear_scheduled_hook( UniversalCPTMigrator\Infrastructure\BackgroundWorker::IMPORT_HOOK, [ $job_id ] );

		$_REQUEST['_wpnonce'] = wp_create_nonce( 'ucm_run_cron_sanity' );

		$location = $this->capture_redirect(
			static function() {
				$controller = new UniversalCPTMigrator\Admin\DiagnosticsController();
				$controller->handle_worker_sanity();
			}
		);

		$this->assertNotFalse( wp_next_scheduled( UniversalCPTMigrator\Infrastructure\BackgroundWorker::CLEANUP_HOOK ) );
		$this->assertNotFalse( wp_next_scheduled( UniversalCPTMigrator\Infrastructure\BackgroundWorker::IMPORT_HOOK, [ $job_id ] ) );

		$query = [];
		parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $query );
		$this->assertSame( 'worker-checked', $query['ucm_notice'] );
		$this->assertSame( '1', (string) $query['ucm_worker_events'] );
		$this->assertSame( '1', (string) $query['ucm_cleanup_hook'] );
	}

	public function test_stale_job_cleanup_action_removes_stale_job_and_resources() {
		$storage     = new UniversalCPTMigrator\Infrastructure\Storage();
		$jobs        = new UniversalCPTMigrator\Infrastructure\JobStore();
		$extract_dir = $storage->get_path( 'temp/stale-diagnostics-job' );
		wp_mkdir_p( $extract_dir );
		file_put_contents( trailingslashit( $extract_dir ) . 'payload.txt', 'fixture' );
		$storage->put_contents( 'exports/stale-export.zip', 'zip-fixture' );
		$storage->put_contents( 'exports/stale-export.json', '{}' );

		$job_id = $jobs->create(
			[
				'type'       => 'import',
				'status'     => 'queued',
				'extract_dir'=> $extract_dir,
				'artifacts'  => [
					'zip_file'  => 'stale-export.zip',
					'json_file' => 'stale-export.json',
				],
			]
		);

		$state               = $jobs->get( $job_id );
		$state['updated_at'] = gmdate( 'c', time() - ( 2 * HOUR_IN_SECONDS ) );
		update_option( UniversalCPTMigrator\Infrastructure\JobStore::OPTION_PREFIX . sanitize_key( $job_id ), $state, false );

		$_REQUEST['_wpnonce'] = wp_create_nonce( 'ucm_cleanup_stale_jobs' );

		$location = $this->capture_redirect(
			static function() {
				$controller = new UniversalCPTMigrator\Admin\DiagnosticsController();
				$controller->handle_stale_job_cleanup();
			}
		);

		$this->assertSame( [], $jobs->get( $job_id ) );
		$this->assertFileDoesNotExist( $extract_dir );
		$this->assertFileDoesNotExist( $storage->get_path( 'exports/stale-export.zip' ) );
		$this->assertFileDoesNotExist( $storage->get_path( 'exports/stale-export.json' ) );

		$query = [];
		parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $query );
		$this->assertSame( 'stale-cleaned', $query['ucm_notice'] );
		$this->assertSame( '1', (string) $query['ucm_stale_jobs'] );
	}

	public function test_snapshot_reports_failed_job_stage_and_recommendation() {
		$jobs = new UniversalCPTMigrator\Infrastructure\JobStore();
		$job_id = $jobs->create(
			[
				'type'         => 'import',
				'status'       => 'failed',
				'stage'        => 'failed',
				'failed_stage' => 'processing_chunk',
				'error'        => 'Chunk processing failed.',
				'error_code'   => 'ucm_invalid_package',
				'error_context'=> [
					'offset' => 3,
					'mode'   => 'import',
				],
				'failure_category'  => 'import',
				'failure_subsystem' => 'import_chunk_processor',
				'remediation_key'   => 'resume_or_reupload_import',
				'retryable'         => true,
				'log_path'     => 'C:\\logs\\ucm-test.txt',
				'package'      => [
					'metadata' => [ 'post_type' => 'post' ],
					'items'    => [],
				],
				'mode'         => 'import',
			]
		);

		$service  = new UniversalCPTMigrator\Infrastructure\DiagnosticsService();
		$snapshot = $service->get_snapshot();

		$this->assertSame( 1, (int) $snapshot['failed_jobs'] );
		$this->assertNotEmpty( $snapshot['attention_rows'] );
		$this->assertSame( $job_id, $snapshot['attention_rows'][0]['job_id'] );
		$this->assertSame( 'processing_chunk', $snapshot['attention_rows'][0]['stage'] );
		$this->assertSame( 'ucm_invalid_package', $snapshot['attention_rows'][0]['error_code'] );
		$this->assertSame( 'import_chunk_processor', $snapshot['attention_rows'][0]['failure_subsystem'] );
		$this->assertTrue( $snapshot['attention_rows'][0]['retryable'] );
		$this->assertStringContainsString( 'chunk', strtolower( $snapshot['attention_rows'][0]['recommended_action'] ) );
	}

	public function test_snapshot_flags_completed_job_with_media_item_failures() {
		$jobs = new UniversalCPTMigrator\Infrastructure\JobStore();
		$job_id = $jobs->create(
			[
				'type'       => 'import',
				'status'     => 'completed',
				'stage'      => 'completed',
				'results'    => [
					'imported' => 3,
					'updated'  => 0,
					'failed'   => 1,
					'items'    => [
						[
							'status'  => 'failed',
							'message' => 'Media manifest import failed - Error: invalid image.',
							'warnings' => [
								[
									'code'      => 'ucm_media_manifest_invalid_image_content',
									'subsystem' => 'media_manifest_content_validation',
									'message'   => 'Invalid image payload.',
								],
							],
						],
					],
				],
				'log_path'   => 'C:\\logs\\ucm-media.txt',
				'validation' => [
					'summary' => [
						'items' => 4,
					],
				],
			]
		);

		$service  = new UniversalCPTMigrator\Infrastructure\DiagnosticsService();
		$snapshot = $service->get_snapshot();

		$attention = array_values(
			array_filter(
				$snapshot['attention_rows'],
				static function( $row ) use ( $job_id ) {
					return $row['job_id'] === $job_id;
				}
			)
		);

		$this->assertNotEmpty( $attention );
		$this->assertSame( 'media', $attention[0]['failure_category'] );
		$this->assertSame( 'media_manifest_content_validation', $attention[0]['failure_subsystem'] );
		$this->assertSame( 1, (int) $attention[0]['failed_items'] );
		$this->assertSame( 1, (int) $attention[0]['warning_items'] );
		$this->assertStringContainsString( 'media', strtolower( $attention[0]['recommended_action'] ) );
	}

	private function capture_redirect( callable $callback ) {
		$filter = static function( $location ) {
			throw new UCM_Test_Redirect_Exception( $location );
		};

		add_filter( 'wp_redirect', $filter );

		try {
			$callback();
		} catch ( UCM_Test_Redirect_Exception $redirect ) {
			return $redirect->get_location();
		} finally {
			remove_filter( 'wp_redirect', $filter );
		}

		$this->fail( 'Expected redirect was not triggered.' );
	}
}
