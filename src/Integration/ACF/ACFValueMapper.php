<?php

namespace UniversalCPTMigrator\Integration\ACF;

use UniversalCPTMigrator\Domain\Import\MediaSideloadService;
use UniversalCPTMigrator\Infrastructure\Logger;

class ACFValueMapper {
	private $logger;
	private $acf_service;
	private $media_service;
	private $attachment_exporter;

	public function __construct( Logger $logger = null, ACFService $acf_service = null, MediaSideloadService $media_service = null, $attachment_exporter = null ) {
		$this->logger              = $logger ?: new Logger();
		$this->acf_service         = $acf_service ?: new ACFService();
		$this->media_service       = $media_service ?: new MediaSideloadService( $this->logger );
		$this->attachment_exporter = is_callable( $attachment_exporter ) ? $attachment_exporter : null;
	}

	public function export_values( array $values, $post_type ) {
		$field_map = $this->acf_service->get_field_map_for_cpt( $post_type );
		if ( empty( $field_map ) ) {
			return $values;
		}

		$exported = [];
		foreach ( $values as $field_name => $value ) {
			$definition               = isset( $field_map[ $field_name ] ) ? $field_map[ $field_name ] : null;
			$exported[ $field_name ] = $this->export_value( $value, $definition );
		}

		return $exported;
	}

	public function import_values( array $values, $post_type, array &$warnings = [] ) {
		$field_map = $this->acf_service->get_field_map_for_cpt( $post_type );
		if ( empty( $field_map ) ) {
			return $values;
		}

		$imported = [];
		foreach ( $values as $field_name => $value ) {
			$definition               = isset( $field_map[ $field_name ] ) ? $field_map[ $field_name ] : null;
			$imported[ $field_name ] = $this->import_value( $value, $definition, $warnings );
		}

		return $imported;
	}

	private function export_value( $value, $definition ) {
		if ( empty( $definition['type'] ) ) {
			return $value;
		}

		switch ( $definition['type'] ) {
			case 'relationship':
			case 'post_object':
			case 'page_link':
				return $this->export_post_reference_value( $value, $definition );
			case 'taxonomy':
				return $this->export_taxonomy_value( $value, $definition );
			case 'user':
				return $this->export_user_value( $value, $definition );
			case 'image':
			case 'file':
				return $this->export_media_value( $value );
			case 'gallery':
				return $this->export_media_collection( $value );
			case 'group':
			case 'clone':
				return $this->export_group_like_value( $value, $definition );
			case 'repeater':
				return $this->export_repeater_value( $value, $definition );
			case 'flexible_content':
				return $this->export_flexible_value( $value, $definition );
			default:
				return $value;
		}
	}

	private function import_value( $value, $definition, array &$warnings ) {
		if ( empty( $definition['type'] ) ) {
			return $value;
		}

		switch ( $definition['type'] ) {
			case 'relationship':
			case 'post_object':
			case 'page_link':
				return $this->import_post_reference_value( $value, $definition );
			case 'taxonomy':
				return $this->import_taxonomy_value( $value, $definition );
			case 'user':
				return $this->import_user_value( $value, $definition );
			case 'image':
			case 'file':
				return $this->import_media_value( $value, $warnings );
			case 'gallery':
				return $this->import_media_collection( $value, $warnings );
			case 'group':
			case 'clone':
				return $this->import_group_like_value( $value, $definition, $warnings );
			case 'repeater':
				return $this->import_repeater_value( $value, $definition, $warnings );
			case 'flexible_content':
				return $this->import_flexible_value( $value, $definition, $warnings );
			default:
				return $value;
		}
	}

	private function export_group_like_value( $value, array $definition ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$result = [];
		foreach ( $value as $sub_name => $sub_value ) {
			$sub_definition       = $this->find_sub_field_definition( $definition, $sub_name );
			$result[ $sub_name ] = $this->export_value( $sub_value, $sub_definition );
		}

		return $result;
	}

	private function import_group_like_value( $value, array $definition, array &$warnings ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$result = [];
		foreach ( $value as $sub_name => $sub_value ) {
			$sub_definition       = $this->find_sub_field_definition( $definition, $sub_name );
			$result[ $sub_name ] = $this->import_value( $sub_value, $sub_definition, $warnings );
		}

		return $result;
	}

