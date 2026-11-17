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
	$body = "Hi {$name},\n\nWelcome to Coral Petal AI Academy. Please confirm your email address to activate your account:\n\n{$link}\n\nIf you didn't sign up, you can ignore this email.\n\nCoral Petal AI Academy";
	return wp_mail( $user->user_email, $subj, $body );
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
		$msg = '<div class="cp-notice"><b>Check your inbox.</b> We sent you a link to verify your email. Click it, then log in here.</div>';
	} elseif ( isset( $_GET['cp_verified'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$msg = '<div class="cp-notice ok"><b>Email verified.</b> You can log in now.</div>';
	} elseif ( isset( $_GET['cp_verify_failed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$msg = '<div class="cp-notice bad"><b>That link didn’t work.</b> It may have been used already. Try logging in, or sign up again.</div>';
	}
	return $msg . $content;
}, 5 );
