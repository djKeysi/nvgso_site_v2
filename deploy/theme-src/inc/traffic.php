<?php
/**
 * Локальная статистика посещаемости: без внешних сервисов и cookies.
 * Считает только посетителей, согласившихся на аналитические cookie.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function nvgso_traffic_table() {
	global $wpdb;
	return $wpdb->prefix . 'nvgso_visits';
}

add_action(
	'init',
	function () {
		global $wpdb;
		if ( '1.1' !== get_option( 'nvgso_traffic_db_version' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$table   = nvgso_traffic_table();
			$charset = $wpdb->get_charset_collate();
			dbDelta(
				"CREATE TABLE {$table} (
					id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					visited_at datetime NOT NULL,
					visit_date date NOT NULL,
					path varchar(255) NOT NULL,
					post_id bigint(20) unsigned NOT NULL DEFAULT 0,
					ip_hash char(32) NOT NULL,
					referer varchar(255) NOT NULL DEFAULT '',
					PRIMARY KEY  (id),
					KEY visit_date (visit_date),
					KEY post_id (post_id)
				) {$charset};"
			);
			update_option( 'nvgso_traffic_db_version', '1.1' );
		}

		if ( ! wp_next_scheduled( 'nvgso_traffic_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'nvgso_traffic_cleanup' );
		}
	},
	5
);

add_action(
	'nvgso_traffic_cleanup',
	function () {
		global $wpdb;
		$table = nvgso_traffic_table();
		$wpdb->query( "DELETE FROM {$table} WHERE visited_at < DATE_SUB(NOW(), INTERVAL 365 DAY)" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
);

/**
 * Запись просмотра из JS-beacon.
 */
add_action( 'wp_ajax_nvgso_track', 'nvgso_traffic_record' );
add_action( 'wp_ajax_nopriv_nvgso_track', 'nvgso_traffic_record' );

function nvgso_traffic_record() {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_send_json_error( null, 405 );
	}
	if ( is_user_logged_in() ) {
		wp_send_json_success();
	}
	if ( '1' === ( $_SERVER['HTTP_DNT'] ?? '' ) ) {
		wp_send_json_success();
	}

	$ua = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
	if ( preg_match( '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|semrush|ahrefs|mj12bot|bytespider|petalbot/i', $ua ) ) {
		wp_send_json_success();
	}

	$host   = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	$origin = (string) ( $_SERVER['HTTP_ORIGIN'] ?? '' );
	if ( $origin && wp_parse_url( $origin, PHP_URL_HOST ) !== $host ) {
		wp_send_json_error( null, 403 );
	}

	$path = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '/';
	$path = (string) wp_parse_url( $path, PHP_URL_PATH );
	$path = '/' . ltrim( $path, '/' );
	if ( '/' !== $path && strlen( $path ) > 250 ) {
		$path = substr( $path, 0, 250 );
	}
	if ( preg_match( '#^/(wp-admin|wp-login|wp-json|wp-content|xmlrpc|feed)#i', $path ) ) {
		wp_send_json_success();
	}

	$ref = isset( $_POST['ref'] ) ? esc_url_raw( wp_unslash( $_POST['ref'] ) ) : '';
	$ref = substr( $ref, 0, 250 );

	$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	if ( '' === $ip ) {
		wp_send_json_success();
	}
	$hash = md5( $ip . '|' . wp_salt( 'auth' ) );

	$lock = 'nvgso_track_' . md5( $hash . $path );
	if ( get_transient( $lock ) ) {
		wp_send_json_success();
	}
	set_transient( $lock, 1, 30 );

	global $wpdb;
	$wpdb->insert(
		nvgso_traffic_table(),
		array(
			'visited_at' => current_time( 'mysql' ),
			'visit_date' => current_time( 'Y-m-d' ),
			'path'       => $path,
			'post_id'    => (int) url_to_postid( home_url( $path ) ),
			'ip_hash'    => $hash,
			'referer'    => $ref,
		),
		array( '%s', '%s', '%s', '%d', '%s', '%s' )
	);
	wp_send_json_success();
}

function nvgso_traffic_totals( $days ) {
	global $wpdb;
	$table = nvgso_traffic_table();
	$from  = current_datetime()->modify( '-' . absint( $days ) . ' days' )->format( 'Y-m-d' );
	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT COUNT(*) AS views, COUNT(DISTINCT ip_hash) AS visitors FROM {$table} WHERE visit_date >= %s",
			$from
		),
		ARRAY_A
	);
}

