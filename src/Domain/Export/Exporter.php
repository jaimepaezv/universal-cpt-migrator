<?php

namespace UniversalCPTMigrator\Domain\Export;

use UniversalCPTMigrator\Infrastructure\Logger;
use UniversalCPTMigrator\Domain\Schema\Analyzer;
use UniversalCPTMigrator\Infrastructure\Storage;
use UniversalCPTMigrator\Domain\Import\RelationshipMapper;
use UniversalCPTMigrator\Infrastructure\Settings;
use UniversalCPTMigrator\Integration\ACF\ACFValueMapper;

class Exporter {
	private $logger;
	private $analyzer;
	private $storage;
	private $relationship_mapper;
	private $settings;
	private $acf_value_mapper;

	public function __construct( Logger $logger = null, ACFValueMapper $acf_value_mapper = null ) {
		$this->logger              = $logger ?: new Logger();
		$this->analyzer            = new Analyzer();
		$this->storage             = new Storage();
		$this->relationship_mapper = new RelationshipMapper( $this->logger );
		$this->settings            = new Settings();
		$this->acf_value_mapper    = $acf_value_mapper ?: new ACFValueMapper( $this->logger, null, null, [ $this, 'map_media_attachment' ] );
	}

	public function export_cpt( $post_type, $per_page = -1, $offset = 0 ) {
		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'post_status'    => 'any',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		$query = new \WP_Query( $args );
		$exported = [];

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$exported[] = $this->transform_post( $post );
				$this->logger->info( "Exporting post ID: {$post->ID} - {$post->post_title}" );
			}
		}

		return apply_filters( 'u_cpt_mgr_export_data', $exported, $post_type );
	}

	public function build_package( $post_type ) {
		$chunk_size = max( 1, (int) $this->settings->get( 'chunk_size' ) );
		$offset     = 0;
		$items      = [];

		do {
			$chunk = $this->export_cpt( $post_type, $chunk_size, $offset );
			$items = array_merge( $items, $chunk );
			$offset += count( $chunk );
		} while ( count( $chunk ) === $chunk_size );

		$schema  = $this->analyzer->analyze_cpt( $post_type );
		$package = [
			'metadata' => [
				'plugin'     => 'Universal CPT Migrator',
				'version'    => UCM_VERSION,
				'post_type'  => $post_type,
				'generated'  => gmdate( 'c' ),
				'item_count' => count( $items ),
				'chunk_size' => $chunk_size,
				'total_chunks' => (int) ceil( max( 1, count( $items ) ) / $chunk_size ),
			],
			'schema'   => $schema,
			'items'    => $items,
		];

		$filename = sprintf( 'exports/%s-%s.json', sanitize_file_name( $post_type ), gmdate( 'Ymd-His' ) );
		$this->storage->put_contents( $filename, wp_json_encode( $package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		$this->logger->info( sprintf( 'Built export package for post type "%s" with %d items.', $post_type, count( $items ) ) );

		return [
			'package'  => $package,
			'file'     => basename( $filename ),
			'log_id'   => $this->logger->get_log_id(),
			'log_path' => $this->logger->get_log_path(),
		];
	}

	private function transform_post( $post ) {
		// 1. Ensure a stable UUID exists for this post
		$uuid = get_post_meta( $post->ID, '_ucm_uuid', true );
		if ( ! $uuid ) {
			$uuid = wp_generate_uuid4();
			update_post_meta( $post->ID, '_ucm_uuid', $uuid );
		}

		// 2. Map Core Fields
		$acf_values  = $this->get_acf_values( $post->ID, $post->post_type );
		$meta_values = $this->get_exportable_meta( $post->ID, $post->post_type );

		$data = [
			'uuid'         => $uuid,
			'post_type'    => $post->post_type,
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_status'  => $post->post_status,
			'post_name'    => $post->post_name,
			'post_date'    => $post->post_date,
			'post_author'  => $this->map_author( $post->post_author ),
			'taxonomies'   => $this->get_post_taxonomies( $post->ID ),
			'acf'          => $acf_values,
			'meta'         => $meta_values,
			'relationships'=> $this->relationship_mapper->extract_post_references(
				[
					'acf'  => $acf_values,
					'meta' => $meta_values,
				]
			),
		];

		// 3. Map Featured Image
		$thumb_id = get_post_thumbnail_id( $post->ID );
		if ( $thumb_id ) {
			$data['featured_media'] = $this->map_media_attachment( $thumb_id );
		}

		return $data;
	}

	private function map_author( $author_id ) {
		$user = get_userdata( $author_id );
		return $user ? $user->user_login : 'admin';
	}

	private function get_post_taxonomies( $post_id ) {
		$tax_objects = get_object_taxonomies( get_post_type( $post_id ), 'objects' );
		$terms_data  = [];

		foreach ( $tax_objects as $tax ) {
			$terms = wp_get_post_terms( $post_id, $tax->name );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$terms_data[ $tax->name ] = array_map( function( $t ) {
					return [
						'name'   => $t->name,
						'slug'   => $t->slug,
						'parent' => $t->parent ? get_term( $t->parent )->slug : '',
					];
				}, $terms );
			}
		}

		return $terms_data;
	}

	private function get_acf_values( $post_id, $post_type ) {
		if ( ! function_exists( 'get_fields' ) ) {
			return [];
		}

		$values = get_fields( $post_id );
		if ( empty( $values ) || ! is_array( $values ) ) {
			return [];
		}

		return $this->acf_value_mapper->export_values( $values, $post_type );
	}

	private function get_exportable_meta( $post_id, $post_type ) {
		$meta              = get_post_meta( $post_id );
		$exportable        = [];
		$relationship_keys = $this->get_meta_relationship_keys( $post_type );

		foreach ( $meta as $key => $values ) {
			$key = (string) $key;
			if ( ! $this->is_exportable_meta_key( $key, $post_type ) ) {
				continue;
			}

			$value = maybe_unserialize( $values[0] );
			if ( in_array( $key, $relationship_keys, true ) ) {
				$value = $this->normalize_meta_relationship_value( $value );
			}

			$exportable[ $key ] = $value;
		}

		return $exportable;
	}

	private function is_exportable_meta_key( $key, $post_type ) {
		if ( '_ucm_uuid' === $key || '_edit_lock' === $key || '_edit_last' === $key ) {
			return false;
		}

		$allowlist = apply_filters( 'u_cpt_mgr_meta_allowlist', [], $post_type );
		if ( in_array( $key, $allowlist, true ) ) {
			return true;
		}

		if ( ! is_protected_meta( $key, 'post' ) ) {
			return true;
		}

		$registered = get_registered_meta_keys( 'post', $post_type );
		return isset( $registered[ $key ] );
	}

	private function get_meta_relationship_keys( $post_type ) {
		$keys = apply_filters( 'u_cpt_mgr_meta_relationship_keys', [], $post_type );
		return array_values( array_unique( array_map( 'sanitize_key', array_filter( (array) $keys ) ) ) );
	}

	private function normalize_meta_relationship_value( $value ) {
		if ( is_numeric( $value ) ) {
			return $this->build_post_reference( (int) $value );
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		$normalized = [];
		foreach ( $value as $item_key => $item_value ) {
			if ( is_numeric( $item_value ) ) {
				$normalized[ $item_key ] = $this->build_post_reference( (int) $item_value );
				continue;
			}

			$normalized[ $item_key ] = $item_value;
		}

		return $normalized;
	}

	private function build_post_reference( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		$uuid = get_post_meta( $post_id, '_ucm_uuid', true );
		if ( ! $uuid ) {
			$uuid = wp_generate_uuid4();
			update_post_meta( $post_id, '_ucm_uuid', $uuid );
		}

		return [
			'uuid'      => $uuid,
			'post_type' => $post->post_type,
			'title'     => $post->post_title,
		];
	}

	public function map_media_attachment( $attachment_id ) {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment ) return null;

		return [
			'url'         => wp_get_attachment_url( $attachment_id ),
			'title'       => $attachment->post_title,
			'slug'        => $attachment->post_name,
			'alt'         => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'source_id'   => (int) $attachment_id,
			'source_hash' => md5( (string) wp_get_attachment_url( $attachment_id ) ),
			'content_hash' => $this->get_attachment_content_hash( $attachment_id ),
			'manifest'     => $this->build_media_manifest( $attachment_id ),
		];
	}

	private function get_attachment_content_hash( $attachment_id ) {
		$existing = get_post_meta( $attachment_id, '_ucm_content_hash', true );
		if ( $existing ) {
			return $existing;
		}

		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return '';
		}

		$hash = md5_file( $file );
		if ( $hash ) {
			update_post_meta( $attachment_id, '_ucm_content_hash', $hash );
		}

		return $hash ? $hash : '';
	}

	private function build_media_manifest( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file || ! file_exists( $file ) ) {
			return null;
		}

		return [
			'filename'      => basename( $file ),
			'relative_path' => 'media/' . basename( $file ),
			'mime_type'     => get_post_mime_type( $attachment_id ),
			'content_hash'  => $this->get_attachment_content_hash( $attachment_id ),
		];
	}
}
