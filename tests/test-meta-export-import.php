<?php

class UCM_Meta_Export_Import_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		register_post_type(
			'ucm_meta_source',
			[
				'public'   => true,
				'supports' => [ 'title', 'editor', 'excerpt', 'custom-fields' ],
			]
		);

		register_post_type(
			'ucm_meta_target',
			[
				'public'   => true,
				'supports' => [ 'title', 'editor', 'excerpt', 'custom-fields' ],
			]
		);

		add_filter( 'u_cpt_mgr_meta_relationship_keys', [ $this, 'filter_relationship_keys' ], 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'u_cpt_mgr_meta_relationship_keys', [ $this, 'filter_relationship_keys' ], 10 );
		unregister_post_type( 'ucm_meta_source' );
		unregister_post_type( 'ucm_meta_target' );
		parent::tearDown();
	}

	public function filter_relationship_keys( $keys, $post_type ) {
		if ( in_array( $post_type, [ 'ucm_meta_source', 'ucm_meta_target' ], true ) ) {
			$keys[] = 'related_vendor_ids';
			$keys[] = 'related_book_ids';
		}

		return $keys;
	}

	public function test_export_and_import_preserve_meta_fields_and_remap_relationship_meta() {
		$vendor_id = self::factory()->post->create(
			[
				'post_type'   => 'post',
				'post_title'  => 'Vendor Ref',
				'post_status' => 'publish',
			]
		);
		update_post_meta( $vendor_id, '_ucm_uuid', '550e8400-e29b-41d4-a716-446655440001' );

		$book_id = self::factory()->post->create(
			[
				'post_type'   => 'post',
				'post_title'  => 'Book Ref',
				'post_status' => 'publish',
			]
		);
		update_post_meta( $book_id, '_ucm_uuid', '550e8400-e29b-41d4-a716-446655440002' );

		$source_id = self::factory()->post->create(
			[
				'post_type'    => 'ucm_meta_source',
				'post_title'   => 'Complex Source Item',
				'post_content' => 'Meta export/import source.',
				'post_status'  => 'publish',
			]
		);

		update_post_meta( $source_id, 'project_code', 'PRJ-500' );
		update_post_meta( $source_id, 'budget', 125000.5 );
		update_post_meta( $source_id, 'is_billable', '1' );
		update_post_meta( $source_id, 'tags', [ 'migration', 'search' ] );
		update_post_meta( $source_id, 'configuration', [ 'flags' => [ 'cdn' => true ], 'locales' => [ 'en_US', 'es_EC' ] ] );
		update_post_meta( $source_id, 'related_vendor_ids', [ $vendor_id ] );
		update_post_meta( $source_id, 'related_book_ids', [ $book_id ] );

		$exporter = new UniversalCPTMigrator\Domain\Export\Exporter();
		$package  = $exporter->build_package( 'ucm_meta_source' )['package'];

		$this->assertNotEmpty( $package['items'][0]['meta']['project_code'] );
		$this->assertSame( 'PRJ-500', $package['items'][0]['meta']['project_code'] );
		$this->assertSame( '125000.5', (string) $package['items'][0]['meta']['budget'] );
		$this->assertSame( [ 'migration', 'search' ], $package['items'][0]['meta']['tags'] );
		$this->assertSame( [ 'flags' => [ 'cdn' => true ], 'locales' => [ 'en_US', 'es_EC' ] ], $package['items'][0]['meta']['configuration'] );
		$this->assertSame( '550e8400-e29b-41d4-a716-446655440001', $package['items'][0]['meta']['related_vendor_ids'][0]['uuid'] );
		$this->assertSame( '550e8400-e29b-41d4-a716-446655440002', $package['items'][0]['meta']['related_book_ids'][0]['uuid'] );

		wp_delete_post( $source_id, true );
		$package['metadata']['post_type'] = 'ucm_meta_target';

		$processor = new UniversalCPTMigrator\Domain\Import\Processor();
		$result    = $processor->import_package( $package, false, 0, 10 );

		$this->assertIsArray( $result );
		$this->assertSame( 1, (int) $result['imported'] );

		$target_posts = get_posts(
			[
				'post_type'      => 'ucm_meta_target',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
			]
		);

		$this->assertCount( 1, $target_posts );
		$target_id = $target_posts[0]->ID;

		$this->assertSame( 'PRJ-500', get_post_meta( $target_id, 'project_code', true ) );
		$this->assertSame( '125000.5', (string) get_post_meta( $target_id, 'budget', true ) );
		$this->assertSame( [ 'migration', 'search' ], get_post_meta( $target_id, 'tags', true ) );
		$this->assertSame( [ 'flags' => [ 'cdn' => true ], 'locales' => [ 'en_US', 'es_EC' ] ], get_post_meta( $target_id, 'configuration', true ) );
		$this->assertSame( [ $vendor_id ], get_post_meta( $target_id, 'related_vendor_ids', true ) );
		$this->assertSame( [ $book_id ], get_post_meta( $target_id, 'related_book_ids', true ) );
	}
}
