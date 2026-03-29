<?php

$bridge_path    = dirname( __DIR__, 2 ) . '/vendor/johnpbloch/wp-config.php';
$target_path    = dirname( __DIR__ ) . '/browser-site/wp-config.php';
$tmp_dir        = dirname( __DIR__ ) . '/tmp';
$browser_logs_dir = dirname( __DIR__ ) . '/browser-site/wp-content/uploads/u-cpt-mgr/logs';
$db_name_file   = $tmp_dir . '/browser-db-file.txt';
$db_file_name   = 'wordpress-browser-' . bin2hex( random_bytes( 6 ) ) . '.sqlite';
$db_path        = $tmp_dir . '/' . $db_file_name;

if ( ! file_exists( $tmp_dir ) ) {
	mkdir( $tmp_dir, 0777, true );
}

if ( ! file_exists( $browser_logs_dir ) ) {
	mkdir( $browser_logs_dir, 0777, true );
}

foreach ( glob( $browser_logs_dir . '/*.txt' ) as $old_log_file ) {
	if ( is_file( $old_log_file ) ) {
		@unlink( $old_log_file );
	}
}

foreach ( glob( $tmp_dir . '/wordpress-browser-*.sqlite' ) as $old_db_file ) {
	if ( is_file( $old_db_file ) ) {
		@unlink( $old_db_file );
	}
}

file_put_contents( $db_name_file, $db_file_name );
touch( $db_path );

file_put_contents(
	$bridge_path,
	"<?php\nrequire " . var_export( $target_path, true ) . ";\n"
);

defined( 'WP_INSTALLING' ) || define( 'WP_INSTALLING', true );
require $target_path;
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

if ( ! class_exists( '\UniversalCPTMigrator\Infrastructure\Storage' ) ) {
	require_once dirname( __DIR__, 2 ) . '/universal-cpt-migrator.php';
}

if ( ! is_blog_installed() ) {
	wp_install(
		'Universal CPT Migrator Browser Tests',
		'admin',
		'admin@example.org',
		true,
		'',
		'password'
	);
}

$user = get_user_by( 'login', 'admin' );
if ( $user ) {
	wp_set_password( 'password', $user->ID );
}

update_option( 'blogname', 'Universal CPT Migrator Browser Tests' );

wp_clear_scheduled_hook( 'wp_version_check' );
wp_clear_scheduled_hook( 'wp_update_plugins' );
wp_clear_scheduled_hook( 'wp_update_themes' );
wp_clear_scheduled_hook( 'delete_expired_transients' );

if ( function_exists( 'add_theme_support' ) ) {
	add_theme_support( 'post-thumbnails', [ 'post' ] );
}

$seed_ids = get_option( 'ucm_browser_seed_posts', [] );
if ( is_array( $seed_ids ) ) {
	foreach ( $seed_ids as $seed_id ) {
		if ( get_post( $seed_id ) ) {
			wp_delete_post( $seed_id, true );
		}
	}
}

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ucm_job_%'" );

$seed_ids   = [];
$seed_ids[] = wp_insert_post(
	[
		'post_type'    => 'post',
		'post_title'   => 'UCM Browser Source Alpha',
		'post_content' => 'Seed content for browser export coverage.',
		'post_status'  => 'publish',
	]
);
$seed_ids[] = wp_insert_post(
	[
		'post_type'    => 'post',
		'post_title'   => 'UCM Browser Source Beta',
		'post_content' => 'Additional seed content for browser export coverage.',
		'post_status'  => 'publish',
	]
);
update_option( 'ucm_browser_seed_posts', array_filter( array_map( 'intval', $seed_ids ) ), false );

$browser_package = [
	'metadata' => [
		'plugin'     => 'Universal CPT Migrator',
		'version'    => defined( 'UCM_VERSION' ) ? UCM_VERSION : '1.0.0',
		'post_type'  => 'post',
		'generated'  => gmdate( 'c' ),
		'item_count' => 1,
	],
	'items' => [
		[
			'uuid'         => '1a111111-1111-4111-8111-111111111111',
			'post_type'    => 'post',
			'post_title'   => 'UCM Browser Imported Post',
			'post_content' => 'Imported through the browser workflow.',
			'post_excerpt' => '',
			'post_status'  => 'publish',
			'post_name'    => 'ucm-browser-imported-post',
			'post_date'    => current_time( 'mysql' ),
			'post_author'  => 'admin',
			'taxonomies'   => [],
			'acf'          => [],
			'meta'         => [],
		],
	],
];

