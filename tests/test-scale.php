<?php

class UCM_Scale_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		add_theme_support( 'post-thumbnails' );

		register_post_type(
			'ucm_scale_source',
			[
				'public'   => true,
				'label'    => 'UCM Scale Source',
				'supports' => [ 'title', 'editor', 'thumbnail' ],
			]
		);

		register_post_type(
			'ucm_scale_target',
			[
				'public'   => true,
				'label'    => 'UCM Scale Target',
				'supports' => [ 'title', 'editor', 'thumbnail' ],
			]
		);

		$storage = new UniversalCPTMigrator\Infrastructure\Storage();
		$storage->setup_directories();
		$this->delete_posts_for_type( 'ucm_scale_source' );
		$this->delete_posts_for_type( 'ucm_scale_target' );

		$settings = new UniversalCPTMigrator\Infrastructure\Settings();
		update_option(
			UniversalCPTMigrator\Infrastructure\Settings::OPTION_KEY,
			array_merge( $settings->defaults(), [ 'chunk_size' => 25 ] ),
			false
		);
	}

	public function tearDown(): void {
		wp_clear_scheduled_hook( UniversalCPTMigrator\Infrastructure\BackgroundWorker::EXPORT_HOOK );
		wp_clear_scheduled_hook( UniversalCPTMigrator\Infrastructure\BackgroundWorker::IMPORT_HOOK );
		remove_theme_support( 'post-thumbnails' );

		$this->delete_posts_for_type( 'ucm_scale_source' );
		$this->delete_posts_for_type( 'ucm_scale_target' );

		$storage = new UniversalCPTMigrator\Infrastructure\Storage();
		$storage->delete_all();

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ucm_job_%'" );

		unregister_post_type( 'ucm_scale_source' );
		unregister_post_type( 'ucm_scale_target' );

		parent::tearDown();
	}

	public function test_scale_export_and_chunked_import_handle_high_volume_media_heavy_dataset() {
		$total_posts   = 120;
		$media_stride  = 4;
		$attachment_id = $this->create_attachment(
			'ucm-scale-image.gif',
			base64_decode( 'R0lGODdhAQABAIAAAP///////ywAAAAAAQABAAACAkQBADs=' )
		);

		for ( $i = 1; $i <= $total_posts; $i++ ) {
			$post_id = self::factory()->post->create(
				[
					'post_type'    => 'ucm_scale_source',
					'post_title'   => 'Scale Source ' . $i,
					'post_content' => str_repeat( 'Payload ' . $i . ' ', 10 ),
					'post_status'  => 'publish',
				]
			);

			if ( 0 === $i % $media_stride ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}

		$worker     = new UniversalCPTMigrator\Infrastructure\BackgroundWorker();
		$async_jobs = new UniversalCPTMigrator\Infrastructure\AsyncJobService();
		$transport  = new UniversalCPTMigrator\Infrastructure\PackageTransport();

		$start      = microtime( true );
		$export_job = $worker->queue_export( 'ucm_scale_source' );
		$worker->process_export_job( $export_job );
		$export_elapsed = microtime( true ) - $start;

		$export_state = $async_jobs->get_job_status( $export_job );
		$this->assertSame( 'completed', $export_state['status'] );
		$this->assertSame( $total_posts, (int) $export_state['package']['metadata']['item_count'] );
		$this->assertSame( 5, (int) $export_state['package']['metadata']['total_chunks'] );
		$this->assertFileExists( $export_state['artifacts']['zip_path'] );

		$bundle = $transport->extract_import_bundle(
			[
				'name'     => 'ucm-scale-export.zip',
				'tmp_name' => $export_state['artifacts']['zip_path'],
				'error'    => 0,
			]
		);

		$this->assertIsArray( $bundle );
		$this->assertCount( $total_posts, $bundle['package']['items'] );

		$media_count = 0;
		foreach ( $bundle['package']['items'] as $item ) {
			if ( ! empty( $item['featured_media']['manifest']['tmp_path'] ) ) {
				$media_count++;
			}
		}

		$this->assertGreaterThanOrEqual( (int) floor( $total_posts / $media_stride ), $media_count );

		$bundle['package']['metadata']['post_type'] = 'ucm_scale_target';
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
					'items'     => $total_posts,
					'post_type' => 'ucm_scale_target',
				],
			],
			'import'
		);

		$job_id           = $import_response['job_id'];
		$import_iterations = 0;
		$import_start     = microtime( true );

		do {
			$worker->process_import_job( $job_id );
			$state = $async_jobs->get_job_status( $job_id );
			$import_iterations++;
		} while ( 'completed' !== $state['status'] && $import_iterations < 10 );

		$import_elapsed = microtime( true ) - $import_start;

		$this->assertSame( 'completed', $state['status'] );
		$this->assertSame( $total_posts, (int) $state['results']['imported'] );
		$this->assertGreaterThan( 1, $import_iterations );

		$imported_posts = get_posts(
			[
				'post_type'      => 'ucm_scale_target',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		$this->assertCount( $total_posts, $imported_posts );

		$thumbnail_count = 0;
		foreach ( $imported_posts as $post_id ) {
			if ( get_post_thumbnail_id( $post_id ) ) {
				$thumbnail_count++;
			}
		}

		$this->assertGreaterThanOrEqual( (int) floor( $total_posts / $media_stride ), $thumbnail_count );

		$this->assertLessThan( 30, $export_elapsed + $import_elapsed );
	}

	public function test_scale_import_recovers_after_missing_worker_event_and_completes_large_media_dataset() {
		update_option(
			UniversalCPTMigrator\Infrastructure\Settings::OPTION_KEY,
			array_merge(
				( new UniversalCPTMigrator\Infrastructure\Settings() )->defaults(),
				[ 'chunk_size' => 15 ]
			),
			false
		);

		$total_posts  = 180;
		$media_stride = 3;
		$attachment_id = $this->create_attachment(
			'ucm-scale-image-2.gif',
			base64_decode( 'R0lGODdhAQABAIAAAP///////ywAAAAAAQABAAACAkQBADs=' )
		);

		for ( $i = 1; $i <= $total_posts; $i++ ) {
			$post_id = self::factory()->post->create(
				[
					'post_type'    => 'ucm_scale_source',
					'post_title'   => 'Retry Source ' . $i,
					'post_content' => str_repeat( 'Retry payload ' . $i . ' ', 8 ),
					'post_status'  => 'publish',
				]
			);

			if ( 0 === $i % $media_stride ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}

		$worker      = new UniversalCPTMigrator\Infrastructure\BackgroundWorker();
		$async_jobs  = new UniversalCPTMigrator\Infrastructure\AsyncJobService();
		$diagnostics = new UniversalCPTMigrator\Infrastructure\DiagnosticsService();
		$transport   = new UniversalCPTMigrator\Infrastructure\PackageTransport();

		$export_job = $worker->queue_export( 'ucm_scale_source' );
		$worker->process_export_job( $export_job );
		$export_state = $async_jobs->get_job_status( $export_job );

		$this->assertSame( 'completed', $export_state['status'] );

		$bundle = $transport->extract_import_bundle(
			[
				'name'     => 'ucm-scale-retry.zip',
				'tmp_name' => $export_state['artifacts']['zip_path'],
				'error'    => 0,
			]
		);

		$this->assertIsArray( $bundle );
		$bundle['package']['metadata']['post_type'] = 'ucm_scale_target';
		foreach ( $bundle['package']['items'] as $index => $item ) {
			$bundle['package']['items'][ $index ]['uuid'] = wp_generate_uuid4();
		}

		$response = $async_jobs->queue_import(
			$bundle,
			[
				'is_valid' => true,
				'errors'   => [],
				'warnings' => [],
				'summary'  => [
					'items'     => $total_posts,
					'post_type' => 'ucm_scale_target',
				],
			],
			'import'
		);

		$job_id = $response['job_id'];
		wp_clear_scheduled_hook( UniversalCPTMigrator\Infrastructure\BackgroundWorker::IMPORT_HOOK, [ $job_id ] );
		$snapshot_before = $diagnostics->get_snapshot();
		$attention = array_values(
			array_filter(
				$snapshot_before['attention_rows'],
				static function( $row ) use ( $job_id ) {
					return $row['job_id'] === $job_id;
				}
			)
		);

		$this->assertNotEmpty( $attention );
		$this->assertTrue( $attention[0]['missing_worker'] );

		$repair = $diagnostics->run_worker_sanity_check();
		$this->assertGreaterThanOrEqual( 1, (int) $repair['worker_events'] );
		$this->assertNotFalse( wp_next_scheduled( UniversalCPTMigrator\Infrastructure\BackgroundWorker::IMPORT_HOOK, [ $job_id ] ) );

		$iterations = 0;
		do {
			$worker->process_import_job( $job_id );
			$state = $async_jobs->get_job_status( $job_id );
			$iterations++;
		} while ( 'completed' !== $state['status'] && $iterations < 20 );

		$this->assertSame( 'completed', $state['status'] );
		$this->assertSame( $total_posts, (int) $state['results']['imported'] );
		$this->assertGreaterThan( 3, $iterations );

		$imported_posts = get_posts(
			[
				'post_type'      => 'ucm_scale_target',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		$this->assertCount( $total_posts, $imported_posts );
	}

	public function test_scale_import_survives_worker_repair_and_media_warnings_on_very_large_dataset() {
		update_option(
			UniversalCPTMigrator\Infrastructure\Settings::OPTION_KEY,
			array_merge(
				( new UniversalCPTMigrator\Infrastructure\Settings() )->defaults(),
				[ 'chunk_size' => 12 ]
			),
			false
		);

		$total_posts   = 240;
		$media_stride  = 2;
		$warning_stride = 24;
		$attachment_id = $this->create_attachment(
			'ucm-scale-image-3.gif',
			base64_decode( 'R0lGODdhAQABAIAAAP///////ywAAAAAAQABAAACAkQBADs=' )
		);

		for ( $i = 1; $i <= $total_posts; $i++ ) {
			$post_id = self::factory()->post->create(
				[
					'post_type'    => 'ucm_scale_source',
					'post_title'   => 'Very Large Source ' . $i,
					'post_content' => str_repeat( 'Large payload ' . $i . ' ', 6 ),
					'post_status'  => 'publish',
				]
			);

			if ( 0 === $i % $media_stride ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}

		$worker      = new UniversalCPTMigrator\Infrastructure\BackgroundWorker();
		$async_jobs  = new UniversalCPTMigrator\Infrastructure\AsyncJobService();
		$diagnostics = new UniversalCPTMigrator\Infrastructure\DiagnosticsService();
		$transport   = new UniversalCPTMigrator\Infrastructure\PackageTransport();

		$export_job = $worker->queue_export( 'ucm_scale_source' );
		$worker->process_export_job( $export_job );
		$export_state = $async_jobs->get_job_status( $export_job );

		$this->assertSame( 'completed', $export_state['status'] );
		$this->assertSame( $total_posts, (int) $export_state['package']['metadata']['item_count'] );
		$this->assertSame( 20, (int) $export_state['package']['metadata']['total_chunks'] );

		$bundle = $transport->extract_import_bundle(
			[
				'name'     => 'ucm-scale-very-large.zip',
				'tmp_name' => $export_state['artifacts']['zip_path'],
				'error'    => 0,
			]
		);

		$this->assertIsArray( $bundle );

		$warning_targets = 0;
		$warning_counter = 0;
		foreach ( $bundle['package']['items'] as $index => $item ) {
			$bundle['package']['items'][ $index ]['uuid'] = wp_generate_uuid4();

			if ( empty( $item['featured_media']['manifest']['tmp_path'] ) ) {
				continue;
			}

			$warning_counter++;
			if ( 0 !== $warning_counter % $warning_stride ) {
				continue;
			}

			$bundle['package']['items'][ $index ]['featured_media']['url'] = '';
			$bundle['package']['items'][ $index ]['featured_media']['content_hash'] = '';
			$bundle['package']['items'][ $index ]['featured_media']['manifest']['content_hash'] = '';
			$bundle['package']['items'][ $index ]['featured_media']['manifest']['tmp_path'] = dirname( __FILE__ ) . '/fixtures/does-not-exist.gif';
			$warning_targets++;
		}

		$this->assertGreaterThan( 0, $warning_targets );

		$bundle['package']['metadata']['post_type'] = 'ucm_scale_target';

		$response = $async_jobs->queue_import(
			$bundle,
			[
				'is_valid' => true,
				'errors'   => [],
				'warnings' => [],
				'summary'  => [
					'items'     => $total_posts,
					'post_type' => 'ucm_scale_target',
				],
			],
			'import'
		);

		$job_id = $response['job_id'];
		wp_clear_scheduled_hook( UniversalCPTMigrator\Infrastructure\BackgroundWorker::IMPORT_HOOK, [ $job_id ] );

		$repair_one = $diagnostics->run_worker_sanity_check();
		$this->assertGreaterThanOrEqual( 1, (int) $repair_one['worker_events'] );
		$this->assertNotFalse( wp_next_scheduled( UniversalCPTMigrator\Infrastructure\BackgroundWorker::IMPORT_HOOK, [ $job_id ] ) );

		$iterations = 0;
		do {
			$worker->process_import_job( $job_id );
			$state = $async_jobs->get_job_status( $job_id );
			$iterations++;
		} while ( 'completed' !== $state['status'] && $iterations < 40 );

		$this->assertSame( 'completed', $state['status'] );
		$this->assertSame( $total_posts, (int) $state['results']['imported'] );
		$this->assertSame( 0, (int) $state['results']['failed'] );
		$this->assertGreaterThan( 10, $iterations );

		$warning_count = 0;
		foreach ( $state['results']['items'] as $item_result ) {
			if ( empty( $item_result['warnings'] ) || ! is_array( $item_result['warnings'] ) ) {
				continue;
			}

			foreach ( $item_result['warnings'] as $warning ) {
				if ( ! empty( $warning['code'] ) && 'ucm_media_manifest_missing' === $warning['code'] ) {
					$warning_count++;
				}
			}
		}

		$this->assertSame( $warning_targets, $warning_count );

		$imported_posts = get_posts(
			[
				'post_type'      => 'ucm_scale_target',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		$this->assertCount( $total_posts, $imported_posts );
	}

	public function test_scale_dense_media_small_chunks_profile_completes_under_slow_io_assumptions() {
		update_option(
			UniversalCPTMigrator\Infrastructure\Settings::OPTION_KEY,
			array_merge(
				( new UniversalCPTMigrator\Infrastructure\Settings() )->defaults(),
				[ 'chunk_size' => 5 ]
			),
			false
		);

		$total_posts  = 90;
		$media_stride = 1;
		$attachment_id = $this->create_attachment(
			'ucm-scale-image-4.gif',
			base64_decode( 'R0lGODdhAQABAIAAAP///////ywAAAAAAQABAAACAkQBADs=' )
		);

		for ( $i = 1; $i <= $total_posts; $i++ ) {
			$post_id = self::factory()->post->create(
				[
					'post_type'    => 'ucm_scale_source',
					'post_title'   => 'Slow IO Source ' . $i,
					'post_content' => str_repeat( 'Slow IO payload ' . $i . ' ', 20 ),
					'post_status'  => 'publish',
				]
			);

			if ( 0 === $i % $media_stride ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}

		$worker      = new UniversalCPTMigrator\Infrastructure\BackgroundWorker();
		$async_jobs  = new UniversalCPTMigrator\Infrastructure\AsyncJobService();
		$transport   = new UniversalCPTMigrator\Infrastructure\PackageTransport();

		$export_job = $worker->queue_export( 'ucm_scale_source' );
		$worker->process_export_job( $export_job );
		$export_state = $async_jobs->get_job_status( $export_job );

		$this->assertSame( 'completed', $export_state['status'] );
		$this->assertSame( $total_posts, (int) $export_state['package']['metadata']['item_count'] );

		$bundle = $transport->extract_import_bundle(
			[
				'name'     => 'ucm-scale-slow-io.zip',
				'tmp_name' => $export_state['artifacts']['zip_path'],
				'error'    => 0,
			]
		);

		$this->assertIsArray( $bundle );
		$bundle['package']['metadata']['post_type'] = 'ucm_scale_target';
		foreach ( $bundle['package']['items'] as $index => $item ) {
			$bundle['package']['items'][ $index ]['uuid'] = wp_generate_uuid4();
		}

		$response = $async_jobs->queue_import(
			$bundle,
			[
				'is_valid' => true,
				'errors'   => [],
				'warnings' => [],
				'summary'  => [
					'items'     => $total_posts,
					'post_type' => 'ucm_scale_target',
				],
			],
			'import'
		);

		$job_id = $response['job_id'];
		$iterations = 0;

		do {
			$worker->process_import_job( $job_id );
			$state = $async_jobs->get_job_status( $job_id );
			$iterations++;
		} while ( 'completed' !== $state['status'] && $iterations < 50 );

		$this->assertSame( 'completed', $state['status'] );
		$this->assertSame( $total_posts, (int) $state['results']['imported'] );
		$this->assertGreaterThan( 12, $iterations );
		$this->assertSame( 0, (int) $state['results']['failed'] );

		$imported_posts = get_posts(
			[
				'post_type'      => 'ucm_scale_target',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		$this->assertCount( $total_posts, $imported_posts );

		$thumbnail_count = 0;
		foreach ( $imported_posts as $post_id ) {
			if ( get_post_thumbnail_id( $post_id ) ) {
				$thumbnail_count++;
			}
		}

		$this->assertSame( $total_posts, $thumbnail_count );
	}

	private function create_attachment( $filename, $contents ) {
		$upload = wp_upload_bits( $filename, null, $contents );
		$this->assertEmpty( $upload['error'] );

		return self::factory()->attachment->create_upload_object( $upload['file'] );
	}

	private function delete_posts_for_type( $post_type ) {
		$post_ids = get_posts(
			[
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}
}
