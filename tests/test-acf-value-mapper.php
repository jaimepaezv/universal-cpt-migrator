<?php

class UCM_Fake_ACF_Map_Service extends UniversalCPTMigrator\Integration\ACF\ACFService {
	private $map;

	public function __construct( array $map ) {
		$this->map = $map;
	}

	public function get_field_map_for_cpt( $post_type ) {
		return $this->map;
	}
}

class UCM_Fake_Media_Service extends UniversalCPTMigrator\Domain\Import\MediaSideloadService {
	private $return_id;
	private $last_error;

	public function __construct( $return_id = 0, $last_error = null ) {
		$this->return_id  = $return_id;
		$this->last_error = $last_error;
	}

	public function import_featured_media( array $media ) {
		return (int) $this->return_id;
	}

	public function get_last_error() {
		return $this->last_error;
	}
}

class UCM_ACF_Value_Mapper_Test extends WP_UnitTestCase {
	public function tearDown(): void {
		if ( taxonomy_exists( 'ucm_topic' ) ) {
			unregister_taxonomy( 'ucm_topic' );
		}

		parent::tearDown();
	}

	public function test_export_values_normalize_nested_relationship_taxonomy_user_and_media_fields() {
		register_taxonomy(
			'ucm_topic',
			'post',
			[
				'label'        => 'Topics',
				'hierarchical' => true,
				'public'       => true,
			]
		);

		$related_id = self::factory()->post->create( [ 'post_type' => 'post' ] );
		update_post_meta( $related_id, '_ucm_uuid', '550e8400-e29b-41d4-a716-446655440000' );

		$term = wp_insert_term( 'Guides', 'ucm_topic', [ 'slug' => 'guides' ] );
		$user_login = 'acf-editor-' . wp_generate_password( 6, false, false );
		$user_id    = self::factory()->user->create( [ 'user_login' => $user_login ] );

		$field_map = [
			'hero_post' => [ 'name' => 'hero_post', 'type' => 'post_object' ],
			'topics'    => [ 'name' => 'topics', 'type' => 'taxonomy', 'taxonomy' => 'ucm_topic', 'field_type' => 'multi_select' ],
			'editor'    => [ 'name' => 'editor', 'type' => 'user' ],
			'asset'     => [ 'name' => 'asset', 'type' => 'image' ],
			'blocks'    => [
				'name'    => 'blocks',
				'type'    => 'flexible_content',
				'layouts' => [
					'cta' => [
						'sub_fields' => [
							[ 'name' => 'linked_post', 'type' => 'relationship' ],
						],
					],
				],
			],
		];

		$mapper = new UniversalCPTMigrator\Integration\ACF\ACFValueMapper(
			null,
			new UCM_Fake_ACF_Map_Service( $field_map ),
			null,
			static function( $attachment_id ) {
				return [
					'source_id' => (int) $attachment_id,
					'url'       => 'https://example.org/media/' . $attachment_id . '.jpg',
				];
			}
		);

		$exported = $mapper->export_values(
			[
				'hero_post' => $related_id,
				'topics'    => [ (int) $term['term_id'] ],
				'editor'    => $user_id,
				'asset'     => 123,
				'blocks'    => [
					[
						'acf_fc_layout' => 'cta',
						'linked_post'   => [ $related_id ],
					],
				],
			],
			'post'
		);

		$this->assertSame( '550e8400-e29b-41d4-a716-446655440000', $exported['hero_post']['uuid'] );
		$this->assertSame( 'guides', $exported['topics'][0]['slug'] );
		$this->assertSame( $user_login, $exported['editor'] );
		$this->assertSame( 123, $exported['asset']['source_id'] );
		$this->assertSame( '550e8400-e29b-41d4-a716-446655440000', $exported['blocks'][0]['linked_post'][0]['uuid'] );
	}

