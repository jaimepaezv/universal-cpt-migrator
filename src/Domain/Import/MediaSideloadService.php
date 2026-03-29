<?php

namespace UniversalCPTMigrator\Domain\Import;

use UniversalCPTMigrator\Infrastructure\Logger;
use UniversalCPTMigrator\Infrastructure\Settings;

class MediaSideloadService {
	private $logger;
	private $settings;
	private $last_error;

	public function __construct( Logger $logger = null, Settings $settings = null ) {
		$this->logger   = $logger ?: new Logger();
		$this->settings = $settings ?: new Settings();
	}

	public function import_featured_media( array $media ) {
		$this->last_error = null;

		$hash = isset( $media['content_hash'] ) ? sanitize_text_field( $media['content_hash'] ) : '';
		if ( $hash ) {
			$existing_by_hash = $this->find_existing_attachment_by_hash( $hash );
			if ( $existing_by_hash ) {
				$this->logger->info( sprintf( 'Reusing existing attachment %d for media content hash %s.', $existing_by_hash, $hash ) );
				return $existing_by_hash;
			}
		}

		if ( ! empty( $media['manifest'] ) && is_array( $media['manifest'] ) ) {
			$attachment_id = $this->import_from_manifest( $media['manifest'], $media );
			if ( $attachment_id ) {
				return $attachment_id;
			}
		}

		$url = isset( $media['url'] ) ? esc_url_raw( $media['url'] ) : '';
		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			if ( is_wp_error( $this->last_error ) ) {
				return 0;
			}

			$this->set_last_error(
				'ucm_media_invalid_url',
				__( 'Rejected featured media because the URL was invalid.', 'universal-cpt-migrator' ),
				[
					'subsystem' => 'media_remote_url',
					'url'       => $url,
				]
			);
			return 0;
		}

		if ( ! $this->is_allowed_remote_url( $url ) ) {
			$this->set_last_error(
				'ucm_media_host_blocked',
				sprintf( __( 'Rejected featured media URL "%s" because the host is not allowlisted.', 'universal-cpt-migrator' ), $url ),
				[
					'subsystem' => 'media_remote_policy',
					'url'       => $url,
					'host'      => (string) wp_parse_url( $url, PHP_URL_HOST ),
				]
			);
			return 0;
		}

		$existing = $this->find_existing_attachment( $url );
		if ( $existing ) {
			$this->logger->info( sprintf( 'Reusing existing attachment %d for media URL %s.', $existing, $url ) );
			return $existing;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $url, 0, isset( $media['title'] ) ? sanitize_text_field( $media['title'] ) : '', 'id' );
		if ( is_wp_error( $attachment_id ) ) {
			$this->set_last_error(
				'ucm_media_sideload_failed',
				'Media sideload failed for URL: ' . $url . ' - Error: ' . $attachment_id->get_error_message(),
				[
					'subsystem'  => 'media_remote_sideload',
					'url'        => $url,
					'wp_error'   => $attachment_id->get_error_code(),
				]
			);
			return 0;
		}

		$attachment_id = (int) $attachment_id;
		$this->apply_media_meta( $attachment_id, $media, $url );

