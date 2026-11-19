<?php
/**
 * Локальный WordPress Preview Runtime для живого рендеринга темы deploy/theme-src/
 * внутри PHP 8.3 WebAssembly без установки MySQL/WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/runtime/wp-stub/' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
}

/* =========================================================
   Базовые классы WordPress
   ========================================================= */
class WP_Error {
	public $code;
	public $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

class WP_Term {
	public $term_id;
	public $name;
	public $slug;
	public $taxonomy;
	public $description;
	public $count;
	public function __construct( array $data = array() ) {
		foreach ( $data as $k => $v ) {
			$this->$k = $v;
		}
	}
}

class WP_Post {
	public $ID;
	public $post_type = 'post';
	public $post_status = 'publish';
	public $post_title = '';
	public $post_name = '';
	public $post_excerpt = '';
	public $post_content = '';
	public $post_date = '2026-01-01 00:00:00';
	public $menu_order = 0;
	public $guid = '';
	public $meta = array();
	public $terms = array();

	public function __construct( array $data = array() ) {
		foreach ( $data as $k => $v ) {
			$this->$k = $v;
		}
	}
}

class NVGSO_WPDB_Stub {
	public $prefix = 'wp_';
	public function get_charset_collate() { return 'DEFAULT CHARSET=utf8mb4'; }
	public function insert( $table, $data, $format = null ) { return 1; }
	public function query( $sql ) { return 0; }
	public function get_results( $sql, $output = OBJECT ) { return array(); }
	public function get_var( $sql ) { return 0; }
	public function prepare( $query, ...$args ) { return $query; }
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
	define( 'ARRAY_A', 'ARRAY_A' );
}

class WP_Query {
	public $posts = array();
	public $post_count = 0;
	public $found_posts = 0;
	public $max_num_pages = 1;
	public $current_post = -1;
	public $post = null;
	public $query_vars = array();

	public function __construct( $args = array() ) {
		if ( ! empty( $args ) ) {
			$this->query( $args );
		}
	}

	public function query( $args ) {
		global $NVGSO_DB;
		$this->query_vars = $args;

		$post_type = $args['post_type'] ?? 'post';
		$types     = is_array( $post_type ) ? $post_type : ( 'any' === $post_type ? array( 'post', 'page', 'document', 'division', 'gallery_item' ) : array( $post_type ) );
		$list      = array();

		foreach ( $NVGSO_DB['posts'] as $p ) {
			if ( ! in_array( $p->post_type, $types, true ) ) {
				continue;
			}
			if ( isset( $args['post_status'] ) && 'any' !== $args['post_status'] ) {
				$statuses = is_array( $args['post_status'] ) ? $args['post_status'] : array( $args['post_status'] );
				if ( ! in_array( $p->post_status, $statuses, true ) ) {
					continue;
				}
			}
			if ( ! empty( $args['post__not_in'] ) && in_array( $p->ID, (array) $args['post__not_in'], true ) ) {
				continue;
			}
			if ( ! empty( $args['meta_key'] ) ) {
				$mk = $args['meta_key'];
				$mv = $p->meta[ $mk ] ?? '';
				if ( isset( $args['meta_value'] ) && (string) $mv !== (string) $args['meta_value'] ) {
					continue;
				}
			}
			if ( ! empty( $args['cat'] ) ) {
				$cat_id   = (int) $args['cat'];
				$post_cat = $p->terms['category'] ?? array();
				if ( ! in_array( $cat_id, $post_cat, true ) ) {
					continue;
				}
			}
			if ( ! empty( $args['category__in'] ) ) {
				$wanted   = array_map( 'intval', (array) $args['category__in'] );
				$post_cat = $p->terms['category'] ?? array();
				if ( ! array_intersect( $wanted, $post_cat ) ) {
					continue;
				}
			}
			if ( ! empty( $args['category_name'] ) ) {
				$slug     = (string) $args['category_name'];
				$post_cat = $p->terms['category'] ?? array();
				$matched  = false;
				foreach ( $post_cat as $tid ) {
					if ( isset( $NVGSO_DB['terms'][ $tid ] ) && $NVGSO_DB['terms'][ $tid ]->slug === $slug ) {
						$matched = true;
						break;
					}
				}
				if ( ! $matched ) {
					continue;
				}
			}
			if ( ! empty( $args['tax_query'] ) ) {
				$ok = true;
				foreach ( $args['tax_query'] as $tq ) {
					if ( ! is_array( $tq ) || empty( $tq['taxonomy'] ) ) {
						continue;
					}
					$tax   = $tq['taxonomy'];
					$field = $tq['field'] ?? 'slug';
					$vals  = (array) ( $tq['terms'] ?? array() );
					$ptids = $p->terms[ $tax ] ?? array();
					$hit   = false;
					foreach ( $ptids as $tid ) {
						$term = $NVGSO_DB['terms'][ $tid ] ?? null;
						if ( ! $term ) {
							continue;
						}
						$cmp = 'term_id' === $field ? $term->term_id : $term->slug;
						if ( in_array( $cmp, $vals, false ) ) {
							$hit = true;
							break;
						}
					}
					if ( ! $hit ) {
						$ok = false;
						break;
					}
				}
				if ( ! $ok ) {
					continue;
				}
			}
			if ( isset( $args['s'] ) && '' !== trim( (string) $args['s'] ) ) {
				$needle = mb_strtolower( trim( (string) $args['s'] ), 'UTF-8' );
				$hay    = mb_strtolower(
					$p->post_title . ' ' . $p->post_excerpt . ' ' . strip_tags( $p->post_content ) . ' ' . implode( ' ', array_map( 'strval', array_filter( $p->meta, 'is_scalar' ) ) ),
					'UTF-8'
				);
				if ( false === mb_strpos( $hay, $needle, 0, 'UTF-8' ) ) {
					continue;
				}
			}
			$list[] = $p;
		}

		// Сортировка
		$orderby = $args['orderby'] ?? 'date';
		$order   = strtoupper( $args['order'] ?? 'DESC' );
		usort(
			$list,
			function ( $a, $b ) use ( $orderby, $order ) {
				if ( is_array( $orderby ) ) {
					foreach ( $orderby as $k => $dir ) {
						$cmp = 0;
						if ( 'menu_order' === $k ) {
							$cmp = $a->menu_order <=> $b->menu_order;
						} elseif ( 'date' === $k ) {
							$cmp = strcmp( $a->post_date, $b->post_date );
						}
						if ( 0 !== $cmp ) {
							return 'DESC' === strtoupper( $dir ) ? -$cmp : $cmp;
						}
					}
					return 0;
				}
				if ( false !== strpos( (string) $orderby, 'menu_order' ) ) {
					$cmp = $a->menu_order <=> $b->menu_order;
					if ( 0 !== $cmp ) {
						return 'DESC' === $order ? -$cmp : $cmp;
					}
				}
				$cmp = strcmp( $a->post_date, $b->post_date );
				return 'ASC' === $order ? $cmp : -$cmp;
			}
		);

		$this->found_posts = count( $list );
		$per_page          = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 9;
		$paged             = max( 1, (int) ( $args['paged'] ?? 1 ) );

		if ( $per_page > 0 ) {
			$this->max_num_pages = max( 1, (int) ceil( $this->found_posts / $per_page ) );
			$list                = array_slice( $list, ( $paged - 1 ) * $per_page, $per_page );
		} else {
			$this->max_num_pages = 1;
		}

		if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
			$this->posts = array_map( fn( $p ) => $p->ID, $list );
		} else {
			$this->posts = $list;
		}
		$this->post_count   = count( $this->posts );
		$this->current_post = -1;
		return $this->posts;
	}

