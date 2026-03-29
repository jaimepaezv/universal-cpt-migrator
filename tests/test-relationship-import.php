<?php

class UCM_Fake_ACF_Service extends UniversalCPTMigrator\Integration\ACF\ACFService {
	private $map;

	public function __construct( array $map ) {
		$this->map = $map;
	}

	public function get_field_map_for_cpt( $post_type ) {
		return $this->map;
	}
}

class UCM_Relationship_Import_Test extends WP_UnitTestCase {
	public function test_remap_acf_fields_handles_nested_group_repeater_and_flexible_relationships() {
		$related_id = self::factory()->post->create( [ 'post_type' => 'post' ] );
		update_post_meta( $related_id, '_ucm_uuid', '550e8400-e29b-41d4-a716-446655440000' );

		$field_map = [
			'hero' => [
				'name' => 'hero',
				'type' => 'group',
				'sub_fields' => [
					[
						'name' => 'featured_post',
						'type' => 'post_object',
					],
					[
						'name' => 'cards',
						'type' => 'repeater',
						'sub_fields' => [
							[
								'name' => 'linked_post',
								'type' => 'relationship',
							],
						],
					],
					[
						'name' => 'content_blocks',
						'type' => 'flexible_content',
						'layouts' => [
							'cta' => [
								'sub_fields' => [
									[
										'name' => 'cta_target',
										'type' => 'page_link',
									],
								],
							],
						],
					],
				],
			],
		];

		$mapper = new UniversalCPTMigrator\Domain\Import\RelationshipMapper(
			null,
			new UCM_Fake_ACF_Service( $field_map )
		);

		$result = $mapper->remap_acf_fields(
			[
				'hero' => [
					'featured_post' => [ 'uuid' => '550e8400-e29b-41d4-a716-446655440000' ],
					'cards' => [
						[
							'linked_post' => [
								[ 'uuid' => '550e8400-e29b-41d4-a716-446655440000' ],
							],
						],
					],
					'content_blocks' => [
						[
							'acf_fc_layout' => 'cta',
							'cta_target' => [ 'uuid' => '550e8400-e29b-41d4-a716-446655440000' ],
						],
					],
				],
			],
			'post'
		);

		$this->assertSame( $related_id, $result['hero']['featured_post'] );
		$this->assertSame( [ $related_id ], $result['hero']['cards'][0]['linked_post'] );
		$this->assertSame( $related_id, $result['hero']['content_blocks'][0]['cta_target'] );
	}
}
