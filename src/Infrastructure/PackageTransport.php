<?php

namespace UniversalCPTMigrator\Infrastructure;

use ZipArchive;

class PackageTransport {
	private $storage;

	public function __construct( Storage $storage = null ) {
		$this->storage = $storage ?: new Storage();
	}

	public function create_export_bundle( array $package, $post_type ) {
		$base_name  = sprintf( '%s-%s', sanitize_file_name( $post_type ), gmdate( 'Ymd-His' ) );
		$json_rel   = 'exports/' . $base_name . '.json';
		$zip_rel    = 'exports/' . $base_name . '.zip';
		$json_path  = $this->storage->get_path( $json_rel );
		$zip_path   = $this->storage->get_path( $zip_rel );
		$temp_media = $this->storage->get_path( 'temp/' . $base_name . '-media' );

		$this->storage->put_contents( $json_rel, wp_json_encode( $package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		if ( ! file_exists( $temp_media ) ) {
			wp_mkdir_p( $temp_media );
		}

		foreach ( $package['items'] as $item ) {
			if ( empty( $item['featured_media']['manifest']['relative_path'] ) ) {
				continue;
			}

			$source_id = ! empty( $item['featured_media']['source_id'] ) ? (int) $item['featured_media']['source_id'] : 0;
			$file      = $source_id ? get_attached_file( $source_id ) : '';
			if ( ! $file || ! file_exists( $file ) ) {
				continue;
			}

			$destination = trailingslashit( $temp_media ) . basename( $item['featured_media']['manifest']['relative_path'] );
			copy( $file, $destination );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new \WP_Error( 'ucm_zip_failed', __( 'Failed to create export ZIP bundle.', 'universal-cpt-migrator' ) );
		}

		$zip->addFile( $json_path, 'package.json' );
		foreach ( glob( trailingslashit( $temp_media ) . '*' ) as $media_file ) {
			if ( is_file( $media_file ) ) {
				$zip->addFile( $media_file, 'media/' . basename( $media_file ) );
			}
		}
		$zip->close();
		$this->storage->delete_relative( 'temp/' . $base_name . '-media' );

		return [
			'json_file' => basename( $json_rel ),
			'zip_file'  => basename( $zip_rel ),
			'zip_path'  => $zip_path,
		];
	}

	public function extract_import_bundle( array $file ) {
		$file_name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		$extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

		if ( 'json' === $extension ) {
			$contents = file_get_contents( $file['tmp_name'] );
			$package  = json_decode( $contents, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return new \WP_Error( 'ucm_malformed_package', __( 'The JSON package is malformed.', 'universal-cpt-migrator' ) );
			}
			return [
				'package' => $package,
				'type'    => 'json',
			];
		}

		if ( 'zip' !== $extension ) {
			return new \WP_Error( 'ucm_invalid_bundle', __( 'Only JSON and ZIP bundles are supported.', 'universal-cpt-migrator' ) );
		}

		$extract_dir = $this->storage->get_path( 'temp/import-' . wp_generate_uuid4() );
		wp_mkdir_p( $extract_dir );

		$zip = new ZipArchive();
		if ( true !== $zip->open( $file['tmp_name'] ) ) {
			return new \WP_Error( 'ucm_zip_open_failed', __( 'The ZIP package could not be opened.', 'universal-cpt-migrator' ) );
		}

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$entry = $zip->getNameIndex( $i );
			if ( false === $entry || $this->contains_path_traversal( $entry ) ) {
				$zip->close();
				return new \WP_Error( 'ucm_zip_unsafe', __( 'The ZIP package contains an unsafe path.', 'universal-cpt-migrator' ) );
			}
		}

		$zip->extractTo( $extract_dir );
		$zip->close();

		$package_file = trailingslashit( $extract_dir ) . 'package.json';
		if ( ! file_exists( $package_file ) ) {
			return new \WP_Error( 'ucm_missing_package', __( 'The ZIP bundle does not include package.json.', 'universal-cpt-migrator' ) );
		}

		$package = json_decode( file_get_contents( $package_file ), true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error( 'ucm_malformed_package', __( 'The package.json file is malformed.', 'universal-cpt-migrator' ) );
		}

		foreach ( $package['items'] as $index => $item ) {
			if ( empty( $item['featured_media']['manifest']['relative_path'] ) ) {
				continue;
			}

			$relative = ltrim( str_replace( '\\', '/', $item['featured_media']['manifest']['relative_path'] ), '/' );
			if ( $this->contains_path_traversal( $relative ) ) {
				continue;
			}

			$tmp_path = trailingslashit( $extract_dir ) . $relative;
			if ( file_exists( $tmp_path ) ) {
				$package['items'][ $index ]['featured_media']['manifest']['tmp_path'] = $tmp_path;
			}
		}

		return [
			'package'     => $package,
			'type'        => 'zip',
			'extract_dir' => $extract_dir,
		];
	}

	private function contains_path_traversal( $path ) {
		return false !== strpos( $path, '..' ) || str_starts_with( $path, '/' ) || preg_match( '/^[A-Za-z]:[\\\\\\/]/', $path );
	}

	public function cleanup_extracted_bundle( $extract_dir ) {
		if ( ! $extract_dir ) {
			return;
		}

		$base = wp_normalize_path( untrailingslashit( $this->storage->get_path( 'temp' ) ) );
		$real = realpath( $extract_dir );
		if ( ! $real ) {
			return;
		}

		$real = wp_normalize_path( $real );
		if ( 0 !== strpos( $real, $base ) ) {
			return;
		}

		$relative = ltrim( str_replace( $base, '', $real ), '/\\' );
		$this->storage->delete_relative( 'temp/' . $relative );
	}
}