$fixture_path = dirname( __DIR__ ) . '/tmp/browser-import-package.json';
file_put_contents( $fixture_path, wp_json_encode( $browser_package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

$storage = null;
if ( class_exists( '\UniversalCPTMigrator\Infrastructure\Storage' ) ) {
	$storage = new \UniversalCPTMigrator\Infrastructure\Storage();
	$storage->setup_directories();
}

$logs_dir = $storage ? $storage->get_path( 'logs' ) : $browser_logs_dir;
if ( ! file_exists( $logs_dir ) ) {
	wp_mkdir_p( $logs_dir );
}

file_put_contents(
	trailingslashit( $logs_dir ) . 'browser-fixture-log.txt',
	"[2026-03-29 00:00:00] [ERROR] Browser remediation fixture log\n" .
	"[2026-03-29 00:00:01] [ERROR] Packaged image bytes did not match the declared image type.\n"
);
file_put_contents(
	trailingslashit( $logs_dir ) . 'browser-retryable-log.txt',
	"[2026-03-29 00:05:00] [ERROR] Browser retryable import fixture log\n" .
	"[2026-03-29 00:05:01] [ERROR] Chunk processing failed while applying package item 1.\n"
);
file_put_contents(
	trailingslashit( $logs_dir ) . 'browser-export-log.txt',
	"[2026-03-29 00:10:00] [ERROR] Browser export packaging fixture log\n" .
	"[2026-03-29 00:10:01] [ERROR] ZIP artifact creation failed while packaging the export bundle.\n"
);
file_put_contents(
	trailingslashit( $logs_dir ) . 'browser-running-log.txt',
	"[2026-03-29 00:15:00] [INFO] Browser running import fixture log\n" .
	"[2026-03-29 00:15:01] [INFO] Long-running import fixture still processing.\n"
);

if ( class_exists( '\UniversalCPTMigrator\Infrastructure\JobStore' ) ) {
	$jobs = new \UniversalCPTMigrator\Infrastructure\JobStore();

	$failed_job = $jobs->create(
		[
			'type'              => 'import',
			'status'            => 'failed',
			'stage'             => 'failed',
			'failed_stage'      => 'processing_chunk',
			'failure_category'  => 'media',
			'failure_subsystem' => 'media_manifest_content_validation',
			'remediation_key'   => 'check_packaged_media_manifest',
			'retryable'         => false,
			'error'             => 'Packaged image bytes did not match the declared image type.',
			'error_code'        => 'ucm_media_manifest_invalid_image_content',
			'error_context'     => [
				'path' => 'media/broken.gif',
				'mime' => 'image/gif',
			],
			'log_path'          => trailingslashit( $logs_dir ) . 'browser-fixture-log.txt',
			'validation'        => [
				'summary' => [
					'items' => 1,
				],
			],
		]
	);

	$retryable_failed_job = $jobs->create(
		[
			'type'              => 'import',
			'status'            => 'failed',
			'stage'             => 'failed',
			'failed_stage'      => 'processing_chunk',
			'mode'              => 'import',
			'package'           => [ 'package' => $browser_package ],
			'validation'        => [
				'is_valid' => true,
				'errors'   => [],
				'warnings' => [],
				'summary'  => [
					'items'     => 1,
					'post_type' => 'post',
				],
			],
			'results'           => [
				'imported' => 0,
				'updated'  => 0,
				'failed'   => 1,
				'items'    => [],
			],
			'offset'            => 0,
			'progress'          => 0,
			'failure_category'  => 'import',
			'failure_subsystem' => 'import_chunk_processor',
			'remediation_key'   => 'resume_or_reupload_import',
			'retryable'         => true,
			'error'             => 'Chunk processing failed while applying package item 1.',
			'error_code'        => 'ucm_invalid_package',
			'error_context'     => [
				'offset' => 0,
				'mode'   => 'import',
			],
			'log_path'          => trailingslashit( $logs_dir ) . 'browser-retryable-log.txt',
		]
	);

	$queued_job = $jobs->create(
		[
			'type'       => 'import',
			'status'     => 'queued',
			'stage'      => 'queued',
			'mode'       => 'import',
			'package'    => $browser_package,
			'validation' => [
				'summary' => [
					'items'     => 1,
					'post_type' => 'post',
				],
			],
			'offset'     => 0,
			'progress'   => 0,
			'results'    => [
				'imported' => 0,
				'updated'  => 0,
				'failed'   => 0,
				'items'    => [],
			],
		]
	);

	$failed_export_job = $jobs->create(
		[
			'type'              => 'export',
			'status'            => 'failed',
			'stage'             => 'failed',
			'failed_stage'      => 'package_transport',
			'progress'          => 80,
			'failure_category'  => 'transport',
			'failure_subsystem' => 'package_transport',
			'remediation_key'   => 'inspect_package_transport',
			'retryable'         => true,
			'error'             => 'ZIP artifact creation failed while packaging the export bundle.',
			'error_code'        => 'ucm_zip_creation_failed',
			'error_context'     => [
				'post_type' => 'post',
			],
			'log_path'          => trailingslashit( $logs_dir ) . 'browser-export-log.txt',
		]
	);

	$running_import_job = $jobs->create(
		[
			'type'       => 'import',
			'status'     => 'running',
			'stage'      => 'processing_chunk',
			'mode'       => 'import',
			'package'    => [ 'package' => $browser_package ],
			'validation' => [
				'is_valid' => true,
				'errors'   => [],
				'warnings' => [],
				'summary'  => [
					'items'     => 50,
					'post_type' => 'post',
				],
			],
			'offset'     => 25,
			'progress'   => 50,
			'results'    => [
				'imported' => 25,
				'updated'  => 0,
				'failed'   => 0,
				'items'    => [],
			],
			'log_path'   => trailingslashit( $logs_dir ) . 'browser-running-log.txt',
		]
	);

	$queued_state               = $jobs->get( $queued_job );
	$queued_state['updated_at'] = gmdate( 'c', time() - ( 2 * HOUR_IN_SECONDS ) );
	update_option( \UniversalCPTMigrator\Infrastructure\JobStore::OPTION_PREFIX . sanitize_key( $queued_job ), $queued_state, false );

	$running_state               = $jobs->get( $running_import_job );
	$running_state['updated_at'] = gmdate( 'c', time() - ( 3 * HOUR_IN_SECONDS ) );
	update_option( \UniversalCPTMigrator\Infrastructure\JobStore::OPTION_PREFIX . sanitize_key( $running_import_job ), $running_state, false );

	update_option(
		'ucm_browser_fixture_jobs',
		[
			'failed'           => $failed_job,
			'failed_retryable' => $retryable_failed_job,
			'failed_export'    => $failed_export_job,
			'queued'           => $queued_job,
			'running_import'   => $running_import_job,
		],
		false
	);
}
