<?php
global $redis;

$token = $_GET['token'];
if ( $token !== getenv( 'ADMIN_TOKEN' ) ) {
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

// TODO: Add stub for IP range validation.

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
	$site_info = json_decode( file_get_contents( 'php://input' ), true );
	if ( ! isset( $site_info['id'], $site_info['url'] ) ) {
		header( 'HTTP/1.1 400 Bad Request' );
		echo 'Missing required fields';
		exit;
	}

	$site_exists = $redis->get( CLIENT_SITE_NS . $site_info['id'] );
	if ( $site_exists ) {
		header( 'HTTP/1.1 409 Conflict' );
		echo 'Site already exists';
		exit;
	}

	$site_info_json = json_encode( $site_info );
	$redis->set( CLIENT_SITE_NS . $site_info['id'], $site_info_json );

	require_once 'es-request.php';
	$es_results = es_map_products( $site_info );
	if ( $es_results['status'] !== 200 ) {
		$redis->del( CLIENT_SITE_NS . $site_info['id'] );
		header( 'HTTP/1.1 500 Internal Server Error' );
		print_r( $es_results );
		echo 'Error creating site index';
		exit;
	}

	echo 'Site info saved';
} elseif ( $_SERVER['REQUEST_METHOD'] === 'GET' ) {
	$site_info_json = $redis->get( CLIENT_SITE_NS . $_GET['id'] );
	if ( ! $site_info_json ) {
		header( 'HTTP/1.1 404 Not Found' );
		exit;
	}

	echo $site_info_json;
} else {
	header( 'HTTP/1.1 405 Method Not Allowed' );
	exit;
}