	public function have_posts() {
		return ( $this->current_post + 1 ) < $this->post_count;
	}

	public function the_post() {
		global $post;
		$this->current_post++;
		$this->post = $this->posts[ $this->current_post ];
		$post       = $this->post;
		return $this->post;
	}

	public function rewind_posts() {
		$this->current_post = -1;
	}
}

/* =========================================================
   Глобальное состояние и хуки
   ========================================================= */
$wpdb = new NVGSO_WPDB_Stub();
$NVGSO_HOOKS   = array();
$NVGSO_STYLES  = array();
$NVGSO_SCRIPTS = array();
$NVGSO_LOCALIZE = array();
$NVGSO_POST_TYPES = array();
$NVGSO_OPTIONS = array(
	'nvgso_consent_db_version' => '1.0',
	'nvgso_traffic_db_version' => '1.1',
	'page_for_posts'           => 0,
);
$NVGSO_TRANSIENTS = array();
$NVGSO_ROUTE = array(
	'type'         => 'front',
	'slug'         => '',
	'page_slug'    => '',
	'cat_term'     => null,
	'search_query' => '',
	'paged'        => 1,
	'status'       => 200,
);

function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	global $NVGSO_HOOKS;
	$NVGSO_HOOKS[ $tag ][ $priority ][] = array(
		'fn'   => $callback,
		'args' => $accepted_args,
	);
	return true;
}
function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $tag, $callback, $priority, $accepted_args );
}
function remove_filter( $tag, $callback, $priority = 10 ) { return true; }
function remove_action( $tag, $callback, $priority = 10 ) { return true; }

function apply_filters( $tag, $value, ...$args ) {
	global $NVGSO_HOOKS;
	if ( empty( $NVGSO_HOOKS[ $tag ] ) ) {
		return $value;
	}
	$groups = $NVGSO_HOOKS[ $tag ];
	ksort( $groups );
	foreach ( $groups as $callbacks ) {
		foreach ( $callbacks as $item ) {
			$call_args = array_merge( array( $value ), $args );
			$call_args = array_slice( $call_args, 0, max( 1, (int) $item['args'] ) );
			$value     = call_user_func_array( $item['fn'], $call_args );
		}
	}
	return $value;
}

