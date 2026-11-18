<?php
// coralpetal.ai contact form endpoint (2026-11-18, Rohit): saves the message in the admin DB (Admin → Inbox)
// and emails the right team address through Proton SMTP. Replaces the mailto-only contact page.
declare(strict_types=1);
require __DIR__ . '/lib.php';
header( 'Content-Type: application/json; charset=utf-8' );
header( 'Cache-Control: no-store' );

function out( int $code, bool $ok, string $msg ): void { http_response_code( $code ); echo json_encode( array( 'ok' => $ok, 'message' => $msg ) ); exit; }

if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) { out( 405, false, 'Method not allowed.' ); }

// Spam guards: honeypot + minimum fill time.
if ( ! empty( $_POST['website'] ) ) { out( 200, true, 'Thank you.' ); }
$t = (int) ( $_POST['t'] ?? 0 );
if ( $t <= 0 || ( time() - intdiv( $t, 1000 ) ) < 4 ) { out( 400, false, 'Please take a moment to fill in the form, then send again.' ); }

$topics = array(
	'services' => array( 'Services & partnerships', 'contact@coralpetal.ai' ),
	'academy'  => array( 'Academy & training', 'academy@coralpetal.ai' ),
	'careers'  => array( 'Careers', 'careers@coralpetal.ai' ),
	'other'    => array( 'Something else', 'contact@coralpetal.ai' ),
);
$f = fn( string $k, int $max ) => mb_substr( trim( (string) ( $_POST[ $k ] ?? '' ) ), 0, $max );
$topic = $f( 'topic', 20 );
$name = $f( 'name', 120 );
$email = $f( 'email', 190 );
$company = $f( 'company', 160 );
$message = $f( 'message', 5000 );

$err = array();
if ( ! isset( $topics[ $topic ] ) ) { $err[] = 'topic'; }
if ( mb_strlen( $name ) < 2 ) { $err[] = 'name'; }
if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) { $err[] = 'email'; }
if ( mb_strlen( $message ) < 10 ) { $err[] = 'message (at least 10 characters)'; }
if ( empty( $_POST['consent'] ) ) { $err[] = 'consent'; }
if ( $err ) { out( 422, false, 'Please check: ' . implode( ', ', $err ) . '.' ); }

$db = cpx_db();
if ( ! $db ) { out( 503, false, 'The form is not available right now. Please email contact@coralpetal.ai.' ); }
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
try {
	cpx_tables( $db );
	// Rate limit: 5 messages per IP per hour.
	$st = $db->prepare( 'SELECT COUNT(*) FROM contact_messages WHERE ip = ? AND created_at > (UTC_TIMESTAMP() - INTERVAL 1 HOUR)' );
	$st->execute( array( $ip ) );
	if ( (int) $st->fetchColumn() >= 5 ) { out( 429, false, 'We have your earlier messages. Please wait a little before sending another.' ); }
	$db->prepare( 'INSERT INTO contact_messages (created_at, env, topic, name, email, company, message, ip) VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?, ?)' )
		->execute( array( cpx_env(), $topic, $name, $email, $company ?: null, $message, $ip ) );
	$id = (int) $db->lastInsertId();
} catch ( Throwable $e ) {
	error_log( 'contact: db ' . $e->getMessage() );
	out( 500, false, 'Something went wrong. Please try again, or email contact@coralpetal.ai.' );
}

list( $label, $to ) = $topics[ $topic ];
$admin = cpx_env() === 'dev' ? 'https://dev-admin.coralpetal.ai/inbox' : 'https://admin.coralpetal.ai/inbox';
$body = "New message from the website contact form" . ( cpx_env() === 'dev' ? ' (DEV)' : '' ) . ".\n\n"
	. "Topic:   $label\nName:    $name\nEmail:   $email\n" . ( $company ? "Company: $company\n" : '' ) . "\n$message\n\n"
	. "Reply to this email to answer $name directly.\nAll messages: $admin\n";
$subject = ( cpx_env() === 'dev' ? '[DEV] ' : '' ) . "Website: $label — $name";
if ( cpx_mail( $to, $subject, $body, "$name <$email>" ) ) {
	$db->prepare( 'UPDATE contact_messages SET mailed = 1 WHERE id = ?' )->execute( array( $id ) );
}
out( 200, true, 'Thank you, ' . $name . '. Your message has reached our team. We reply within two working days.' );
