<?php
// wp eval-file (dev only, scripts/academy_wp_dev.sh): review account for the logged-in dashboard
// check. Password comes over stdin (never argv); enrolled in the Signature Course so the dashboard
// shows stats and "continue learning". Email verified, tour skipped.
$pass = trim( stream_get_contents( STDIN ) );
if ( strlen( $pass ) < 20 ) {
	WP_CLI::error( 'review password too short' );
}
$login = 'cp-review';
$user  = get_user_by( 'login', $login );
$uid   = $user ? $user->ID : wp_insert_user( array(
	'user_login' => $login,
	'user_email' => 'review@dev-academy.coralpetal.ai',
	'first_name' => 'Review',
	'last_name'  => 'Student',
	'role'       => 'subscriber',
	'user_pass'  => $pass,
) );
if ( is_wp_error( $uid ) ) {
	WP_CLI::error( $uid->get_error_message() );
}
wp_set_password( $pass, $uid );
update_user_meta( $uid, 'cp_email_verified', '1' );
if ( class_exists( '\TUTOR\User' ) && defined( '\TUTOR\User::TOUR_COMPLETED_META' ) ) {
	update_user_meta( $uid, \TUTOR\User::TOUR_COMPLETED_META, 1 );
}
$course = get_page_by_path( 'gen-agentic-ai', OBJECT, 'courses' );
if ( $course && class_exists( '\Tutor\Models\EnrollmentModel' ) && ! \Tutor\Models\EnrollmentModel::is_enrolled( $course->ID, $uid ) ) {
	\Tutor\Models\EnrollmentModel::do_enroll( $course->ID, 0, $uid, false );
}
WP_CLI::success( "review account ready ($uid)" );