function do_action( $tag, ...$args ) {
	global $NVGSO_HOOKS;
	if ( empty( $NVGSO_HOOKS[ $tag ] ) ) {
		return;
	}
	$groups = $NVGSO_HOOKS[ $tag ];
	ksort( $groups );
	foreach ( $groups as $callbacks ) {
		foreach ( $callbacks as $item ) {
			$call_args = array_slice( $args, 0, max( 0, (int) $item['args'] ) );
			call_user_func_array( $item['fn'], $call_args );
		}
	}
}

/* =========================================================
   Утилиты экранирования, санитизации, URL
   ========================================================= */
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
function esc_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}
	return htmlspecialchars( $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}
function esc_url_raw( $url ) { return trim( (string) $url ); }
function wp_kses_post( $data ) { return (string) $data; }
function wp_strip_all_tags( $text, $remove_breaks = false ) {
	$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
	$text = strip_tags( $text );
	if ( $remove_breaks ) {
		$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
	}
	return trim( $text );
}
function wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}
	return is_string( $value ) ? stripslashes( $value ) : $value;
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function sanitize_title( $title ) {
	$title = trim( mb_strtolower( (string) $title, 'UTF-8' ) );
	$title = preg_replace( '/[^a-zа-яё0-9_\-]+/u', '-', $title );
	return trim( $title, '-' );
}
function sanitize_text_field( $str ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags( (string) $str ) ) );
}
function sanitize_textarea_field( $str ) { return trim( wp_strip_all_tags( (string) $str ) ); }
function sanitize_email( $email ) { return filter_var( trim( (string) $email ), FILTER_SANITIZE_EMAIL ); }
function sanitize_file_name( $filename ) { return preg_replace( '/[^a-zA-Z0-9._\-]/', '_', (string) $filename ); }
function is_email( $email ) { return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL ); }
function absint( $val ) { return abs( (int) $val ); }
function wp_parse_args( $args, $defaults = array() ) {
	$r = is_array( $args ) ? $args : array();
	return array_merge( $defaults, $r );
}
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags | JSON_UNESCAPED_UNICODE ); }
function wp_rand( $min = 0, $max = 9999 ) { return mt_rand( (int) $min, (int) $max ); }
function wp_generate_uuid4() {
	return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0x0fff ) | 0x4000, mt_rand( 0, 0x3fff ) | 0x8000, mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ) );
}
function wp_salt( $scheme = 'auth' ) { return 'nvgso-preview-salt-' . $scheme; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function current_time( $type = 'mysql' ) { return 'timestamp' === $type ? time() : gmdate( 'Y-m-d H:i:s' ); }

function trailingslashit( $string ) { return rtrim( (string) $string, '/\\' ) . '/'; }
function user_trailingslashit( $string ) { return '/' . trim( (string) $string, '/' ) . '/'; }

function home_url( $path = '' ) {
	$path = (string) $path;
	if ( '' === $path || '/' === $path ) {
		return '/';
	}
	if ( '?' === $path[0] ) {
		return '/' . $path;
	}
	return '/' . ltrim( $path, '/' );
}
function admin_url( $path = '' ) {
	$path = ltrim( (string) $path, '/' );
	return '/wp-admin/' . $path;
}
function get_stylesheet_directory() { return '/theme'; }
function get_template_directory() { return '/theme'; }
function get_stylesheet_directory_uri() { return '/wp-content/themes/nvgso'; }
function get_template_directory_uri() { return '/wp-content/themes/nvgso'; }

function add_query_arg( $key, $value = null, $url = '' ) {
	if ( is_array( $key ) ) {
		$url   = (string) ( $value ?? '' );
		$query = $key;
	} else {
		$query = array( $key => $value );
	}
	$parts = parse_url( $url );
	$base  = ( $parts['path'] ?? '/' );
	$exist = array();
	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $exist );
	}
	$merged = array_merge( $exist, $query );
	$qs     = http_build_query( $merged );
	$frag   = ! empty( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';
	return $base . ( $qs ? '?' . $qs : '' ) . $frag;
}

/* =========================================================
   Опции, стили, скрипты, шаблоны
   ========================================================= */
function get_option( $name, $default = false ) {
	global $NVGSO_OPTIONS;
	return array_key_exists( $name, $NVGSO_OPTIONS ) ? $NVGSO_OPTIONS[ $name ] : $default;
}
function update_option( $name, $value ) {
	global $NVGSO_OPTIONS;
	$NVGSO_OPTIONS[ $name ] = $value;
	return true;
}
function get_transient( $key ) {
	global $NVGSO_TRANSIENTS;
	return $NVGSO_TRANSIENTS[ $key ] ?? false;
}
function set_transient( $key, $val, $exp = 0 ) {
	global $NVGSO_TRANSIENTS;
	$NVGSO_TRANSIENTS[ $key ] = $val;
	return true;
}

