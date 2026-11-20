<?php
/**
 * Plugin Name: Coral Petal – Back soon (prod)
 * Description: academy.coralpetal.ai shows the designed "We'll be back soon" page (HTTP 503) to everyone
 *              except signed-in site administrators. Dev (dev-academy) is unaffected. Rohit 2026-11-18.
 *              Go live = remove this file in a PR.
 */
if ( ! isset( $_SERVER['HTTP_HOST'] ) || strtolower( $_SERVER['HTTP_HOST'] ) !== 'academy.coralpetal.ai' ) {
	return;
}
function cp_back_soon_page() {
	status_header( 503 );
	header( 'Retry-After: 86400' );
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	nocache_headers();
	readfile( __DIR__ . '/coralpetal-maintenance/back-soon.html' );
	exit;
}
add_action( 'template_redirect', function () {
	if ( current_user_can( 'manage_options' ) ) { return; }
	cp_back_soon_page();
}, 0 );
// Keep the REST API and feeds closed to the public too.
add_filter( 'rest_authentication_errors', function ( $result ) {
	if ( ! current_user_can( 'manage_options' ) && ! empty( $_SERVER['REQUEST_URI'] ) && false === strpos( $_SERVER['REQUEST_URI'], '/wp-json/tutor' ) ) {
		return new WP_Error( 'cp_back_soon', 'Back soon.', array( 'status' => 503 ) );
	}
	return $result;
} );
