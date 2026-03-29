<?php

namespace UniversalCPTMigrator\Domain\Schema;

class CompatibilityService {
	public function compare( array $incoming_schema, array $current_schema ) {
		$errors   = [];
		$warnings = [];

		$incoming_post_type = isset( $incoming_schema['post_type'] ) ? sanitize_key( $incoming_schema['post_type'] ) : '';
		$current_post_type  = isset( $current_schema['post_type'] ) ? sanitize_key( $current_schema['post_type'] ) : '';

		if ( $incoming_post_type && $current_post_type && $incoming_post_type !== $current_post_type ) {
			$errors[] = sprintf(
				__( 'Schema post type mismatch: package targets "%1$s" but current site schema is "%2$s".', 'universal-cpt-migrator' ),
				$incoming_post_type,
				$current_post_type
			);
		}

		$incoming_taxonomies = isset( $incoming_schema['taxonomies'] ) && is_array( $incoming_schema['taxonomies'] ) ? array_keys( $incoming_schema['taxonomies'] ) : [];
		$current_taxonomies  = isset( $current_schema['taxonomies'] ) && is_array( $current_schema['taxonomies'] ) ? array_keys( $current_schema['taxonomies'] ) : [];

		foreach ( array_diff( $incoming_taxonomies, $current_taxonomies ) as $taxonomy ) {
			$warnings[] = sprintf(
				__( 'Package references taxonomy "%s" which is not registered on this site.', 'universal-cpt-migrator' ),
				$taxonomy
			);
		}

		foreach ( array_intersect( $incoming_taxonomies, $current_taxonomies ) as $taxonomy ) {
			$incoming_tax = isset( $incoming_schema['taxonomies'][ $taxonomy ] ) ? $incoming_schema['taxonomies'][ $taxonomy ] : [];
			$current_tax  = isset( $current_schema['taxonomies'][ $taxonomy ] ) ? $current_schema['taxonomies'][ $taxonomy ] : [];

			foreach ( [ 'hierarchical', 'show_in_rest' ] as $key ) {
				if ( isset( $incoming_tax[ $key ], $current_tax[ $key ] ) && (bool) $incoming_tax[ $key ] !== (bool) $current_tax[ $key ] ) {
					$warnings[] = sprintf(
						__( 'Taxonomy "%1$s" differs for "%2$s": package "%3$s", site "%4$s".', 'universal-cpt-migrator' ),
						$taxonomy,
						$key,
						$incoming_tax[ $key ] ? 'true' : 'false',
						$current_tax[ $key ] ? 'true' : 'false'
					);
				}
			}
		}

		$incoming_supports = isset( $incoming_schema['supports'] ) && is_array( $incoming_schema['supports'] ) ? $incoming_schema['supports'] : [];
		$current_supports  = isset( $current_schema['supports'] ) && is_array( $current_schema['supports'] ) ? $current_schema['supports'] : [];

		foreach ( array_diff( $incoming_supports, $current_supports ) as $support ) {
			$warnings[] = sprintf(
				__( 'Package expects post type support "%s" which is not enabled on this site.', 'universal-cpt-migrator' ),
				$support
			);
		}

		$incoming_meta = isset( $incoming_schema['meta_fields'] ) && is_array( $incoming_schema['meta_fields'] ) ? $incoming_schema['meta_fields'] : [];
		$current_meta  = isset( $current_schema['meta_fields'] ) && is_array( $current_schema['meta_fields'] ) ? $current_schema['meta_fields'] : [];

		foreach ( $incoming_meta as $meta_key => $definition ) {
			if ( ! isset( $current_meta[ $meta_key ] ) ) {
				$warnings[] = sprintf(
					__( 'Package references meta field "%s" which is not currently present on this site.', 'universal-cpt-migrator' ),
					$meta_key
				);
				continue;
			}

			$incoming_type = isset( $definition['type'] ) ? $definition['type'] : 'unknown';
			$current_type  = isset( $current_meta[ $meta_key ]['type'] ) ? $current_meta[ $meta_key ]['type'] : 'unknown';

			if ( $incoming_type !== $current_type ) {
				$warnings[] = sprintf(
					__( 'Meta field "%1$s" type mismatch: package "%2$s", site "%3$s".', 'universal-cpt-migrator' ),
					$meta_key,
					$incoming_type,
					$current_type
				);
			}
		}

		$incoming_fields = $this->flatten_acf_fields( isset( $incoming_schema['acf_groups'] ) ? $incoming_schema['acf_groups'] : [] );
		$current_fields  = $this->flatten_acf_fields( isset( $current_schema['acf_groups'] ) ? $current_schema['acf_groups'] : [] );

		foreach ( $incoming_fields as $field_name => $field_definition ) {
			if ( ! isset( $current_fields[ $field_name ] ) ) {
				$warnings[] = sprintf(
					__( 'Package includes ACF field "%s" which is missing on this site.', 'universal-cpt-migrator' ),
					$field_name
				);
				continue;
			}

			if ( $current_fields[ $field_name ]['type'] !== $field_definition['type'] ) {
				$warnings[] = sprintf(
					__( 'ACF field "%1$s" type mismatch: package "%2$s", site "%3$s".', 'universal-cpt-migrator' ),
					$field_name,
					$field_definition['type'],
					$current_fields[ $field_name ]['type']
				);
			}
		}

		return [
			'is_compatible' => empty( $errors ),
			'errors'        => array_values( array_unique( $errors ) ),
			'warnings'      => array_values( array_unique( $warnings ) ),
		];
	}

	private function flatten_acf_fields( array $groups ) {
		$fields = [];

		foreach ( $groups as $group ) {
			if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
				continue;
			}

			foreach ( $group['fields'] as $field ) {
				$this->add_field( $fields, $field );
			}
		}

		return $fields;
	}

	private function add_field( array &$fields, array $field, $prefix = '' ) {
		if ( empty( $field['name'] ) ) {
			return;
		}

		$name            = $prefix ? $prefix . '.' . $field['name'] : $field['name'];
		$fields[ $name ] = [
			'type'     => isset( $field['type'] ) ? $field['type'] : 'unknown',
			'required' => ! empty( $field['required'] ),
		];

		if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			foreach ( $field['sub_fields'] as $sub_field ) {
				$this->add_field( $fields, $sub_field, $name );
			}
		}

		if ( ! empty( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
			foreach ( $field['layouts'] as $layout ) {
				if ( empty( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
					continue;
				}

				foreach ( $layout['sub_fields'] as $sub_field ) {
					$this->add_field( $fields, $sub_field, $name );
				}
			}
		}
	}
}
