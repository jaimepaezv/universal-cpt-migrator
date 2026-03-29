<?php

namespace UniversalCPTMigrator\Domain\Import;

use UniversalCPTMigrator\Infrastructure\Logger;
use UniversalCPTMigrator\Infrastructure\Settings;
use UniversalCPTMigrator\Integration\ACF\ACFValueMapper;

class Processor {
	private $logger;
	private $relationship_mapper;
	private $media_service;
	private $settings;
	private $acf_value_mapper;

	public function __construct( Logger $logger = null, ACFValueMapper $acf_value_mapper = null ) {
		$this->logger              = $logger ?: new Logger();
		$this->relationship_mapper = new RelationshipMapper( $this->logger );
		$this->media_service       = new MediaSideloadService( $this->logger );
		$this->settings            = new Settings();
		$this->acf_value_mapper    = $acf_value_mapper ?: new ACFValueMapper( $this->logger, null, $this->media_service );
	}

	public function import_package( $package, $dry_run = false, $offset = 0, $limit = 0 ) {
		$post_type = isset( $package['metadata']['post_type'] ) ? sanitize_key( $package['metadata']['post_type'] ) : '';
		$items     = isset( $package['items'] ) && is_array( $package['items'] ) ? $package['items'] : [];
		$limit     = $limit > 0 ? $limit : (int) $this->settings->get( 'chunk_size' );
		$slice     = array_slice( $items, max( 0, absint( $offset ) ), $limit );
		$results   = [
			'imported' => 0,
			'updated'  => 0,
			'failed'   => 0,
			'items'    => [],
			'offset'   => max( 0, absint( $offset ) ),
			'limit'    => $limit,
			'has_more' => false,
			'next_offset' => 0,
		];

		if ( ! $post_type || empty( $items ) ) {
			return new \WP_Error( 'ucm_invalid_package', __( 'Package is missing a valid post type or items collection.', 'universal-cpt-migrator' ) );
		}

		foreach ( $slice as $item ) {
			$result = $this->import_item( $item, $post_type, $dry_run );

			if ( is_wp_error( $result ) ) {
				$results['failed']++;
				$results['items'][] = [
					'uuid'    => isset( $item['uuid'] ) ? $item['uuid'] : '',
					'status'  => 'failed',
					'message' => $result->get_error_message(),
				];
				continue;
			}

			if ( ! empty( $result['updated'] ) ) {
				$results['updated']++;
			} else {
				$results['imported']++;
			}

			$results['items'][] = $result;
		}

		$results['next_offset'] = $results['offset'] + count( $slice );
		$results['has_more']    = $results['next_offset'] < count( $items );

		return $results;
	}

	public function import_item( $item, $post_type, $dry_run = false ) {
		// 1. Check for existing post by UUID
		$uuid        = isset( $item['uuid'] ) ? sanitize_text_field( $item['uuid'] ) : '';
		$existing_id = $uuid ? $this->get_post_id_by_uuid( $uuid ) : null;

		if ( ! $uuid || ! wp_is_uuid( $uuid ) ) {
			return new \WP_Error( 'ucm_invalid_uuid', __( 'Each imported item must provide a valid UUID.', 'universal-cpt-migrator' ) );
		}

		$post_data = array(
			'post_title'   => isset( $item['post_title'] ) ? sanitize_text_field( $item['post_title'] ) : '',
			'post_content' => isset( $item['post_content'] ) ? wp_kses_post( $item['post_content'] ) : '',
			'post_excerpt' => isset( $item['post_excerpt'] ) ? sanitize_textarea_field( $item['post_excerpt'] ) : '',
			'post_status'  => isset( $item['post_status'] ) ? sanitize_key( $item['post_status'] ) : 'draft',
			'post_name'    => isset( $item['post_name'] ) ? sanitize_title( $item['post_name'] ) : '',
			'post_type'    => $post_type,
			'post_date'    => isset( $item['post_date'] ) ? $item['post_date'] : current_time( 'mysql' ),
			'post_author'  => $this->resolve_author_id( isset( $item['post_author'] ) ? $item['post_author'] : '' ),
		);

		if ( $dry_run ) {
			return [
				'uuid'    => $uuid,
				'post_id' => $existing_id ? (int) $existing_id : 0,
				'status'  => $existing_id ? 'would_update' : 'would_create',
				'updated' => ! empty( $existing_id ),
				'warnings' => [],
				'message' => $existing_id
					? __( 'Dry run: existing record would be updated.', 'universal-cpt-migrator' )
					: __( 'Dry run: new record would be created.', 'universal-cpt-migrator' ),
			];
		}

		$item_warnings = [];

		if ( $existing_id ) {
			$post_data['ID'] = $existing_id;
			$this->logger->info( "Updating existing post ID: $existing_id - UUID: {$uuid}" );
			$post_id = wp_update_post( $post_data );
		} else {
			$this->logger->info( "Creating new post for UUID: {$uuid}" );
			$post_id = wp_insert_post( $post_data );
		}

		if ( is_wp_error( $post_id ) ) {
			$this->logger->error( "Failed to import post: " . $post_id->get_error_message() );
			return $post_id;
		}

		update_post_meta( $post_id, '_ucm_uuid', $uuid );

		// 2. Map Taxonomies
		if ( ! empty( $item['taxonomies'] ) && is_array( $item['taxonomies'] ) ) {
			$this->map_taxonomies( $post_id, $item['taxonomies'] );
		}

		// 3. Map ACF
		if ( ! empty( $item['acf'] ) && is_array( $item['acf'] ) ) {
			$item_warnings = array_merge( $item_warnings, $this->map_acf_fields( $post_id, $item['acf'], $post_type ) );
		}

		// 4. Map allowlisted meta.
		if ( ! empty( $item['meta'] ) && is_array( $item['meta'] ) ) {
			$this->map_meta( $post_id, $item['meta'], $post_type );
		}

		// 5. Map Featured Image
		if ( ! empty( $item['featured_media'] ) && is_array( $item['featured_media'] ) ) {
			$item_warnings = array_merge( $item_warnings, $this->map_featured_media( $post_id, $item['featured_media'] ) );
		}

		return [
			'uuid'    => $uuid,
			'post_id' => (int) $post_id,
			'status'  => $existing_id ? 'updated' : 'created',
			'updated' => ! empty( $existing_id ),
			'warnings' => $item_warnings,
			'message' => $existing_id
				? __( 'Existing record updated.', 'universal-cpt-migrator' )
				: __( 'New record created.', 'universal-cpt-migrator' ),
		];
	}

