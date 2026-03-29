<?php

namespace UniversalCPTMigrator;

use UniversalCPTMigrator\Admin\Menu;
use UniversalCPTMigrator\Admin\Assets;
use UniversalCPTMigrator\Admin\Controller;
use UniversalCPTMigrator\Admin\DiagnosticsController;
use UniversalCPTMigrator\Infrastructure\Storage;
use UniversalCPTMigrator\Infrastructure\Settings;
use UniversalCPTMigrator\Infrastructure\Logger;
use UniversalCPTMigrator\Infrastructure\BackgroundWorker;

class Plugin {
	private static $instance = null;
	private $services = [];

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Private constructor for singleton
	}

	public function init() {
		$this->register_services();
		$this->boot_services();
	}

	private function register_services() {
		$this->services['storage']    = new Storage();
		$this->services['settings']   = new Settings();
		$this->services['background'] = new BackgroundWorker();
		$this->services['menu']       = new Menu();
		$this->services['assets']     = new Assets();
		$this->services['controller'] = new Controller();
		$this->services['diagnostics_controller'] = new DiagnosticsController();
	}

	private function boot_services() {
		foreach ( $this->services as $service ) {
			if ( method_exists( $service, 'init' ) ) {
				$service->init();
			}
		}
	}

	public function get_service( $id ) {
		return isset( $this->services[ $id ] ) ? $this->services[ $id ] : null;
	}

	public static function activate() {
		$storage = new Storage();
		$storage->setup_directories();
		$settings = new Settings();
		if ( ! get_option( Settings::OPTION_KEY ) ) {
			add_option( Settings::OPTION_KEY, $settings->defaults() );
		}
		flush_rewrite_rules();
	}

	public static function deactivate() {
		$settings = new Settings();
		Logger::cleanup_retained_logs( $settings->get( 'log_retention_days' ) );
		wp_clear_scheduled_hook( BackgroundWorker::CLEANUP_HOOK );
		flush_rewrite_rules();
	}
}