function add_theme_support( $feature, ...$args ) {}
function register_nav_menus( $locations = array() ) {}
function add_image_size( $name, $w = 0, $h = 0, $crop = false ) {}
function add_options_page( ...$args ) {}
function add_menu_page( ...$args ) {}
function add_submenu_page( ...$args ) {}
function add_meta_box( ...$args ) {}
function register_setting( ...$args ) {}
function add_shortcode( $tag, $cb ) {}
function nocache_headers() {}
function wp_next_scheduled( $hook ) { return time() + 3600; }
function wp_schedule_event( ...$args ) {}
function wp_is_post_autosave( $id ) { return false; }
function wp_is_post_revision( $id ) { return false; }
function wp_upload_dir() { return array( 'basedir' => '/tmp/uploads', 'baseurl' => '/uploads' ); }
function dbDelta( $sql ) { return array(); }

function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
	global $NVGSO_STYLES;
	$NVGSO_STYLES[ $handle ] = array( 'src' => $src, 'ver' => $ver );
}
function wp_dequeue_style( $handle ) {
	global $NVGSO_STYLES;
	unset( $NVGSO_STYLES[ $handle ] );
}
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
	global $NVGSO_SCRIPTS;
	$NVGSO_SCRIPTS[ $handle ] = array( 'src' => $src, 'ver' => $ver, 'in_footer' => $in_footer );
}
function wp_localize_script( $handle, $object_name, $l10n ) {
	global $NVGSO_LOCALIZE;
	$NVGSO_LOCALIZE[ $handle ][] = array( 'name' => $object_name, 'data' => $l10n );
}

function language_attributes() { echo 'lang="ru-RU"'; }
function bloginfo( $show = '' ) { echo esc_html( get_bloginfo( $show ) ); }
function get_bloginfo( $show = '' ) {
	if ( 'charset' === $show ) {
		return 'UTF-8';
	}
	return 'Новокузнецкий ВГСО';
}
function body_class( $class = '' ) {
	$classes = apply_filters( 'body_class', $class ? explode( ' ', $class ) : array() );
	echo 'class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '"';
}
function wp_body_open() { do_action( 'wp_body_open' ); }

function wp_head() {
	global $NVGSO_STYLES, $NVGSO_ROUTE, $post;
	$page_title = 'Официальный сайт филиала «Новокузнецкий ВГСО» ФГУП «ВГСЧ»';
	if ( ! is_front_page() ) {
		if ( is_search() ) {
			$page_title = 'Поиск: ' . get_search_query() . ' — Новокузнецкий ВГСО';
		} elseif ( is_404() ) {
			$page_title = 'Страница не найдена — Новокузнецкий ВГСО';
		} elseif ( is_category() ) {
			$page_title = single_cat_title( '', false ) . ' — Новокузнецкий ВГСО';
		} elseif ( is_home() ) {
			$page_title = 'Новости — Новокузнецкий ВГСО';
		} elseif ( $post && ! empty( $post->post_title ) ) {
			$page_title = $post->post_title . ' — Новокузнецкий ВГСО';
		}
	}
	echo '<title>' . esc_html( $page_title ) . "</title>\n";
	foreach ( $NVGSO_STYLES as $handle => $st ) {
		$href = $st['src'] . ( $st['ver'] ? '?ver=' . rawurlencode( (string) $st['ver'] ) : '' );
		echo '<link rel="stylesheet" id="' . esc_attr( $handle ) . '-css" href="' . esc_url( $href ) . '">' . "\n";
	}
	do_action( 'wp_head' );
}

function wp_footer() {
	global $NVGSO_SCRIPTS, $NVGSO_LOCALIZE;
	foreach ( $NVGSO_SCRIPTS as $handle => $sc ) {
		if ( ! empty( $NVGSO_LOCALIZE[ $handle ] ) ) {
			foreach ( $NVGSO_LOCALIZE[ $handle ] as $loc ) {
				echo '<script>var ' . $loc['name'] . ' = ' . wp_json_encode( $loc['data'] ) . ";</script>\n";
			}
		}
		$src = $sc['src'] . ( $sc['ver'] ? '?ver=' . rawurlencode( (string) $sc['ver'] ) : '' );
		echo '<script src="' . esc_url( $src ) . '" id="' . esc_attr( $handle ) . '-js"></script>' . "\n";
	}
	do_action( 'wp_footer' );
}

function get_header() { require '/theme/header.php'; }
function get_footer() { require '/theme/footer.php'; }

/* =========================================================
   Типы записей, таксономии, записи, вложения
   ========================================================= */
