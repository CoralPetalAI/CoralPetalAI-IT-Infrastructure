<?php
/**
 * Coral Petal Academy child theme.
 */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'cp-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', array(), null );
	wp_enqueue_style( 'coralpetal-academy', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
}, 20 );

add_action( 'wp_head', function () {
	echo '<meta name="theme-color" content="#0B1F33">' . "\n";
	echo '<link rel="icon" href="' . esc_url( get_stylesheet_directory_uri() . '/assets/logo.svg' ) . '" type="image/svg+xml">' . "\n";
} );

// Light reading surface on Tutor LMS pages (see style.css).
add_filter( 'body_class', function ( $classes ) {
	if ( is_singular( 'courses' ) || is_post_type_archive( 'courses' ) || is_page( array( 'dashboard', 'student-registration' ) ) ) {
		$classes[] = 'cp-light';
	}
	return $classes;
} );
