<?php

class UCM_Discovery_And_Schema_Test extends WP_UnitTestCase {
	public function tear_down(): void {
		if ( post_type_exists( 'ucm_admin_doc' ) ) {
			unregister_post_type( 'ucm_admin_doc' );
		}

		if ( post_type_exists( 'ucm_schema_book' ) ) {
			unregister_post_type( 'ucm_schema_book' );
		}

		if ( taxonomy_exists( 'ucm_topic' ) ) {
			unregister_taxonomy( 'ucm_topic' );
		}

		parent::tear_down();
	}

	public function test_discovery_includes_admin_visible_non_public_types() {
		register_post_type(
			'ucm_admin_doc',
			[
				'label'        => 'Admin Docs',
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => false,
				'supports'     => [ 'title', 'editor' ],
			]
		);

		$service = new UniversalCPTMigrator\Domain\Discovery\DiscoveryService();
		$summary = $service->get_all_cpts_summary();
		$found   = wp_list_filter( $summary, [ 'slug' => 'ucm_admin_doc' ] );

		$this->assertCount( 1, $found );
		$this->assertSame( 'admin-only', array_values( $found )[0]['visibility'] );
	}

	public function test_analyzer_reports_supports_taxonomies_meta_and_post_type_details() {
		register_taxonomy(
			'ucm_topic',
			'ucm_schema_book',
			[
				'label'        => 'Topics',
				'hierarchical' => true,
				'public'       => true,
				'show_ui'      => true,
				'show_in_rest' => true,
			]
		);

		register_post_type(
			'ucm_schema_book',
			[
				'label'         => 'Schema Books',
				'description'   => 'Schema test type',
				'public'        => true,
				'show_ui'       => true,
				'show_in_rest'  => true,
				'supports'      => [ 'title', 'editor', 'excerpt', 'thumbnail', 'author' ],
				'taxonomies'    => [ 'ucm_topic' ],
				'has_archive'   => true,
				'hierarchical'  => false,
				'capability_type' => 'post',
			]
		);

		$post_id = self::factory()->post->create(
			[
				'post_type'   => 'ucm_schema_book',
				'post_title'  => 'Schema Sample',
				'post_status' => 'publish',
			]
		);

		update_post_meta( $post_id, 'reading_time', '12' );
		update_post_meta( $post_id, '_internal_flag', '1' );

		$analyzer = new UniversalCPTMigrator\Domain\Schema\Analyzer();
		$schema   = $analyzer->analyze_cpt( 'ucm_schema_book' );

		$this->assertSame( 2, $schema['schema_version'] );
		$this->assertContains( 'thumbnail', $schema['supports'] );
		$this->assertArrayHasKey( '_thumbnail_id', $schema['core_fields'] );
		$this->assertArrayHasKey( 'publish', $schema['post_statuses'] );
		$this->assertSame( 'Schema Books', $schema['post_type_object']['label'] );
		$this->assertTrue( $schema['post_type_object']['show_in_rest'] );
		$this->assertArrayHasKey( 'ucm_topic', $schema['taxonomies'] );
		$this->assertSame( 'integer', $schema['meta_fields']['reading_time']['type'] );
		$this->assertTrue( $schema['meta_fields']['_internal_flag']['private'] );
	}
}