function register_post_type( $type, $args = array() ) {
	global $NVGSO_POST_TYPES;
	$labels = (object) ( $args['labels'] ?? array( 'singular_name' => $type, 'name' => $type ) );
	$NVGSO_POST_TYPES[ $type ] = (object) array( 'name' => $type, 'labels' => $labels );
}
function register_taxonomy( $tax, $types, $args = array() ) {}
function get_post_type_object( $type ) {
	global $NVGSO_POST_TYPES;
	if ( isset( $NVGSO_POST_TYPES[ $type ] ) ) {
		return $NVGSO_POST_TYPES[ $type ];
	}
	$defaults = array(
		'post' => 'Новость',
		'page' => 'Страница',
	);
	return (object) array( 'labels' => (object) array( 'singular_name' => $defaults[ $type ] ?? 'Материал' ) );
}

function _nvgso_find_post( $post_id = null ) {
	global $NVGSO_DB, $post;
	if ( $post_id instanceof WP_Post ) {
		return $post_id;
	}
	if ( null === $post_id ) {
		return $post;
	}
	$id = (int) $post_id;
	return $NVGSO_DB['posts_by_id'][ $id ] ?? null;
}

function get_posts( $args = array() ) {
	$q = new WP_Query( $args );
	return $q->posts;
}

function get_page_by_path( $slug ) {
	global $NVGSO_DB;
	$slug = trim( (string) $slug, '/' );
	foreach ( $NVGSO_DB['posts'] as $p ) {
		if ( 'page' === $p->post_type && $p->post_name === $slug ) {
			return $p;
		}
	}
	return null;
}

function get_post_meta( $post_id, $key = '', $single = false ) {
	$p = _nvgso_find_post( $post_id );
	if ( ! $p ) {
		return $single ? '' : array();
	}
	if ( '' === $key ) {
		return $p->meta;
	}
	if ( ! array_key_exists( $key, $p->meta ) ) {
		return $single ? '' : array();
	}
	return $single ? $p->meta[ $key ] : array( $p->meta[ $key ] );
}

function get_the_ID() {
	global $post;
	return $post ? (int) $post->ID : 0;
}
function get_the_title( $post_id = null ) {
	$p = _nvgso_find_post( $post_id );
	return $p ? $p->post_title : '';
}
function the_title() { echo esc_html( get_the_title() ); }
function the_title_attribute() { echo esc_attr( get_the_title() ); }
function get_the_content( $post_id = null ) {
	$p = _nvgso_find_post( $post_id );
	return $p ? $p->post_content : '';
}
function the_content() { echo apply_filters( 'the_content', get_the_content() ); }
function has_excerpt( $post_id = null ) {
	$p = _nvgso_find_post( $post_id );
	return $p && '' !== trim( (string) $p->post_excerpt );
}
function get_the_excerpt( $post_id = null ) {
	$p = _nvgso_find_post( $post_id );
	if ( ! $p ) {
		return '';
	}
	return '' !== trim( (string) $p->post_excerpt ) ? $p->post_excerpt : wp_strip_all_tags( $p->post_content );
}
function get_the_date( $format = 'Y-m-d', $post_id = null ) {
	$p  = _nvgso_find_post( $post_id );
	$ts = $p ? strtotime( $p->post_date ) : time();
	return date( $format, $ts ?: time() );
}
function get_post_type( $post_id = null ) {
	$p = _nvgso_find_post( $post_id );
	return $p ? $p->post_type : 'post';
}
function get_permalink( $post_id = null ) {
	$p = _nvgso_find_post( $post_id );
	if ( ! $p ) {
		return '/';
	}
	if ( 'page' === $p->post_type ) {
		return '/' . trim( $p->post_name, '/' ) . '/';
	}
	if ( 'post' === $p->post_type ) {
		return '/news/' . rawurlencode( $p->post_name ) . '/';
	}
	if ( 'document' === $p->post_type ) {
		$fid = (int) ( $p->meta['_nvgso_file_id'] ?? 0 );
		return $fid ? wp_get_attachment_url( $fid ) : '/documents/';
	}
	return '/' . $p->post_type . '/' . rawurlencode( $p->post_name ) . '/';
}
function the_permalink() { echo esc_url( get_permalink() ); }