	private function export_repeater_value( $value, array $definition ) {
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
				$mapped_row[ $sub_name ] = $this->export_value( $sub_value, $sub_definition );
			}
			$result[] = $mapped_row;
		}

		return $result;
	}

	private function import_repeater_value( $value, array $definition, array &$warnings ) {
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
				$mapped_row[ $sub_name ] = $this->import_value( $sub_value, $sub_definition, $warnings );
			}
			$result[] = $mapped_row;
		}

		return $result;
	}

	private function export_flexible_value( $value, array $definition ) {
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
				$mapped_row[ $sub_name ] = $this->export_value( $sub_value, $sub_definition );
			}

			$result[] = $mapped_row;
		}

		return $result;
	}

	private function import_flexible_value( $value, array $definition, array &$warnings ) {
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
				$mapped_row[ $sub_name ] = $this->import_value( $sub_value, $sub_definition, $warnings );
			}

			$result[] = $mapped_row;
		}

		return $result;
	}

	private function export_post_reference_value( $value, array $definition ) {
		if ( $this->is_multiple_value( $value, $definition ) ) {
			$values = is_array( $value ) ? $value : [ $value ];
			$result = [];

			foreach ( $values as $candidate ) {
				$reference = $this->export_post_reference_candidate( $candidate );
				if ( ! empty( $reference ) ) {
					$result[] = $reference;
				}
			}

			return $result;
		}

		return $this->export_post_reference_candidate( $value );
	}

	private function import_post_reference_value( $value, array $definition ) {
		if ( $this->is_multiple_value( $value, $definition ) ) {
			$values = is_array( $value ) ? $value : [ $value ];
			$result = [];

			foreach ( $values as $candidate ) {
				$post_id = $this->resolve_post_id_from_candidate( $candidate );
				if ( $post_id ) {
					$result[] = $post_id;
				}
			}

			return $result;
		}

		return $this->resolve_post_id_from_candidate( $value );
	}

	private function export_taxonomy_value( $value, array $definition ) {
		if ( $this->is_multiple_value( $value, $definition ) ) {
			$values = is_array( $value ) ? $value : [ $value ];
			$result = [];

			foreach ( $values as $candidate ) {
				$term_data = $this->export_term_candidate( $candidate, $definition );
				if ( ! empty( $term_data ) ) {
					$result[] = $term_data;
				}
			}

			return $result;
		}

		return $this->export_term_candidate( $value, $definition );
	}

	private function import_taxonomy_value( $value, array $definition ) {
		if ( $this->is_multiple_value( $value, $definition ) ) {
			$values = is_array( $value ) ? $value : [ $value ];
			$result = [];

			foreach ( $values as $candidate ) {
				$term_id = $this->resolve_term_id_from_candidate( $candidate, $definition );
				if ( $term_id ) {
					$result[] = $term_id;
				}
			}

			return $result;
		}

		return $this->resolve_term_id_from_candidate( $value, $definition );
	}

	private function export_user_value( $value, array $definition ) {
		if ( $this->is_multiple_value( $value, $definition ) ) {
			$values = is_array( $value ) ? $value : [ $value ];
			$result = [];

			foreach ( $values as $candidate ) {
				$user_value = $this->export_user_candidate( $candidate );
				if ( '' !== $user_value ) {
					$result[] = $user_value;
				}
			}

			return $result;
		}

		return $this->export_user_candidate( $value );
	}

	private function import_user_value( $value, array $definition ) {
		if ( $this->is_multiple_value( $value, $definition ) ) {
			$values = is_array( $value ) ? $value : [ $value ];
			$result = [];

			foreach ( $values as $candidate ) {
				$user_id = $this->resolve_user_id_from_candidate( $candidate );
				if ( $user_id ) {
					$result[] = $user_id;
				}
			}

			return $result;
		}

		return $this->resolve_user_id_from_candidate( $value );
	}

	private function export_media_value( $value ) {
		return $this->export_media_candidate( $value );
	}

	private function import_media_value( $value, array &$warnings ) {
		return $this->import_media_candidate( $value, $warnings );
	}

	private function export_media_collection( $value ) {
		$values = is_array( $value ) ? $value : [ $value ];
		$result = [];

		foreach ( $values as $candidate ) {
			$media = $this->export_media_candidate( $candidate );
			if ( ! empty( $media ) ) {
				$result[] = $media;
			}
		}

		return $result;
	}

	private function import_media_collection( $value, array &$warnings ) {
		$values = is_array( $value ) ? $value : [ $value ];
		$result = [];

		foreach ( $values as $candidate ) {
			$attachment_id = $this->import_media_candidate( $candidate, $warnings );
			if ( $attachment_id ) {
				$result[] = $attachment_id;
			}
		}

		return $result;
	}

	private function export_post_reference_candidate( $candidate ) {
		$post_id = $this->extract_post_id( $candidate );
		if ( ! $post_id ) {
			return null;
		}

		$uuid = get_post_meta( $post_id, '_ucm_uuid', true );
		if ( ! $uuid ) {
			$uuid = wp_generate_uuid4();
			update_post_meta( $post_id, '_ucm_uuid', $uuid );
		}

		return [
			'uuid' => $uuid,
		];
	}

	private function resolve_post_id_from_candidate( $candidate ) {
		$post_id = $this->extract_post_id( $candidate );
		if ( $post_id ) {
			return $post_id;
		}

		if ( is_array( $candidate ) && ! empty( $candidate['uuid'] ) && wp_is_uuid( $candidate['uuid'] ) ) {
			$query = new \WP_Query(
				[
					'post_type'      => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => '_ucm_uuid',
					'meta_value'     => sanitize_text_field( $candidate['uuid'] ),
				]
			);

			if ( $query->have_posts() ) {
				return (int) $query->posts[0];
			}
		}

		return 0;
	}

	private function export_term_candidate( $candidate, array $definition ) {
		$term = $this->extract_term( $candidate, $definition );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}

		return [
			'name'   => $term->name,
			'slug'   => $term->slug,
			'parent' => $term->parent ? get_term( $term->parent, $term->taxonomy )->slug : '',
		];
	}

	private function resolve_term_id_from_candidate( $candidate, array $definition ) {
		$term = $this->extract_term( $candidate, $definition );
		return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
	}

	private function extract_term( $candidate, array $definition ) {
		$taxonomy = ! empty( $definition['taxonomy'] ) ? $definition['taxonomy'] : '';

		if ( is_object( $candidate ) && ! empty( $candidate->term_id ) ) {
			return $candidate;
		}

		if ( is_numeric( $candidate ) && $taxonomy ) {
			return get_term( (int) $candidate, $taxonomy );
		}

		if ( is_string( $candidate ) && $taxonomy ) {
			$term = get_term_by( 'slug', sanitize_title( $candidate ), $taxonomy );
			if ( $term ) {
				return $term;
			}

			return get_term_by( 'name', sanitize_text_field( $candidate ), $taxonomy );
		}

		if ( is_array( $candidate ) ) {
			if ( ! empty( $candidate['term_id'] ) && $taxonomy ) {
				return get_term( (int) $candidate['term_id'], $taxonomy );
			}

			if ( ! empty( $candidate['slug'] ) && $taxonomy ) {
				$term = get_term_by( 'slug', sanitize_title( $candidate['slug'] ), $taxonomy );
				if ( $term ) {
					return $term;
				}
			}

			if ( ! empty( $candidate['name'] ) && $taxonomy ) {
				return get_term_by( 'name', sanitize_text_field( $candidate['name'] ), $taxonomy );
			}
		}

		return null;
	}

	private function export_user_candidate( $candidate ) {
		$user = $this->extract_user( $candidate );
		return $user ? $user->user_login : '';
	}

	private function resolve_user_id_from_candidate( $candidate ) {
		$user = $this->extract_user( $candidate );
		return $user ? (int) $user->ID : 0;
	}

	private function extract_user( $candidate ) {
		if ( $candidate instanceof \WP_User ) {
			return $candidate;
		}

		if ( is_numeric( $candidate ) ) {
			return get_user_by( 'id', (int) $candidate );
		}

		if ( is_string( $candidate ) ) {
			$user = get_user_by( 'login', sanitize_user( $candidate, true ) );
			if ( $user ) {
				return $user;
			}

			return get_user_by( 'email', sanitize_email( $candidate ) );
		}

		if ( is_array( $candidate ) ) {
			if ( ! empty( $candidate['ID'] ) ) {
				return get_user_by( 'id', (int) $candidate['ID'] );
			}

			if ( ! empty( $candidate['user_login'] ) ) {
				return get_user_by( 'login', sanitize_user( $candidate['user_login'], true ) );
			}

			if ( ! empty( $candidate['user_email'] ) ) {
				return get_user_by( 'email', sanitize_email( $candidate['user_email'] ) );
			}
		}

		return null;
	}

	private function export_media_candidate( $candidate ) {
		$attachment_id = $this->extract_attachment_id( $candidate );
		if ( ! $attachment_id || ! $this->attachment_exporter ) {
			return $candidate;
		}

		return call_user_func( $this->attachment_exporter, $attachment_id );
	}

	private function import_media_candidate( $candidate, array &$warnings ) {
		$attachment_id = $this->extract_attachment_id( $candidate );
		if ( $attachment_id ) {
			return $attachment_id;
		}

		if ( is_array( $candidate ) && ( ! empty( $candidate['url'] ) || ! empty( $candidate['manifest'] ) ) ) {
			$attachment_id = $this->media_service->import_featured_media( $candidate );
			if ( $attachment_id ) {
				return $attachment_id;
			}

			$error = $this->media_service->get_last_error();
			if ( is_wp_error( $error ) ) {
				$warnings[] = $this->format_warning( $error );
			}

			return 0;
		}

		return 0;
	}

	private function extract_attachment_id( $candidate ) {
		if ( is_numeric( $candidate ) ) {
			return (int) $candidate;
		}

		if ( $candidate instanceof \WP_Post && 'attachment' === $candidate->post_type ) {
			return (int) $candidate->ID;
		}

		if ( is_array( $candidate ) ) {
			if ( ! empty( $candidate['ID'] ) ) {
				return (int) $candidate['ID'];
			}

			if ( ! empty( $candidate['id'] ) ) {
				return (int) $candidate['id'];
			}

			if ( ! empty( $candidate['source_id'] ) ) {
				return (int) $candidate['source_id'];
			}
		}

		return 0;
	}

	private function is_multiple_value( $value, array $definition ) {
		if ( 'relationship' === $definition['type'] || 'gallery' === $definition['type'] ) {
			return true;
		}

		if ( ! empty( $definition['multiple'] ) ) {
			return true;
		}

		if ( 'taxonomy' === $definition['type'] && ! empty( $definition['field_type'] ) && in_array( $definition['field_type'], [ 'checkbox', 'multi_select' ], true ) ) {
			return true;
		}

		if ( is_array( $value ) && ! isset( $value['uuid'] ) && ! isset( $value['slug'] ) && ! isset( $value['url'] ) && ! isset( $value['ID'] ) && ! isset( $value['acf_fc_layout'] ) ) {
			return true;
		}

		return false;
	}

	private function find_sub_field_definition( array $definition, $sub_name ) {
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

	private function extract_post_id( $candidate ) {
		if ( is_numeric( $candidate ) ) {
			return (int) $candidate;
		}

		if ( $candidate instanceof \WP_Post ) {
			return (int) $candidate->ID;
		}

		if ( is_array( $candidate ) ) {
			if ( ! empty( $candidate['ID'] ) ) {
				return (int) $candidate['ID'];
			}

			if ( ! empty( $candidate['id'] ) ) {
				return (int) $candidate['id'];
			}
		}

		return 0;
	}

	private function format_warning( \WP_Error $error ) {
		$data = $error->get_error_data();
		$data = is_array( $data ) ? $data : [];

		return [
			'code'      => (string) $error->get_error_code(),
			'subsystem' => ! empty( $data['subsystem'] ) ? (string) $data['subsystem'] : 'acf_media',
			'message'   => $error->get_error_message(),
			'context'   => $data,
		];
	}
}
