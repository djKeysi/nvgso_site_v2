<?php
/**
 * Сброс кэша сайта при сохранении страниц и кнопка в админ-баре.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Чистим кэш WP Super Cache после сохранения записи/страницы.
 */
add_action(
	'save_post',
	function ( $post_id ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
	},
	99
);

/**
 * Кнопка «Очистить кэш» в админ-баре.
 */
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=nvgso_clear_cache' ),
			'nvgso_clear_cache'
		);
		$bar->add_node(
			array(
				'id'    => 'nvgso-clear-cache',
				'title' => 'Очистить кэш',
				'href'  => $url,
			)
		);
	},
	100
);

add_action(
	'admin_post_nvgso_clear_cache',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Недостаточно прав.' );
		}
		check_admin_referer( 'nvgso_clear_cache' );

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url();
		}
		wp_safe_redirect( $back );
		exit;
	}
);

/**
 * Юридические страницы и редакторы всегда видят свежую версию.
 */
add_action(
	'wp',
	function () {
		if ( is_page( array( 'privacy-policy', 'company-policy', 'cookie-policy' ) ) ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
		}
		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
		}
	}
);

add_action(
	'template_redirect',
	function () {
		if ( is_page( array( 'privacy-policy', 'company-policy', 'cookie-policy' ) ) ) {
			nocache_headers();
		}
	}
);
