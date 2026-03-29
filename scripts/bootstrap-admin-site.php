<?php

$workspace_root = dirname( __DIR__ );
$tests_dir      = $workspace_root . '/tests';
$tmp_dir        = $tests_dir . '/tmp';
$site_dir       = $tests_dir . '/admin-site';
$content_dir    = $site_dir . '/wp-content';
$uploads_dir    = $content_dir . '/uploads';
$bridge_path    = $workspace_root . '/vendor/johnpbloch/wp-config.php';
$target_path    = $site_dir . '/wp-config.php';
$db_name_file   = $tmp_dir . '/admin-db-file.txt';
$db_file_name   = 'wordpress-admin.sqlite';
$db_path        = $tmp_dir . '/' . $db_file_name;
$sample_json    = $tmp_dir . '/admin-import-package.json';
$sample_zip     = $tmp_dir . '/admin-import-package.zip';

foreach ( [ $tmp_dir, $content_dir, $content_dir . '/mu-plugins', $uploads_dir ] as $dir ) {
	if ( ! file_exists( $dir ) ) {
		mkdir( $dir, 0777, true );
	}
}

file_put_contents( $db_name_file, $db_file_name );
if ( ! file_exists( $db_path ) ) {
	touch( $db_path );
}

file_put_contents(
	$bridge_path,
	"<?php\nrequire " . var_export( $target_path, true ) . ";\n"
);

defined( 'WP_INSTALLING' ) || define( 'WP_INSTALLING', true );
require $target_path;
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

if ( ! is_blog_installed() ) {
	wp_install(
		'Universal CPT Migrator Admin Sandbox',
		'admin',
		'admin@example.org',
		true,
		'127.0.0.1',
		'password'
	);
}

$user = get_user_by( 'login', 'admin' );
if ( $user ) {
	wp_set_password( 'password', $user->ID );
}

update_option( 'blogname', 'Universal CPT Migrator Admin Sandbox' );
update_option( 'blogdescription', 'Manual admin sandbox for Universal CPT Migrator.' );
update_option( 'stylesheet', 'ucm-admin-sandbox' );
update_option( 'template', 'ucm-admin-sandbox' );

wp_clear_scheduled_hook( 'wp_version_check' );
wp_clear_scheduled_hook( 'wp_update_plugins' );
wp_clear_scheduled_hook( 'wp_update_themes' );
wp_clear_scheduled_hook( 'delete_expired_transients' );

if ( function_exists( 'add_theme_support' ) ) {
	add_theme_support( 'post-thumbnails', [ 'post', 'ucm_demo_book', 'ucm_demo_event', 'ucm_demo_case', 'ucm_demo_vendor', 'ucm_demo_project' ] );
}

$seed_post_ids = get_option( 'ucm_admin_seed_posts', [] );
if ( is_array( $seed_post_ids ) ) {
	foreach ( $seed_post_ids as $seed_id ) {
		if ( get_post( $seed_id ) ) {
			wp_delete_post( $seed_id, true );
		}
	}
}

$seed_term_map = get_option( 'ucm_admin_seed_terms', [] );
if ( is_array( $seed_term_map ) ) {
	foreach ( $seed_term_map as $taxonomy => $term_ids ) {
		if ( ! taxonomy_exists( $taxonomy ) || ! is_array( $term_ids ) ) {
			continue;
		}

		foreach ( $term_ids as $seed_term_id ) {
			$seed_term_id = (int) $seed_term_id;
			if ( $seed_term_id > 0 ) {
				wp_delete_term( $seed_term_id, $taxonomy );
			}
		}
	}
}

$seed_attachment_ids = get_option( 'ucm_admin_seed_attachments', [] );
if ( is_array( $seed_attachment_ids ) ) {
	foreach ( $seed_attachment_ids as $seed_attachment_id ) {
		if ( get_post( $seed_attachment_id ) ) {
			wp_delete_attachment( $seed_attachment_id, true );
		}
	}
}

$term_map = [];
$taxonomy_seed = [
	'ucm_demo_genre'         => [ 'Migration', 'Operations', 'Playbooks', 'Governance', 'Automation' ],
	'ucm_demo_event_type'    => [ 'Workshop', 'Webinar', 'Launch', 'Training' ],
	'ucm_demo_sector'        => [ 'Healthcare', 'Finance', 'Education', 'Retail' ],
	'ucm_demo_region'        => [ 'North America', 'LATAM', 'Europe', 'APAC' ],
	'ucm_demo_project_phase' => [ 'Discovery', 'Design', 'Implementation', 'Hypercare' ],
	'ucm_demo_capability'    => [ 'Content Migration', 'Search', 'Personalization', 'Analytics', 'DAM' ],
];

