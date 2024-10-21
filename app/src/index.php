<?php

global $redis;

const WEBHOOK_NS = 'wh:';
const CLIENT_SITE_NS = 'si:';

$redis = new Redis();
try {
	$redis->connect( getenv( 'valkey' ) );
} catch ( RedisException $e ) {
	header( 'HTTP/1.1 500 Internal Server Error' );
	exit;
}

function init_client(): void {
	global $site_info, $redis;

	if ( ! isset( $_SERVER['HTTP_X_WB_CLIENT_SITE'] ) ) {
		header( 'HTTP/1.1 400 Bad Request' );
		echo 'HTTP_X_WB_CLIENT_SITE header is required';
		exit;
	}

	ini_set( 'session.save_handler', 'redis' );
	ini_set( 'session.save_path', 'tcp://' . getenv('valkey') . ':6379' );
	$site_info_json = $redis->get( CLIENT_SITE_NS . $_SERVER['HTTP_X_WB_CLIENT_SITE'] );

	if ( ! $site_info_json ) {
		header( 'HTTP/1.1 403 Forbidden' );
		exit;
	}

	$site_info = json_decode( $site_info_json, true );

	if ( ! isset( $_SESSION ) ) {
		session_start();
	}

	header("Access-Control-Allow-Origin: {$site_info['url']}");
}

$request = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

switch ( $request ) {
	case '/store/v1/cart':
		init_client();
		require __DIR__ . '/store/cart.php';
		break;
	case '/store/v1/products':
		init_client();
		require __DIR__ . '/store/products.php';
		break;
	case '/webhooks/products':
		require __DIR__ . '/webhooks/products.php';
		break;
	default:
		header( 'HTTP/1.1 404 Not Found' );
		break;
}
