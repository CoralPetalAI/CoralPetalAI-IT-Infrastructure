<?php
/**
 * Plugin Name: Coral Petal – noindex on dev
 * Description: Tells search engines not to index dev-academy.coralpetal.ai.
 */
if ( isset( $_SERVER['HTTP_HOST'] ) && str_starts_with( strtolower( $_SERVER['HTTP_HOST'] ), 'dev-' ) ) {
	add_filter( 'wp_robots', 'wp_robots_no_robots' );
	add_filter( 'pre_option_blog_public', '__return_zero' );
}
