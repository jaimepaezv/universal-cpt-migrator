<?php

namespace UniversalCPTMigrator\Domain\Schema;

use UniversalCPTMigrator\Integration\ACF\ACFService;

class Analyzer {
	private $acf_service;

	public function __construct( ACFService $acf_service = null ) {
		$this->acf_service = $acf_service ?: new ACFService();
	}

	public function analyze_cpt( $post_type ) {
		$post_type_object = get_post_type_object( $post_type );
		$supports         = $this->get_post_type_supports( $post_type );

		$schema = array(
			'schema_version'   => 2,
			'post_type'        => $post_type,
			'post_type_object' => $this->get_post_type_definition( $post_type_object ),
			'supports'         => $supports,
			'post_statuses'    => $this->get_post_status_definitions(),
			'core_fields'      => $this->get_core_field_definitions( $supports ),
			'taxonomies'       => $this->get_taxonomy_definitions( $post_type ),
			'meta_fields'      => $this->get_meta_field_definitions( $post_type ),
			'acf_groups'       => $this->acf_service->get_field_groups_for_cpt( $post_type ),
		);

		return apply_filters( 'u_cpt_mgr_cpt_schema', $schema, $post_type );
	}

	private function get_post_type_definition( $post_type_object ) {
		if ( ! $post_type_object ) {
			return [];
		}

		return [
			'label'          => $post_type_object->label,
			'singular_label' => isset( $post_type_object->labels->singular_name ) ? $post_type_object->labels->singular_name : $post_type_object->label,
			'description'    => $post_type_object->description,
			'hierarchical'   => (bool) $post_type_object->hierarchical,
			'public'         => (bool) $post_type_object->public,
			'show_ui'        => (bool) $post_type_object->show_ui,
			'show_in_rest'   => (bool) $post_type_object->show_in_rest,
			'rest_base'      => isset( $post_type_object->rest_base ) ? $post_type_object->rest_base : '',
			'menu_icon'      => isset( $post_type_object->menu_icon ) ? $post_type_object->menu_icon : '',
			'capability_type'=> is_array( $post_type_object->capability_type ) ? $post_type_object->capability_type : [ $post_type_object->capability_type ],
			'has_archive'    => ! empty( $post_type_object->has_archive ),
		];
	}

	private function get_post_type_supports( $post_type ) {
		$support_keys = [
			'title',
			'editor',
			'excerpt',
			'thumbnail',
			'author',
			'comments',
			'revisions',
			'page-attributes',
			'custom-fields',
			'post-formats',
		];
		$supports     = [];

		foreach ( $support_keys as $feature ) {
			if ( post_type_supports( $post_type, $feature ) ) {
				$supports[] = $feature;
			}
		}

		return $supports;
	}

	private function get_core_field_definitions( array $supports ) {
		$fields = [
			'post_title'   => [ 'type' => 'text', 'required' => true, 'label' => 'Post Title' ],
			'post_status'  => [ 'type' => 'select', 'choices' => [ 'publish', 'draft', 'pending', 'private' ], 'label' => 'Status' ],
			'post_name'    => [ 'type' => 'slug', 'required' => false, 'label' => 'Slug' ],
			'post_date'    => [ 'type' => 'datetime', 'required' => false, 'label' => 'Publish Date' ],
		];

		if ( in_array( 'editor', $supports, true ) ) {
			$fields['post_content'] = [ 'type' => 'wysiwyg', 'required' => false, 'label' => 'Content' ];
		}

		if ( in_array( 'excerpt', $supports, true ) ) {
			$fields['post_excerpt'] = [ 'type' => 'textarea', 'required' => false, 'label' => 'Excerpt' ];
		}

		if ( in_array( 'author', $supports, true ) ) {
			$fields['post_author'] = [ 'type' => 'user_id', 'required' => false, 'label' => 'Author ID' ];
		}

		if ( in_array( 'thumbnail', $supports, true ) ) {
			$fields['_thumbnail_id'] = [ 'type' => 'image', 'required' => false, 'label' => 'Featured Image' ];
		}

		return $fields;
	}

	private function get_post_status_definitions() {
		$statuses   = get_post_stati( [ 'internal' => false ], 'objects' );
		$definition = [];

		foreach ( $statuses as $status ) {
			$definition[ $status->name ] = [
				'label'       => $status->label,
				'public'      => (bool) $status->public,
				'protected'   => (bool) $status->protected,
				'private'     => (bool) $status->private,
				'show_in_admin_status_list' => ! empty( $status->show_in_admin_status_list ),
			];
		}

		return $definition;
	}

	private function get_taxonomy_definitions( $post_type ) {
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		$defs       = [];

		foreach ( $taxonomies as $taxonomy ) {
			$defs[ $taxonomy->name ] = [
				'label'        => $taxonomy->label,
				'hierarchical' => $taxonomy->hierarchical,
				'public'       => $taxonomy->public,
				'show_ui'      => (bool) $taxonomy->show_ui,
				'show_in_rest' => (bool) $taxonomy->show_in_rest,
				'rest_base'    => isset( $taxonomy->rest_base ) ? $taxonomy->rest_base : '',
				'query_var'    => $taxonomy->query_var,
				'rewrite'      => ! empty( $taxonomy->rewrite ),
			];
		}

		return $defs;
	}

	private function get_meta_field_definitions( $post_type ) {
		global $wpdb;

		$sample_limit = (int) apply_filters( 'u_cpt_mgr_meta_sample_limit', 20, $post_type );
		$post_ids     = get_posts(
			[
				'post_type'              => $post_type,
				'post_status'            => 'any',
				'posts_per_page'         => max( 1, $sample_limit ),
				'fields'                 => 'ids',
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		if ( empty( $post_ids ) ) {
			return [];
		}

		$excluded_keys = apply_filters(
			'u_cpt_mgr_excluded_meta_keys',
			[
				'_edit_lock',
				'_edit_last',
				'_wp_old_slug',
				'_wp_attached_file',
				'_wp_attachment_metadata',
			],
			$post_type
		);

		$placeholders = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );
		$query        = $wpdb->prepare(
			"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($placeholders)",
			$post_ids
		);
		$rows         = $wpdb->get_results( $query, ARRAY_A );
		$fields       = [];

		foreach ( $rows as $row ) {
			$key = isset( $row['meta_key'] ) ? (string) $row['meta_key'] : '';

			if ( '' === $key || in_array( $key, $excluded_keys, true ) ) {
				continue;
			}

			if ( ! isset( $fields[ $key ] ) ) {
				$fields[ $key ] = [
					'type'    => $this->infer_meta_value_type( isset( $row['meta_value'] ) ? $row['meta_value'] : '' ),
					'private' => 0 === strpos( $key, '_' ),
				];
			}
		}

		ksort( $fields );

		return $fields;
	}

	private function infer_meta_value_type( $value ) {
		if ( '' === $value || null === $value ) {
			return 'empty';
		}

		if ( is_serialized( $value ) ) {
			$decoded = maybe_unserialize( $value );
			if ( is_array( $decoded ) ) {
				return array_values( $decoded ) === $decoded ? 'array' : 'object';
			}
		}

		if ( '1' === $value || '0' === $value ) {
			return 'boolean';
		}

		if ( is_numeric( $value ) ) {
			return false === strpos( (string) $value, '.' ) ? 'integer' : 'number';
		}

		return 'string';
	}
}
