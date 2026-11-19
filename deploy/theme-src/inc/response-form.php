<?php
/**
 * Редактирование блока «Порядок реагирования».
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function nvgso_default_response_steps() {
	return array(
		array(
			'number' => '01',
			'title'  => 'Получение сигнала',
			'text'   => 'Дежурный диспетчер принимает сообщение, уточняет место и характер аварии, оповещает командование и дежурное отделение.',
		),
		array(
			'number' => '02',
			'title'  => 'Выезд и развёртывание',
			'text'   => 'Отделение выезжает на предприятие, развёртывает оперативный штаб и устанавливает связь с руководством шахты.',
		),
		array(
			'number' => '03',
			'title'  => 'Разведка и спасение',
			'text'   => 'Горноспасатели проводят разведку выработок, определяют границы опасной зоны, выводят людей и оказывают первую помощь.',
		),
		array(
			'number' => '04',
			'title'  => 'Ликвидация аварии',
			'text'   => 'Совместно с предприятием и подразделениями МЧС выполняются работы по локализации и ликвидации аварии, проводится разбор действий.',
		),
	);
}

function nvgso_get_response_steps() {
	$saved = get_option( 'nvgso_response_steps', false );
	if ( false === $saved || ! is_array( $saved ) ) {
		return nvgso_default_response_steps();
	}

	$out = array();
	foreach ( $saved as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$title = isset( $item['title'] ) ? trim( (string) $item['title'] ) : '';
		if ( '' === $title ) {
			continue;
		}
		$out[] = array(
			'number' => isset( $item['number'] ) ? trim( (string) $item['number'] ) : '',
			'title'  => $title,
			'text'   => isset( $item['text'] ) ? trim( (string) $item['text'] ) : '',
		);
	}
	return $out;
}

add_action(
	'admin_menu',
	function () {
		add_options_page(
			'Порядок реагирования',
			'Порядок реагирования',
			'manage_options',
			'nvgso-response',
			'nvgso_response_form_page'
		);
	}
);

function nvgso_response_form_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$items = nvgso_get_response_steps();
	$count = count( $items );
	$s     = nvgso_settings();
	?>
	<div class="wrap">
		<h1>Порядок реагирования</h1>
		<p>Блок на странице <strong>«Структура»</strong> — от сигнала до спасения.</p>
		<p class="description">Порядок строк — порядок шагов на сайте. Стрелки ↑/↓ меняют порядок, затем нажмите «Сохранить».</p>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Порядок реагирования сохранён.</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="nvgso_response_save">
			<?php wp_nonce_field( 'nvgso_response_save' ); ?>

			<h2>Заголовок блока</h2>
			<table class="form-table" role="presentation" style="max-width:1000px">
				<tr><th><label for="response_eyebrow">Надзаголовок</label></th>
					<td><input class="regular-text" id="response_eyebrow" name="response_eyebrow" value="<?php echo esc_attr( $s['response_eyebrow'] ); ?>"></td></tr>
				<tr><th><label for="response_title">Заголовок</label></th>
					<td><input class="large-text" id="response_title" name="response_title" value="<?php echo esc_attr( $s['response_title'] ); ?>"></td></tr>
				<tr><th><label for="response_lead">Подзаголовок</label></th>
					<td><textarea class="large-text" rows="3" id="response_lead" name="response_lead"><?php echo esc_textarea( $s['response_lead'] ); ?></textarea></td></tr>
			</table>

			<h2>Шаги</h2>
			<table class="widefat striped" id="nvgso-response-table" style="max-width:1000px">
				<thead>
					<tr>
						<th style="width:7rem">Порядок</th>
						<th style="width:7rem">Номер</th>
						<th style="min-width:18rem">Заголовок шага</th>
						<th style="min-width:24rem">Описание</th>
						<th style="width:8rem">Удалить</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $i => $item ) : ?>
						<tr>
							<td>
								<button type="button" class="button button-small" data-move="up" aria-label="Выше">↑</button>
								<button type="button" class="button button-small" data-move="down" aria-label="Ниже">↓</button>
							</td>
							<td><input type="text" class="small-text" name="response[<?php echo (int) $i; ?>][number]" value="<?php echo esc_attr( $item['number'] ); ?>"></td>
							<td><input type="text" class="large-text" name="response[<?php echo (int) $i; ?>][title]" value="<?php echo esc_attr( $item['title'] ); ?>" required></td>
							<td><textarea class="large-text" rows="2" name="response[<?php echo (int) $i; ?>][text]"><?php echo esc_textarea( $item['text'] ); ?></textarea></td>
							<td><label><input type="checkbox" name="response[<?php echo (int) $i; ?>][delete]" value="1"> Удалить</label></td>
						</tr>
					<?php endforeach; ?>

					<tr>
						<td><span class="description">новый</span></td>
						<td><input type="text" class="small-text" name="response[<?php echo (int) $count; ?>][number]" value=""></td>
						<td><input type="text" class="large-text" name="response[<?php echo (int) $count; ?>][title]" value="" placeholder="Новый шаг"></td>
						<td><textarea class="large-text" rows="2" name="response[<?php echo (int) $count; ?>][text]"></textarea></td>
						<td></td>
					</tr>
				</tbody>
			</table>

			<p style="margin-top:1rem">
				<button type="submit" class="button button-primary">Сохранить</button>
				<a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=nvgso-settings' ) ); ?>">Настройки сайта</a>
				<a class="button" href="<?php echo esc_url( home_url( '/structure/' ) ); ?>" target="_blank" rel="noopener">Открыть страницу</a>
			</p>
		</form>
	</div>

	<script>
	(function () {
		var table = document.getElementById('nvgso-response-table');
		if (!table || !table.tBodies[0]) return;
		var body = table.tBodies[0];

		function renumber() {
			var rows = Array.prototype.slice.call(body.rows);
			rows.forEach(function (row, index) {
				Array.prototype.forEach.call(row.querySelectorAll('input, textarea'), function (input) {
					input.name = input.name.replace(/response\[\d+\]/, 'response[' + index + ']');
				});
				var up = row.querySelector('[data-move="up"]');
				var down = row.querySelector('[data-move="down"]');
				if (up) up.disabled = index === 0;
				if (down) down.disabled = index === rows.length - 1;
			});
		}

		table.addEventListener('click', function (event) {
			var button = event.target.closest('[data-move]');
			if (!button) return;
			var row = button.closest('tr');
			if (!row || !row.parentNode) return;
			if (button.dataset.move === 'up' && row.previousElementSibling) {
				body.insertBefore(row, row.previousElementSibling);
			}
			if (button.dataset.move === 'down' && row.nextElementSibling) {
				body.insertBefore(row.nextElementSibling, row);
			}
			renumber();
		});

		renumber();
	})();
	</script>
	<?php
}

add_action(
	'admin_post_nvgso_response_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Недостаточно прав.' );
		}
		check_admin_referer( 'nvgso_response_save' );

		$settings = get_option( 'nvgso_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		$settings['response_eyebrow'] = sanitize_text_field( wp_unslash( $_POST['response_eyebrow'] ?? '' ) );
		$settings['response_title']   = sanitize_text_field( wp_unslash( $_POST['response_title'] ?? '' ) );
		$settings['response_lead']    = sanitize_textarea_field( wp_unslash( $_POST['response_lead'] ?? '' ) );
		update_option( 'nvgso_settings', $settings );

		if ( isset( $_POST['response'] ) && is_array( $_POST['response'] ) ) {
			$rows = wp_unslash( $_POST['response'] );
			$out  = array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || ! empty( $row['delete'] ) ) {
					continue;
				}
				$title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
				if ( '' === $title ) {
					continue;
				}
				$out[] = array(
					'number' => isset( $row['number'] ) ? sanitize_text_field( $row['number'] ) : '',
					'title'  => $title,
					'text'   => isset( $row['text'] ) ? sanitize_textarea_field( $row['text'] ) : '',
				);
			}
			update_option( 'nvgso_response_steps', $out );
		}

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'options-general.php?page=nvgso-response' );
		}
		wp_safe_redirect( add_query_arg( 'updated', '1', $back ) );
		exit;
	}
);