foreach ( $taxonomy_seed as $taxonomy => $term_names ) {
	$term_map[ $taxonomy ] = [];
	foreach ( $term_names as $term_name ) {
		$result = wp_insert_term( $term_name, $taxonomy );
		if ( ! is_wp_error( $result ) ) {
			$term_map[ $taxonomy ][] = (int) $result['term_id'];
		}
	}
}

$tiny_gif = base64_decode( 'R0lGODdhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==' );
$seed_attachments = [];
for ( $index = 1; $index <= 12; $index++ ) {
	$upload = wp_upload_bits( 'ucm-admin-demo-' . $index . '.gif', null, $tiny_gif );
	if ( empty( $upload['error'] ) ) {
		$filetype   = wp_check_filetype( $upload['file'] );
		$attachment = [
			'post_mime_type' => $filetype['type'],
			'post_title'     => 'UCM Admin Demo Image ' . $index,
			'post_status'    => 'inherit',
		];
		$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );
		if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
			$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
			wp_update_attachment_metadata( $attachment_id, $metadata );
			$seed_attachments[] = (int) $attachment_id;
		}
	}
}

$seed_posts = [];
$attachment_index = 0;

$create_post = static function( $args, $thumbnail_id = 0, array $tax_assignments = [] ) use ( &$seed_posts ) {
	$meta_input = [];
	if ( isset( $args['meta_input'] ) && is_array( $args['meta_input'] ) ) {
		$meta_input = $args['meta_input'];
		unset( $args['meta_input'] );
	}

	$post_id = wp_insert_post( $args );
	if ( $post_id && ! is_wp_error( $post_id ) ) {
		$seed_posts[] = (int) $post_id;
		foreach ( $meta_input as $meta_key => $meta_value ) {
			update_post_meta( $post_id, $meta_key, $meta_value );
		}
		if ( $thumbnail_id ) {
			set_post_thumbnail( $post_id, $thumbnail_id );
		}
		foreach ( $tax_assignments as $taxonomy => $terms ) {
			if ( taxonomy_exists( $taxonomy ) && ! empty( $terms ) ) {
				wp_set_object_terms( $post_id, $terms, $taxonomy );
			}
		}
		return (int) $post_id;
	}
	return 0;
};

for ( $i = 1; $i <= 6; $i++ ) {
	$create_post(
		[
			'post_type'    => 'post',
			'post_title'   => 'UCM Admin Source Post ' . $i,
			'post_content' => 'Baseline post content for export and sample generation tests. Item ' . $i . '.',
			'post_excerpt' => 'Source post excerpt ' . $i . '.',
			'post_status'  => 'publish',
			'meta_input'   => [
				'ucm_demo_flag'     => 'enabled',
				'ucm_priority'      => $i,
				'ucm_sync_strategy' => ( $i % 2 ) ? 'push' : 'pull',
			],
		],
		! empty( $seed_attachments[ $attachment_index % count( $seed_attachments ) ] ) ? $seed_attachments[ $attachment_index % count( $seed_attachments ) ] : 0
	);
	$attachment_index++;
}

for ( $i = 1; $i <= 7; $i++ ) {
	$create_post(
		[
			'post_type'    => 'ucm_demo_book',
			'post_title'   => 'Migration Playbook Volume ' . $i,
			'post_content' => 'Demo book content for export/import testing. Volume ' . $i . '.',
			'post_excerpt' => 'Playbook excerpt ' . $i . '.',
			'post_status'  => 'publish',
			'meta_input'   => [
				'ucm_book_isbn'      => sprintf( '978-0-0000-%04d', $i ),
				'ucm_book_region'    => $i % 2 ? 'LATAM' : 'Global',
				'ucm_book_edition'   => $i,
				'ucm_book_page_count'=> 120 + ( $i * 14 ),
			],
		],
		! empty( $seed_attachments[ $attachment_index % count( $seed_attachments ) ] ) ? $seed_attachments[ $attachment_index % count( $seed_attachments ) ] : 0,
		[
			'ucm_demo_genre' => array_slice( $term_map['ucm_demo_genre'], 0, ( $i % 3 ) + 1 ),
		]
	);
	$attachment_index++;
}

