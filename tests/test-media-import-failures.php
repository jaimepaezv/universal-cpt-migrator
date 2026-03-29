<?php

class UCM_Media_Import_Failures_Test extends WP_UnitTestCase {
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

	public function test_manifest_import_rejects_non_file_extracted_path() {
		$tmp_dir = trailingslashit( sys_get_temp_dir() ) . 'ucm-manifest-dir-' . wp_generate_uuid4();
		wp_mkdir_p( $tmp_dir );

		$service = new UniversalCPTMigrator\Domain\Import\MediaSideloadService();
		$result  = $service->import_featured_media(
			[
				'manifest' => [
					'relative_path' => 'media/broken.gif',
					'filename'      => 'broken.gif',
					'tmp_path'      => $tmp_dir,
				],
				'url' => '',
			]
		);

		$this->assertSame( 0, $result );
		$this->assertSame( 0, $this->count_attachments() );
	}

	public function test_manifest_import_rejects_invalid_filetype_content_pair() {
		$tmp_file = trailingslashit( sys_get_temp_dir() ) . 'ucm-invalid-manifest-' . wp_generate_uuid4() . '.php';
		file_put_contents( $tmp_file, '<?php echo "not media";' );

		$service = new UniversalCPTMigrator\Domain\Import\MediaSideloadService();
		$result  = $service->import_featured_media(
			[
				'manifest' => [
					'relative_path' => 'media/invalid.php',
					'filename'      => 'invalid.php',
					'mime_type'     => 'image/gif',
					'tmp_path'      => $tmp_file,
				],
				'url' => '',
			]
		);

		$this->assertSame( 0, $result );
		$this->assertSame( 0, $this->count_attachments() );
	}

	public function test_manifest_import_rejects_non_image_featured_media_file() {
		$tmp_file = trailingslashit( sys_get_temp_dir() ) . 'ucm-non-image-manifest-' . wp_generate_uuid4() . '.txt';
		file_put_contents( $tmp_file, 'plain text attachment content' );

		$service = new UniversalCPTMigrator\Domain\Import\MediaSideloadService();
		$result  = $service->import_featured_media(
			[
				'manifest' => [
					'relative_path' => 'media/not-an-image.txt',
					'filename'      => 'not-an-image.txt',
					'mime_type'     => 'text/plain',
					'tmp_path'      => $tmp_file,
				],
				'url' => '',
			]
		);

		$this->assertSame( 0, $result );
		$this->assertSame( 0, $this->count_attachments() );
	}

	public function test_manifest_import_rejects_fake_gif_with_non_image_contents() {
		$tmp_file = trailingslashit( sys_get_temp_dir() ) . 'ucm-fake-image-manifest-' . wp_generate_uuid4() . '.gif';
		file_put_contents( $tmp_file, 'this is not actually a gif image' );

		$service = new UniversalCPTMigrator\Domain\Import\MediaSideloadService();
		$result  = $service->import_featured_media(
			[
				'manifest' => [
					'relative_path' => 'media/fake-image.gif',
					'filename'      => 'fake-image.gif',
					'mime_type'     => 'image/gif',
					'tmp_path'      => $tmp_file,
				],
				'url' => '',
			]
		);

		$this->assertSame( 0, $result );
		$this->assertSame( 0, $this->count_attachments() );
	}

	private function count_attachments() {
		$query = new WP_Query(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		return count( $query->posts );
	}
}
