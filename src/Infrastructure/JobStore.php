<?php

namespace UniversalCPTMigrator\Infrastructure;

class JobStore {
	const OPTION_PREFIX = 'ucm_job_';

	public function create( array $state ) {
		$job_id = 'job_' . wp_generate_uuid4();
		$this->save( $job_id, $state );
		return $job_id;
	}

	public function save( $job_id, array $state ) {
		$state['updated_at'] = gmdate( 'c' );
		if ( empty( $state['created_at'] ) ) {
			$state['created_at'] = gmdate( 'c' );
		}
		update_option( self::OPTION_PREFIX . sanitize_key( $job_id ), $state, false );
	}

	public function get( $job_id ) {
		$state = get_option( self::OPTION_PREFIX . sanitize_key( $job_id ), [] );
		return is_array( $state ) ? $state : [];
	}

	public function delete( $job_id ) {
		delete_option( self::OPTION_PREFIX . sanitize_key( $job_id ) );
	}

	public function update( $job_id, array $new_state ) {
		$current = $this->get( $job_id );
		$this->save( $job_id, array_merge( $current, $new_state ) );
	}

	public function cleanup_stale_jobs( $max_age_days ) {
		$stale_jobs = $this->get_stale_jobs( $max_age_days );
		foreach ( $stale_jobs as $stale_job ) {
			delete_option( $stale_job['option_name'] );
		}

		return count( $stale_jobs );
	}

	public function get_stale_jobs( $max_age_days ) {
		$rows = $this->query_job_rows();
		$threshold = time() - ( DAY_IN_SECONDS * max( 1, absint( $max_age_days ) ) );
		$stale_jobs = [];

		foreach ( $rows as $row ) {
			$state   = maybe_unserialize( $row['option_value'] );
			$updated = ! empty( $state['updated_at'] ) ? strtotime( $state['updated_at'] ) : 0;
			if ( $updated && $updated < $threshold ) {
				$stale_jobs[] = [
					'job_id'      => $this->get_job_id_from_option_name( $row['option_name'] ),
					'option_name' => $row['option_name'],
					'state'       => is_array( $state ) ? $state : [],
				];
			}
		}

		return $stale_jobs;
	}

	public function get_stale_queued_jobs( $max_age_seconds ) {
		$rows       = $this->query_job_rows();
		$threshold  = time() - max( HOUR_IN_SECONDS, absint( $max_age_seconds ) );
		$stale_jobs = [];

		foreach ( $rows as $row ) {
			$state   = maybe_unserialize( $row['option_value'] );
			$updated = ! empty( $state['updated_at'] ) ? strtotime( $state['updated_at'] ) : 0;
			$status  = is_array( $state ) && ! empty( $state['status'] ) ? $state['status'] : '';

			if ( 'queued' !== $status || ! $updated || $updated >= $threshold ) {
				continue;
			}

			$stale_jobs[] = [
				'job_id'      => $this->get_job_id_from_option_name( $row['option_name'] ),
				'option_name' => $row['option_name'],
				'state'       => is_array( $state ) ? $state : [],
			];
		}

		return $stale_jobs;
	}

	public function get_all_jobs() {
		$rows = $this->query_job_rows();
		$jobs = [];

		foreach ( $rows as $row ) {
			$jobs[] = [
				'job_id'      => $this->get_job_id_from_option_name( $row['option_name'] ),
				'option_name' => $row['option_name'],
				'state'       => maybe_unserialize( $row['option_value'] ),
			];
		}

		usort(
			$jobs,
			static function( $a, $b ) {
				$a_time = ! empty( $a['state']['updated_at'] ) ? strtotime( $a['state']['updated_at'] ) : 0;
				$b_time = ! empty( $b['state']['updated_at'] ) ? strtotime( $b['state']['updated_at'] ) : 0;
				return $b_time <=> $a_time;
			}
		);

		return $jobs;
	}

	private function query_job_rows() {
		global $wpdb;

		$like      = $wpdb->esc_like( self::OPTION_PREFIX ) . '%';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			),
			ARRAY_A
		);
	}

	private function get_job_id_from_option_name( $option_name ) {
		return str_replace( self::OPTION_PREFIX, '', (string) $option_name );
	}
}
