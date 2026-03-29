<?php

namespace UniversalCPTMigrator\Integration\ACF;

class ACFService {
	public function is_active() {
		return function_exists( 'acf_get_field_groups' );
	}

	public function get_field_groups_for_cpt( $post_type ) {
		if ( ! $this->is_active() ) {
			return [];
		}

		$groups = acf_get_field_groups( [ 'post_type' => $post_type ] );
		$detailed_groups = [];

		foreach ( $groups as $group ) {
			$detailed_groups[] = [
				'key'      => $group['key'],
				'title'    => $group['title'],
				'fields'   => $this->get_fields_recursively( $group['key'] ),
				'location' => $group['location'],
			];
		}

		return $detailed_groups;
	}

	public function get_field_map_for_cpt( $post_type ) {
		$groups = $this->get_field_groups_for_cpt( $post_type );
		$map    = [];

		foreach ( $groups as $group ) {
			if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
				continue;
			}

			foreach ( $group['fields'] as $field ) {
				$this->flatten_field_map( $map, $field );
			}
		}

		return $map;
	}

	public function get_fields_recursively( $parent_id ) {
		$fields = acf_get_fields( $parent_id );
		if ( ! $fields ) {
			return [];
		}

		$parsed_fields = [];
		foreach ( $fields as $field ) {
			$parsed_fields[] = $this->normalize_field( $field );
		}

		return $parsed_fields;
	}

	private function normalize_field( array $field ) {
		$normalized = $this->normalize_ad_hoc_field( $field );

		if ( in_array( $normalized['type'], [ 'repeater', 'flexible_content', 'group' ], true ) ) {
			$normalized['sub_fields'] = $this->get_fields_recursively( $field['key'] );
		}

		if ( ! empty( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
			$normalized['layouts'] = [];
			foreach ( $field['layouts'] as $layout ) {
				$layout_fields = [];
				if ( ! empty( $layout['sub_fields'] ) ) {
					foreach ( $layout['sub_fields'] as $sub_field ) {
						$layout_fields[] = $this->normalize_ad_hoc_field( $sub_field );
					}
				}

				$normalized['layouts'][ $layout['name'] ] = [
					'name'       => $layout['name'],
					'label'      => isset( $layout['label'] ) ? $layout['label'] : $layout['name'],
					'display'    => isset( $layout['display'] ) ? $layout['display'] : '',
					'sub_fields' => $layout_fields,
				];
			}
		}

		return $normalized;
	}

	private function flatten_field_map( array &$map, array $field, $prefix = '' ) {
		if ( empty( $field['name'] ) ) {
			return;
		}

		$name = $prefix ? $prefix . '.' . $field['name'] : $field['name'];
		$map[ $name ] = $field;

		if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			foreach ( $field['sub_fields'] as $sub_field ) {
				$this->flatten_field_map( $map, $sub_field, $name );
			}
		}

		if ( ! empty( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
			foreach ( $field['layouts'] as $layout ) {
				if ( empty( $layout['sub_fields'] ) ) {
					continue;
				}
				foreach ( $layout['sub_fields'] as $sub_field ) {
					$this->flatten_field_map( $map, $sub_field, $name );
				}
			}
		}
	}

	private function normalize_ad_hoc_field( array $field ) {
		$normalized = [
			'key'          => isset( $field['key'] ) ? $field['key'] : '',
			'name'         => isset( $field['name'] ) ? $field['name'] : '',
			'label'        => isset( $field['label'] ) ? $field['label'] : '',
			'type'         => isset( $field['type'] ) ? $field['type'] : 'text',
			'required'     => isset( $field['required'] ) ? $field['required'] : 0,
			'instructions' => isset( $field['instructions'] ) ? $field['instructions'] : '',
		];

		$copy_keys = [
			'choices',
			'default_value',
			'return_format',
			'multiple',
			'allow_null',
			'min',
			'max',
			'taxonomy',
			'post_type',
			'mime_types',
			'library',
			'conditional_logic',
			'wrapper',
			'clone',
			'display',
			'prefix_name',
		];

		foreach ( $copy_keys as $key ) {
			if ( isset( $field[ $key ] ) ) {
				$normalized[ $key ] = $field[ $key ];
			}
		}

		if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			$normalized['sub_fields'] = [];
			foreach ( $field['sub_fields'] as $sub_field ) {
				$normalized['sub_fields'][] = $this->normalize_ad_hoc_field( $sub_field );
			}
		}

		if ( ! empty( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
			$normalized['layouts'] = [];
			foreach ( $field['layouts'] as $layout ) {
				$layout_fields = [];
				if ( ! empty( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
					foreach ( $layout['sub_fields'] as $sub_field ) {
						$layout_fields[] = $this->normalize_ad_hoc_field( $sub_field );
					}
				}

				$normalized['layouts'][ $layout['name'] ] = [
					'name'       => isset( $layout['name'] ) ? $layout['name'] : '',
					'label'      => isset( $layout['label'] ) ? $layout['label'] : '',
					'display'    => isset( $layout['display'] ) ? $layout['display'] : '',
					'sub_fields' => $layout_fields,
				];
			}
		}

		return $normalized;
	}
}
