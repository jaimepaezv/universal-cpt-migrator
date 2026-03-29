<?php

$core = realpath( dirname( __DIR__, 2 ) . '/vendor/johnpbloch/wordpress-core' );
$workspace = realpath( dirname( __DIR__, 2 ) );
$bridge = $workspace . '/vendor/johnpbloch/wp-config.php';
$target_config = dirname( __DIR__ ) . '/browser-site/wp-config.php';
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$path = $path ? $path : '/';
$candidate = realpath( $core . $path );
$directory_index = null;
$workspace_candidate = null;

function ucm_browser_router_mime_type( $path ) {
	$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

	switch ( $extension ) {
		case 'css':
			return 'text/css; charset=UTF-8';
		case 'js':
			return 'application/javascript; charset=UTF-8';
		case 'json':
			return 'application/json; charset=UTF-8';
		case 'svg':
			return 'image/svg+xml';
		case 'png':
			return 'image/png';
		case 'jpg':
		case 'jpeg':
			return 'image/jpeg';
		case 'gif':
			return 'image/gif';
		case 'woff':
			return 'font/woff';
		case 'woff2':
			return 'font/woff2';
		default:
			return function_exists( 'mime_content_type' ) ? mime_content_type( $path ) : 'application/octet-stream';
	}
}

if ( ! file_exists( $bridge ) || strpos( (string) file_get_contents( $bridge ), str_replace( '\\', '\\\\', $target_config ) ) === false ) {
	file_put_contents(
		$bridge,
		"<?php\nrequire " . var_export( $target_config, true ) . ";\n"
	);
}

if ( $candidate && 0 === strpos( $candidate, $core ) && is_dir( $candidate ) ) {
	$directory_index = realpath( rtrim( $candidate, '/\\' ) . '/index.php' );
}

if ( 0 === strpos( $path, '/wp-content/mu-plugins/universal-cpt-migrator/' ) ) {
	$workspace_relative  = substr( $path, strlen( '/wp-content/mu-plugins/universal-cpt-migrator/' ) );
	$workspace_candidate = realpath( $workspace . '/' . ltrim( $workspace_relative, '/' ) );
}

if ( $candidate && 0 === strpos( $candidate, $core ) && is_file( $candidate ) ) {
	$extension = strtolower( pathinfo( $candidate, PATHINFO_EXTENSION ) );

	if ( 'php' === $extension ) {
		$_SERVER['SCRIPT_FILENAME'] = $candidate;
		$_SERVER['SCRIPT_NAME']     = $path;
		$_SERVER['PHP_SELF']        = $path;
		chdir( dirname( $candidate ) );
		require $candidate;
		return true;
	}

	$mime = ucm_browser_router_mime_type( $candidate );
	header( 'Content-Type: ' . $mime );
	readfile( $candidate );
	return true;
}

if ( $directory_index && 0 === strpos( $directory_index, $core ) && is_file( $directory_index ) ) {
	$normalized_path           = rtrim( $path, '/' ) . '/index.php';
	$_SERVER['SCRIPT_FILENAME'] = $directory_index;
	$_SERVER['SCRIPT_NAME']     = $normalized_path;
	$_SERVER['PHP_SELF']        = $normalized_path;
	chdir( dirname( $directory_index ) );
	require $directory_index;
	return true;
}

if ( $workspace_candidate && 0 === strpos( $workspace_candidate, $workspace ) && is_file( $workspace_candidate ) ) {
	$mime = ucm_browser_router_mime_type( $workspace_candidate );
	header( 'Content-Type: ' . $mime );
	readfile( $workspace_candidate );
	return true;
}

$_SERVER['SCRIPT_FILENAME'] = $core . '/index.php';
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';
chdir( $core );
require $core . '/index.php';
return true;