function nvgso_traffic_daily( $days = 14 ) {
	global $wpdb;
	$table = nvgso_traffic_table();
	$from  = current_datetime()->modify( '-' . ( absint( $days ) - 1 ) . ' days' )->format( 'Y-m-d' );
	$rows  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT visit_date, COUNT(*) AS views, COUNT(DISTINCT ip_hash) AS visitors FROM {$table} WHERE visit_date >= %s GROUP BY visit_date",
			$from
		),
		ARRAY_A
	);
	$map = array();
	foreach ( $rows as $row ) {
		$map[ $row['visit_date'] ] = $row;
	}

	$out = array();
	for ( $i = absint( $days ) - 1; $i >= 0; $i-- ) {
		$date  = current_datetime()->modify( "-{$i} days" )->format( 'Y-m-d' );
		$out[] = array(
			'date'     => $date,
			'views'    => isset( $map[ $date ] ) ? (int) $map[ $date ]['views'] : 0,
			'visitors' => isset( $map[ $date ] ) ? (int) $map[ $date ]['visitors'] : 0,
		);
	}
	return $out;
}

function nvgso_traffic_top_pages( $days = 30, $limit = 10 ) {
	global $wpdb;
	$table = nvgso_traffic_table();
	$from  = current_datetime()->modify( '-' . absint( $days ) . ' days' )->format( 'Y-m-d' );
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT path, MAX(post_id) AS post_id, COUNT(*) AS views FROM {$table} WHERE visit_date >= %s GROUP BY path ORDER BY views DESC LIMIT %d",
			$from,
			absint( $limit )
		),
		ARRAY_A
	);
}

function nvgso_traffic_top_refs( $days = 30, $limit = 10 ) {
	global $wpdb;
	$table = nvgso_traffic_table();
	$from  = current_datetime()->modify( '-' . absint( $days ) . ' days' )->format( 'Y-m-d' );
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT referer, COUNT(*) AS views FROM {$table} WHERE visit_date >= %s AND referer <> '' GROUP BY referer ORDER BY views DESC LIMIT %d",
			$from,
			absint( $limit )
		),
		ARRAY_A
	);
}

add_action(
	'wp_dashboard_setup',
	function () {
		if ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget( 'nvgso_traffic', 'Посещаемость сайта', 'nvgso_traffic_widget' );
		}
	}
);

add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'index.php',
			'Статистика сайта',
			'Статистика сайта',
			'manage_options',
			'nvgso-stats',
			'nvgso_traffic_page'
		);
	}
);

function nvgso_traffic_widget() {
	nvgso_render_traffic( true );
}

function nvgso_traffic_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap nvgso-traffic-page">
		<h1>Статистика посещаемости</h1>
		<p>Локальная статистика без внешних сервисов. Учитываются просмотры посетителей, согласившихся на аналитические cookie.</p>
		<?php nvgso_render_traffic( false ); ?>
	</div>
	<?php
}

