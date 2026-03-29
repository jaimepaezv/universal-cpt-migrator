<?php

namespace UniversalCPTMigrator\Admin;

class RequestGuard {
	public function authorize_ajax( $nonce_action, $nonce_field, $capability, $message ) {
		check_ajax_referer( $nonce_action, $nonce_field );

		return $this->authorize_capability( $capability, $message );
	}

	public function authorize_capability( $capability, $message ) {
		if ( current_user_can( $capability ) ) {
			return true;
		}

		return new \WP_Error( 'ucm_forbidden', (string) $message );
	}

	public function verify_admin_nonce( $action ) {
		check_admin_referer( $action );
		return true;
	}

	public function resolve_post_type( array $post ) {
		$post_type = isset( $post['post_type'] ) ? sanitize_key( wp_unslash( $post['post_type'] ) ) : '';

		if ( ! $post_type || ! post_type_exists( $post_type ) ) {
			return new \WP_Error( 'ucm_invalid_post_type', __( 'Invalid post type.', 'universal-cpt-migrator' ) );
		}

		return $post_type;
	}
}
