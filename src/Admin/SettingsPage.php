<?php

namespace UniversalCPTMigrator\Admin;

use UniversalCPTMigrator\Infrastructure\Settings;

class SettingsPage {
	private $settings;

	public function __construct() {
		$this->settings = new Settings();
	}

	public function render() {
		$values = $this->settings->get_all();
		include UCM_PATH . 'templates/settings.php';
	}
}