function nvgso_render_traffic( $compact = true ) {
	$today = nvgso_traffic_totals( 0 );
	$week  = nvgso_traffic_totals( 7 );
	$month = nvgso_traffic_totals( 30 );
	$daily = nvgso_traffic_daily( $compact ? 14 : 30 );
	$top   = nvgso_traffic_top_pages( 30, $compact ? 5 : 15 );
	$refs  = $compact ? array() : nvgso_traffic_top_refs( 30, 10 );
	$max   = 1;
	foreach ( $daily as $day ) {
		$max = max( $max, (int) $day['views'] );
	}
	?>
	<div class="nvgso-traffic">
		<div class="nvgso-traffic__cards">
			<div class="nvgso-traffic__card"><span>Сегодня</span><strong><?php echo esc_html( number_format_i18n( (int) $today['views'] ) ); ?></strong><small>просмотров</small></div>
			<div class="nvgso-traffic__card"><span>7 дней</span><strong><?php echo esc_html( number_format_i18n( (int) $week['views'] ) ); ?></strong><small><?php echo esc_html( number_format_i18n( (int) $week['visitors'] ) ); ?> посетителей</small></div>
			<div class="nvgso-traffic__card"><span>30 дней</span><strong><?php echo esc_html( number_format_i18n( (int) $month['views'] ) ); ?></strong><small><?php echo esc_html( number_format_i18n( (int) $month['visitors'] ) ); ?> посетителей</small></div>
			<div class="nvgso-traffic__card"><span>Уникальных за 30 дней</span><strong><?php echo esc_html( number_format_i18n( (int) $month['visitors'] ) ); ?></strong><small>по хешу IP</small></div>
		</div>

		<?php if ( ! (int) $month['views'] ) : ?>
			<div class="nvgso-traffic__empty">
				<strong>Пока нет данных.</strong>
				Счётчик включится после того, как посетитель примет аналитические cookie.
			</div>
		<?php else : ?>
			<h2 class="nvgso-traffic__title">Просмотры<?php echo $compact ? ' за 14 дней' : ' за 30 дней'; ?></h2>
			<div class="nvgso-traffic__chart" role="img" aria-label="График просмотров">
				<?php foreach ( $daily as $day ) : ?>
					<?php
					$height = (int) $day['views'] ? max( 4, round( ( (int) $day['views'] / $max ) * 100 ) ) : 2;
					$label  = date_i18n( 'j.m', strtotime( $day['date'] ) );
					?>
					<div class="nvgso-traffic__bar-wrap" title="<?php echo esc_attr( $label . ': ' . $day['views'] ); ?>">
						<div class="nvgso-traffic__bar" style="height:<?php echo (int) $height; ?>%"></div>
						<span><?php echo esc_html( $label ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<h2 class="nvgso-traffic__title">Популярные страницы</h2>
			<table class="widefat striped nvgso-traffic__table">
				<thead><tr><th>Страница</th><th style="width:7rem">Просмотры</th></tr></thead>
				<tbody>
					<?php foreach ( $top as $row ) : ?>
						<?php
						$title = $row['post_id'] ? get_the_title( (int) $row['post_id'] ) : '';
						$title = $title ? $title : $row['path'];
						?>
						<tr><td><a href="<?php echo esc_url( home_url( $row['path'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $title ); ?></a></td><td><?php echo esc_html( number_format_i18n( (int) $row['views'] ) ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( ! $compact && $refs ) : ?>
			<h2 class="nvgso-traffic__title">Источники переходов</h2>
			<table class="widefat striped nvgso-traffic__table">
				<thead><tr><th>Источник</th><th style="width:7rem">Переходы</th></tr></thead>
				<tbody>
					<?php foreach ( $refs as $row ) : ?>
						<tr><td><?php echo esc_html( $row['referer'] ); ?></td><td><?php echo esc_html( number_format_i18n( (int) $row['views'] ) ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( $compact ) : ?>
			<p class="nvgso-traffic__more"><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'index.php?page=nvgso-stats' ) ); ?>">Подробная статистика</a></p>
		<?php endif; ?>
	</div>
	<?php
}

add_action(
	'admin_head',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'dashboard_page_nvgso-stats' ), true ) ) {
			return;
		}
		?>
		<style>
			.nvgso-traffic__cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:8px 0 18px}
			.nvgso-traffic__card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 16px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
			.nvgso-traffic__card span{display:block;color:#646970;font-size:12px;margin-bottom:4px}
			.nvgso-traffic__card strong{display:block;font-size:26px;line-height:1.1;color:#1d2327}
			.nvgso-traffic__card small{display:block;color:#646970;margin-top:4px}
			.nvgso-traffic__title{font-size:15px;margin:20px 0 10px}
			.nvgso-traffic__chart{display:flex;align-items:flex-end;gap:4px;height:130px;padding:8px 0 0;border-bottom:1px solid #dcdcde}
			.nvgso-traffic__bar-wrap{flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;height:100%;min-width:0}
			.nvgso-traffic__bar{width:100%;max-width:28px;background:linear-gradient(180deg,#c9a44c,#8a6a24);border-radius:3px 3px 0 0;min-height:2px}
			.nvgso-traffic__bar-wrap span{font-size:9px;color:#646970;margin-top:4px;white-space:nowrap;transform:rotate(-45deg);transform-origin:center;height:16px}
			.nvgso-traffic__table{margin-top:4px;max-width:900px}
			.nvgso-traffic__empty{background:#fff;border:1px solid #dcdcde;border-left:4px solid #c9a44c;padding:14px 16px;border-radius:6px;max-width:720px}
			.nvgso-traffic__more{margin-top:14px}
			@media(max-width:960px){.nvgso-traffic__cards{grid-template-columns:repeat(2,minmax(0,1fr))}}
		</style>
		<?php
	}
);
