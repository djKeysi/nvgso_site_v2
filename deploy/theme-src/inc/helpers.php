<?php
/**
 * Вспомогательные функции темы.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Настройки сайта (option nvgso_settings, значения по умолчанию — из прототипа).
 */
function nvgso_settings() {
	static $settings = null;
	if ( null !== $settings ) {
		return $settings;
	}
	$saved     = get_option( 'nvgso_settings', array() );
	$settings  = wp_parse_args( is_array( $saved ) ? $saved : array(), nvgso_default_settings() );
	$settings  = apply_filters( 'nvgso_settings', $settings );
	return $settings;
}

function nvgso_setting( $key, $fallback = '' ) {
	$settings = nvgso_settings();
	return isset( $settings[ $key ] ) && '' !== $settings[ $key ] ? $settings[ $key ] : $fallback;
}

/**
 * SVG-иконка из набора прототипа.
 */
function nvgso_icon( $name, $class = '', $size = 24 ) {
	static $paths = null;
	if ( null === $paths ) {
		$paths = require get_stylesheet_directory() . '/inc/icons-data.php';
	}
	$path = isset( $paths[ $name ] ) ? $paths[ $name ] : $paths['info'];
	return '<svg class="icon ' . esc_attr( $class ) . '" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
}

function nvgso_phone_href( $phone ) {
	$digits = preg_replace( '/\D+/', '', (string) $phone );
	if ( 11 === strlen( $digits ) && '8' === $digits[0] ) {
		$digits = '7' . substr( $digits, 1 );
	}
	return $digits ? 'tel:+' . $digits : '';
}

function nvgso_tel( $phone ) {
	$href = nvgso_phone_href( $phone );
	return $href ? $href : '#';
}

/**
 * Ссылка на страницу по slug (с запасным вариантом).
 */
function nvgso_page_url( $slug ) {
	$page = get_page_by_path( $slug );
	if ( $page ) {
		return get_permalink( $page );
	}
	return home_url( user_trailingslashit( $slug ) );
}

function nvgso_attachment_url( $id, $size = 'large' ) {
	$id = (int) $id;
	if ( ! $id ) {
		return '';
	}
	$url = wp_get_attachment_image_url( $id, $size );
	if ( ! $url ) {
		$url = wp_get_attachment_url( $id );
	}
	return $url ? $url : '';
}

function nvgso_attachment_img( $id, $size = 'large', $alt = '', $class = '', $attrs = array() ) {
	$id = (int) $id;
	if ( ! $id ) {
		return '';
	}
	$attr = array_merge(
		array(
			'class'    => $class,
			'alt'      => $alt,
			'loading'  => 'lazy',
			'decoding' => 'async',
		),
		$attrs
	);
	return wp_get_attachment_image( $id, $size, false, $attr );
}

/**
 * Изображение из медиатеки по «ключу» photo-01 и т.п.
 * Ключ хранится в мета-поле _nvgso_image_key.
 */
function nvgso_image_by_key( $key, $size = 'large' ) {
	$key = sanitize_key( $key );
	if ( ! $key ) {
		return 0;
	}
	$q = new WP_Query(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_nvgso_image_key',
			'meta_value'     => $key,
			'no_found_rows'  => true,
		)
	);
	return $q->posts ? (int) $q->posts[0] : 0;
}

/**
 * Дата в русском формате.
 */
