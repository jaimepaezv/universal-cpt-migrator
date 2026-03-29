<?php

namespace UniversalCPTMigrator\Domain\Import;

use UniversalCPTMigrator\Infrastructure\Logger;
use UniversalCPTMigrator\Integration\ACF\ACFService;

class RelationshipMapper {
	private $logger;
	private $acf_service;

	public function __construct( Logger $logger = null, ACFService $acf_service = null ) {
		$this->logger      = $logger ?: new Logger();
		$this->acf_service = $acf_service ?: new ACFService();
	}

	public function extract_post_references( array $item ) {
		$references = [];

		$this->walk_structure(
			$item,
			function( $value, $path ) use ( &$references ) {
				if ( is_array( $value ) && ! empty( $value['uuid'] ) && wp_is_uuid( $value['uuid'] ) ) {
					$references[ $path ] = sanitize_text_field( $value['uuid'] );
				}
			}
		);

		return $references;
	}

	public function remap_item_relationships( array $item ) {
		return $this->walk_and_transform(
			$item,
			function( $value ) {
				if ( ! is_array( $value ) || empty( $value['uuid'] ) || ! wp_is_uuid( $value['uuid'] ) ) {
					return $value;
				}

				$post_id = $this->get_post_id_by_uuid( $value['uuid'] );
				if ( ! $post_id ) {
					$this->logger->warning( sprintf( 'Relationship target missing for UUID %s.', $value['uuid'] ) );
					return null;
				}

				return (int) $post_id;
			}
		);
	}

	public function remap_acf_fields( array $acf_data, $post_type ) {
		$field_map = $this->acf_service->get_field_map_for_cpt( $post_type );
		if ( empty( $field_map ) ) {
			return $this->remap_item_relationships( $acf_data );
		}

		$mapped = [];
		foreach ( $acf_data as $field_name => $value ) {
			$definition          = isset( $field_map[ $field_name ] ) ? $field_map[ $field_name ] : null;
			$mapped[ $field_name ] = $this->remap_field_value( $value, $definition );
		}

		return $mapped;
	}

	private function get_post_id_by_uuid( $uuid ) {
		$query = new \WP_Query(
			[
				'post_type'      => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_ucm_uuid',
				'meta_value'     => sanitize_text_field( $uuid ),
			]
		);

		return $query->have_posts() ? (int) $query->posts[0] : 0;
	}

	private function remap_field_value( $value, $definition ) {
		if ( empty( $definition ) || empty( $definition['type'] ) ) {
			return $this->walk_and_transform(
				$value,
				function( $candidate ) {
					if ( ! is_array( $candidate ) || empty( $candidate['uuid'] ) || ! wp_is_uuid( $candidate['uuid'] ) ) {
						return $candidate;
					}

					$post_id = $this->get_post_id_by_uuid( $candidate['uuid'] );
					return $post_id ? (int) $post_id : null;
				}
			);
		}

		switch ( $definition['type'] ) {
			case 'relationship':
			case 'post_object':
			case 'page_link':
				return $this->normalize_relationship_value( $value, $definition );
			case 'group':
				return $this->remap_group_value( $value, $definition );
			case 'repeater':
				return $this->remap_repeater_value( $value, $definition );
			case 'flexible_content':
				return $this->remap_flexible_value( $value, $definition );
			default:
				return $value;
		}
	}

	private function remap_group_value( $value, $definition ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$result = [];
		foreach ( $value as $sub_name => $sub_value ) {
			$sub_definition       = $this->find_sub_field_definition( $definition, $sub_name );
			$result[ $sub_name ] = $this->remap_field_value( $sub_value, $sub_definition );
		}

		return $result;
	}

	private function remap_repeater_value( $value, $definition ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$result = [];
		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				$result[] = $row;
				continue;
			}

