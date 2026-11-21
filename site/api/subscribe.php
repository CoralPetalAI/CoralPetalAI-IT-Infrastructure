<?php
// Insights newsletter sign-up (2026-11-21, Rohit: "newsletter functionality on Insights page for people to
// subscribe to these insights"). Double opt-in, stored in the ADMIN database like the contact form.
//   POST email + consent    → pending row + confirmation email (Proton SMTP via lib.php)
//   GET  ?confirm=<token>    → confirmed      } both send the visitor back to
//   GET  ?unsubscribe=<token> → unsubscribed  } /insights/?newsletter=<result>#subscribe
// Sending the briefs themselves is a separate, later step that reads the confirmed rows.
declare(strict_types=1);
require __DIR__ . '/lib.php';

function nl_out( int $code, bool $ok, string $msg ): void {
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Cache-Control: no-store' );
	http_response_code( $code );
	echo json_encode( array( 'ok' => $ok, 'message' => $msg ) );
	exit;
}

function nl_back( string $result ): void {
	header( 'Cache-Control: no-store' );
	header( 'Location: /insights/?newsletter=' . rawurlencode( $result ) . '#subscribe', true, 303 );
	exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';

// Confirmation and unsubscribe links.
if ( $method === 'GET' ) {
	$action = isset( $_GET['confirm'] ) ? 'confirm' : ( isset( $_GET['unsubscribe'] ) ? 'unsubscribe' : '' );
	$token = $action ? (string) $_GET[ $action ] : '';
	if ( $action === '' || ! preg_match( '/^[a-f0-9]{48}$/', $token ) ) { nl_back( 'invalid' ); }
	$db = cpx_db();
	if ( ! $db ) { nl_back( 'unavailable' ); }
	try {
		cpx_newsletter_table( $db );
		if ( $action === 'confirm' ) {
			$db->prepare( "UPDATE newsletter_subscribers SET status = 'confirmed', confirmed_at = COALESCE(confirmed_at, UTC_TIMESTAMP()), updated_at = UTC_TIMESTAMP() WHERE token = ? AND status = 'pending'" )
				->execute( array( $token ) );
		} else {
			$db->prepare( "UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE token = ? AND status <> 'unsubscribed'" )
				->execute( array( $token ) );
		}
		$st = $db->prepare( 'SELECT status FROM newsletter_subscribers WHERE token = ?' );
		$st->execute( array( $token ) );
		$status = (string) $st->fetchColumn();
	} catch ( Throwable $e ) {
		error_log( 'subscribe: link ' . $e->getMessage() );
		nl_back( 'unavailable' );
	}
	$want = $action === 'confirm' ? 'confirmed' : 'unsubscribed';
	nl_back( $status === $want ? $want : 'invalid' );
}

if ( $method !== 'POST' ) { nl_out( 405, false, 'Method not allowed.' ); }

$done = 'Almost there. Check your inbox and click the link in our email to confirm your subscription.';

// Spam guards: honeypot + minimum fill time (same approach as the contact form).
if ( ! empty( $_POST['website'] ) ) { nl_out( 200, true, $done ); }
$t = (int) ( $_POST['t'] ?? 0 );
if ( $t <= 0 || ( time() - intdiv( $t, 1000 ) ) < 2 ) { nl_out( 400, false, 'Please wait a moment, then press Subscribe again.' ); }

$email = mb_strtolower( mb_substr( trim( (string) ( $_POST['email'] ?? '' ) ), 0, 190 ) );
if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) { nl_out( 422, false, 'Enter a valid email address, like name@company.com.' ); }
if ( empty( $_POST['consent'] ) ) { nl_out( 422, false, 'Tick the box to agree to receive Insights by email.' ); }
$source = substr( (string) preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) ( $_POST['source'] ?? '' ) ) ), 0, 40 );
if ( $source === '' ) { $source = 'insights'; }

$db = cpx_db();
if ( ! $db ) { nl_out( 503, false, 'Sign-up is not available right now. Please try again later.' ); }
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$env = cpx_env();
try {
	cpx_newsletter_table( $db );
	// Privacy policy: unconfirmed sign-ups are deleted after 30 days.
	$db->exec( "DELETE FROM newsletter_subscribers WHERE status = 'pending' AND updated_at < (UTC_TIMESTAMP() - INTERVAL 30 DAY)" );
	// Rate limit: 5 sign-ups per IP per hour.
	$st = $db->prepare( 'SELECT COUNT(*) FROM newsletter_subscribers WHERE ip = ? AND updated_at > (UTC_TIMESTAMP() - INTERVAL 1 HOUR)' );
	$st->execute( array( $ip ) );
	if ( (int) $st->fetchColumn() >= 5 ) { nl_out( 429, false, 'Too many sign-ups from this connection. Please try again in an hour.' ); }

	$st = $db->prepare( 'SELECT id, status, token, (mailed_at > (UTC_TIMESTAMP() - INTERVAL 10 MINUTE)) AS recent FROM newsletter_subscribers WHERE env = ? AND email = ?' );
	$st->execute( array( $env, $email ) );
	$row = $st->fetch();
	// Already subscribed, or a confirmation went out minutes ago: same answer, no second email.
	if ( $row && ( $row['status'] === 'confirmed' || ( $row['status'] === 'pending' && (int) $row['recent'] === 1 ) ) ) { nl_out( 200, true, $done ); }
	if ( $row ) {
		$id = (int) $row['id'];
		$token = (string) $row['token'];
		$db->prepare( "UPDATE newsletter_subscribers SET status = 'pending', source = ?, consent_at = UTC_TIMESTAMP(), unsubscribed_at = NULL, updated_at = UTC_TIMESTAMP(), ip = ? WHERE id = ?" )
			->execute( array( $source, $ip, $id ) );
	} else {
		$token = bin2hex( random_bytes( 24 ) );
		$db->prepare( "INSERT INTO newsletter_subscribers (created_at, updated_at, env, email, status, token, source, consent_at, ip) VALUES (UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?, ?, 'pending', ?, ?, UTC_TIMESTAMP(), ?)" )
			->execute( array( $env, $email, $token, $source, $ip ) );
		$id = (int) $db->lastInsertId();
	}
} catch ( Throwable $e ) {
	error_log( 'subscribe: db ' . $e->getMessage() );
	nl_out( 500, false, 'Something went wrong. Please try again in a moment.' );
}

$base = $env === 'dev' ? 'https://dev.coralpetal.ai' : 'https://coralpetal.ai';
$link = $base . '/api/subscribe.php?confirm=' . $token;
$body = 'Please confirm that you want to receive Coral Petal AI Insights' . ( $env === 'dev' ? ' (DEV)' : '' ) . ".\n\n"
	. "Confirm your subscription:\n$link\n\n"
	. "Insights are research-backed briefs on enterprise and agentic AI. We email you only when a new brief is published, and every issue has a one-click unsubscribe link.\n\n"
	. "If you did not ask for this, ignore this email and you will not be subscribed.\n\n"
	. "Coral Petal AI\n$base/insights/\n";
$subject = ( $env === 'dev' ? '[DEV] ' : '' ) . 'Confirm your Coral Petal AI Insights subscription';
if ( ! cpx_mail( $email, $subject, $body ) ) {
	nl_out( 502, false, 'We could not send the confirmation email just now. Please try again later.' );
}
$db->prepare( 'UPDATE newsletter_subscribers SET mailed_at = UTC_TIMESTAMP() WHERE id = ?' )->execute( array( $id ) );
nl_out( 200, true, $done );