function nvgso_date( $date = null, $long = false ) {
	$ts = $date ? strtotime( $date ) : null;
	if ( ! $ts ) {
		$ts = $date ? strtotime( (string) $date ) : time();
	}
	if ( ! $ts ) {
		return '';
	}
	if ( $long ) {
		$months = array( 1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря' );
		return (int) date( 'j', $ts ) . ' ' . $months[ (int) date( 'n', $ts ) ] . ' ' . date( 'Y', $ts );
	}
	return date( 'd.m.Y', $ts );
}

function nvgso_excerpt( $text, $length = 160 ) {
	$text = wp_strip_all_tags( (string) $text );
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
	if ( mb_strlen( $text, 'UTF-8' ) <= $length ) {
		return $text;
	}
	return mb_substr( $text, 0, $length, 'UTF-8' ) . '…';
}

function nvgso_is_placeholder( $value ) {
	$value = trim( (string) $value );
	return '' === $value || false !== mb_strpos( $value, '[ФИО]' ) || false !== mb_strpos( $value, '00-00-00' );
}

/**
 * Записи новостей.
 */
function nvgso_get_news( $limit = 6, $category = '', $paged = 1 ) {
	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => (int) $limit,
		'paged'               => max( 1, (int) $paged ),
		'ignore_sticky_posts' => true,
	);
	if ( $category && 'all' !== $category ) {
		if ( is_numeric( $category ) ) {
			$args['cat'] = (int) $category;
		} else {
			$args['category_name'] = sanitize_title( $category );
		}
	}
	return new WP_Query( $args );
}

function nvgso_news_categories() {
	return get_categories(
		array(
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
}

function nvgso_post_category_label( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$cats    = get_the_category( $post_id );
	return $cats ? $cats[0]->name : 'События';
}

/**
 * Документы.
 */
function nvgso_get_docs( $limit = -1, $category = '' ) {
	$args = array(
		'post_type'      => 'document',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $limit,
		'orderby'        => array(
			'menu_order' => 'ASC',
			'date'       => 'DESC',
		),
	);
	if ( $category && 'all' !== $category ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'doc_category',
				'field'    => 'slug',
				'terms'    => sanitize_title( $category ),
			),
		);
	}
	return new WP_Query( $args );
}

function nvgso_doc_meta( $post_id ) {
	return array(
		'file_id' => (int) get_post_meta( $post_id, '_nvgso_file_id', true ),
		'pages'   => (int) get_post_meta( $post_id, '_nvgso_pages', true ),
		'note'    => (string) get_post_meta( $post_id, '_nvgso_note', true ),
		'date'    => (string) get_post_meta( $post_id, '_nvgso_doc_date', true ),
	);
}

/**
 * Фотогалерея.
 */
function nvgso_get_gallery( $limit = -1, $album = '' ) {
	$args = array(
		'post_type'      => 'gallery_item',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $limit,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	if ( $album && 'all' !== $album ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'album',
				'field'    => 'slug',
				'terms'    => sanitize_title( $album ),
			),
		);
	}
	return new WP_Query( $args );
}

function nvgso_gallery_meta( $post_id ) {
	return array(
		'image_id' => (int) get_post_meta( $post_id, '_nvgso_image_id', true ),
		'caption'  => (string) get_post_meta( $post_id, '_nvgso_caption', true ),
		'date'     => (string) get_post_meta( $post_id, '_nvgso_date', true ),
		'type'     => (string) get_post_meta( $post_id, '_nvgso_type', true ),
	);
}

/**
 * Подразделения и командование.
 */
function nvgso_get_divisions( $published_only = true ) {
	return get_posts(
		array(
			'post_type'      => 'division',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
		)
	);
}

function nvgso_get_command() {
	return get_posts(
		array(
			'post_type'      => 'staff_contact',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order date',
			'order'          => 'ASC',
		)
	);
}

function nvgso_get_phones() {
	$phones = get_option( 'nvgso_phones', array() );
	return is_array( $phones ) ? $phones : array();
}

function nvgso_get_milestones() {
	$items = get_option( 'nvgso_milestones', array() );
	return is_array( $items ) ? $items : array();
}

function nvgso_get_zone() {
	$items = get_option( 'nvgso_zone', array() );
	return is_array( $items ) ? $items : array();
}

/**
 * Хлебные крошки.
 */