for ( $i = 1; $i <= 6; $i++ ) {
	$create_post(
		[
			'post_type'    => 'ucm_demo_note',
			'post_title'   => 'Field Note ' . $i,
			'post_content' => 'Short operational note for migration testing. Note ' . $i . '.',
			'post_excerpt' => 'Operational note ' . $i . '.',
			'post_status'  => 'publish',
			'meta_input'   => [
				'ucm_note_priority' => [ 'low', 'medium', 'high' ][ $i % 3 ],
				'ucm_note_owner'    => 'ops-team-' . $i,
			],
		]
	);
}

for ( $i = 1; $i <= 6; $i++ ) {
	$create_post(
		[
			'post_type'    => 'ucm_demo_event',
			'post_title'   => 'Migration Event ' . $i,
			'post_content' => 'Event record with schedule and venue details. Event ' . $i . '.',
			'post_excerpt' => 'Event excerpt ' . $i . '.',
			'post_status'  => 'publish',
			'meta_input'   => [
				'ucm_event_start'    => gmdate( 'Y-m-d', strtotime( '+' . $i . ' days' ) ),
				'ucm_event_end'      => gmdate( 'Y-m-d', strtotime( '+' . ( $i + 1 ) . ' days' ) ),
				'ucm_event_capacity' => 40 + ( $i * 15 ),
				'ucm_event_city'     => [ 'Quito', 'Bogota', 'Lima', 'CDMX' ][ $i % 4 ],
			],
		],
		! empty( $seed_attachments[ $attachment_index % count( $seed_attachments ) ] ) ? $seed_attachments[ $attachment_index % count( $seed_attachments ) ] : 0,
		[
			'ucm_demo_event_type' => [ $term_map['ucm_demo_event_type'][ $i % count( $term_map['ucm_demo_event_type'] ) ] ],
		]
	);
	$attachment_index++;
}

for ( $i = 1; $i <= 5; $i++ ) {
	$create_post(
		[
			'post_type'    => 'ucm_demo_case',
			'post_title'   => 'Transformation Case Study ' . $i,
			'post_content' => 'Case study narrative with outcomes and migration notes. Case ' . $i . '.',
			'post_excerpt' => 'Case study excerpt ' . $i . '.',
			'post_status'  => 'publish',
			'menu_order'   => $i,
			'meta_input'   => [
				'ucm_case_client'       => 'Client ' . chr( 64 + $i ),
				'ucm_case_success_rate' => 80 + $i,
				'ucm_case_duration_weeks' => 6 + $i,
			],
		],
		! empty( $seed_attachments[ $attachment_index % count( $seed_attachments ) ] ) ? $seed_attachments[ $attachment_index % count( $seed_attachments ) ] : 0,
		[
			'ucm_demo_sector' => [ $term_map['ucm_demo_sector'][ $i % count( $term_map['ucm_demo_sector'] ) ] ],
		]
	);
	$attachment_index++;
}

$vendor_ids = [];
for ( $i = 1; $i <= 5; $i++ ) {
	$vendor_ids[] = $create_post(
		[
			'post_type'    => 'ucm_demo_vendor',
			'post_title'   => 'Vendor Partner ' . $i,
			'post_content' => 'Vendor profile used for project relationship testing. Vendor ' . $i . '.',
			'post_excerpt' => 'Vendor excerpt ' . $i . '.',
			'post_status'  => 'publish',
			'meta_input'   => [
				'ucm_vendor_tier'     => [ 'gold', 'silver', 'bronze' ][ $i % 3 ],
				'ucm_vendor_contact'  => 'vendor' . $i . '@example.org',
				'ucm_vendor_sla_days' => 3 + $i,
			],
		],
		! empty( $seed_attachments[ $attachment_index % count( $seed_attachments ) ] ) ? $seed_attachments[ $attachment_index % count( $seed_attachments ) ] : 0,
		[
			'ucm_demo_region' => [ $term_map['ucm_demo_region'][ $i % count( $term_map['ucm_demo_region'] ) ] ],
		]
	);
	$attachment_index++;
}

