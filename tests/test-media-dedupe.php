<?php

class UCM_Media_Dedupe_Test extends WP_UnitTestCase {
	public function test_media_service_reuses_attachment_by_content_hash() {
		$upload = wp_upload_bits( 'ucm-test.txt', null, 'fixture-content' );
		$this->assertEmpty( $upload['error'] );

		$attachment_id = self::factory()->attachment->create_upload_object( $upload['file'] );
		$hash          = md5_file( $upload['file'] );
		update_post_meta( $attachment_id, '_ucm_content_hash', $hash );

		$service = new UniversalCPTMigrator\Domain\Import\MediaSideloadService();
		$result  = $service->import_featured_media(
			[
				'content_hash' => $hash,
				'url'          => 'https://example.org/media/ucm-test.txt',
				'title'        => 'Fixture',
				'alt'          => 'Fixture alt',
			]
		);

		$this->assertSame( $attachment_id, $result );
	}
}