function nvgso_breadcrumbs( $items = array() ) {
	$parts   = array();
	$parts[] = '<a href="' . esc_url( home_url( '/' ) ) . '">Главная</a>';
	foreach ( $items as $item ) {
		if ( ! empty( $item['url'] ) ) {
			$parts[] = '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
		} else {
			$parts[] = '<span aria-current="page">' . esc_html( $item['label'] ) . '</span>';
		}
	}
	return '<nav class="page-hero__crumbs" aria-label="Хлебные крошки">' . implode( nvgso_icon( 'chevronRight', '', 14 ), $parts ) . '</nav>';
}

function nvgso_pagination( $query = null ) {
	$query = $query ? $query : $GLOBALS['wp_query'];
	$links = paginate_links(
		array(
			'total'     => (int) $query->max_num_pages,
			'current'   => max( 1, get_query_var( 'paged' ) ),
			'type'      => 'array',
			'prev_text' => nvgso_icon( 'chevronLeft', '', 16 ) . ' Назад',
			'next_text' => 'Вперёд ' . nvgso_icon( 'chevronRight', '', 16 ),
		)
	);
	if ( ! $links ) {
		return '';
	}
	$out = '<nav class="pagination" aria-label="Постраничная навигация">';
	foreach ( $links as $link ) {
		$out .= $link;
	}
	return $out . '</nav>';
}

/**
 * Меню категорий для фильтров.
 */
