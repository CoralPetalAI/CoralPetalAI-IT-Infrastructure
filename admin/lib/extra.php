<?php
// Admin 1.4.0 (2026-11-18): Inbox (contact form), Applications (careers), Traffic (first-party page counts).
declare(strict_types=1);

const MSG_STATUS = array( 'New', 'Read', 'Replied', 'Archived' );
const APP_STATUS = array( 'new', 'reviewing', 'shortlisted', 'interview', 'rejected', 'hired' );
const MSG_TOPICS = array( 'services' => 'Services', 'academy' => 'Academy', 'careers' => 'Careers', 'other' => 'Other' );

function cp_home_dir(): string {
	$home = getenv( 'HOME' );
	if ( ! $home && function_exists( 'posix_getpwuid' ) ) { $home = posix_getpwuid( posix_geteuid() )['dir'] ?? ''; }
	return rtrim( (string) $home, '/' );
}

// ---------- Careers (reads the careers DB; config written by scripts/careers_config_store.sh) ----------
function cp_careers_cfg(): ?array {
	$f = cp_home_dir() . '/.coralpetal/' . ( cp_env() === 'dev' ? 'careers-config-dev.json' : 'careers-config.json' );
	return is_readable( $f ) ? ( json_decode( (string) file_get_contents( $f ), true ) ?: null ) : null;
}

function cp_careers_db(): ?PDO {
	static $pdo = false;
	if ( false !== $pdo ) { return $pdo; }
	$pdo = null;
	$d = cp_careers_cfg()['db'] ?? null;
	if ( ! $d ) { return null; }
	try {
		$pdo = new PDO( "mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4", $d['user'], $d['pass'],
			array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC ) );
		if ( ! cp_table_exists( $pdo, 'applications' ) ) { $pdo = null; }
	} catch ( Throwable $e ) { error_log( 'admin: careers db ' . $e->getMessage() ); $pdo = null; }
	return $pdo;
}

/** Read-only SAS for the applications container: ~/.coralpetal/careers-read-sas(-dev).txt (deploy, secret CAREERS_READ_SAS(_DEV)). */
function cp_careers_read_sas(): string {
	$f = cp_home_dir() . '/.coralpetal/' . ( cp_env() === 'dev' ? 'careers-read-sas-dev.txt' : 'careers-read-sas.txt' );
	return is_readable( $f ) ? ltrim( trim( (string) file_get_contents( $f ) ), '?' ) : '';
}

/** Streams one PDF from the private container to the signed-in admin. The SAS never reaches the browser. */
function cp_stream_blob( string $path, string $download_name ): void {
	$cfg = cp_careers_cfg(); $sas = cp_careers_read_sas();
	if ( ! $cfg || $sas === '' || ! preg_match( '#^(dev|prod)/[a-z-]+/[0-9a-f-]+/(resume|cover-letter)\.pdf$#', $path ) ) {
		cp_fail( 'File access is not set up yet (read key missing).', 503 );
	}
	$url = rtrim( $cfg['blob']['container_url'], '/' ) . '/' . $path . '?' . $sas;
	$ch = curl_init( $url );
	curl_setopt_array( $ch, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => array( 'x-ms-version: 2021-08-06' ) ) );
	$body = curl_exec( $ch ); $code = curl_getinfo( $ch, CURLINFO_HTTP_CODE ); curl_close( $ch );
	if ( $code !== 200 || ! is_string( $body ) || strncmp( $body, '%PDF-', 5 ) !== 0 ) {
		error_log( "admin: blob read $code for $path" );
		cp_fail( 'That file could not be loaded from storage.', 502 );
	}
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="' . preg_replace( '/[^A-Za-z0-9._-]/', '_', $download_name ) . '"' );
	header( 'Content-Length: ' . strlen( $body ) );
	echo $body;
	exit;
}

// ---------- Traffic ----------
/** Page-view summary for the last $days days (UTC days). */
function cp_traffic( int $days = 30 ): array {
	$db = cp_db();
	$since = gmdate( 'Y-m-d', strtotime( '-' . ( $days - 1 ) . ' days' ) );
	$q = function ( string $sql, array $a = array() ) use ( $db ) { $st = $db->prepare( $sql ); $st->execute( $a ); return $st; };
	$tot = $q( 'SELECT COUNT(*) v, COUNT(DISTINCT visitor, day) u FROM pageviews WHERE day >= ?', array( $since ) )->fetch();
	$today = $q( 'SELECT COUNT(*) v, COUNT(DISTINCT visitor) u FROM pageviews WHERE day = ?', array( gmdate( 'Y-m-d' ) ) )->fetch();
	$daily = array_column( $q( 'SELECT day, COUNT(*) v, COUNT(DISTINCT visitor) u FROM pageviews WHERE day >= ? GROUP BY day', array( $since ) )->fetchAll(), null, 'day' );
	$series = array();
	for ( $i = $days - 1; $i >= 0; $i-- ) { $d = gmdate( 'Y-m-d', strtotime( "-$i days" ) ); $series[ $d ] = array( (int) ( $daily[ $d ]['v'] ?? 0 ), (int) ( $daily[ $d ]['u'] ?? 0 ) ); }
	return array(
		'views' => (int) $tot['v'], 'visitors' => (int) $tot['u'], 'today_v' => (int) $today['v'], 'today_u' => (int) $today['u'], 'series' => $series,
		'sites' => $q( 'SELECT site, COUNT(*) v, COUNT(DISTINCT visitor, day) u FROM pageviews WHERE day >= ? GROUP BY site ORDER BY v DESC', array( $since ) )->fetchAll(),
		'pages' => $q( 'SELECT site, path, COUNT(*) v, COUNT(DISTINCT visitor, day) u FROM pageviews WHERE day >= ? GROUP BY site, path ORDER BY v DESC LIMIT 15', array( $since ) )->fetchAll(),
		'refs' => $q( 'SELECT ref_host, COUNT(*) v FROM pageviews WHERE day >= ? AND ref_host IS NOT NULL GROUP BY ref_host ORDER BY v DESC LIMIT 10', array( $since ) )->fetchAll(),
		'devices' => array_column( $q( 'SELECT device, COUNT(*) v FROM pageviews WHERE day >= ? GROUP BY device', array( $since ) )->fetchAll(), 'v', 'device' ),
	);
}

/** Small SVG bar chart of daily views (same look as the enrollments chart). */
function cp_daily_chart( array $series ): string {
	$max = max( 1, max( array_map( fn( $x ) => $x[0], $series ) ?: array( 0 ) ) );
	$n = count( $series ); $w = 22; $bars = ''; $i = 0;
	foreach ( $series as $d => $x ) {
		$hgt = (int) round( 110 * $x[0] / $max ); $px = 6 + $i * $w;
		$bars .= '<rect x="' . $px . '" y="' . ( 125 - $hgt ) . '" width="' . ( $w - 6 ) . '" height="' . max( 2, $hgt ) . '" rx="4" class="bar"><title>' . h( gmdate( 'j M', strtotime( $d ) ) ) . ': ' . $x[0] . ' views, ' . $x[1] . ' visitors</title></rect>';
		if ( $i % 7 === 0 || $i === $n - 1 ) { $bars .= '<text x="' . ( $px + ( $w - 6 ) / 2 ) . '" y="142" text-anchor="middle">' . h( gmdate( 'j M', strtotime( $d ) ) ) . '</text>'; }
		$i++;
	}
	return '<svg class="chart" viewBox="0 0 ' . ( 12 + $n * $w ) . ' 148" role="img" aria-label="Daily page views">' . $bars . '</svg>';
}
