<?php

class UCM_Background_Jobs_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		add_theme_support( 'post-thumbnails' );

		register_post_type(
			'ucm_source_item',
			[
				'public'   => true,
				'label'    => 'UCM Source Item',
				'supports' => [ 'title', 'editor', 'thumbnail' ],
			]
		);

		register_post_type(
			'ucm_target_item',
			[
				'public'   => true,
				'label'    => 'UCM Target Item',
				'supports' => [ 'title', 'editor', 'thumbnail' ],
			]
		);

		$storage = new UniversalCPTMigrator\Infrastructure\Storage();
		$storage->setup_directories();

		$settings = new UniversalCPTMigrator\Infrastructure\Settings();
		update_option(
			UniversalCPTMigrator\Infrastructure\Settings::OPTION_KEY,
			array_merge( $settings->defaults(), [ 'chunk_size' => 1 ] ),
			false
		);
	}

	public function tearDown(): void {
		wp_clear_scheduled_hook( UniversalCPTMigrator\Infrastructure\BackgroundWorker::EXPORT_HOOK );
		wp_clear_scheduled_hook( UniversalCPTMigrator\Infrastructure\BackgroundWorker::IMPORT_HOOK );
		remove_theme_support( 'post-thumbnails' );

		$storage = new UniversalCPTMigrator\Infrastructure\Storage();
		$storage->delete_all();

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ucm_job_%'" );

		unregister_post_type( 'ucm_source_item' );
		unregister_post_type( 'ucm_target_item' );

		parent::tearDown();
	}

	public function test_background_export_creates_zip_bundle_and_resumed_import_completes_from_extracted_bundle() {
		$attachment_id = $this->create_attachment(
			'ucm-e2e-export.gif',
			base64_decode( 'R0lGODdhAQABAIAAAP///////ywAAAAAAQABAAACAkQBADs=' )
		);

		$source_ids = [
			self::factory()->post->create(
				[
					'post_type'    => 'ucm_source_item',
					'post_title'   => 'Source Item One',
					'post_content' => 'Alpha body',
					'post_status'  => 'publish',
				]
			),
			self::factory()->post->create(
				[
					'post_type'    => 'ucm_source_item',
					'post_title'   => 'Source Item Two',
					'post_content' => 'Beta body',
					'post_status'  => 'publish',
				]
			),
		];

		set_post_thumbnail( $source_ids[0], $attachment_id );

		$worker      = new UniversalCPTMigrator\Infrastructure\BackgroundWorker();
		$async_jobs  = new UniversalCPTMigrator\Infrastructure\AsyncJobService();
		$transport   = new UniversalCPTMigrator\Infrastructure\PackageTransport();
		$export_job  = $worker->queue_export( 'ucm_source_item' );

		$worker->process_export_job( $export_job );
		$export_state = $async_jobs->get_job_status( $export_job );

		$this->assertSame( 'completed', $export_state['status'] );
		$this->assertFileExists( $export_state['artifacts']['zip_path'] );

		$bundle = $transport->extract_import_bundle(
			[
				'name'     => 'background-export.zip',
				'tmp_name' => $export_state['artifacts']['zip_path'],
				'error'    => 0,
			]
		);

		$this->assertIsArray( $bundle );
		$this->assertSame( 2, count( $bundle['package']['items'] ) );
		$media_item = null;
		foreach ( $bundle['package']['items'] as $item ) {
			if ( ! empty( $item['featured_media']['manifest']['tmp_path'] ) ) {
				$media_item = $item;
				break;
			}
		}

		$this->assertNotNull( $media_item );
		$this->assertFileExists( $media_item['featured_media']['manifest']['tmp_path'] );

		$bundle['package']['metadata']['post_type'] = 'ucm_target_item';
		foreach ( $bundle['package']['items'] as $index => $item ) {
			$bundle['package']['items'][ $index ]['uuid'] = wp_generate_uuid4();
		}

		$import_response = $async_jobs->queue_import(
			$bundle,
			[
				'is_valid' => true,
				'errors'   => [],
				'warnings' => [],
				'summary'  => [
					'items' => count( $bundle['package']['items'] ),
				],
			],
			'import'
		);

		$job_id = $import_response['job_id'];

		$worker->process_import_job( $job_id );
		$resume_state = $async_jobs->get_resume_state( $job_id );
		$this->assertSame( 'running', $resume_state['status'] );
		$this->assertSame( 1, (int) $resume_state['offset'] );
		$this->assertNotFalse( wp_next_scheduled( UniversalCPTMigrator\Infrastructure\BackgroundWorker::IMPORT_HOOK, [ $job_id ] ) );

		$worker->process_import_job( $job_id );
		$completed_state = $async_jobs->get_job_status( $job_id );

		$this->assertSame( 'completed', $completed_state['status'] );
		$this->assertSame( 2, (int) $completed_state['results']['imported'] );
		$this->assertSame( '', $completed_state['extract_dir'] );
		$this->assertFileDoesNotExist( $bundle['extract_dir'] );

		$imported_posts = get_posts(
			[
				'post_type'      => 'ucm_target_item',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			]
		);

		$this->assertCount( 2, $imported_posts );

		$thumbnail_posts = array_filter(
			$imported_posts,
			static function( $post ) {
				return (bool) get_post_thumbnail_id( $post->ID );
			}
		);

		$this->assertNotEmpty( $thumbnail_posts );
	}

	public function test_background_import_with_missing_media_manifest_completes_without_thumbnail() {
		$async_jobs = new UniversalCPTMigrator\Infrastructure\AsyncJobService();
		$worker     = new UniversalCPTMigrator\Infrastructure\BackgroundWorker();

		$response = $async_jobs->queue_import(
			[
				'package' => [
					'metadata' => [
						'post_type' => 'ucm_target_item',
					],
					'items' => [
						[
							'uuid'           => wp_generate_uuid4(),
							'post_title'     => 'Missing Manifest Item',
							'post_status'    => 'publish',
							'featured_media' => [
								'manifest' => [
									'relative_path' => 'media/missing.gif',
									'tmp_path'      => dirname( __FILE__ ) . '/fixtures/does-not-exist.gif',
								],
								'url' => '',
							],
						],
					],
				],
			],
			[
				'is_valid' => true,
				'errors'   => [],
				'warnings' => [],
				'summary'  => [
					'items' => 1,
				],
			],
			'import'
		);

		$job_id = $response['job_id'];
		$worker->process_import_job( $job_id );

		$state = $async_jobs->get_job_status( $job_id );
		$this->assertSame( 'completed', $state['status'] );
		$this->assertSame( 1, (int) $state['results']['imported'] );
		$this->assertNotEmpty( $state['results']['items'][0]['warnings'] );
		$this->assertSame( 'ucm_media_manifest_missing', $state['results']['items'][0]['warnings'][0]['code'] );

		$imported_posts = get_posts(
			[
				'post_type'      => 'ucm_target_item',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			]
		);

		$this->assertNotEmpty( $imported_posts );
		$this->assertSame( 0, (int) get_post_thumbnail_id( $imported_posts[0]->ID ) );
	}

	public function test_background_import_from_valid_zip_with_corrupted_media_manifest_completes_without_thumbnail() {
		$transport = new UniversalCPTMigrator\Infrastructure\PackageTransport();
		$async_jobs = new UniversalCPTMigrator\Infrastructure\AsyncJobService();
		$worker     = new UniversalCPTMigrator\Infrastructure\BackgroundWorker();

		$zip_path = $this->create_zip_bundle(
			'ucm-corrupted-manifest.zip',
			[
				'package.json' => wp_json_encode(
					[
						'metadata' => [
							'post_type' => 'ucm_target_item',
						],
						'items' => [
							[
								'uuid'           => wp_generate_uuid4(),
								'post_title'     => 'Corrupted Manifest Item',
								'post_status'    => 'publish',
								'featured_media' => [
									'manifest' => [
										'relative_path' => '../media/corrupted.gif',
										'filename'      => 'corrupted.gif',
									],
									'url' => '',
								],
							],
						],
					]
				),
				'media/corrupted.gif' => base64_decode( 'R0lGODdhAQABAIAAAP///////ywAAAAAAQABAAACAkQBADs=' ),
			]
		);

		$bundle = $transport->extract_import_bundle(
			[
				'name'     => 'ucm-corrupted-manifest.zip',
				'tmp_name' => $zip_path,
				'error'    => 0,
			]
		);

		$this->assertIsArray( $bundle );
		$this->assertArrayNotHasKey( 'tmp_path', $bundle['package']['items'][0]['featured_media']['manifest'] );

		$response = $async_jobs->queue_import(
			$bundle,
			[
				'is_valid' => true,
				'errors'   => [],
				'warnings' => [],
				'summary'  => [
					'items' => 1,
				],
			],
			'import'
		);

		$worker->process_import_job( $response['job_id'] );
		$state = $async_jobs->get_job_status( $response['job_id'] );

		$this->assertSame( 'completed', $state['status'] );
		$this->assertNotEmpty( $state['results']['items'][0]['warnings'] );
		$this->assertSame( 'ucm_media_manifest_missing', $state['results']['items'][0]['warnings'][0]['code'] );

		$imported_posts = get_posts(
			[
				'post_type'      => 'ucm_target_item',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			]
		);

		$this->assertNotEmpty( $imported_posts );
		$this->assertSame( 0, (int) get_post_thumbnail_id( $imported_posts[0]->ID ) );
	}

	public function test_background_import_failure_records_failed_stage_and_error_context() {
		$async_jobs = new UniversalCPTMigrator\Infrastructure\AsyncJobService();
		$worker     = new UniversalCPTMigrator\Infrastructure\BackgroundWorker();

		$response = $async_jobs->queue_import(
			[
				'package' => [],
			],
			[
				'is_valid' => true,
				'errors'   => [],
				'warnings' => [],
				'summary'  => [
					'items' => 0,
				],
			],
			'import'
		);

		$worker->process_import_job( $response['job_id'] );
		$state = $async_jobs->get_job_status( $response['job_id'] );

		$this->assertSame( 'failed', $state['status'] );
		$this->assertSame( 'failed', $state['stage'] );
		$this->assertSame( 'bootstrap', $state['failed_stage'] );
		$this->assertSame( 'ucm_missing_package', $state['error_code'] );
	}

	private function create_attachment( $filename, $contents ) {
		$upload = wp_upload_bits( $filename, null, $contents );
		$this->assertEmpty( $upload['error'] );

		return self::factory()->attachment->create_upload_object( $upload['file'] );
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