function get_post_thumbnail_id( $post_id = null ) {
	$p = _nvgso_find_post( $post_id );
	return $p ? (int) ( $p->meta['_thumbnail_id'] ?? 0 ) : 0;
}
function wp_get_attachment_url( $att_id ) {
	$p = _nvgso_find_post( (int) $att_id );
	return $p ? (string) $p->guid : '';
}
function wp_get_attachment_image_url( $att_id, $size = 'large' ) {
	$p = _nvgso_find_post( (int) $att_id );
	if ( ! $p ) {
		return '';
	}
	if ( 'nvgso-card' === $size && ! empty( $p->meta['_nvgso_thumb_url'] ) ) {
		return $p->meta['_nvgso_thumb_url'];
	}
	return (string) $p->guid;
}
function wp_get_attachment_image( $att_id, $size = 'large', $icon = false, $attr = array() ) {
	$src = wp_get_attachment_image_url( $att_id, $size );
	if ( ! $src ) {
		return '';
	}
	$attrs = array_merge( array( 'src' => $src, 'alt' => '' ), is_array( $attr ) ? $attr : array() );
	$html  = '<img';
	foreach ( $attrs as $k => $v ) {
		if ( '' !== (string) $v || 'alt' === $k ) {
			$html .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
	}
	return $html . '>';
}

function get_terms( $args = array() ) {
	global $NVGSO_DB;
	$tax = is_string( $args ) ? $args : ( $args['taxonomy'] ?? '' );
	$out = array();
	foreach ( $NVGSO_DB['terms'] as $t ) {
		if ( $t->taxonomy === $tax ) {
			if ( ! empty( $args['hide_empty'] ) && (int) $t->count <= 0 ) {
				continue;
			}
			$out[] = $t;
		}
	}
	return $out;
}
function get_categories( $args = array() ) {
	$args['taxonomy'] = 'category';
	return get_terms( $args );
}
function get_the_terms( $post_id, $tax ) {
	global $NVGSO_DB;
	$p = _nvgso_find_post( $post_id );
	if ( ! $p || empty( $p->terms[ $tax ] ) ) {
		return false;
	}
	$out = array();
	foreach ( $p->terms[ $tax ] as $tid ) {
		if ( isset( $NVGSO_DB['terms'][ $tid ] ) ) {
			$out[] = $NVGSO_DB['terms'][ $tid ];
		}
	}
	return $out ?: false;
}
function get_the_category( $post_id = null ) {
	$terms = get_the_terms( $post_id, 'category' );
	return is_array( $terms ) ? $terms : array();
}
function wp_get_post_categories( $post_id = null ) {
	$p = _nvgso_find_post( $post_id );
	return $p && ! empty( $p->terms['category'] ) ? $p->terms['category'] : array();
}
function single_cat_title( $prefix = '', $display = true ) {
	global $NVGSO_ROUTE;
	$title = $NVGSO_ROUTE['cat_term'] ? $NVGSO_ROUTE['cat_term']->name : 'Новости';
	if ( $display ) {
		echo esc_html( $prefix . $title );
	}
	return $prefix . $title;
}

/* =========================================================
   Main Loop & Conditionals
   ========================================================= */
function have_posts() {
	global $wp_query;
	return $wp_query ? $wp_query->have_posts() : false;
}
function the_post() {
	global $wp_query;
	return $wp_query ? $wp_query->the_post() : null;
}
function wp_reset_postdata() {
	global $wp_query, $post;
	if ( $wp_query && $wp_query->post ) {
		$post = $wp_query->post;
	}
}
function get_query_var( $var, $default = '' ) {
	global $NVGSO_ROUTE;
	if ( 'paged' === $var ) {
		return $NVGSO_ROUTE['paged'] ?? 1;
	}
	return $default;
}
function paginate_links( $args = array() ) {
	$total   = (int) ( $args['total'] ?? 1 );
	$current = max( 1, (int) ( $args['current'] ?? 1 ) );
	if ( $total <= 1 ) {
		return array();
	}
	$base_path = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) ?: '/';
	$q_params  = $_GET;
	$links     = array();
	if ( $current > 1 ) {
		$q_params['paged'] = $current - 1;
		$links[] = '<a class="prev page-numbers" href="' . esc_url( $base_path . '?' . http_build_query( $q_params ) ) . '">' . ( $args['prev_text'] ?? 'Назад' ) . '</a>';
	}
	for ( $i = 1; $i <= $total; $i++ ) {
		if ( $i === $current ) {
			$links[] = '<span aria-current="page" class="page-numbers current">' . $i . '</span>';
		} else {
			$q_params['paged'] = $i;
			$links[] = '<a class="page-numbers" href="' . esc_url( $base_path . '?' . http_build_query( $q_params ) ) . '">' . $i . '</a>';
		}
	}
	if ( $current < $total ) {
		$q_params['paged'] = $current + 1;
		$links[] = '<a class="next page-numbers" href="' . esc_url( $base_path . '?' . http_build_query( $q_params ) ) . '">' . ( $args['next_text'] ?? 'Вперёд' ) . '</a>';
	}
	return $links;
}