		return $attachment_id;
	}

	public function get_last_error() {
		return $this->last_error;
	}

	public function find_existing_attachment( $url ) {
		$query = new \WP_Query(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'   => '_ucm_source_url',
						'value' => esc_url_raw( $url ),
					],
				],
			]
		);

		return $query->have_posts() ? (int) $query->posts[0] : 0;
	}

	public function find_existing_attachment_by_hash( $hash ) {
		$query = new \WP_Query(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'   => '_ucm_content_hash',
						'value' => sanitize_text_field( $hash ),
					],
				],
			]
		);

		return $query->have_posts() ? (int) $query->posts[0] : 0;
	}

	private function import_from_manifest( array $manifest, array $media ) {
		$relative_path = isset( $manifest['relative_path'] ) ? sanitize_text_field( $manifest['relative_path'] ) : '';
		$hash          = isset( $manifest['content_hash'] ) ? sanitize_text_field( $manifest['content_hash'] ) : '';
		$tmp_path      = isset( $manifest['tmp_path'] ) ? (string) $manifest['tmp_path'] : '';
		$file_name     = ! empty( $manifest['filename'] ) ? sanitize_file_name( $manifest['filename'] ) : basename( $tmp_path );
		$declared_mime = ! empty( $manifest['mime_type'] ) ? sanitize_mime_type( $manifest['mime_type'] ) : '';

		if ( $hash ) {
			$existing = $this->find_existing_attachment_by_hash( $hash );
			if ( $existing ) {
				return $existing;
			}
		}

		$validated_mime = $this->validate_manifest_file( $tmp_path, $file_name, $declared_mime, $relative_path );
		if ( ! $validated_mime ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$file_array = [
			'name'     => $file_name,
			'type'     => $validated_mime,
			'tmp_name' => $tmp_path,
			'error'    => 0,
			'size'     => filesize( $tmp_path ),
		];

		$attachment_id = media_handle_sideload( $file_array, 0, isset( $media['title'] ) ? sanitize_text_field( $media['title'] ) : '' );
		if ( is_wp_error( $attachment_id ) ) {
			$this->set_last_error(
				'ucm_media_manifest_sideload_failed',
				'Media manifest import failed - Error: ' . $attachment_id->get_error_message(),
				[
					'subsystem' => 'media_manifest_sideload',
					'file'      => $file_name,
					'wp_error'  => $attachment_id->get_error_code(),
				]
			);
			return 0;
		}

		$this->apply_media_meta( (int) $attachment_id, $media, isset( $media['url'] ) ? esc_url_raw( $media['url'] ) : '' );
		return (int) $attachment_id;
	}

	private function validate_manifest_file( $tmp_path, $file_name, $declared_mime, $relative_path ) {
		if ( ! $tmp_path || ! file_exists( $tmp_path ) || ! is_file( $tmp_path ) || ! is_readable( $tmp_path ) ) {
			$this->set_last_error(
				'ucm_media_manifest_missing',
				sprintf( __( 'Media manifest asset "%s" was not available in the current upload.', 'universal-cpt-migrator' ), $relative_path ),
				[
					'subsystem' => 'media_manifest_lookup',
					'path'      => $relative_path,
				]
			);
			return '';
		}

		$filetype = wp_check_filetype_and_ext( $tmp_path, $file_name );
		$detected_mime = ! empty( $filetype['type'] ) ? sanitize_mime_type( $filetype['type'] ) : '';

		if ( ! $detected_mime ) {
			$this->set_last_error(
				'ucm_media_manifest_unvalidated_type',
				sprintf( __( 'Media manifest asset "%s" was rejected because the file type could not be validated.', 'universal-cpt-migrator' ), $relative_path ),
				[
					'subsystem' => 'media_manifest_type_validation',
					'path'      => $relative_path,
					'file'      => $file_name,
				]
			);
			return '';
		}

		if ( 0 !== strpos( $detected_mime, 'image/' ) ) {
			$this->set_last_error(
				'ucm_media_manifest_not_image',
				sprintf( __( 'Media manifest asset "%s" was rejected because featured media must be an image.', 'universal-cpt-migrator' ), $relative_path ),
				[
					'subsystem' => 'media_manifest_type_validation',
					'path'      => $relative_path,
					'mime'      => $detected_mime,
				]
			);
			return '';
		}

		if ( $declared_mime && $declared_mime !== $detected_mime ) {
			$this->set_last_error(
				'ucm_media_manifest_mime_mismatch',
				sprintf( __( 'Media manifest asset "%s" was rejected because the declared MIME type did not match the detected file type.', 'universal-cpt-migrator' ), $relative_path ),
				[
					'subsystem'     => 'media_manifest_type_validation',
					'path'          => $relative_path,
					'declared_mime' => $declared_mime,
					'detected_mime' => $detected_mime,
				]
			);
			return '';
		}

		if ( ! $this->matches_image_content( $tmp_path, $detected_mime ) ) {
			$this->set_last_error(
				'ucm_media_manifest_invalid_image_content',
				sprintf( __( 'Media manifest asset "%s" was rejected because the file contents did not match the detected image type.', 'universal-cpt-migrator' ), $relative_path ),
				[
					'subsystem' => 'media_manifest_content_validation',
					'path'      => $relative_path,
					'mime'      => $detected_mime,
				]
			);
			return '';
		}

		return $detected_mime;
	}

	private function matches_image_content( $tmp_path, $detected_mime ) {
		if ( 'image/svg+xml' === $detected_mime ) {
			return true;
		}

		$image_mime = wp_get_image_mime( $tmp_path );
		if ( ! $image_mime ) {
			return false;
		}

		return sanitize_mime_type( $image_mime ) === $detected_mime;
	}

	private function apply_media_meta( $attachment_id, array $media, $url ) {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', isset( $media['alt'] ) ? sanitize_text_field( $media['alt'] ) : '' );

		if ( $url ) {
			update_post_meta( $attachment_id, '_ucm_source_url', $url );
			update_post_meta( $attachment_id, '_ucm_source_hash', md5( $url ) );
		}

		if ( ! empty( $media['content_hash'] ) ) {
			update_post_meta( $attachment_id, '_ucm_content_hash', sanitize_text_field( $media['content_hash'] ) );
		}
	}

	private function is_allowed_remote_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return false;
		}

		$host            = strtolower( $host );
		$allow_remote    = (bool) $this->settings->get( 'allow_remote_media' );
		$allowed_hosts   = array_filter( preg_split( '/[\r\n,]+/', (string) $this->settings->get( 'allowed_media_hosts' ) ) );
		$site_host       = wp_parse_url( home_url(), PHP_URL_HOST );
		$default_allowed = array_filter( [ $site_host ] );
		$allowlist       = array_map( 'strtolower', array_unique( array_merge( $default_allowed, $allowed_hosts ) ) );

		if ( in_array( $host, $allowlist, true ) ) {
			return true;
		}

		return $allow_remote && $this->matches_allowlist_pattern( $host, $allowlist );
	}

	private function matches_allowlist_pattern( $host, array $allowlist ) {
		foreach ( $allowlist as $allowed_host ) {
			$pattern = '/^' . str_replace( '\*', '.*', preg_quote( trim( $allowed_host ), '/' ) ) . '$/i';
			if ( preg_match( $pattern, $host ) ) {
				return true;
			}
		}

		return false;
	}

	private function set_last_error( $code, $message, array $data = [] ) {
		$this->last_error = new \WP_Error( $code, $message, $data );
		$this->logger->warning( $message );
	}
}
