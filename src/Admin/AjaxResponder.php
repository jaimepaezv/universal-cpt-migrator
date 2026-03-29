<?php

namespace UniversalCPTMigrator\Admin;

class AjaxResponder {
	public function success( $data ) {
		wp_send_json_success( $data );
	}

	public function error_message( $message, $status = 400 ) {
		wp_send_json_error(
			[
				'message' => (string) $message,
			],
			$status
		);
	}

	public function import_result( $result ) {
		if ( ! is_wp_error( $result ) ) {
			$this->success( $result );
		}

		$data = $result->get_error_data();
		if ( ! is_array( $data ) ) {
			$data = [
				'message' => $result->get_error_message(),
			];
		}

		if ( empty( $data['message'] ) ) {
			$data['message'] = $result->get_error_message();
		}

		$status = 'ucm_invalid_import_package' === $result->get_error_code() ? 422 : 500;
		wp_send_json_error( $data, $status );
	}

	public function validation_failure( array $validation ) {
		wp_send_json_error(
			[
				'validation' => $validation,
			],
			422
		);
	}
}
