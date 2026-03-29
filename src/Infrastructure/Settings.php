<?php

namespace UniversalCPTMigrator\Infrastructure;

class Settings {
	const OPTION_KEY = 'ucm_settings';

	public function init() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function register_settings() {
		register_setting(
			'ucm_settings_group',
			self::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => $this->defaults(),
			]
		);
	}

	public function sanitize( $settings ) {
		$defaults = $this->defaults();
		$settings = is_array( $settings ) ? $settings : [];

		return [
			'chunk_size'               => max( 1, min( 250, absint( isset( $settings['chunk_size'] ) ? $settings['chunk_size'] : $defaults['chunk_size'] ) ) ),
			'log_retention_days'       => max( 1, min( 365, absint( isset( $settings['log_retention_days'] ) ? $settings['log_retention_days'] : $defaults['log_retention_days'] ) ) ),
			'artifact_retention_days'  => max( 1, min( 365, absint( isset( $settings['artifact_retention_days'] ) ? $settings['artifact_retention_days'] : $defaults['artifact_retention_days'] ) ) ),
			'temp_retention_days'      => max( 1, min( 60, absint( isset( $settings['temp_retention_days'] ) ? $settings['temp_retention_days'] : $defaults['temp_retention_days'] ) ) ),
			'job_retention_days'       => max( 1, min( 365, absint( isset( $settings['job_retention_days'] ) ? $settings['job_retention_days'] : $defaults['job_retention_days'] ) ) ),
			'delete_data_on_uninstall' => ! empty( $settings['delete_data_on_uninstall'] ) ? 1 : 0,
			'allow_remote_media'       => ! empty( $settings['allow_remote_media'] ) ? 1 : 0,
			'allowed_media_hosts'      => $this->sanitize_host_list( isset( $settings['allowed_media_hosts'] ) ? $settings['allowed_media_hosts'] : $defaults['allowed_media_hosts'] ),
		];
	}

	public function get_all() {
		return wp_parse_args( get_option( self::OPTION_KEY, [] ), $this->defaults() );
	}

	public function get( $key ) {
		$settings = $this->get_all();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	public function defaults() {
		return [
			'chunk_size'               => 50,
			'log_retention_days'       => 30,
			'artifact_retention_days'  => 30,
			'temp_retention_days'      => 3,
			'job_retention_days'       => 14,
			'delete_data_on_uninstall' => 0,
			'allow_remote_media'       => 0,
			'allowed_media_hosts'      => '',
		];
	}

	public function sanitize_host_list( $value ) {
		$hosts = preg_split( '/[\r\n,]+/', (string) $value );
		$hosts = array_filter( array_map( 'trim', $hosts ) );
		$hosts = array_map(
			static function( $host ) {
				return strtolower( preg_replace( '/[^a-z0-9\.\-\*]/i', '', $host ) );
			},
			$hosts
		);

		return implode( "\n", array_unique( array_filter( $hosts ) ) );
	}
}
