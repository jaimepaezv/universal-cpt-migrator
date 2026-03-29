<?php

namespace UniversalCPTMigrator\Domain\Import;

use UniversalCPTMigrator\Domain\Schema\Analyzer;
use UniversalCPTMigrator\Domain\Schema\CompatibilityService;

class Validator {
	public function validate_package( $package ) {
		$errors   = [];
		$warnings = [];
		$summary  = [
			'items'      => 0,
			'post_type'  => '',
			'has_schema' => false,
		];

		if ( ! is_array( $package ) ) {
			$errors[] = __( 'Package payload must decode to a JSON object.', 'universal-cpt-migrator' );

			return $this->result( $errors, $warnings, $summary );
		}

		if ( empty( $package['metadata'] ) || ! is_array( $package['metadata'] ) ) {
			$errors[] = __( 'Package metadata is missing.', 'universal-cpt-migrator' );
		}

		if ( empty( $package['metadata']['post_type'] ) ) {
			$errors[] = __( 'Package metadata is missing the target post type.', 'universal-cpt-migrator' );
		} else {
			$summary['post_type'] = sanitize_key( $package['metadata']['post_type'] );
			if ( ! post_type_exists( $summary['post_type'] ) ) {
				$errors[] = sprintf(
					/* translators: %s: post type slug */
					__( 'The target post type "%s" is not registered on this site.', 'universal-cpt-migrator' ),
					$summary['post_type']
				);
			}
		}

		$summary['has_schema'] = ! empty( $package['schema'] ) && is_array( $package['schema'] );
		if ( ! $summary['has_schema'] ) {
			$warnings[] = __( 'Package schema block is missing. Import can still proceed, but compatibility checks are limited.', 'universal-cpt-migrator' );
		} else {
			$analyzer      = new Analyzer();
			$compatibility = new CompatibilityService();
			$current       = $analyzer->analyze_cpt( $summary['post_type'] );
			$comparison    = $compatibility->compare( $package['schema'], $current );

			$errors   = array_merge( $errors, $comparison['errors'] );
			$warnings = array_merge( $warnings, $comparison['warnings'] );
		}

		if ( empty( $package['items'] ) || ! is_array( $package['items'] ) ) {
			$errors[] = __( 'Package items are missing or invalid.', 'universal-cpt-migrator' );
		} else {
			$summary['items'] = count( $package['items'] );

			foreach ( $package['items'] as $index => $item ) {
				$item_errors = $this->validate_item( $item, $index );
				$errors      = array_merge( $errors, $item_errors );
			}
		}

		return $this->result( $errors, $warnings, $summary );
	}

	private function validate_item( $item, $index ) {
		$errors = [];

		if ( ! is_array( $item ) ) {
			$errors[] = sprintf(
				/* translators: %d: item index */
				__( 'Item %d is not an object.', 'universal-cpt-migrator' ),
				$index + 1
			);

			return $errors;
		}

		$required_fields = [ 'uuid', 'post_title', 'post_status' ];
		foreach ( $required_fields as $field ) {
			if ( ! array_key_exists( $field, $item ) || '' === (string) $item[ $field ] ) {
				$errors[] = sprintf(
					/* translators: 1: item index, 2: field name */
					__( 'Item %1$d is missing required field "%2$s".', 'universal-cpt-migrator' ),
					$index + 1,
					$field
				);
			}
		}

		if ( ! empty( $item['uuid'] ) && ! wp_is_uuid( $item['uuid'] ) ) {
			$errors[] = sprintf(
				/* translators: %d: item index */
				__( 'Item %d has an invalid UUID.', 'universal-cpt-migrator' ),
				$index + 1
			);
		}

		return $errors;
	}

	private function result( array $errors, array $warnings, array $summary ) {
		return [
			'is_valid' => empty( $errors ),
			'errors'   => array_values( array_unique( $errors ) ),
			'warnings' => array_values( array_unique( $warnings ) ),
			'summary'  => $summary,
		];
	}
}
