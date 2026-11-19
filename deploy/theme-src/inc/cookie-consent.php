<?php
/**
 * Cookie-согласие: журнал согласий в MariaDB, AJAX-запись,
 * страница просмотра и экспорт CSV.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function nvgso_consent_table() {
	global $wpdb;
	return $wpdb->prefix . 'nvgso_consents';
}

function nvgso_consent_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = nvgso_consent_table();
	$collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		created_at DATETIME NOT NULL,
		consent_id VARCHAR(64) NOT NULL,
		action VARCHAR(20) NOT NULL,
		categories TEXT NOT NULL,
		page_url VARCHAR(255) NOT NULL DEFAULT '',
		ip_hash CHAR(64) NOT NULL DEFAULT '',
		ua_hash CHAR(64) NOT NULL DEFAULT '',
		policy_version VARCHAR(20) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY created_at (created_at),
		KEY action (action),
		KEY consent_id (consent_id)
	) {$collate};";

	dbDelta( $sql );
	update_option( 'nvgso_consent_db_version', '1.0' );
}
add_action( 'after_switch_theme', 'nvgso_consent_install' );

add_action(
	'init',
	function () {
		if ( '1.0' !== get_option( 'nvgso_consent_db_version' ) ) {
			nvgso_consent_install();
		}
	},
	5
);

/**
 * Запись согласия.
 */
function nvgso_consent_record() {
	check_ajax_referer( 'nvgso_consent', 'nonce' );

	$payload = isset( $_POST['consent'] ) ? json_decode( wp_unslash( $_POST['consent'] ), true ) : null;
	if ( ! is_array( $payload ) ) {
		wp_send_json_error( array( 'message' => 'Некорректные данные' ), 400 );
	}

	$allowed = array( 'accept_all', 'necessary', 'custom' );
	$action  = isset( $payload['action'] ) && in_array( $payload['action'], $allowed, true ) ? $payload['action'] : 'custom';

	$categories = array(
		'functional' => ! empty( $payload['categories']['functional'] ),
		'analytics'  => ! empty( $payload['categories']['analytics'] ),
		'marketing'  => ! empty( $payload['categories']['marketing'] ),
	);

	$consent_id = isset( $payload['consent_id'] ) ? preg_replace( '/[^a-zA-Z0-9\-]/', '', (string) $payload['consent_id'] ) : '';
	if ( ! $consent_id ) {
		$consent_id = wp_generate_uuid4();
	}

	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';

	// Простое ограничение частоты: не более 30 записей в минуту с одного IP.
	$rate_key = 'nvgso_consent_' . md5( $ip );
	$count    = (int) get_transient( $rate_key );
	if ( $count > 30 ) {
		wp_send_json_error( array( 'message' => 'Слишком много запросов' ), 429 );
	}
	set_transient( $rate_key, $count + 1, MINUTE_IN_SECONDS );

	global $wpdb;
	$wpdb->insert(
		nvgso_consent_table(),
		array(
			'created_at'     => current_time( 'mysql' ),
			'consent_id'     => $consent_id,
			'action'         => $action,
			'categories'     => wp_json_encode( $categories ),
			'page_url'       => isset( $payload['page'] ) ? esc_url_raw( substr( (string) $payload['page'], 0, 255 ) ) : '',
			'ip_hash'        => hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ),
			'ua_hash'        => hash_hmac( 'sha256', $ua, wp_salt( 'auth' ) ),
			'policy_version' => '1.0',
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	wp_send_json_success(
		array(
			'id'         => (int) $wpdb->insert_id,
			'consent_id' => $consent_id,
		)
	);
}
add_action( 'wp_ajax_nvgso_consent', 'nvgso_consent_record' );
add_action( 'wp_ajax_nopriv_nvgso_consent', 'nvgso_consent_record' );

/**
 * Страница журнала согласий.
 */
add_action(
	'admin_menu',
	function () {
		add_options_page(
			'Cookie-согласия',
			'Cookie-согласия',
			'manage_options',
			'nvgso-consents',
			'nvgso_consents_page'
		);
	}
);

function nvgso_consents_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	$table = nvgso_consent_table();
	$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 200" );
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=nvgso_export_consents' ), 'nvgso_export_consents' );
	?>
	<div class="wrap">
		<h1>Журнал cookie-согласий</h1>
		<p>Всего записей: <strong><?php echo (int) $total; ?></strong>. Показаны последние 200.
			Записываются обезличенные данные: хеш IP и User-Agent, категории согласия, адрес страницы и время.</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( $export_url ); ?>">Экспорт CSV</a>
			<button type="button" class="button" id="nvgso-consent-reset">Сбросить моё согласие</button>
		</p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th>ID</th><th>Дата</th><th>Действие</th><th>Категории</th><th>Страница</th><th>Версия</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="6">Записей пока нет.</td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo (int) $row->id; ?></td>
						<td><?php echo esc_html( $row->created_at ); ?></td>
						<td><?php echo esc_html( $row->action ); ?></td>
						<td><code><?php echo esc_html( $row->categories ); ?></code></td>
						<td><?php echo esc_html( $row->page_url ); ?></td>
						<td><?php echo esc_html( $row->policy_version ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<script>
		document.getElementById('nvgso-consent-reset')?.addEventListener('click', function () {
			document.cookie = 'nvgso_consent=; Max-Age=0; path=/; SameSite=Lax';
			alert('Локальное согласие сброшено. Обновите сайт — баннер появится снова.');
		});
		</script>
	</div>
	<?php
}

/**
 * Экспорт CSV.
 */
add_action(
	'admin_post_nvgso_export_consents',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Недостаточно прав' );
		}
		check_admin_referer( 'nvgso_export_consents' );

		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . nvgso_consent_table() . ' ORDER BY id DESC', ARRAY_A );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="nvgso-consents-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'ID', 'Дата', 'consent_id', 'Действие', 'Категории', 'Страница', 'Хеш IP', 'Хеш UA', 'Версия политики' ) );
		foreach ( $rows as $row ) {
			fputcsv(
				$out,
				array(
					$row['id'],
					$row['created_at'],
					$row['consent_id'],
					$row['action'],
					$row['categories'],
					$row['page_url'],
					$row['ip_hash'],
					$row['ua_hash'],
					$row['policy_version'],
				)
			);
		}
		fclose( $out );
		exit;
	}
);