	public function test_import_values_normalize_nested_relationship_taxonomy_user_and_media_fields() {
		register_taxonomy(
			'ucm_topic',
			'post',
			[
				'label'        => 'Topics',
				'hierarchical' => true,
				'public'       => true,
			]
		);

		$related_id = self::factory()->post->create( [ 'post_type' => 'post' ] );
		update_post_meta( $related_id, '_ucm_uuid', '550e8400-e29b-41d4-a716-446655440000' );

		wp_insert_term( 'Guides', 'ucm_topic', [ 'slug' => 'guides' ] );
		$user_login = 'acf-editor-' . wp_generate_password( 6, false, false );
		$user_id    = self::factory()->user->create( [ 'user_login' => $user_login ] );

		$field_map = [
			'hero_post' => [ 'name' => 'hero_post', 'type' => 'post_object' ],
			'topics'    => [ 'name' => 'topics', 'type' => 'taxonomy', 'taxonomy' => 'ucm_topic', 'field_type' => 'multi_select' ],
			'editor'    => [ 'name' => 'editor', 'type' => 'user' ],
			'asset'     => [ 'name' => 'asset', 'type' => 'image' ],
			'gallery'   => [ 'name' => 'gallery', 'type' => 'gallery' ],
			'blocks'    => [
				'name'    => 'blocks',
				'type'    => 'flexible_content',
				'layouts' => [
					'cta' => [
						'sub_fields' => [
							[ 'name' => 'linked_post', 'type' => 'relationship' ],
						],
					],
				],
			],
		];

		$mapper   = new UniversalCPTMigrator\Integration\ACF\ACFValueMapper(
			null,
			new UCM_Fake_ACF_Map_Service( $field_map ),
			new UCM_Fake_Media_Service( 555 )
		);
		$warnings = [];

		$imported = $mapper->import_values(
			[
				'hero_post' => [ 'uuid' => '550e8400-e29b-41d4-a716-446655440000' ],
				'topics'    => [
					[ 'slug' => 'guides', 'name' => 'Guides' ],
				],
				'editor'    => $user_login,
				'asset'     => [
					'url' => 'https://example.org/media/555.jpg',
				],
				'gallery'   => [
					[ 'url' => 'https://example.org/media/555-a.jpg' ],
					[ 'url' => 'https://example.org/media/555-b.jpg' ],
				],
				'blocks'    => [
					[
						'acf_fc_layout' => 'cta',
						'linked_post'   => [
							[ 'uuid' => '550e8400-e29b-41d4-a716-446655440000' ],
						],
					],
				],
			],
			'post',
			$warnings
		);

		$this->assertSame( $related_id, $imported['hero_post'] );
		$this->assertSame( [ get_term_by( 'slug', 'guides', 'ucm_topic' )->term_id ], $imported['topics'] );
		$this->assertSame( $user_id, $imported['editor'] );
		$this->assertSame( 555, $imported['asset'] );
		$this->assertSame( [ 555, 555 ], $imported['gallery'] );
		$this->assertSame( [ $related_id ], $imported['blocks'][0]['linked_post'] );
		$this->assertSame( [], $warnings );
	}

	public function test_import_values_capture_media_warning_when_acf_media_mapping_fails() {
		$field_map = [
			'asset' => [ 'name' => 'asset', 'type' => 'image' ],
		];

		$mapper   = new UniversalCPTMigrator\Integration\ACF\ACFValueMapper(
			null,
			new UCM_Fake_ACF_Map_Service( $field_map ),
			new UCM_Fake_Media_Service(
				0,
				new WP_Error(
					'ucm_media_manifest_invalid_image_content',
					'Packaged image bytes did not match the declared image type.',
					[ 'subsystem' => 'media_manifest_content_validation' ]
				)
			)
		);
		$warnings = [];

		$imported = $mapper->import_values(
			[
				'asset' => [
					'url' => 'https://example.org/media/invalid.gif',
				],
			],
			'post',
			$warnings
		);

		$this->assertSame( 0, $imported['asset'] );
		$this->assertCount( 1, $warnings );
		$this->assertSame( 'ucm_media_manifest_invalid_image_content', $warnings[0]['code'] );
		$this->assertSame( 'media_manifest_content_validation', $warnings[0]['subsystem'] );
	}
}
