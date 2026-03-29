<?php

namespace UniversalCPTMigrator\Application;

use UniversalCPTMigrator\Domain\Import\Processor;
use UniversalCPTMigrator\Domain\Import\Validator;
use UniversalCPTMigrator\Infrastructure\Logger;
use UniversalCPTMigrator\Infrastructure\PackageTransport;
use UniversalCPTMigrator\Infrastructure\Storage;

class ImportApplicationService {
	public function extract_uploaded_bundle( array $file ) {
		$transport = new PackageTransport();
		$bundle    = $transport->extract_import_bundle( $file );

		if ( is_wp_error( $bundle ) ) {
			return $bundle;
		}

		if ( empty( $bundle['package'] ) || ! is_array( $bundle['package'] ) ) {
			return new \WP_Error( 'ucm_invalid_bundle_payload', __( 'The uploaded package did not contain a readable payload.', 'universal-cpt-migrator' ) );
		}

		return $bundle;
	}

	public function validate_bundle( array $bundle ) {
		$validator = new Validator();
		return $validator->validate_package( $bundle['package'] );
	}

	public function run_synchronous_import( array $bundle, $dry_run, $offset, $limit, $job_id = '' ) {
		$logger    = new Logger();
		$storage   = new Storage();
		$validator = new Validator();
		$processor = new Processor( $logger );
		$package   = $bundle['package'];
		$result    = $validator->validate_package( $package );

		if ( ! $result['is_valid'] ) {
			$logger->error( 'Import validation failed: ' . implode( '; ', $result['errors'] ) );
			return new \WP_Error( 'ucm_invalid_import_package', __( 'Import validation failed.', 'universal-cpt-migrator' ), [
				'validation' => $result,
				'log_id'     => $logger->get_log_id(),
				'log_path'   => $logger->get_log_path(),
			] );
		}

		$storage->put_contents(
			sprintf( 'imports/%s-%s.json', $dry_run ? 'validated' : 'imported', gmdate( 'Ymd-His' ) ),
			wp_json_encode( $package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		);

		$process_result = $processor->import_package( $package, $dry_run, $offset, $limit );
		if ( is_wp_error( $process_result ) ) {
			$logger->error( 'Import failed: ' . $process_result->get_error_message() );
			return new \WP_Error( 'ucm_sync_import_failed', $process_result->get_error_message(), [
				'log_id'   => $logger->get_log_id(),
				'log_path' => $logger->get_log_path(),
				'job_id'   => $job_id,
			] );
		}

		return [
			'validation' => $result,
			'results'    => $process_result,
			'log_id'     => $logger->get_log_id(),
			'log_path'   => $logger->get_log_path(),
			'mode'       => $dry_run ? 'dry-run' : 'import',
			'job_id'     => $job_id,
		];
	}
}
