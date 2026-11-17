<?php
/**
 * Plugin Name: Coral Petal Academy: 1-year course access
 * Description: Access to an enrolled course lasts 1 year from the date of purchase/enrolment
 *              (Rohit, 2026-11-18). Then the enrolment is set to "cancel" automatically (kept on record,
 *              never deleted). Runs daily (WP-Cron) and also when the student opens a page, so access
 *              ends on time even if cron is late. Admins are never affected. Replace with Tutor Pro's
 *              "Enrollment expiry: 365 days" if we switch to Pro.
 */
defined( 'ABSPATH' ) || exit;

const CP_ACCESS_DAYS  = 365;
const CP_ACCESS_META  = '_cp_access_until';   // on the tutor_enrolled post, 'Y-m-d H:i:s' site time
const CP_EXPIRED_META = '_cp_access_expired'; // when we ended it

function cp_access_until( $enrolment ) {
	$until = get_post_meta( $enrolment->ID, CP_ACCESS_META, true );
	if ( ! $until ) {
		$until = gmdate( 'Y-m-d H:i:s', strtotime( $enrolment->post_date . ' +' . CP_ACCESS_DAYS . ' days' ) );
		update_post_meta( $enrolment->ID, CP_ACCESS_META, $until );
	}
	return $until;
}

// Stamp the end date when a student is enrolled (purchase or free enrol).
add_action( 'tutor_after_enrolled', function ( $course_id, $user_id, $enrolled_id ) {
	$e = get_post( $enrolled_id );
	if ( $e ) {
		delete_post_meta( $e->ID, CP_ACCESS_META );
		cp_access_until( $e );
	}
}, 10, 3 );

// End every enrolment past its date. $user_id limits it to one student.
function cp_expire_enrolments( $user_id = 0 ) {
	global $wpdb;
	$sql  = "SELECT * FROM {$wpdb->posts} WHERE post_type = 'tutor_enrolled' AND post_status = 'completed'";
	$args = array();
	if ( $user_id ) {
		$sql   .= ' AND post_author = %d';
		$args[] = $user_id;
	}
	$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql ); // phpcs:ignore
	$now  = current_time( 'mysql' );
	$done = array();
	foreach ( (array) $rows as $e ) {
		if ( user_can( (int) $e->post_author, 'manage_options' ) ) {
			continue;
		}
		if ( cp_access_until( $e ) <= $now ) {
			$done[] = (int) $e->ID;
			update_post_meta( $e->ID, CP_EXPIRED_META, $now );
		}
	}
	if ( $done && class_exists( '\Tutor\Models\EnrollmentModel' ) ) {
		\Tutor\Models\EnrollmentModel::update_enrollments( 'cancel', $done );
	}
	return $done;
}

// Daily sweep.
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'cp_access_expiry_daily' ) ) {
		wp_schedule_event( time() + 300, 'daily', 'cp_access_expiry_daily' );
	}
} );
add_action( 'cp_access_expiry_daily', function () {
	cp_expire_enrolments();
} );

// On time for the student in front of us (cheap: only their enrolments, once per hour).
add_action( 'template_redirect', function () {
	if ( ! is_user_logged_in() ) {
		return;
	}
	$uid = get_current_user_id();
	$key = 'cp_acc_chk_' . $uid;
	if ( ! get_transient( $key ) ) {
		cp_expire_enrolments( $uid );
		set_transient( $key, 1, HOUR_IN_SECONDS );
	}
}, 2 );

// Course page: tell an enrolled student when access ends; tell an expired one why.
add_filter( 'the_content', function ( $content ) {
	if ( ! is_singular( 'courses' ) || ! is_user_logged_in() || ! in_the_loop() ) {
		return $content;
	}
	global $wpdb;
	$e = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE post_type = 'tutor_enrolled' AND post_parent = %d AND post_author = %d ORDER BY ID DESC LIMIT 1", get_the_ID(), get_current_user_id() ) ); // phpcs:ignore
	if ( ! $e ) {
		return $content;
	}
	$fmt = get_option( 'date_format' );
	if ( 'completed' === $e->post_status ) {
		$msg = '<div class="cp-notice cp-access">Your access to this course runs until <b>' . esc_html( date_i18n( $fmt, strtotime( cp_access_until( $e ) ) ) ) . '</b>.</div>';
	} elseif ( get_post_meta( $e->ID, CP_EXPIRED_META, true ) ) {
		$msg = '<div class="cp-notice bad cp-access">Your 1-year access to this course ended on <b>' . esc_html( date_i18n( $fmt, strtotime( cp_access_until( $e ) ) ) ) . '</b>. Enrol again to continue.</div>';
	} else {
		return $content;
	}
	return $msg . $content;
}, 4 );

// Dashboard home: "Your access" list (theme styles .cp-access-list).
add_action( 'tutor_after_continue_learning_section', function () {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE post_type = 'tutor_enrolled' AND post_status = 'completed' AND post_author = %d ORDER BY ID DESC", get_current_user_id() ) ); // phpcs:ignore
	if ( ! $rows ) {
		return;
	}
	$fmt = get_option( 'date_format' );
	echo '<section class="cp-access-list" aria-labelledby="cp-acc-h"><h2 id="cp-acc-h">Your access</h2><p>Each course stays open for 1 year from the day you enrol.</p><ul>';
	foreach ( $rows as $e ) {
		$until = strtotime( cp_access_until( $e ) );
		$days  = max( 0, (int) ceil( ( $until - current_time( 'timestamp' ) ) / DAY_IN_SECONDS ) );
		$soon  = $days <= 30 ? ' soon' : '';
		echo '<li class="' . esc_attr( trim( $soon ) ) . '"><a href="' . esc_url( get_permalink( $e->post_parent ) ) . '">' . esc_html( get_the_title( $e->post_parent ) ) . '</a><span>Until ' . esc_html( date_i18n( $fmt, $until ) ) . ' · ' . (int) $days . ' days left</span></li>';
	}
	echo '</ul></section>';
}, 5 );
