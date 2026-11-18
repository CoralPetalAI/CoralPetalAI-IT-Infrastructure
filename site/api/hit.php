<?php
// First-party, cookieless page-view counter (2026-11-18, Rohit: privacy-friendly analytics on the admin dashboard).
// Pages on coralpetal.ai, careers and academy send navigator.sendBeacon(<this url>, JSON {p: path, r: referrer}).
// Stored: day, site, path, referrer HOST only, device class, and a visitor hash that changes every day
// (sha256 of a daily salt + IP + user agent, cut to 16 chars). No cookies, no IP, no full user agent stored.
declare(strict_types=1);
require __DIR__ . '/lib.php';
http_response_code( 204 );
header( 'Cache-Control: no-store' );
if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) { exit; }

$ua = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
if ( $ua === '' || preg_match( '/bot|crawl|spider|slurp|preview|headless|lighthouse|monitor|curl|wget|python|go-http/i', $ua ) ) { exit; }
if ( ( $_SERVER['HTTP_DNT'] ?? '' ) === '1' || ( $_SERVER['HTTP_SEC_GPC'] ?? '' ) === '1' ) { exit; }

// Which site sent it: from the Origin (or Referer) header, never from the body.
$origin = (string) ( $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '' );
$host = strtolower( (string) parse_url( $origin, PHP_URL_HOST ) );
$sites = array( 'coralpetal.ai' => 'main', 'www.coralpetal.ai' => 'main', 'careers.coralpetal.ai' => 'careers', 'academy.coralpetal.ai' => 'academy',
	'dev.coralpetal.ai' => 'main', 'dev-careers.coralpetal.ai' => 'careers', 'dev-academy.coralpetal.ai' => 'academy' );
if ( ! isset( $sites[ $host ] ) ) { exit; }
// A dev page only counts in the dev DB and a prod page only in prod.
if ( ( strpos( $host, 'dev' ) === 0 ) !== ( cpx_env() === 'dev' ) ) { exit; }

$in = json_decode( (string) file_get_contents( 'php://input', false, null, 0, 2048 ), true );
if ( ! is_array( $in ) ) { exit; }
$path = '/' . ltrim( (string) parse_url( (string) ( $in['p'] ?? '/' ), PHP_URL_PATH ), '/' );
$path = mb_substr( preg_replace( '#/+#', '/', $path ), 0, 255 );
$ref = strtolower( (string) parse_url( (string) ( $in['r'] ?? '' ), PHP_URL_HOST ) );
$ref = ( $ref === '' || isset( $sites[ $ref ] ) ) ? null : mb_substr( preg_replace( '/^www\./', '', $ref ), 0, 120 );
$device = preg_match( '/iPad|Tablet/i', $ua ) ? 'tablet' : ( preg_match( '/Mobi|Android|iPhone/i', $ua ) ? 'mobile' : 'desktop' );

$db = cpx_db();
if ( ! $db ) { exit; }
try {
	cpx_tables( $db );
	$day = gmdate( 'Y-m-d' );
	$salt = hash( 'sha256', $day . '|' . __FILE__ . '|' . php_uname( 'n' ) );
	$visitor = substr( hash( 'sha256', $salt . '|' . ( $_SERVER['REMOTE_ADDR'] ?? '' ) . '|' . $ua ), 0, 16 );
	$db->prepare( 'INSERT INTO pageviews (at, day, site, path, ref_host, visitor, device) VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?)' )
		->execute( array( $day, $sites[ $host ], $path, $ref, $visitor, $device ) );
} catch ( Throwable $e ) {
	error_log( 'hit: ' . $e->getMessage() );
}
