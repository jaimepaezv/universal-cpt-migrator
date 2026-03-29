<?php

class UCM_Compatibility_Test extends WP_UnitTestCase {
	public function test_compare_returns_warning_for_missing_acf_field() {
		$service = new UniversalCPTMigrator\Domain\Schema\CompatibilityService();
		$result  = $service->compare(
			[
				'post_type'  => 'post',
				'taxonomies' => [],
				'acf_groups' => [
					[
						'fields' => [
							[
								'name' => 'hero_title',
								'type' => 'text',
							],
						],
					],
				],
			],
			[
				'post_type'  => 'post',
				'taxonomies' => [],
				'acf_groups' => [],
			]
		);

		$this->assertTrue( $result['is_compatible'] );
		$this->assertNotEmpty( $result['warnings'] );
	}

	public function test_compare_returns_warnings_for_support_taxonomy_meta_and_layout_mismatch() {
		$service = new UniversalCPTMigrator\Domain\Schema\CompatibilityService();
		$result  = $service->compare(
			[
				'post_type'  => 'book',
				'supports'   => [ 'title', 'thumbnail' ],
				'meta_fields'=> [
					'reading_time' => [ 'type' => 'integer' ],
				],
				'taxonomies' => [
					'genre' => [
						'hierarchical' => true,
						'show_in_rest' => true,
					],
				],
				'acf_groups' => [
					[
						'fields' => [
							[
								'name'    => 'content_blocks',
								'type'    => 'flexible_content',
								'layouts' => [
									'cta' => [
										'sub_fields' => [
											[
												'name' => 'linked_post',
												'type' => 'relationship',
											],
										],
									],
								],
							],
						],
					],
				],
			],
			[
				'post_type'  => 'book',
				'supports'   => [ 'title' ],
				'meta_fields'=> [
					'reading_time' => [ 'type' => 'string' ],
				],
				'taxonomies' => [
					'genre' => [
						'hierarchical' => false,
						'show_in_rest' => false,
					],
				],
				'acf_groups' => [
					[
						'fields' => [
							[
								'name'    => 'content_blocks',
								'type'    => 'flexible_content',
								'layouts' => [
									'cta' => [
										'sub_fields' => [],
									],
								],
							],
						],
					],
				],
			]
		);

		$warnings = implode( "\n", $result['warnings'] );

		$this->assertStringContainsString( 'support "thumbnail"', $warnings );
		$this->assertStringContainsString( 'Taxonomy "genre" differs', $warnings );
		$this->assertStringContainsString( 'Meta field "reading_time" type mismatch', $warnings );
		$this->assertStringContainsString( 'content_blocks.linked_post', $warnings );
	}
}
