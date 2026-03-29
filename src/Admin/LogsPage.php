<?php

namespace UniversalCPTMigrator\Admin;

use UniversalCPTMigrator\Infrastructure\Storage;

class LogsPage {
	public function render() {
		$storage        = new Storage();
		$logs           = $storage->list_files( 'logs', 'txt' );
		$log            = isset( $_GET['log'] ) ? sanitize_file_name( wp_unslash( $_GET['log'] ) ) : '';
		$job_id         = isset( $_GET['job_id'] ) ? sanitize_text_field( wp_unslash( $_GET['job_id'] ) ) : '';
		$trace_search   = isset( $_GET['trace_search'] ) ? sanitize_text_field( wp_unslash( $_GET['trace_search'] ) ) : ( isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '' );
		$trace_subsystem = isset( $_GET['trace_subsystem'] ) ? sanitize_text_field( wp_unslash( $_GET['trace_subsystem'] ) ) : '';
		$level          = isset( $_GET['level'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['level'] ) ) ) : 'ALL';
		$view           = $log ? $storage->read_contents( 'logs/' . $log ) : '';
		$entries        = $view ? $this->parse_entries( $view ) : [];
		$filtered       = $this->filter_entries( $entries, $level, $trace_search );
		$level_counts   = $this->count_levels( $entries );
		$preview        = ! empty( $filtered ) ? implode( "\n", wp_list_pluck( $filtered, 'raw' ) ) : '';
		$selected_entry = null;

		foreach ( $logs as $entry ) {
			if ( $entry['name'] === $log ) {
				$selected_entry = $entry;
				break;
			}
		}

		include UCM_PATH . 'templates/logs.php';
	}

	private function parse_entries( $contents ) {
		$lines   = preg_split( '/\r\n|\r|\n/', (string) $contents );
		$entries = [];

		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}

			$entry = [
				'raw'       => $line,
				'timestamp' => '',
				'level'     => 'UNKNOWN',
				'message'   => $line,
			];

			if ( preg_match( '/^\[(.*?)\]\s+\[(.*?)\]\s+(.*)$/', $line, $matches ) ) {
				$entry['timestamp'] = $matches[1];
				$entry['level']     = strtoupper( $matches[2] );
				$entry['message']   = $matches[3];
			}

			$entries[] = $entry;
		}

		return $entries;
	}

	private function filter_entries( array $entries, $level, $search ) {
		return array_values(
			array_filter(
				$entries,
				static function( $entry ) use ( $level, $search ) {
					if ( 'ALL' !== $level && $entry['level'] !== $level ) {
						return false;
					}

					if ( $search && false === stripos( $entry['raw'], $search ) ) {
						return false;
					}

					return true;
				}
			)
		);
	}

	private function count_levels( array $entries ) {
		$counts = [
			'ERROR'   => 0,
			'WARNING' => 0,
			'INFO'    => 0,
		];

		foreach ( $entries as $entry ) {
			if ( isset( $counts[ $entry['level'] ] ) ) {
				$counts[ $entry['level'] ]++;
			}
		}

		return $counts;
	}
}
