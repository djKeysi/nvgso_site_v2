<?php
/**
 * Заголовок главной и favicon для браузера/поисковиков.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function nvgso_home_seo_title() {
	return 'Официальный сайт филиала «Новокузнецкий ВГСО» ФГУП «ВГСЧ»';
}

add_filter(
	'wpseo_title',
	function ( $title ) {
		return is_front_page() ? nvgso_home_seo_title() : $title;
	}
);

add_filter(
	'wpseo_opengraph_title',
	function ( $title ) {
		return is_front_page() ? nvgso_home_seo_title() : $title;
	}
);

add_filter(
	'document_title_parts',
	function ( $parts ) {
		if ( is_front_page() ) {
			$parts['title']   = nvgso_home_seo_title();
			$parts['tagline'] = '';
		}
		return $parts;
	}
);

add_action(
	'wp_head',
	function () {
		$base = get_stylesheet_directory_uri() . '/assets/img/';
		echo '<link rel="icon" type="image/png" sizes="32x32" href="' . esc_url( $base . 'favicon-32.png' ) . '">' . "\n";
		echo '<link rel="icon" type="image/png" sizes="120x120" href="' . esc_url( $base . 'favicon-120.png' ) . '">' . "\n";
		echo '<link rel="icon" type="image/png" sizes="192x192" href="' . esc_url( $base . 'favicon-192.png' ) . '">' . "\n";
		echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url( $base . 'favicon-180.png' ) . '">' . "\n";
		echo '<link rel="shortcut icon" href="' . esc_url( home_url( '/favicon.ico' ) ) . '">' . "\n";
	},
	6
);