$project_parent = 0;
for ( $i = 1; $i <= 8; $i++ ) {
	$project_id = $create_post(
		[
			'post_type'    => 'ucm_demo_project',
			'post_title'   => 'Enterprise Migration Program ' . $i,
			'post_content' => 'Complex project record with linked vendors, books, milestones, and structured meta. Project ' . $i . '.',
			'post_excerpt' => 'Project excerpt ' . $i . '.',
			'post_status'  => 'publish',
			'post_parent'  => ( $i > 4 ) ? $project_parent : 0,
			'menu_order'   => $i,
			'meta_input'   => [
				'ucm_project_code'           => 'PRJ-' . str_pad( (string) $i, 3, '0', STR_PAD_LEFT ),
				'ucm_project_status'         => [ 'discovery', 'design', 'delivery', 'hypercare' ][ $i % 4 ],
				'ucm_project_priority'       => [ 'low', 'medium', 'high', 'critical' ][ $i % 4 ],
				'ucm_project_owner_name'     => 'Program Lead ' . $i,
				'ucm_project_owner_email'    => 'lead' . $i . '@example.org',
				'ucm_project_client_name'    => 'Enterprise Client ' . chr( 64 + $i ),
				'ucm_project_client_url'     => 'https://example.org/client-' . $i,
				'ucm_project_budget'         => 50000 + ( $i * 7500 ),
				'ucm_project_margin_target'  => 22.5 + $i,
				'ucm_project_score'          => 74.2 + $i,
				'ucm_project_team_size'      => 6 + $i,
				'ucm_project_duration_weeks' => 10 + $i,
				'ucm_project_progress_percent' => min( 95, 12 * $i ),
				'ucm_project_is_billable'    => ( $i % 2 ) === 0,
				'ucm_project_is_multisite'   => ( $i % 3 ) === 0,
				'ucm_project_requires_sso'   => ( $i % 2 ) === 1,
				'ucm_project_go_live'        => gmdate( 'Y-m-d', strtotime( '+' . ( $i * 7 ) . ' days' ) ),
				'ucm_project_last_sync_at'   => gmdate( 'c', strtotime( '+' . ( $i * 5 ) . ' days 14:30:00' ) ),
				'ucm_project_timezone'       => [ 'America/Guayaquil', 'America/Bogota', 'Europe/Madrid', 'UTC' ][ $i % 4 ],
				'ucm_project_risk_level'     => [ 'low', 'medium', 'high' ][ $i % 3 ],
				'ucm_project_color'          => [ '#0f766e', '#b45309', '#1d4ed8', '#7c3aed' ][ $i % 4 ],
				'ucm_project_primary_locale' => [ 'en_US', 'es_EC', 'es_CO', 'pt_BR' ][ $i % 4 ],
				'ucm_project_summary'        => 'Program summary for migration project ' . $i . ' spanning content, taxonomy, media, and search concerns.',
				'ucm_project_notes'          => "Long-form notes for project {$i}.\nIncludes deployment, rollback, QA, and editorial coordination details.",
				'ucm_project_vendor_ids'     => array_values( array_filter( [ $vendor_ids[ $i % count( $vendor_ids ) ] ?? 0, $vendor_ids[ ( $i + 1 ) % count( $vendor_ids ) ] ?? 0 ] ) ),
				'ucm_project_related_books'  => array_slice( array_values( array_filter( $seed_posts ) ), 1, 2 ),
				'ucm_project_tags'           => [ 'migration', 'search', 'qa', 'launch-' . $i ],
				'ucm_project_stakeholders'   => [
					[ 'name' => 'Executive Sponsor ' . $i, 'role' => 'sponsor', 'email' => 'sponsor' . $i . '@example.org' ],
					[ 'name' => 'Platform Owner ' . $i, 'role' => 'owner', 'email' => 'platform' . $i . '@example.org' ],
					[ 'name' => 'Editorial Lead ' . $i, 'role' => 'editorial', 'email' => 'editorial' . $i . '@example.org' ],
				],
				'ucm_project_milestones'     => [
					[ 'title' => 'Discovery', 'owner' => 'team-' . $i, 'status' => 'complete', 'due' => gmdate( 'Y-m-d', strtotime( '+' . $i . ' days' ) ) ],
					[ 'title' => 'Build', 'owner' => 'team-' . ( $i + 1 ), 'status' => 'active', 'due' => gmdate( 'Y-m-d', strtotime( '+' . ( $i + 14 ) . ' days' ) ) ],
					[ 'title' => 'Launch', 'owner' => 'team-' . ( $i + 2 ), 'status' => 'planned', 'due' => gmdate( 'Y-m-d', strtotime( '+' . ( $i + 28 ) . ' days' ) ) ],
				],
				'ucm_project_integrations'   => [
					[ 'system' => 'DAM', 'enabled' => true, 'mode' => 'push' ],
					[ 'system' => 'CRM', 'enabled' => ( $i % 2 ) === 0, 'mode' => 'pull' ],
					[ 'system' => 'Search', 'enabled' => true, 'mode' => 'sync' ],
				],
				'ucm_project_configuration'  => [
					'search_enabled' => ( $i % 2 ) === 0,
					'regions'        => [ 'latam', 'na' ],
					'locales'        => [ 'en_US', 'es_EC' ],
					'flags'          => [
						'sso'         => ( $i % 2 ) === 1,
						'cdn'         => true,
						'multilingual'=> true,
					],
					'thresholds'     => [
						'warning' => 75,
						'error'   => 90,
					],
				],
				'ucm_project_content_model'  => [
					'types'         => [ 'article', 'landing_page', 'faq', 'resource' ],
					'taxonomies'    => [ 'region', 'product', 'audience' ],
					'has_acf'       => true,
					'relationship_depth' => 3,
				],
				'ucm_project_launch_checklist' => [
					[ 'item' => 'Schema freeze', 'done' => true ],
					[ 'item' => 'Editorial signoff', 'done' => ( $i % 2 ) === 0 ],
					[ 'item' => 'Search reindex', 'done' => false ],
					[ 'item' => 'Post-launch QA', 'done' => false ],
				],
			],
		],
		! empty( $seed_attachments[ $attachment_index % count( $seed_attachments ) ] ) ? $seed_attachments[ $attachment_index % count( $seed_attachments ) ] : 0,
		[
			'ucm_demo_region'        => [ $term_map['ucm_demo_region'][ $i % count( $term_map['ucm_demo_region'] ) ] ],
			'ucm_demo_project_phase' => [ $term_map['ucm_demo_project_phase'][ $i % count( $term_map['ucm_demo_project_phase'] ) ] ],
			'ucm_demo_capability'    => array_slice( $term_map['ucm_demo_capability'], 0, ( $i % 3 ) + 2 ),
		]
	);
	if ( 1 === $i ) {
		$project_parent = $project_id;
	}
	$attachment_index++;
}

