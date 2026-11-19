<?php
/**
 * NVGSO — дочерняя тема Twenty Twenty-Four.
 * Дизайн перенесён из статического прототипа site/ (css/public.css).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NVGSO_VERSION', '1.3.27' );

require get_stylesheet_directory() . '/inc/helpers.php';
require get_stylesheet_directory() . '/inc/settings.php';
require get_stylesheet_directory() . '/inc/post-types.php';
require get_stylesheet_directory() . '/inc/cookie-consent.php';
require get_stylesheet_directory() . '/inc/settings-page.php';
require get_stylesheet_directory() . '/inc/feedback.php';
require get_stylesheet_directory() . '/inc/meta-boxes.php';
require get_stylesheet_directory() . '/inc/command-form.php';
require get_stylesheet_directory() . '/inc/documents-form.php';
require get_stylesheet_directory() . '/inc/divisions-form.php';
require get_stylesheet_directory() . '/inc/traffic.php';
require get_stylesheet_directory() . '/inc/footer-links.php';
require get_stylesheet_directory() . '/inc/zone-form.php';
require get_stylesheet_directory() . '/inc/response-form.php';
require get_stylesheet_directory() . '/inc/cache.php';
require get_stylesheet_directory() . '/inc/admin-ui.php';
require get_stylesheet_directory() . '/inc/seo.php';

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support( 'custom-logo', array( 'height' => 120, 'width' => 120, 'flex-height' => true, 'flex-width' => true ) );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );

		register_nav_menus(
			array(
				'primary' => 'Основное меню',
				'footer'  => 'Меню в подвале',
			)
		);

		add_image_size( 'nvgso-card', 700, 467, true );
		add_image_size( 'nvgso-wide', 1400, 900, true );
		add_image_size( 'nvgso-hero', 1600, 900, true );
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'nvgso-public',
			get_stylesheet_directory_uri() . '/assets/css/public.css',
			array(),
			'20261110-6'
		);
		wp_enqueue_style(
			'nvgso-theme',
			get_stylesheet_directory_uri() . '/assets/css/theme.css',
			array( 'nvgso-public' ),
			NVGSO_VERSION
		);
		wp_enqueue_script(
			'nvgso-theme',
			get_stylesheet_directory_uri() . '/assets/js/theme.js',
			array(),
			NVGSO_VERSION,
			true
		);

		$settings = nvgso_settings();
		wp_localize_script(
			'nvgso-theme',
			'NVGSO',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'nvgso_consent' ),
				'home'       => home_url( '/' ),
				'cookieUrl'  => nvgso_page_url( 'cookie-policy' ),
				'privacyUrl' => nvgso_page_url( 'privacy-policy' ),
				'consentVersion' => '1.0',
				'phoneDuty'  => $settings['phone_duty'],
			)
		);
	}
);

add_filter(
	'body_class',
	function ( $classes ) {
		$classes[] = 'nvgso';
		return $classes;
	}
);

/**
 * Убираем лишние стили блочной темы, чтобы дизайн прототипа не конфликтовал.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_dequeue_style( 'wp-block-library-theme' );
	},
	20
);

/**
 * Меню шапки: пункты соответствуют структуре прототипа.
 * Возвращает дерево для header.php.
 */
