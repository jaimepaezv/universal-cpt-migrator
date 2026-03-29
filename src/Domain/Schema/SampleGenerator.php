<?php

namespace UniversalCPTMigrator\Domain\Schema;

class SampleGenerator {
	public function generate_sample( $post_type, $schema ) {
		$sample = [
			'metadata'  => [
				'plugin'    => 'Universal CPT Migrator',
				'version'   => UCM_VERSION,
				'post_type' => $post_type,
				'generated' => date( 'c' ),
			],
			'items'     => [
				$this->generate_sample_item( $post_type, $schema )
			]
		];

		return apply_filters( 'u_cpt_mgr_sample_payload', $sample, $post_type, $schema );
	}

	private function generate_sample_item( $post_type, $schema ) {
		$item = [
			'uuid'         => wp_generate_uuid4(),
			'post_title'   => 'Sample ' . ucfirst( $post_type ) . ' Title',
			'post_content' => '<!-- wp:paragraph --><p>This is generated sample content for your ' . $post_type . ' CPT.</p><!-- /wp:paragraph -->',
			'post_status'  => 'draft',
			'post_name'    => 'sample-' . $post_type,
			'taxonomies'   => [],
			'acf'          => [],
			'meta'         => [],
		];

		// Fill Taxonomies with mock terms
		foreach ( $schema['taxonomies'] as $tax_name => $tax_def ) {
			$item['taxonomies'][ $tax_name ] = [
				[ 'name' => 'Sample Term', 'slug' => 'sample-term' ]
			];
		}

		// Fill ACF fields using deep recursion
		foreach ( $schema['acf_groups'] as $group ) {
			foreach ( $group['fields'] as $field ) {
				$item['acf'][ $field['name'] ] = $this->generate_field_value( $field );
			}
		}

		return $item;
	}

	private function generate_field_value( $field ) {
		switch ( $field['type'] ) {
			case 'text':
			case 'textarea':
			case 'wysiwyg':
				return 'Sample ' . $field['label'] . ' Data';
			case 'number':
			case 'range':
				return 42;
			case 'email':
				return 'sample@example.com';
			case 'url':
				return 'https://example.com';
			case 'true_false':
				return 1;
			case 'select':
			case 'radio':
			case 'button_group':
				return isset( $field['choices'] ) ? array_keys( $field['choices'] )[0] : '';
			case 'date_picker':
				return date( 'Ymd' );
			case 'date_time_picker':
				return date( 'Y-m-d H:i:s' );
			case 'image':
			case 'file':
				return [ 'url' => 'https://example.com/placeholder.jpg', 'title' => 'Sample Asset' ];
			case 'repeater':
				$row = [];
				foreach ( $field['sub_fields'] as $sub ) { $row[ $sub['name'] ] = $this->generate_field_value( $sub ); }
				return [ $row ];
			case 'flexible_content':
				return []; // Layouts are complex; return empty for the sample base
			case 'group':
				$nested = [];
				foreach ( $field['sub_fields'] as $sub ) { $nested[ $sub['name'] ] = $this->generate_field_value( $sub ); }
				return $nested;
			default:
				return null;
		}
	}
}