update_option( 'ucm_admin_seed_posts', $seed_posts, false );
update_option( 'ucm_admin_seed_terms', $term_map, false );
update_option( 'ucm_admin_seed_attachments', $seed_attachments, false );

$sample_package = [
	'metadata' => [
		'plugin'     => 'Universal CPT Migrator',
		'version'    => defined( 'UCM_VERSION' ) ? UCM_VERSION : '1.0.0',
		'post_type'  => 'post',
		'generated'  => gmdate( 'c' ),
		'item_count' => 1,
	],
	'items' => [
		[
			'uuid'         => '5a111111-1111-4111-8111-111111111111',
			'post_type'    => 'post',
			'post_title'   => 'UCM Admin Imported Fixture',
			'post_content' => 'Fixture package generated for manual import validation.',
			'post_excerpt' => 'Fixture import excerpt.',
			'post_status'  => 'publish',
			'post_name'    => 'ucm-admin-imported-fixture',
			'post_date'    => current_time( 'mysql' ),
			'post_author'  => 'admin',
			'taxonomies'   => [],
			'acf'          => [],
			'meta'         => [
				'ucm_demo_flag' => 'from-fixture',
			],
		],
	],
];

file_put_contents( $sample_json, wp_json_encode( $sample_package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

if ( class_exists( 'ZipArchive' ) ) {
	$zip = new ZipArchive();
	if ( true === $zip->open( $sample_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		$zip->addFromString( 'package.json', wp_json_encode( $sample_package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		$zip->close();
	}
}

$info = [
	'site_url'      => 'http://127.0.0.1:8890',
	'login_url'     => 'http://127.0.0.1:8890/wp-login.php',
	'admin_user'    => 'admin',
	'admin_pass'    => 'password',
	'dashboard_url' => 'http://127.0.0.1:8890/wp-admin/admin.php?page=u-cpt-migrator',
	'export_url'    => 'http://127.0.0.1:8890/wp-admin/admin.php?page=u-cpt-migrator-export',
	'import_url'    => 'http://127.0.0.1:8890/wp-admin/admin.php?page=u-cpt-migrator-import',
	'logs_url'      => 'http://127.0.0.1:8890/wp-admin/admin.php?page=u-cpt-migrator-logs',
	'diagnostics_url' => 'http://127.0.0.1:8890/wp-admin/admin.php?page=u-cpt-migrator-diagnostics',
	'settings_url'  => 'http://127.0.0.1:8890/wp-admin/admin.php?page=u-cpt-migrator-settings',
	'sample_json'   => $sample_json,
	'sample_zip'    => file_exists( $sample_zip ) ? $sample_zip : '',
];

file_put_contents(
	$tmp_dir . '/admin-site-info.json',
	wp_json_encode( $info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
);

echo "Admin site bootstrapped.\n";
echo "URL: http://127.0.0.1:8890\n";
echo "Login: admin / password\n";
echo "Sample JSON: {$sample_json}\n";
if ( file_exists( $sample_zip ) ) {
	echo "Sample ZIP: {$sample_zip}\n";
}