function nvgso_nav_items() {
	return array(
		array(
			'label' => 'Главная',
			'slug'  => 'home',
			'url'   => home_url( '/' ),
			'match' => is_front_page(),
		),
		array(
			'label'    => 'О филиале',
			'slug'     => 'about',
			'url'      => nvgso_page_url( 'about' ),
			'match'    => is_page( array( 'about', 'structure', 'activity', 'veterans' ) ),
			'children' => array(
				array( 'label' => 'Об отряде', 'url' => nvgso_page_url( 'about' ), 'text' => 'История, командование и зона ответственности', 'icon' => 'shield' ),
				array( 'label' => 'Структура', 'url' => nvgso_page_url( 'structure' ), 'text' => 'Взводы, отделения и дислокация', 'icon' => 'structure' ),
				array( 'label' => 'Деятельность', 'url' => nvgso_page_url( 'activity' ), 'text' => 'Горноспасательные работы и профилактика', 'icon' => 'helmet' ),
				array( 'label' => 'Ветеранам и память', 'url' => nvgso_page_url( 'veterans' ), 'text' => 'Ветеранская организация и книга памяти', 'icon' => 'star' ),
			),
		),
		array(
			'label'    => 'Пресс-центр',
			'slug'     => 'press',
			'url'      => nvgso_page_url( 'news' ),
			'match'    => is_home() || is_single() || is_category() || is_page( array( 'gallery', 'documents' ) ) || is_post_type_archive( array( 'document', 'gallery_item' ) ),
			'children' => array(
				array( 'label' => 'Новости', 'url' => nvgso_page_url( 'news' ), 'text' => 'События и учения отряда', 'icon' => 'news' ),
				array( 'label' => 'Фотогалерея', 'url' => nvgso_page_url( 'gallery' ), 'text' => 'Фото и видео из жизни отряда', 'icon' => 'gallery' ),
				array( 'label' => 'Документы', 'url' => nvgso_page_url( 'documents' ), 'text' => 'Положения, лицензии и памятки', 'icon' => 'doc' ),
			),
		),
		array(
			'label' => 'Контакты',
			'slug'  => 'contacts',
			'url'   => nvgso_page_url( 'contacts' ),
			'match' => is_page( 'contacts' ),
		),
	);
}

/**
 * Заголовки поиска и архивов на русском.
 */
add_filter(
	'document_title_parts',
	function ( $parts ) {
		if ( is_search() ) {
			$parts['title'] = 'Поиск: ' . get_search_query();
		} elseif ( is_category() ) {
			$parts['title'] = 'Новости: ' . single_cat_title( '', false );
		} elseif ( is_404() ) {
			$parts['title'] = 'Страница не найдена';
		}
		return $parts;
	}
);

/**
 * Напоминание в админке о демонстрационных данных.
 */
add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = nvgso_settings();
		$has_phones = false !== strpos( (string) $settings['phone_duty'], '00-00-00' ) || false !== strpos( (string) $settings['phone_office'], '00-00-00' );
		$has_names  = false;
		foreach ( nvgso_get_command() as $person ) {
			if ( nvgso_is_placeholder( get_post_meta( $person->ID, '_nvgso_name', true ) ) ) {
				$has_names = true;
				break;
			}
		}
		if ( ! $has_phones && ! $has_names ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>NVGSO:</strong> в контенте остались демонстрационные данные прототипа. ';
		echo 'Замените телефоны <code>+7 (3843) 00-00-00</code> и ФИО <code>[ФИО]</code> на реальные: ';
		echo '<a href="' . esc_url( admin_url( 'options-general.php?page=nvgso-settings' ) ) . '">Настройки → Сайт NVGSO</a>, ';
		echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=staff_contact' ) ) . '">Командование</a>.</p></div>';
	}
);

/**
 * Заголовки Yoast для поиска и архивов.
 */
add_filter(
	'wpseo_title',
	function ( $title ) {
		if ( is_search() ) {
			return 'Поиск: ' . get_search_query() . ' — ' . get_bloginfo( 'name' );
		}
		if ( is_category() ) {
			return 'Новости: ' . single_cat_title( '', false ) . ' — ' . get_bloginfo( 'name' );
		}
		if ( is_404() ) {
			return 'Страница не найдена — ' . get_bloginfo( 'name' );
		}
		return $title;
	}
);

/**
 * Отключаем стили блочной темы-родителя: дизайн полностью на public.css.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		foreach ( array( 'wp-block-library', 'global-styles', 'classic-theme-styles' ) as $handle ) {
			wp_dequeue_style( $handle );
		}
	},
	100
);