function nvgso_category_chips( $base_url, $current = '' ) {
	$out   = '<a class="chip' . ( ! $current || 'all' === $current ? ' is-active' : '' ) . '" href="' . esc_url( $base_url ) . '">Все</a>';
	$terms = get_categories( array( 'hide_empty' => true ) );
	foreach ( $terms as $term ) {
		$out .= '<a class="chip' . ( $current === $term->slug ? ' is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'cat', $term->term_id, $base_url ) ) . '">' . esc_html( $term->name ) . '</a>';
	}
	return $out;
}

/**
 * Заголовок секции.
 */
function nvgso_section_head( $eyebrow, $title, $lead = '', $more = '' ) {
	$out  = '<div class="section__head"><div>';
	$out .= $eyebrow ? '<div class="eyebrow">' . esc_html( $eyebrow ) . '</div>' : '';
	$out .= '<h2 class="section__title">' . esc_html( $title ) . '</h2>';
	$out .= $lead ? '<p class="section__lead">' . esc_html( $lead ) . '</p>' : '';
	$out .= '</div>';
	$out .= $more ? '<div class="section__more">' . $more . '</div>' : '';
	return $out . '</div>';
}

function nvgso_more_link( $url, $text ) {
	return '<a class="text-link" href="' . esc_url( $url ) . '">' . esc_html( $text ) . ' ' . nvgso_icon( 'arrowRight', '', 16 ) . '</a>';
}

/**
 * Hero страницы.
 */
function nvgso_page_hero( $title, $lead = '', $crumbs = array(), $meta = array(), $compact = false ) {
	$out  = '<section class="page-hero' . ( $compact ? ' page-hero--compact' : '' ) . '"><div class="container">';
	$out .= nvgso_breadcrumbs( $crumbs );
	$out .= '<h1>' . esc_html( $title ) . '</h1>';
	$out .= $lead ? '<p class="page-hero__lead">' . esc_html( $lead ) . '</p>' : '';
	if ( $meta ) {
		$out .= '<div class="page-hero__meta">';
		foreach ( $meta as $item ) {
			$out .= '<span>' . nvgso_icon( $item['icon'] ?? 'info', '', 15 ) . esc_html( $item['text'] ) . '</span>';
		}
		$out .= '</div>';
	}
	return $out . '</div></section>';
}

function nvgso_thumb_url( $post_id, $size = 'nvgso-card' ) {
	$thumb_id = get_post_thumbnail_id( $post_id );
	if ( $thumb_id ) {
		return wp_get_attachment_image_url( $thumb_id, $size );
	}
	return '';
}

function nvgso_news_card( $post ) {
	$post_id = $post instanceof WP_Post ? $post->ID : (int) $post;
	$url     = get_permalink( $post_id );
	$img     = nvgso_thumb_url( $post_id, 'nvgso-card' );
	$date    = get_the_date( 'Y-m-d', $post_id );
	$cat     = nvgso_post_category_label( $post_id );
	$excerpt = nvgso_excerpt( get_the_excerpt( $post_id ), 150 );

	$out  = '<a class="news-card" href="' . esc_url( $url ) . '">';
	$out .= '<span class="news-card__media"><span class="badge news-card__badge">' . esc_html( $cat ) . '</span>';
	$out .= $img ? '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( get_the_title( $post_id ) ) . '" loading="lazy" decoding="async">' : '';
	$out .= '</span><span class="news-card__body">';
	$out .= '<span class="news-card__date">' . nvgso_icon( 'calendar', '', 14 ) . esc_html( nvgso_date( $date ) ) . '</span>';
	$out .= '<h3>' . esc_html( get_the_title( $post_id ) ) . '</h3>';
	$out .= '<p>' . esc_html( $excerpt ) . '</p>';
	$out .= '<span class="news-card__more">Читать новость ' . nvgso_icon( 'arrowRight', '', 14 ) . '</span>';
	return $out . '</span></a>';
}

function nvgso_news_feature( $post ) {
	$post_id = $post instanceof WP_Post ? $post->ID : (int) $post;
	$url     = get_permalink( $post_id );
	$img     = nvgso_thumb_url( $post_id, 'nvgso-wide' );
	$date    = get_the_date( 'Y-m-d', $post_id );
	$cat     = nvgso_post_category_label( $post_id );
	$excerpt = nvgso_excerpt( get_the_excerpt( $post_id ), 220 );

	$out  = '<a class="news-feature" href="' . esc_url( $url ) . '">';
	$out .= '<span class="news-feature__media">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( get_the_title( $post_id ) ) . '" loading="lazy" decoding="async">' : '' ) . '</span>';
	$out .= '<span class="news-feature__body"><span class="badge badge--red" style="align-self:flex-start">Главная новость · ' . esc_html( $cat ) . '</span>';
	$out .= '<h3>' . esc_html( get_the_title( $post_id ) ) . '</h3><p>' . esc_html( $excerpt ) . '</p>';
	$out .= '<span class="news-card__date" style="margin-top:1rem">' . nvgso_icon( 'calendar', '', 14 ) . esc_html( nvgso_date( $date, true ) ) . '</span>';
	$out .= '<span class="text-link" style="margin-top:1rem">Читать полностью ' . nvgso_icon( 'arrowRight', '', 16 ) . '</span>';
	return $out . '</span></a>';
}

function nvgso_doc_row( $post ) {
	$post_id = $post instanceof WP_Post ? $post->ID : (int) $post;
	$meta    = nvgso_doc_meta( $post_id );
	$url     = $meta['file_id'] ? wp_get_attachment_url( $meta['file_id'] ) : '';
	$cats    = get_the_terms( $post_id, 'doc_category' );
	$cat     = $cats && ! is_wp_error( $cats ) ? $cats[0]->name : 'Документ';
	$date    = $meta['date'] ? $meta['date'] : get_the_date( 'Y-m-d', $post_id );

	$out  = '<a class="doc-row" href="' . esc_url( $url ? $url : '#' ) . '"' . ( $url ? ' target="_blank" rel="noopener"' : '' ) . '>';
	$out .= '<span class="doc-row__icon">' . nvgso_icon( 'doc', '', 22 ) . '</span>';
	$out .= '<span class="doc-row__main"><span class="doc-row__title">' . esc_html( get_the_title( $post_id ) ) . '</span><span class="doc-row__meta">';
	$out .= '<span>' . nvgso_icon( 'calendar', '', 13 ) . esc_html( nvgso_date( $date ) ) . '</span>';
	$out .= '<span>' . esc_html( $cat ) . '</span>';
	$out .= '<span>' . ( $meta['pages'] ? (int) $meta['pages'] . ' стр.' : 'PDF' ) . '</span>';
	$out .= $meta['note'] ? '<span>' . esc_html( nvgso_excerpt( $meta['note'], 80 ) ) . '</span>' : '';
	$out .= '</span></span><span class="doc-row__download" aria-hidden="true">' . nvgso_icon( 'download', '', 18 ) . '</span>';
	return $out . '</a>';
}

function nvgso_person_card( $post ) {
	$post_id   = $post instanceof WP_Post ? $post->ID : (int) $post;
	$position  = get_the_title( $post_id );
	$name      = (string) get_post_meta( $post_id, '_nvgso_name', true );
	$rank      = (string) get_post_meta( $post_id, '_nvgso_rank', true );
	$reception = (string) get_post_meta( $post_id, '_nvgso_reception', true );
	$phone     = (string) get_post_meta( $post_id, '_nvgso_phone', true );

	$out  = '<div class="contact-card"><div class="contact-card__icon">' . nvgso_icon( 'user', '', 20 ) . '</div><div>';
	$out .= '<div class="contact-card__label">' . esc_html( $position ) . '</div>';
	$out .= '<div class="contact-card__value">' . esc_html( nvgso_is_placeholder( $name ) ? 'ФИО уточняется' : $name ) . '</div>';
	$out .= $rank ? '<div class="contact-card__note">' . esc_html( $rank ) . '</div>' : '';
	$out .= $reception ? '<div class="contact-card__note">' . esc_html( $reception ) . '</div>' : '';
	$out .= $phone && ! nvgso_is_placeholder( $phone ) ? '<div style="margin-top:.35rem"><a href="' . esc_attr( nvgso_tel( $phone ) ) . '" style="font-weight:700">' . esc_html( $phone ) . '</a></div>' : '';
	return $out . '</div></div>';
}

function nvgso_cta_band( $title, $text, $primary_url, $primary_text, $secondary_url = '', $secondary_text = '' ) {
	$out  = '<section class="cta-band"><div class="container cta-band__in"><div>';
	$out .= '<h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $text ) . '</p></div><div class="cta-band__actions">';
	$out .= '<a class="btn btn-red" href="' . esc_url( $primary_url ) . '">' . nvgso_icon( 'send', '', 18 ) . esc_html( $primary_text ) . '</a>';
	$out .= $secondary_url ? '<a class="btn btn-outline" href="' . esc_url( $secondary_url ) . '">' . esc_html( $secondary_text ) . '</a>' : '';
	return $out . '</div></div></section>';
}

function nvgso_plural( $n, $forms ) {
	$n = abs( (int) $n ) % 100;
	$n1 = $n % 10;
	if ( $n > 10 && $n < 20 ) {
		return $forms[2];
	}
	if ( $n1 > 1 && $n1 < 5 ) {
		return $forms[1];
	}
	if ( 1 === $n1 ) {
		return $forms[0];
	}
	return $forms[2];
}

/**
 * Оглавление по h2 с id (для юридических страниц).
 */
function nvgso_toc( $content ) {
	if ( ! preg_match_all( '/<h2[^>]*id="([^"]+)"[^>]*>(.*?)<\/h2>/is', $content, $m, PREG_SET_ORDER ) || count( $m ) < 2 ) {
		return '';
	}
	$out = '<aside class="toc" aria-label="Содержание"><h2>Содержание</h2><ol>';
	foreach ( $m as $item ) {
		$out .= '<li><a href="#' . esc_attr( $item[1] ) . '">' . wp_strip_all_tags( $item[2] ) . '</a></li>';
	}
	return $out . '</ol></aside>';
}

/**
 * Транслитерация для читаемых URL.
 */
function nvgso_translit( $text ) {
	$map = array(
		'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
		'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
		'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
		'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
		'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
	);
	$text = strtr( mb_strtolower( (string) $text, 'UTF-8' ), $map );
	$text = preg_replace( '/[^a-z0-9]+/u', '-', $text );
	$text = trim( $text, '-' );
	return $text ? $text : 'post-' . wp_rand( 1000, 9999 );
}
