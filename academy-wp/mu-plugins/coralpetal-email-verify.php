<?php
/**
 * Plugin Name: Coral Petal Academy: email verification
 * Description: New students must verify their email before they can log in (Rohit, 2026-11-18).
 *              Sign-up via the Tutor registration form sends a one-time link; unverified accounts are
 *              logged out and cannot sign in until they click it. Existing users are unaffected.
 */
defined( 'ABSPATH' ) || exit;

const CP_EV_META  = 'cp_email_verified';   // '0' = waiting; '1' or missing = verified
const CP_EV_TOKEN = 'cp_email_verify_token';

function cp_ev_send( $user_id ) {
	$user  = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}
	$token = wp_generate_password( 32, false, false );
	update_user_meta( $user_id, CP_EV_TOKEN, wp_hash_password( $token ) );
	update_user_meta( $user_id, CP_EV_META, '0' );
	$link = add_query_arg( array( 'cp_verify' => $token, 'u' => $user_id ), home_url( '/' ) );
	$name = $user->first_name ? $user->first_name : $user->user_login;
	$subj = 'Verify your email for Coral Petal AI Academy';
	// Branded HTML email (2026-11-18, Rohit): logo + soft-coral "Activate account" button; plain-text part kept for old clients.
	$logo = esc_url( get_stylesheet_directory_uri() . '/assets/apple-touch-icon.png' );
	$url  = esc_url( $link );
	$hi   = esc_html( $name );
	$html = '<!doctype html><html><body style="margin:0;padding:0;background:#F6F6F6;">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6F6F6;padding:32px 12px;"><tr><td align="center">'
		. '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#FFFFFF;border:1px solid #FFD9D3;border-radius:20px;font-family:Inter,Segoe UI,Helvetica,Arial,sans-serif;color:#1B1B1B;">'
		. '<tr><td style="padding:28px 32px 8px;"><table role="presentation" cellpadding="0" cellspacing="0"><tr>'
		. '<td style="vertical-align:middle;"><img src="' . $logo . '" width="40" height="40" alt="Coral Petal AI" style="display:block;border-radius:10px;"></td>'
		. '<td style="vertical-align:middle;padding-left:12px;font-size:18px;font-weight:600;">Coral <span style="color:#FF6F61;">Petal AI</span><sup style="font-size:10px;">&trade;</sup> <span style="color:#6E6E6E;font-weight:500;">Academy</span></td>'
		. '</tr></table></td></tr>'
		. '<tr><td style="padding:16px 32px 0;"><h1 style="margin:0 0 12px;font-family:Georgia,serif;font-weight:400;font-size:26px;line-height:1.2;">Activate your account</h1>'
		. '<p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#46464A;">Hi ' . $hi . ', welcome to Coral Petal AI Academy. Confirm your email address to activate your account and start learning.</p></td></tr>'
		. '<tr><td style="padding:0 32px 8px;"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="border-radius:12px;background:#FFB4A9;">'
		. '<a href="' . $url . '" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#111111;text-decoration:none;border-radius:12px;">Activate account</a>'
		. '</td></tr></table></td></tr>'
		. '<tr><td style="padding:20px 32px 28px;font-size:12.5px;line-height:1.6;color:#8A8A8E;">If the button does not work, copy this link into your browser:<br><a href="' . $url . '" style="color:#B23A31;word-break:break-all;">' . $url . '</a><br><br>If you did not sign up, you can ignore this email.</td></tr>'
		. '</table>'
		. '<p style="margin:16px 0 0;font-family:Helvetica,Arial,sans-serif;font-size:11.5px;color:#9A9A9E;">&copy; ' . gmdate( 'Y' ) . ' Coral Petal AI&trade; Academy &middot; academy@coralpetal.ai</p>'
		. '</td></tr></table></body></html>';
	$text = "Hi {$name},\n\nWelcome to Coral Petal AI Academy. Confirm your email address to activate your account:\n\n{$link}\n\nIf you didn't sign up, you can ignore this email.\n\nCoral Petal AI Academy";
	$alt  = function ( $mailer ) use ( $text ) { $mailer->AltBody = $text; };
	add_action( 'phpmailer_init', $alt );
	$sent = wp_mail( $user->user_email, $subj, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
	remove_action( 'phpmailer_init', $alt );
	return $sent;
}

