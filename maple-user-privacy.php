<?php
/**
 * Plugin Name: Maple – User Privacy
 * Description: Stops logged-out visitors from listing a site's user accounts. Maple Creative standard hardening.
 * Version:     1.0.1
 * Author:      Maple Creative
 *
 * Covers the same two routes as Stop User Enumeration, plus the leaks it leaves:
 *   1. REST /wp/v2/users (list and single) – removed for logged-out requests.
 *   2. /?author=N – 404 for logged-out requests (front end only).
 *   3. oEmbed responses – author name/URL removed.
 *   4. Core users sitemap (wp-sitemap-users-*.xml) – disabled.
 * Logged-in users (block editor author picker, app-password/REST clients, Jetpack) are unaffected.
 * Author archive pages (/author/<slug>/) still work; email-style slugs are fixed separately
 * by renaming user_nicename.
 */

defined( 'ABSPATH' ) || exit;

// Per-site opt-out: define( 'MAPLE_USER_PRIVACY_DISABLE', true ); in wp-config.php.
if ( defined( 'MAPLE_USER_PRIVACY_DISABLE' ) && MAPLE_USER_PRIVACY_DISABLE ) {
	return;
}

add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( is_user_logged_in() ) {
		return $endpoints;
	}
	foreach ( array_keys( $endpoints ) as $route ) {
		if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
			unset( $endpoints[ $route ] );
		}
	}
	return $endpoints;
} );

add_action( 'parse_request', function ( $wp ) {
	if ( is_admin() || is_user_logged_in() ) {
		return;
	}
	if ( isset( $_GET['author'] ) || isset( $wp->query_vars['author'] ) ) {
		status_header( 404 );
		nocache_headers();
		exit;
	}
}, 1 );

add_filter( 'oembed_response_data', function ( $data ) {
	unset( $data['author_name'], $data['author_url'] );
	return $data;
} );

add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}, 10, 2 );
