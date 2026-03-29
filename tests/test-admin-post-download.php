<?php

class UCM_Admin_Post_Download_Test extends WP_UnitTestCase {
	private $controller;
	private $storage;
	private $download_responder;

	public function setUp(): void {
		parent::setUp();

		$this->storage = new UniversalCPTMigrator\Infrastructure\Storage();
		$this->storage->setup_directories();
		$this->download_responder = new UCM_Test_Download_Responder();
		$this->controller = new UniversalCPTMigrator\Admin\Controller(
			[
				'downloads' => $this->download_responder,
			]
		);
		$this->controller->init();
	}

	public function tearDown(): void {
		$this->storage->delete_all();

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ucm_job_%'" );

		$_GET = [];
		parent::tearDown();
	}

	public function test_export_download_denies_unauthorized_user() {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$_GET['job_id'] = 'job_anything';

		$exception = $this->capture_wp_die(
			function() {
				$this->controller->handle_export_download();
			}
		);

		$this->assertSame( 403, $exception->getCode() );
		$this->assertStringContainsString( 'permission', strtolower( $exception->getMessage() ) );
	}

	public function test_export_download_fails_with_invalid_nonce() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$job_id = $this->create_export_job_with_artifact();
		$_GET['job_id'] = $job_id;
		$_REQUEST['_wpnonce'] = 'invalid-nonce';

		$exception = $this->capture_wp_die(
			function() {
				$this->controller->handle_export_download();
			}
		);

		$this->assertSame( 403, $exception->getCode() );
		$this->assertStringContainsString( 'link you followed has expired', strtolower( $exception->getMessage() ) );
	}

	public function test_export_download_fails_for_missing_artifact() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$jobs   = new UniversalCPTMigrator\Infrastructure\JobStore();
		$job_id = $jobs->create(
			[
				'type'      => 'export',
				'status'    => 'completed',
				'artifacts' => [
					'zip_file' => 'missing.zip',
					'zip_path' => $this->storage->get_path( 'exports/missing.zip' ),
				],
			]
		);

		$_GET['job_id'] = $job_id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'ucm_download_export_' . $job_id );

		$exception = $this->capture_wp_die(
			function() {
				$this->controller->handle_export_download();
			}
		);

		$this->assertSame( 404, $exception->getCode() );
		$this->assertStringContainsString( 'no longer available', strtolower( $exception->getMessage() ) );
	}

	public function test_export_download_success_uses_streaming_responder_without_exiting() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$job_id = $this->create_export_job_with_artifact();
		$_GET['job_id'] = $job_id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'ucm_download_export_' . $job_id );

		$this->controller->handle_export_download();

		$this->assertNull( $this->download_responder->error_payload );
		$this->assertNotNull( $this->download_responder->captured_state );
		$this->assertSame( 'test-download.zip', $this->download_responder->captured_state['artifacts']['zip_file'] );
		$this->assertFileExists( $this->download_responder->captured_state['artifacts']['zip_path'] );
	}

	public function test_export_download_probe_returns_metadata_without_streaming() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$job_id = $this->create_export_job_with_artifact();
		$_GET['job_id'] = $job_id;
		$_GET['ucm_probe'] = '1';
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'ucm_download_export_' . $job_id );

		$this->controller->handle_export_download();

		$this->assertNull( $this->download_responder->captured_state );
		$this->assertNotNull( $this->download_responder->probe_payload );
		$this->assertSame( $job_id, $this->download_responder->probe_payload['job_id'] );
		$this->assertSame( 'test-download.zip', $this->download_responder->probe_payload['filename'] );
		$this->assertGreaterThan( 0, (int) $this->download_responder->probe_payload['filesize'] );
	}

	public function test_direct_export_builds_and_streams_zip_without_queueing() {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		self::factory()->post->create(
			[
				'post_type'   => 'post',
				'post_title'  => 'Direct export post',
				'post_status' => 'publish',
			]
		);

		$_POST['post_type'] = 'post';
		$_POST['_wpnonce']  = wp_create_nonce( 'ucm_export_now' );
		$_REQUEST['post_type'] = 'post';
		$_REQUEST['_wpnonce']  = $_POST['_wpnonce'];

		$this->controller->handle_direct_export();

		$this->assertNull( $this->download_responder->error_payload );
		$this->assertNotNull( $this->download_responder->captured_state );
		$this->assertSame( 'completed', $this->download_responder->captured_state['status'] );
		$this->assertSame( 'post', $this->download_responder->captured_state['package']['metadata']['post_type'] );
		$this->assertFileExists( $this->download_responder->captured_state['artifacts']['zip_path'] );
	}

	private function create_export_job_with_artifact() {
		$this->storage->put_contents( 'exports/test-download.zip', 'zip-content' );

		$jobs = new UniversalCPTMigrator\Infrastructure\JobStore();

		return $jobs->create(
			[
				'type'      => 'export',
				'status'    => 'completed',
				'artifacts' => [
					'zip_file' => 'test-download.zip',
					'zip_path' => $this->storage->get_path( 'exports/test-download.zip' ),
				],
			]
		);
	}

	private function capture_wp_die( callable $callback ) {
		try {
			$callback();
		} catch ( WPDieException $exception ) {
			return $exception;
		}

		$this->fail( 'Expected wp_die() was not triggered.' );
	}
}

class UCM_Test_Download_Responder extends UniversalCPTMigrator\Admin\DownloadResponder {
	public $captured_state = null;
	public $error_payload  = null;
	public $probe_payload  = null;

	public function send_probe( array $state, $job_id ) {
		$this->probe_payload = [
			'job_id'       => $job_id,
			'filename'     => basename( $state['artifacts']['zip_file'] ),
			'filesize'     => filesize( $state['artifacts']['zip_path'] ),
			'content_type' => 'application/zip',
		];
	}

	public function send_zip( array $state ) {
		$this->captured_state = $state;
	}

	public function error( $message, $status = 400 ) {
		$this->error_payload = [
			'message' => $message,
			'status'  => $status,
		];

		parent::error( $message, $status );
	}
}