function is_front_page() { global $NVGSO_ROUTE; return 'front' === $NVGSO_ROUTE['type']; }
function is_home() { global $NVGSO_ROUTE; return 'home' === $NVGSO_ROUTE['type']; }
function is_single() { global $NVGSO_ROUTE; return 'single' === $NVGSO_ROUTE['type']; }
function is_category() { global $NVGSO_ROUTE; return 'category' === $NVGSO_ROUTE['type']; }
function is_search() { global $NVGSO_ROUTE; return 'search' === $NVGSO_ROUTE['type']; }
function is_404() { global $NVGSO_ROUTE; return '404' === $NVGSO_ROUTE['type']; }
function is_post_type_archive( $types = '' ) { return false; }
function is_admin() { return false; }
function is_user_logged_in() { return false; }
function current_user_can( $cap ) { return false; }
function is_page( $page = '' ) {
	global $NVGSO_ROUTE;
	if ( 'page' !== $NVGSO_ROUTE['type'] ) {
		return false;
	}
	if ( '' === $page ) {
		return true;
	}
	$list = is_array( $page ) ? $page : array( $page );
	return in_array( $NVGSO_ROUTE['page_slug'], $list, true );
}
function get_search_query( $escaped = true ) {
	global $NVGSO_ROUTE;
	$q = (string) ( $NVGSO_ROUTE['search_query'] ?? '' );
	return $escaped ? esc_attr( $q ) : $q;
}

/* =========================================================
   Формы, Nonces, AJAX, Редиректы
   ========================================================= */
function wp_create_nonce( $action = -1 ) { return substr( md5( 'nvgso-preview-' . $action ), 0, 10 ); }
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
	$out = '<input type="hidden" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_create_nonce( $action ) ) . '">';
	if ( $display ) {
		echo $out;
	}
	return $out;
}
function wp_nonce_url( $url, $action = -1, $name = '_wpnonce' ) { return add_query_arg( $name, wp_create_nonce( $action ), $url ); }
function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) { return 1; }
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) { return 1; }
function wp_get_referer() { return $_SERVER['HTTP_REFERER'] ?? ''; }
function wp_send_json_success( $data = null, $status_code = 200 ) {
	http_response_code( $status_code ?: 200 );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( array( 'success' => true, 'data' => $data ) );
	exit;
}
function wp_send_json_error( $data = null, $status_code = 400 ) {
	http_response_code( $status_code ?: 400 );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( array( 'success' => false, 'data' => $data ) );
	exit;
}
function wp_safe_redirect( $location, $status = 302 ) {
	http_response_code( $status );
	header( 'Location: ' . $location );
	exit;
}
function wp_die( $msg = '' ) {
	http_response_code( 403 );
	echo esc_html( $msg );
	exit;
}
function wp_mail( ...$args ) { return true; }
function wp_insert_post( $args ) { return 99999; }
function update_post_meta( $post_id, $key, $val ) { return true; }

/* =========================================================
   Загрузка seed-данных из /runtime/seed.json
   ========================================================= */
$seed_raw = @file_get_contents( '/runtime/seed.json' );
$seed     = $seed_raw ? json_decode( $seed_raw, true ) : array();

$NVGSO_DB = array(
	'posts'       => array(),
	'posts_by_id' => array(),
	'terms'       => array(),
);

foreach ( ( $seed['terms'] ?? array() ) as $t ) {
	$term = new WP_Term( $t );
	$NVGSO_DB['terms'][ $term->term_id ] = $term;
}
foreach ( ( $seed['posts'] ?? array() ) as $p ) {
	$obj = new WP_Post( $p );
	$NVGSO_DB['posts'][] = $obj;
	$NVGSO_DB['posts_by_id'][ $obj->ID ] = $obj;
}
foreach ( ( $seed['options'] ?? array() ) as $k => $v ) {
	$NVGSO_OPTIONS[ $k ] = $v;
}

/* =========================================================
   Загрузка темы deploy/theme-src/functions.php
   ========================================================= */
require '/theme/functions.php';

do_action( 'after_setup_theme' );
do_action( 'init' );

/* =========================================================
   Маршрутизация запроса
   ========================================================= */
$req_uri = $_SERVER['REQUEST_URI'] ?? '/';
$path    = parse_url( $req_uri, PHP_URL_PATH ) ?: '/';
$slug    = trim( $path, '/' );
$paged   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
$NVGSO_ROUTE['paged'] = $paged;

// 1. AJAX-обработчик
if ( '/wp-admin/admin-ajax.php' === $path ) {
	$action = sanitize_key( $_REQUEST['action'] ?? '' );
	if ( 'nvgso_consent' === $action || 'nvgso_track' === $action ) {
		wp_send_json_success( array( 'saved' => true ) );
	}
	wp_send_json_success( array( 'ok' => true ) );
}

// 2. admin-post.php (форма обратной связи)
if ( '/wp-admin/admin-post.php' === $path ) {
	$action = sanitize_key( $_REQUEST['action'] ?? '' );
	if ( 'nvgso_feedback' === $action ) {
		$name    = sanitize_text_field( $_POST['name'] ?? '' );
		$email   = sanitize_email( $_POST['email'] ?? '' );
		$message = sanitize_textarea_field( $_POST['message'] ?? '' );
		$consent = ! empty( $_POST['consent'] );
		$ok      = ( '' !== $name && is_email( $email ) && '' !== $message && $consent );
		wp_safe_redirect( '/contacts/?feedback=' . ( $ok ? 'ok' : 'error' ) . '#feedback' );
	}
	wp_safe_redirect( '/' );
}

