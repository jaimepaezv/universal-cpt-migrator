<?php

class UCM_Validator_Test extends WP_UnitTestCase {
	public function test_validator_flags_missing_items() {
		$validator = new UniversalCPTMigrator\Domain\Import\Validator();
		$result    = $validator->validate_package(
			[
				'metadata' => [
					'post_type' => 'post',
				],
				'schema' => [
					'post_type'  => 'post',
					'taxonomies' => [],
					'acf_groups' => [],
				],
				'items' => [],
			]
		);

		$this->assertFalse( $result['is_valid'] );
	}
}
