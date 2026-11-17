<?php
/**
 * Plugin Name: Coral Petal Academy: mail sender
 * Description: Every Academy email (verification, password reset, Tutor notices) is sent as
 *              "Coral Petal AI Academy <academy@coralpetal.ai>" (Rohit, 2026-11-18), never
 *              wordpress@<host>. When ~/.coralpetal/academy-smtp.php exists on the server (written
 *              by the deploy from repo secrets over stdin, chmod 600, outside every docroot, never in
 *              git) or CP_SMTP_USER / CP_SMTP_PASS are defined in wp-config.php, mail goes out through
 *              Proton Mail SMTP so it passes SPF/DKIM/DMARC for coralpetal.ai (DNS: Proton only,
 *              DMARC p=quarantine). Without them WordPress falls back to the server's PHP mail.
 */
defined( 'ABSPATH' ) || exit;

const CP_MAIL_FROM      = 'academy@coralpetal.ai';
const CP_MAIL_FROM_NAME = 'Coral Petal AI Academy';

// SMTP credentials from the private file (array with user, pass), if wp-config.php has none.
if ( ! defined( 'CP_SMTP_USER' ) && preg_match( '#^(/home/[^/]+)/#', ABSPATH, $cp_m ) ) {
	$cp_f = $cp_m[1] . '/.coralpetal/academy-smtp.php';
	if ( is_readable( $cp_f ) ) {
		$cp_c = include $cp_f;
		if ( is_array( $cp_c ) && ! empty( $cp_c['user'] ) && ! empty( $cp_c['pass'] ) ) {
			define( 'CP_SMTP_USER', $cp_c['user'] );
			define( 'CP_SMTP_PASS', $cp_c['pass'] );
		}
	}
	unset( $cp_f, $cp_c );
}
unset( $cp_m );

add_filter( 'wp_mail_from', function () {
	return CP_MAIL_FROM;
}, 99 );

add_filter( 'wp_mail_from_name', function () {
	return CP_MAIL_FROM_NAME;
}, 99 );

add_action( 'phpmailer_init', function ( $mailer ) {
	$mailer->Sender = CP_MAIL_FROM; // envelope sender (Return-Path) on the same domain
	if ( ! $mailer->getReplyToAddresses() ) {
		$mailer->addReplyTo( CP_MAIL_FROM, CP_MAIL_FROM_NAME );
	}
	if ( defined( 'CP_SMTP_USER' ) && defined( 'CP_SMTP_PASS' ) && CP_SMTP_USER && CP_SMTP_PASS ) {
		$mailer->isSMTP();
		$mailer->Host       = defined( 'CP_SMTP_HOST' ) && CP_SMTP_HOST ? CP_SMTP_HOST : 'smtp.protonmail.ch';
		$mailer->Port       = defined( 'CP_SMTP_PORT' ) && CP_SMTP_PORT ? (int) CP_SMTP_PORT : 587;
		$mailer->SMTPAuth   = true;
		$mailer->SMTPSecure = 'tls';
		$mailer->Username   = CP_SMTP_USER;
		$mailer->Password   = CP_SMTP_PASS;
	}
}, 99 );

// Admin-only check: Tools → Site Health shows which route mail takes.
add_filter( 'debug_information', function ( $info ) {
	$smtp = defined( 'CP_SMTP_USER' ) && defined( 'CP_SMTP_PASS' ) && CP_SMTP_USER && CP_SMTP_PASS;
	$info['coralpetal-mail'] = array(
		'label'  => 'Coral Petal mail',
		'fields' => array(
			'from'  => array( 'label' => 'From', 'value' => CP_MAIL_FROM_NAME . ' <' . CP_MAIL_FROM . '>' ),
			'route' => array( 'label' => 'Route', 'value' => $smtp ? 'Proton Mail SMTP' : 'Server PHP mail (may land in spam: add the SMTP token)' ),
		),
	);
	return $info;
} );