// 3. Поиск (?s=...)
if ( isset( $_GET['s'] ) ) {
	$q_str = trim( (string) wp_unslash( $_GET['s'] ) );
	$NVGSO_ROUTE['type']         = 'search';
	$NVGSO_ROUTE['search_query'] = $q_str;
	$wp_query = new WP_Query(
		array(
			'post_type'      => array( 'post', 'page', 'document' ),
			'post_status'    => 'publish',
			's'              => $q_str,
			'posts_per_page' => 20,
			'paged'          => $paged,
		)
	);
	do_action( 'wp_enqueue_scripts' );
	require '/theme/search.php';
	exit;
}

// 4. Главная страница
if ( '' === $slug ) {
	$NVGSO_ROUTE['type'] = 'front';
	$wp_query = new WP_Query( array( 'post_type' => 'page', 'posts_per_page' => 1 ) );
	do_action( 'wp_enqueue_scripts' );
	require '/theme/front-page.php';
	exit;
}

// 5. Новости (страница записей)
if ( 'news' === $slug ) {
	$NVGSO_ROUTE['type'] = 'home';
	$cat_id = isset( $_GET['cat'] ) ? (int) $_GET['cat'] : 0;
	$q_args = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 9,
		'paged'          => $paged,
	);
	if ( $cat_id > 0 ) {
		$q_args['cat'] = $cat_id;
	}
	$wp_query = new WP_Query( $q_args );
	do_action( 'wp_enqueue_scripts' );
	require '/theme/home.php';
	exit;
}

// 6. Отдельная новость (/news/<slug>/)
if ( preg_match( '#^news/([^/]+)$#', $slug, $m ) ) {
	$post_slug = rawurldecode( $m[1] );
	$found     = null;
	foreach ( $NVGSO_DB['posts'] as $p ) {
		if ( 'post' === $p->post_type && $p->post_name === $post_slug ) {
			$found = $p;
			break;
		}
	}
	if ( $found ) {
		$NVGSO_ROUTE['type'] = 'single';
		$wp_query            = new WP_Query();
		$wp_query->posts     = array( $found );
		$wp_query->post_count = 1;
		$wp_query->found_posts = 1;
		$post                = $found;
		do_action( 'wp_enqueue_scripts' );
		require '/theme/single.php';
		exit;
	}
}

// 7. Архив категории (/category/<slug>/)
if ( preg_match( '#^category/([^/]+)$#', $slug, $m ) ) {
	$cat_slug = rawurldecode( $m[1] );
	$cat_term = null;
	foreach ( $NVGSO_DB['terms'] as $t ) {
		if ( 'category' === $t->taxonomy && $t->slug === $cat_slug ) {
			$cat_term = $t;
			break;
		}
	}
	if ( $cat_term ) {
		$NVGSO_ROUTE['type']     = 'category';
		$NVGSO_ROUTE['cat_term'] = $cat_term;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'cat'            => $cat_term->term_id,
				'posts_per_page' => 9,
				'paged'          => $paged,
			)
		);
		do_action( 'wp_enqueue_scripts' );
		require '/theme/category.php';
		exit;
	}
}

// 8. Специальные и правовые страницы
$page_templates = array(
	'about'          => '/theme/page-about.php',
	'structure'      => '/theme/page-structure.php',
	'activity'       => '/theme/page-activity.php',
	'contacts'       => '/theme/page-contacts.php',
	'documents'      => '/theme/page-documents.php',
	'gallery'        => '/theme/page-gallery.php',
	'veterans'       => '/theme/page-veterans.php',
	'privacy-policy' => '/theme/page-legal.php',
	'company-policy' => '/theme/page-legal.php',
	'cookie-policy'  => '/theme/page-legal.php',
);

if ( isset( $page_templates[ $slug ] ) ) {
	$NVGSO_ROUTE['type']      = 'page';
	$NVGSO_ROUTE['page_slug'] = $slug;
	$page_obj                 = get_page_by_path( $slug );
	if ( ! $page_obj ) {
		$page_obj = new WP_Post( array( 'ID' => 9000, 'post_type' => 'page', 'post_name' => $slug, 'post_title' => ucfirst( $slug ) ) );
	}
	$wp_query              = new WP_Query();
	$wp_query->posts       = array( $page_obj );
	$wp_query->post_count  = 1;
	$wp_query->found_posts = 1;
	$post                  = $page_obj;
	do_action( 'wp_enqueue_scripts' );
	require $page_templates[ $slug ];
	exit;
}

// 9. Страница 404
$NVGSO_ROUTE['type']   = '404';
$NVGSO_ROUTE['status'] = 404;
http_response_code( 404 );
$wp_query = new WP_Query();
do_action( 'wp_enqueue_scripts' );
require '/theme/404.php';
exit;