	private function get_post_id_by_uuid( $uuid ) {
		$query = new \WP_Query( array(
			'meta_key'       => '_ucm_uuid',
			'meta_value'      => $uuid,
			'posts_per_page'  => 1,
			'post_type'       => 'any',
			'fields'          => 'ids',
		) );

		return ( $query->have_posts() ) ? $query->posts[0] : null;
	}

	private function map_taxonomies( $post_id, $taxonomies ) {
		foreach ( $taxonomies as $taxonomy => $terms ) {
			if ( ! taxonomy_exists( $taxonomy ) || ! is_array( $terms ) ) {
				continue;
			}

			$term_ids = [];
			foreach ( $terms as $term_data ) {
				if ( empty( $term_data['slug'] ) || empty( $term_data['name'] ) ) {
					continue;
				}

				$term = get_term_by( 'slug', $term_data['slug'], $taxonomy );
				if ( ! $term ) {
					$args = [ 'slug' => sanitize_title( $term_data['slug'] ) ];
					if ( ! empty( $term_data['parent'] ) ) {
						$parent = get_term_by( 'slug', sanitize_title( $term_data['parent'] ), $taxonomy );
						if ( $parent && ! is_wp_error( $parent ) ) {
							$args['parent'] = (int) $parent->term_id;
						}
					}

					$new_term = wp_insert_term( sanitize_text_field( $term_data['name'] ), $taxonomy, $args );
					if ( ! is_wp_error( $new_term ) ) {
						$term_ids[] = (int) $new_term['term_id'];
					}
				} else {
					$term_ids[] = (int) $term->term_id;
				}
			}
			wp_set_object_terms( $post_id, $term_ids, $taxonomy );
		}
	}

	private function map_acf_fields( $post_id, $acf_data, $post_type ) {
		if ( ! function_exists( 'update_field' ) ) {
			return [
				[
					'code'      => 'ucm_acf_unavailable',
					'subsystem' => 'acf_runtime',
					'message'   => __( 'ACF fields were present in the package but Advanced Custom Fields is not active on this site.', 'universal-cpt-migrator' ),
					'context'   => [ 'post_type' => $post_type ],
				],
			];
		}

		$warnings    = [];
		$remapped    = $this->relationship_mapper->remap_acf_fields( $acf_data, $post_type );
		$normalized  = $this->acf_value_mapper->import_values( $remapped, $post_type, $warnings );

		foreach ( $normalized as $key => $value ) {
			update_field( $key, $value, $post_id );
		}

		return $warnings;
	}

	private function map_meta( $post_id, $meta_data, $post_type ) {
		$allowlist         = apply_filters( 'u_cpt_mgr_meta_allowlist', [], $post_type );
		$relationship_keys = array_values( array_unique( array_map( 'sanitize_key', array_filter( (array) apply_filters( 'u_cpt_mgr_meta_relationship_keys', [], $post_type ) ) ) ) );
		$registered        = get_registered_meta_keys( 'post', $post_type );

		foreach ( $meta_data as $key => $value ) {
			$key = sanitize_key( $key );
			$is_allowed = in_array( $key, $allowlist, true ) || ! is_protected_meta( $key, 'post' ) || isset( $registered[ $key ] );
			if ( ! $is_allowed || '_ucm_uuid' === $key ) {
				continue;
			}

			if ( in_array( $key, $relationship_keys, true ) ) {
				$value = $this->relationship_mapper->remap_item_relationships( $value );
			}

			update_post_meta( $post_id, $key, maybe_unserialize( $value ) );
		}
	}

	private function map_featured_media( $post_id, $media ) {
		$attachment_id = $this->media_service->import_featured_media( $media );
		if ( $attachment_id ) {
			set_post_thumbnail( $post_id, $attachment_id );
			return [];
		}

		$error = $this->media_service->get_last_error();
		if ( ! is_wp_error( $error ) ) {
			return [];
		}

		return [
			$this->format_item_warning( $error ),
		];
	}

	private function format_item_warning( \WP_Error $error ) {
		$data = $error->get_error_data();
		$data = is_array( $data ) ? $data : [];

		return [
			'code'      => (string) $error->get_error_code(),
			'subsystem' => ! empty( $data['subsystem'] ) ? (string) $data['subsystem'] : 'import_media',
			'message'   => $error->get_error_message(),
			'context'   => $data,
		];
	}

	private function resolve_author_id( $author_login ) {
		if ( empty( $author_login ) ) {
			return get_current_user_id();
		}

		if ( is_numeric( $author_login ) ) {
			$user = get_user_by( 'id', (int) $author_login );
			return $user ? (int) $user->ID : get_current_user_id();
		}

		$user = get_user_by( 'login', sanitize_user( $author_login, true ) );

		return $user ? (int) $user->ID : get_current_user_id();
	}
}
