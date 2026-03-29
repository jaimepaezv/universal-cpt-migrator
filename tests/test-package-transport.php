<?php

class UCM_Package_Transport_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		$storage = new UniversalCPTMigrator\Infrastructure\Storage();
		$storage->setup_directories();
	}

	public function tearDown(): void {
		$storage = new UniversalCPTMigrator\Infrastructure\Storage();
		$storage->delete_all();

		parent::tearDown();
	}

	public function test_extract_import_bundle_rejects_malformed_package_json() {
		$zip_path = $this->create_zip_bundle(
			'ucm-malformed-package.zip',
			[
				'package.json' => '{invalid-json',
			]
		);

		$transport = new UniversalCPTMigrator\Infrastructure\PackageTransport();
		$result    = $transport->extract_import_bundle(
			[
				'name'     => 'ucm-malformed-package.zip',
				'tmp_name' => $zip_path,
				'error'    => 0,
			]
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ucm_malformed_package', $result->get_error_code() );
	}

	public function test_extract_import_bundle_rejects_malformed_raw_json_package() {
		$json_path = trailingslashit( sys_get_temp_dir() ) . 'ucm-malformed-package.json';
		file_put_contents( $json_path, '{broken-json' );

		$transport = new UniversalCPTMigrator\Infrastructure\PackageTransport();
		$result    = $transport->extract_import_bundle(
			[
				'name'     => 'ucm-malformed-package.json',
				'tmp_name' => $json_path,
				'error'    => 0,
			]
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ucm_malformed_package', $result->get_error_code() );
	}

	public function test_extract_import_bundle_rejects_unsafe_archive_entries() {
		$zip_path = $this->create_zip_bundle(
			'ucm-unsafe-package.zip',
			[
				'../package.json' => '{}',
			]
		);

		$transport = new UniversalCPTMigrator\Infrastructure\PackageTransport();
		$result    = $transport->extract_import_bundle(
			[
				'name'     => 'ucm-unsafe-package.zip',
				'tmp_name' => $zip_path,
				'error'    => 0,
			]
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ucm_zip_unsafe', $result->get_error_code() );
	}

	public function test_extract_import_bundle_does_not_bind_tmp_path_for_unsafe_manifest_reference_inside_valid_package() {
		$zip_path = $this->create_zip_bundle(
			'ucm-valid-package-unsafe-manifest.zip',
			[
				'package.json' => wp_json_encode(
					[
						'metadata' => [
							'post_type' => 'post',
						],
						'items' => [
							[
								'uuid'           => wp_generate_uuid4(),
								'post_title'     => 'Unsafe Manifest Item',
								'post_status'    => 'publish',
								'featured_media' => [
									'manifest' => [
										'relative_path' => '../media/evil.gif',
										'filename'      => 'evil.gif',
									],
								],
							],
						],
					]
				),
				'media/evil.gif' => base64_decode( 'R0lGODdhAQABAIAAAP///////ywAAAAAAQABAAACAkQBADs=' ),
			]
		);

		$transport = new UniversalCPTMigrator\Infrastructure\PackageTransport();
		$result    = $transport->extract_import_bundle(
			[
				'name'     => 'ucm-valid-package-unsafe-manifest.zip',
				'tmp_name' => $zip_path,
				'error'    => 0,
			]
		);

		$this->assertIsArray( $result );
		$this->assertArrayNotHasKey( 'tmp_path', $result['package']['items'][0]['featured_media']['manifest'] );
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
