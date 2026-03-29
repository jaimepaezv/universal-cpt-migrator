<?php

namespace UniversalCPTMigrator\Admin;

class Menu {
	private $settings_page;
	private $logs_page;
	private $diagnostics_page;

	public function __construct() {
		$this->settings_page = new SettingsPage();
		$this->logs_page     = new LogsPage();
		$this->diagnostics_page = new DiagnosticsPage();
	}

	public function init() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
	}

	public function register_menu() {
		add_menu_page(
			__( 'CPT Migrator', 'universal-cpt-migrator' ),
			__( 'CPT Migrator', 'universal-cpt-migrator' ),
			'manage_options',
			'u-cpt-migrator',
			[ $this, 'render_dashboard' ],
			'dashicons-migrate',
			30
		);

		add_submenu_page(
			'u-cpt-migrator',
			__( 'Dashboard', 'universal-cpt-migrator' ),
			__( 'Dashboard', 'universal-cpt-migrator' ),
			'manage_options',
			'u-cpt-migrator',
			[ $this, 'render_dashboard' ]
		);

		add_submenu_page(
			'u-cpt-migrator',
			__( 'Export Items', 'universal-cpt-migrator' ),
			__( 'Export Items', 'universal-cpt-migrator' ),
			'manage_options',
			'u-cpt-migrator-export',
			[ $this, 'render_export' ]
		);

		add_submenu_page(
			'u-cpt-migrator',
			__( 'Import Packages', 'universal-cpt-migrator' ),
			__( 'Import Packages', 'universal-cpt-migrator' ),
			'manage_options',
			'u-cpt-migrator-import',
			[ $this, 'render_import' ]
		);

		add_submenu_page(
			'u-cpt-migrator',
			__( 'Logs', 'universal-cpt-migrator' ),
			__( 'Logs', 'universal-cpt-migrator' ),
			'manage_options',
			'u-cpt-migrator-logs',
			[ $this->logs_page, 'render' ]
		);

		add_submenu_page(
			'u-cpt-migrator',
			__( 'Diagnostics', 'universal-cpt-migrator' ),
			__( 'Diagnostics', 'universal-cpt-migrator' ),
			'manage_options',
			'u-cpt-migrator-diagnostics',
			[ $this->diagnostics_page, 'render' ]
		);

		add_submenu_page(
			'u-cpt-migrator',
			__( 'Settings', 'universal-cpt-migrator' ),
			__( 'Settings', 'universal-cpt-migrator' ),
			'manage_options',
			'u-cpt-migrator-settings',
			[ $this->settings_page, 'render' ]
		);
	}

	public function render_dashboard() {
		include UCM_PATH . 'templates/dashboard.php';
	}

	public function render_export() {
		include UCM_PATH . 'templates/export.php';
	}

	public function render_import() {
		include UCM_PATH . 'templates/import.php';
	}
}
