<?php

namespace UniversalCPTMigrator\Admin;

class Assets {
	public function init() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'u-cpt-migrator' ) === false ) {
			return;
		}

		wp_enqueue_style( 'ucm-admin-css', UCM_URL . 'assets/css/admin.css', [], UCM_VERSION );
		wp_enqueue_script( 'ucm-admin-js', UCM_URL . 'assets/js/admin.js', [ 'jquery' ], UCM_VERSION, true );

		wp_localize_script( 'ucm-admin-js', 'ucmLocal', [
			'nonce'   => wp_create_nonce( 'ucm_admin_nonce' ),
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'urls'    => [
				'dashboard'   => admin_url( 'admin.php?page=u-cpt-migrator' ),
				'export'      => admin_url( 'admin.php?page=u-cpt-migrator-export' ),
				'import'      => admin_url( 'admin.php?page=u-cpt-migrator-import' ),
				'diagnostics' => admin_url( 'admin.php?page=u-cpt-migrator-diagnostics' ),
				'logs'        => admin_url( 'admin.php?page=u-cpt-migrator-logs' ),
				'settings'    => admin_url( 'admin.php?page=u-cpt-migrator-settings' ),
			],
		] );
	}
}
