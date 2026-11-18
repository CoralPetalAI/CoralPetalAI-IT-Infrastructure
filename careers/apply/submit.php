<?php
// careers.coralpetal.ai job application endpoint (2026-11-18, Rohit).
// Saves the application in MySQL and the PDFs in the private Azure container.
// Config (never in the repo): ~/.coralpetal/careers-config.json, written by the deploy from secret CAREERS_CONFIG.
header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store' );

function out( $code, $ok, $msg ) { http_response_code( $code ); echo json_encode( array( 'ok' => $ok, 'message' => $msg ) ); exit; }

if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) { out( 405, false, 'Method not allowed.' ); }

$home = getenv( 'HOME' );
if ( ! $home && function_exists( 'posix_getpwuid' ) ) { $pw = posix_getpwuid( posix_geteuid() ); $home = $pw['dir'] ?? ''; }
$cfg_file = rtrim( (string) $home, '/' ) . '/.coralpetal/careers-config.json';
$cfg = is_readable( $cfg_file ) ? json_decode( file_get_contents( $cfg_file ), true ) : null;
if ( ! $cfg ) { out( 503, false, 'Applications are not open yet. Please email careers@coralpetal.ai.' ); }

// Spam guards: honeypot + minimum fill time.
if ( ! empty( $_POST['website'] ) ) { out( 200, true, 'Thank you.' ); }
$t = (int) ( $_POST['t'] ?? 0 );
if ( $t <= 0 || ( time() - (int) ( $t / 1000 ) ) < 5 ) { out( 400, false, 'Please take a moment to fill in the form, then submit again.' ); }

$roles = array( 'video-editor' => 'Video Editor' );
$f = function ( $k, $max = 200 ) { return mb_substr( trim( (string) ( $_POST[ $k ] ?? '' ) ), 0, $max ); };
$role = $f( 'role', 60 );
$name = $f( 'name', 120 );
$email = $f( 'email', 190 );
$country = strtoupper( $f( 'country', 2 ) );
$dial = $f( 'dial', 8 );
$phone = preg_replace( '/[^0-9]/', '', $f( 'phone', 20 ) );
$city = $f( 'city', 120 );
$link = $f( 'link', 300 );
$consent = ! empty( $_POST['consent'] );

$err = array();
if ( ! isset( $roles[ $role ] ) ) { $err[] = 'role'; }
if ( mb_strlen( $name ) < 2 ) { $err[] = 'full name'; }
if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) { $err[] = 'email'; }
if ( ! preg_match( '/^[A-Z]{2}$/', $country ) ) { $err[] = 'country'; }
if ( ! preg_match( '/^\+[0-9]{1,4}$/', $dial ) ) { $err[] = 'country code'; }
if ( strlen( $phone ) < 6 || strlen( $phone ) > 15 ) { $err[] = 'mobile number'; }
if ( $city === '' ) { $err[] = 'city'; }
if ( $link !== '' && ! filter_var( $link, FILTER_VALIDATE_URL ) ) { $err[] = 'portfolio link'; }
if ( ! $consent ) { $err[] = 'consent'; }

function pdf_ok( $key, $required, &$err ) {
	$file = $_FILES[ $key ] ?? null;
	if ( ! $file || ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) === UPLOAD_ERR_NO_FILE ) { if ( $required ) { $err[] = $key === 'resume' ? 'resume (PDF)' : 'cover letter (PDF)'; } return null; }
	if ( $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024 || $file['size'] < 100 ) { $err[] = ( $key === 'resume' ? 'resume' : 'cover letter' ) . ' (PDF, max 5 MB)'; return null; }
	$head = file_get_contents( $file['tmp_name'], false, null, 0, 5 );
	if ( $head !== '%PDF-' ) { $err[] = ( $key === 'resume' ? 'resume' : 'cover letter' ) . ' must be a PDF'; return null; }
	return $file['tmp_name'];
}
$resume = pdf_ok( 'resume', true, $err );
$cover = pdf_ok( 'cover', false, $err );
if ( $err ) { out( 422, false, 'Please check: ' . implode( ', ', $err ) . '.' ); }

$env = preg_match( '/^dev-/', $_SERVER['HTTP_HOST'] ?? '' ) ? 'dev' : 'prod';
$id = gmdate( 'Ymd-His' ) . '-' . bin2hex( random_bytes( 4 ) );

function blob_put( $cfg, $path, $tmp ) {
	$url = rtrim( $cfg['blob']['container_url'], '/' ) . '/' . $path . '?' . ltrim( $cfg['blob']['sas'], '?' );
	$ch = curl_init( $url );
	curl_setopt_array( $ch, array(
		CURLOPT_CUSTOMREQUEST => 'PUT', CURLOPT_POSTFIELDS => file_get_contents( $tmp ), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
		CURLOPT_HTTPHEADER => array( 'x-ms-blob-type: BlockBlob', 'x-ms-version: 2021-08-06', 'Content-Type: application/pdf' ),
	) );
	curl_exec( $ch );
	$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );
	return $code === 201;
}
$resume_path = "$env/$role/$id/resume.pdf";
$cover_path = $cover ? "$env/$role/$id/cover-letter.pdf" : null;
if ( ! blob_put( $cfg, $resume_path, $resume ) || ( $cover && ! blob_put( $cfg, $cover_path, $cover ) ) ) {
	error_log( "careers: blob upload failed for $id" );
	out( 502, false, 'We could not upload your files. Please try again, or email careers@coralpetal.ai.' );
}

try {
	$db = $cfg['db'];
	$pdo = new PDO( "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass'], array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ) );
	$pdo->exec( "CREATE TABLE IF NOT EXISTS applications (
		id VARCHAR(40) PRIMARY KEY, created_at DATETIME NOT NULL, env VARCHAR(8) NOT NULL, role VARCHAR(60) NOT NULL,
		name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL, country CHAR(2) NOT NULL, dial_code VARCHAR(8) NOT NULL,
		phone VARCHAR(20) NOT NULL, city VARCHAR(120) NOT NULL, link VARCHAR(300) NULL,
		resume_blob VARCHAR(300) NOT NULL, cover_blob VARCHAR(300) NULL, consent TINYINT(1) NOT NULL,
		status VARCHAR(20) NOT NULL DEFAULT 'new', ip VARCHAR(45) NULL, INDEX (role), INDEX (created_at)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );
	$st = $pdo->prepare( 'INSERT INTO applications (id,created_at,env,role,name,email,country,dial_code,phone,city,link,resume_blob,cover_blob,consent,ip) VALUES (?,UTC_TIMESTAMP(),?,?,?,?,?,?,?,?,?,?,?,1,?)' );
	$st->execute( array( $id, $env, $role, $name, $email, $country, $dial, $phone, $city, $link ?: null, $resume_path, $cover_path, $_SERVER['REMOTE_ADDR'] ?? null ) );
} catch ( Throwable $e ) {
	error_log( 'careers: db error ' . $e->getMessage() );
	out( 500, false, 'Something went wrong saving your application. Please try again, or email careers@coralpetal.ai.' );
}
out( 200, true, 'Thank you, ' . $name . '. Your application for ' . $roles[ $role ] . ' has been received. We will be in touch by email.' );
