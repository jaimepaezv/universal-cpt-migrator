<?php

namespace UniversalCPTMigrator\Domain\Discovery;

class DiscoveryService {
	public function get_all_cpts() {
		$post_types        = [];
		$include_builtins  = apply_filters( 'u_cpt_mgr_include_builtin_types', [ 'post', 'page' ] );
		$exclude_types     = apply_filters(
			'u_cpt_mgr_exclude_types',
			[
				'attachment',
				'revision',
				'nav_menu_item',
				'custom_css',
				'customize_changeset',
				'oembed_cache',
				'user_request',
				'wp_block',
				'wp_template',
				'wp_template_part',
				'wp_global_styles',
				'wp_navigation',
				'acf-field-group',
				'acf-field',
			]
		);

		$candidates = array_merge(
			get_post_types( [ 'show_ui' => true ], 'objects' ),
			get_post_types( [ 'public' => true ], 'objects' )
		);

		foreach ( $include_builtins as $builtin ) {
			$builtin_object = get_post_type_object( $builtin );
			if ( $builtin_object ) {
				$candidates[ $builtin ] = $builtin_object;
			}
		}

		foreach ( $candidates as $slug => $object ) {
			if ( empty( $object ) || ! is_object( $object ) || empty( $object->name ) ) {
				continue;
			}

			$slug = sanitize_key( $slug );
			if ( in_array( $slug, $exclude_types, true ) ) {
				continue;
			}

			$post_types[ $slug ] = $object;
		}

		uasort(
			$post_types,
			static function( $left, $right ) {
				$left_label  = isset( $left->label ) ? (string) $left->label : ( isset( $left->name ) ? (string) $left->name : '' );
				$right_label = isset( $right->label ) ? (string) $right->label : ( isset( $right->name ) ? (string) $right->name : '' );
				return strnatcasecmp( $left_label, $right_label );
			}
		);

		return apply_filters( 'u_cpt_mgr_discovered_cpts', $post_types );
	}

	public function get_taxonomies_for_cpt( $post_type ) {
		return get_object_taxonomies( $post_type, 'objects' );
	}

	public function get_all_cpts_summary() {
		$summaries = [];

		foreach ( $this->get_all_cpts() as $slug => $object ) {
			$summaries[] = $this->summarize_post_type( $slug, $object );
		}

		return $summaries;
	}

	public function summarize_post_type( $slug, $object ) {
		$slug       = sanitize_key( $slug );
		$count      = wp_count_posts( $slug );
		$supports   = get_all_post_type_supports( $slug );
		$taxonomies = get_object_taxonomies( $slug, 'objects' );

		return [
			'slug'           => $slug,
			'label'          => isset( $object->label ) ? (string) $object->label : $slug,
			'singular_label' => isset( $object->labels->singular_name ) ? (string) $object->labels->singular_name : ( isset( $object->label ) ? (string) $object->label : $slug ),
			'description'    => isset( $object->description ) ? (string) $object->description : '',
			'count'          => isset( $count->publish ) ? (int) $count->publish : 0,
			'hierarchical'   => ! empty( $object->hierarchical ),
			'show_in_rest'   => ! empty( $object->show_in_rest ),
			'public'         => ! empty( $object->public ),
			'show_ui'        => ! empty( $object->show_ui ),
			'visibility'     => ! empty( $object->public ) ? 'public' : ( ! empty( $object->show_ui ) ? 'admin-only' : 'internal' ),
			'supports'       => array_values( array_keys( array_filter( $supports ) ) ),
			'taxonomies'     => array_values(
				array_map(
					static function( $taxonomy ) {
						return isset( $taxonomy->name ) ? (string) $taxonomy->name : '';
					},
					$taxonomies
				)
			),
		];
	}
}
