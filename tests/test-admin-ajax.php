<?php

class UCM_Admin_Ajax_Test extends WP_Ajax_UnitTestCase {
	public function set_up() {
		parent::set_up();

		$storage = new UniversalCPTMigrator\Infrastructure\Storage();
		$storage->setup_directories();

		register_post_type(
			'ucm_ajax_target',
			[
				'public'   => true,
				'label'    => 'UCM Ajax Target',
				'supports' => [ 'title', 'editor', 'thumbnail' ],
			]
		);

		$this->_setRole( 'administrator' );
	}

	public function tear_down() {
		wp_clear_scheduled_hook( UniversalCPTMigrator\Infrastructure\BackgroundWorker::EXPORT_HOOK );
		wp_clear_scheduled_hook( UniversalCPTMigrator\Infrastructure\BackgroundWorker::IMPORT_HOOK );

		$storage = new UniversalCPTMigrator\Infrastructure\Storage();
		$storage->delete_all();

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ucm_job_%'" );

		unregister_post_type( 'ucm_ajax_target' );

		parent::tear_down();
	}

	public function test_trigger_export_ajax_queues_job_for_valid_post_type() {
		$_POST['nonce']     = wp_create_nonce( 'ucm_admin_nonce' );
		$_POST['post_type'] = 'post';

		$response = $this->dispatch_ajax_request( 'ucm_trigger_export' );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'queued', $response['data']['status'] );
		$this->assertNotEmpty( $response['data']['job_id'] );

		$jobs = new UniversalCPTMigrator\Infrastructure\JobStore();
		$state = $jobs->get( $response['data']['job_id'] );
		$this->assertSame( 'export', $state['type'] );
		$this->assertSame( 'queued', $state['status'] );
	}

	public function test_trigger_export_ajax_fails_without_nonce() {
		$_POST['post_type'] = 'post';

		try {
			$this->_handleAjax( 'ucm_trigger_export' );
			$this->fail( 'Expected nonce failure did not occur.' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		}
	}

	public function test_trigger_export_ajax_fails_for_insufficient_capability() {
		$this->_setRole( 'subscriber' );
		$_POST['nonce']     = wp_create_nonce( 'ucm_admin_nonce' );
		$_POST['post_type'] = 'post';

		$response = $this->dispatch_ajax_request( 'ucm_trigger_export' );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'permission', strtolower( $response['data']['message'] ) );
	}

	public function test_trigger_export_ajax_fails_for_invalid_post_type() {
		$_POST['nonce']     = wp_create_nonce( 'ucm_admin_nonce' );
		$_POST['post_type'] = 'not_a_real_post_type';

		$response = $this->dispatch_ajax_request( 'ucm_trigger_export' );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Invalid post type', $response['data']['message'] );
	}

	public function test_validate_import_ajax_rejects_malformed_zip_bundle() {
		$zip_path = $this->create_zip_bundle(
			'malformed.zip',
			[
				'readme.txt' => 'missing package payload',
			]
		);

		$_POST['nonce'] = wp_create_nonce( 'ucm_admin_nonce' );
		$_FILES['package'] = [
			'name'     => 'malformed.zip',
			'type'     => 'application/zip',
			'tmp_name' => $zip_path,
			'error'    => 0,
			'size'     => filesize( $zip_path ),
		];

		$response = $this->dispatch_ajax_request( 'ucm_validate_import' );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'package.json', $response['data']['message'] );
	}

	public function test_run_import_ajax_queues_valid_zip_bundle_job() {
		$zip_path = $this->create_zip_bundle(
			'valid.zip',
			[
				'package.json' => wp_json_encode(
					[
						'metadata' => [
							'post_type' => 'ucm_ajax_target',
						],
						'schema' => [
							'post_type'  => 'ucm_ajax_target',
							'taxonomies' => [],
							'acf_groups' => [],
						],
						'items' => [
							[
								'uuid'        => wp_generate_uuid4(),
								'post_title'  => 'Ajax Import Item',
								'post_status' => 'publish',
							],
						],
					]
				),
			]
		);

		$_POST['nonce'] = wp_create_nonce( 'ucm_admin_nonce' );
		$_FILES['package'] = [
			'name'     => 'valid.zip',
			'type'     => 'application/zip',
			'tmp_name' => $zip_path,
			'error'    => 0,
			'size'     => filesize( $zip_path ),
		];

		$response = $this->dispatch_ajax_request( 'ucm_run_import' );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'queued', $response['data']['status'] );
		$this->assertNotEmpty( $response['data']['job_id'] );
		$this->assertNotEmpty( $response['data']['resume_url'] );

		$jobs  = new UniversalCPTMigrator\Infrastructure\JobStore();
		$state = $jobs->get( $response['data']['job_id'] );
		$this->assertSame( 'import', $state['type'] );
		$this->assertSame( 'queued', $state['status'] );
		$this->assertNotEmpty( $state['extract_dir'] );
	}

	public function test_resume_import_ajax_returns_not_found_for_missing_job() {
		$_POST['nonce']  = wp_create_nonce( 'ucm_admin_nonce' );
		$_POST['job_id'] = 'job_missing_resume';

		$response = $this->dispatch_ajax_request( 'ucm_resume_import' );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'could not be resumed', $response['data']['message'] );
	}

	public function test_get_job_status_ajax_returns_bad_request_for_missing_job_id() {
		$_POST['nonce'] = wp_create_nonce( 'ucm_admin_nonce' );

		$response = $this->dispatch_ajax_request( 'ucm_get_job_status' );

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'Missing job ID', $response['data']['message'] );
	}

	private function dispatch_ajax_request( $action ) {
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			return json_decode( $this->_last_response, true );
		} catch ( WPAjaxDieStopException $e ) {
			$this->fail( 'Unexpected AJAX stop: ' . $e->getMessage() );
		}

		$this->fail( 'Expected AJAX response was not captured.' );
	}

	private function create_zip_bundle( $filename, array $entries ) {
		$path = trailingslashit( sys_get_temp_dir() ) . $filename;
		$zip  = new ZipArchive();

		$this->assertTrue( true === $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) );

		foreach ( $entries as $entry_name => $contents ) {
			$zip->addFromString( $entry_name, $contents );
		}

		$zip->close();

		return $path;
	}
}