// 1. A student who signs up through the Tutor form starts unverified and gets the link.
add_action( 'user_register', function ( $user_id ) {
	if ( isset( $_POST['tutor_action'] ) && 'tutor_register_student' === $_POST['tutor_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		cp_ev_send( $user_id );
	}
}, 20 );

// 2. Unverified accounts cannot log in.
add_filter( 'wp_authenticate_user', function ( $user ) {
	if ( $user instanceof WP_User && '0' === get_user_meta( $user->ID, CP_EV_META, true ) ) {
		return new WP_Error( 'cp_unverified', 'Please verify your email first: we sent you a link when you signed up. <a href="' . esc_url( add_query_arg( array( 'cp_resend' => $user->ID ), home_url( '/dashboard/' ) ) ) . '">Send it again</a>.' );
	}
	return $user;
} );

// 3. Tutor logs new students in straight after sign-up: log them out until they verify.
add_action( 'template_redirect', function () {
	if ( is_user_logged_in() ) {
		$uid = get_current_user_id();
		if ( '0' === get_user_meta( $uid, CP_EV_META, true ) && ! current_user_can( 'manage_options' ) ) {
			wp_logout();
			wp_safe_redirect( add_query_arg( 'cp_check_email', '1', home_url( '/dashboard/' ) ) );
			exit;
		}
	}
	// Link clicked
	if ( isset( $_GET['cp_verify'], $_GET['u'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$uid   = absint( $_GET['u'] );
		$token = sanitize_text_field( wp_unslash( $_GET['cp_verify'] ) );
		$hash  = get_user_meta( $uid, CP_EV_TOKEN, true );
		$ok    = $uid && $hash && wp_check_password( $token, $hash );
		if ( $ok ) {
			update_user_meta( $uid, CP_EV_META, '1' );
			delete_user_meta( $uid, CP_EV_TOKEN );
		}
		wp_safe_redirect( add_query_arg( $ok ? 'cp_verified' : 'cp_verify_failed', '1', home_url( '/dashboard/' ) ) );
		exit;
	}
	// Resend
	if ( isset( $_GET['cp_resend'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$uid = absint( $_GET['cp_resend'] );
		$key = 'cp_ev_resend_' . $uid;
		if ( $uid && '0' === get_user_meta( $uid, CP_EV_META, true ) && ! get_transient( $key ) ) {
			cp_ev_send( $uid );
			set_transient( $key, 1, 5 * MINUTE_IN_SECONDS );
		}
		wp_safe_redirect( add_query_arg( 'cp_check_email', '1', home_url( '/dashboard/' ) ) );
		exit;
	}
}, 1 );

// 4. Friendly notices on the login page.
add_filter( 'the_content', function ( $content ) {
	if ( ! is_page( 'dashboard' ) || is_user_logged_in() ) {
		return $content;
	}
	$msg = '';
	if ( isset( $_GET['cp_check_email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$msg = '<div class="cp-vnote" role="status"><span class="cp-vnote-ico"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3.5 7l8.5 6 8.5-6"/></svg></span><div><p class="cp-vnote-t">Verify your email to activate your account</p><p class="cp-vnote-d">We have sent an activation email to your inbox. Open it and select <b>Activate account</b>, then sign in below.</p><p class="cp-vnote-s">Not there after a few minutes? Check your spam or promotions folder. Signing in again lets you request a new email.</p></div></div>';
	} elseif ( isset( $_GET['cp_verified'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$msg = '<div class="cp-vnote ok" role="status"><span class="cp-vnote-ico"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg></span><div><p class="cp-vnote-t">Your account is active</p><p class="cp-vnote-d">Thank you for verifying your email. Sign in below to start learning.</p></div></div>';
	} elseif ( isset( $_GET['cp_verify_failed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$msg = '<div class="cp-vnote bad" role="alert"><span class="cp-vnote-ico"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/></svg></span><div><p class="cp-vnote-t">This activation link is no longer valid</p><p class="cp-vnote-d">It may have been used already or replaced by a newer email. Sign in below, and if your account is not yet active you can request a new activation email.</p></div></div>';
	}
	return $msg . $content;
}, 5 );