			$mapped_row = [];
			foreach ( $row as $sub_name => $sub_value ) {
				$sub_definition         = $this->find_sub_field_definition( $definition, $sub_name );
				$mapped_row[ $sub_name ] = $this->remap_field_value( $sub_value, $sub_definition );
			}
			$result[] = $mapped_row;
		}

		return $result;
	}

	private function remap_flexible_value( $value, $definition ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$result = [];
		foreach ( $value as $layout_row ) {
			if ( ! is_array( $layout_row ) ) {
				$result[] = $layout_row;
				continue;
			}

			$layout_name = isset( $layout_row['acf_fc_layout'] ) ? $layout_row['acf_fc_layout'] : '';
			$layout_def  = ! empty( $definition['layouts'][ $layout_name ] ) ? $definition['layouts'][ $layout_name ] : null;
			$mapped_row  = $layout_row;

			foreach ( $layout_row as $sub_name => $sub_value ) {
				if ( 'acf_fc_layout' === $sub_name ) {
					continue;
				}

				$sub_definition         = $this->find_layout_sub_field_definition( $layout_def, $sub_name );
				$mapped_row[ $sub_name ] = $this->remap_field_value( $sub_value, $sub_definition );
			}

			$result[] = $mapped_row;
		}

		return $result;
	}

	private function normalize_relationship_value( $value, $definition ) {
		$is_multiple = 'relationship' === $definition['type'] || ( isset( $definition['return_format'] ) && 'array' === $definition['return_format'] );

		if ( $is_multiple ) {
			$values = is_array( $value ) ? $value : [ $value ];
			$result = [];
			foreach ( $values as $candidate ) {
				$post_id = $this->extract_post_id_from_candidate( $candidate );
				if ( $post_id ) {
					$result[] = $post_id;
				}
			}
			return $result;
		}

		return $this->extract_post_id_from_candidate( $value );
	}

	private function extract_post_id_from_candidate( $candidate ) {
		if ( is_numeric( $candidate ) ) {
			return (int) $candidate;
		}

		if ( is_array( $candidate ) && ! empty( $candidate['uuid'] ) && wp_is_uuid( $candidate['uuid'] ) ) {
			$post_id = $this->get_post_id_by_uuid( $candidate['uuid'] );
			if ( $post_id ) {
				return $post_id;
			}

			$this->logger->warning( sprintf( 'Relationship target missing for UUID %s.', $candidate['uuid'] ) );
		}

		return 0;
	}

	private function find_sub_field_definition( $definition, $sub_name ) {
		if ( empty( $definition['sub_fields'] ) || ! is_array( $definition['sub_fields'] ) ) {
			return null;
		}

		foreach ( $definition['sub_fields'] as $sub_field ) {
			if ( isset( $sub_field['name'] ) && $sub_field['name'] === $sub_name ) {
				return $sub_field;
			}
		}

		return null;
	}

	private function find_layout_sub_field_definition( $layout_definition, $sub_name ) {
		if ( empty( $layout_definition['sub_fields'] ) || ! is_array( $layout_definition['sub_fields'] ) ) {
			return null;
		}

		foreach ( $layout_definition['sub_fields'] as $sub_field ) {
			if ( isset( $sub_field['name'] ) && $sub_field['name'] === $sub_name ) {
				return $sub_field;
			}
		}

		return null;
	}

	private function walk_structure( $value, callable $callback, $path = 'root' ) {
		if ( is_array( $value ) ) {
			$callback( $value, $path );
			foreach ( $value as $key => $child ) {
				$this->walk_structure( $child, $callback, $path . '.' . $key );
			}
		}
	}

	private function walk_and_transform( $value, callable $callback ) {
		if ( is_array( $value ) ) {
			$transformed = $callback( $value );
			if ( ! is_array( $transformed ) ) {
				return $transformed;
			}

			foreach ( $transformed as $key => $child ) {
				$transformed[ $key ] = $this->walk_and_transform( $child, $callback );
			}

			return $transformed;
		}

		return $value;
	}
}
