<?php
/**
 * Coral Petal Academy child theme.
 */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'cp-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Newsreader:opsz,wght@6..72,400;6..72,500&display=swap', array(), null );
	wp_enqueue_style( 'coralpetal-academy', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
}, 20 );

add_action( 'wp_head', function () {
	echo '<meta name="theme-color" content="#FBFBFB">' . "\n";
	echo '<link rel="icon" href="' . esc_url( get_stylesheet_directory_uri() . '/assets/logo.svg' ) . '" type="image/svg+xml">' . "\n";
} );

// Light reading surface on Tutor LMS pages (see style.css).
add_filter( 'body_class', function ( $classes ) {
	if ( is_singular( 'courses' ) || is_post_type_archive( 'courses' ) || is_page( array( 'dashboard', 'student-registration' ) ) ) {
		$classes[] = 'cp-light';
	}
	return $classes;
} );

// Course catalog grouped by section (Tutor course categories) on /courses/ and section archives.
add_filter( 'template_include', function ( $template ) {
	if ( is_post_type_archive( 'courses' ) || is_tax( 'course-category' ) ) {
		$t = get_stylesheet_directory() . '/templates-php/catalog.php';
		if ( file_exists( $t ) ) {
			return $t;
		}
	}
	return $template;
}, 99 );
