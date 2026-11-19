<?php
/**
 * Удобное редактирование зоны ответственности.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_options_page(
			'Зона ответственности',
			'Зона ответственности',
			'manage_options',
			'nvgso-zone',
			'nvgso_zone_form_page'
		);
	}
);

function nvgso_zone_form_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$items = nvgso_get_zone();
	$count = count( $items );
	$s     = nvgso_settings();
	?>
	<div class="wrap">
		<h1>Зона ответственности</h1>
		<p>Предприятия, которые выводятся на странице <strong>«Об отряде»</strong> в блоке «Зона ответственности».</p>
		<p class="description">Порядок строк — порядок карточек на сайте. Стрелки ↑/↓ меняют порядок, затем нажмите «Сохранить».</p>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Зона ответственности сохранена.</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="nvgso_zone_save">
			<?php wp_nonce_field( 'nvgso_zone_save' ); ?>

			<h2>Заголовок блока</h2>
			<table class="form-table" role="presentation" style="max-width:1000px">
				<tr><th><label for="zone_eyebrow">Надзаголовок</label></th>
					<td><input class="regular-text" id="zone_eyebrow" name="zone_eyebrow" value="<?php echo esc_attr( $s['zone_eyebrow'] ); ?>"></td></tr>
				<tr><th><label for="zone_title">Заголовок</label></th>
					<td><input class="large-text" id="zone_title" name="zone_title" value="<?php echo esc_attr( $s['zone_title'] ); ?>"></td></tr>
				<tr><th><label for="zone_lead">Подзаголовок</label></th>
					<td><textarea class="large-text" rows="3" id="zone_lead" name="zone_lead"><?php echo esc_textarea( $s['zone_lead'] ); ?></textarea></td></tr>
			</table>

			<table class="widefat striped" id="nvgso-zone-table" style="max-width:1000px">
				<thead>
					<tr>
						<th style="width:7rem">Порядок</th>
						<th style="min-width:20rem">Предприятие</th>
						<th style="min-width:24rem">Примечание</th>
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
							<td><input type="text" class="large-text" name="zone[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $item['name'] ?? '' ); ?>" required></td>
							<td><input type="text" class="large-text" name="zone[<?php echo (int) $i; ?>][note]" value="<?php echo esc_attr( $item['note'] ?? '' ); ?>"></td>
							<td><label><input type="checkbox" name="zone[<?php echo (int) $i; ?>][delete]" value="1"> Удалить</label></td>
						</tr>
					<?php endforeach; ?>

					<tr>
						<td><span class="description">новая</span></td>
						<td><input type="text" class="large-text" name="zone[<?php echo (int) $count; ?>][name]" value="" placeholder="Шахта «...»"></td>
						<td><input type="text" class="large-text" name="zone[<?php echo (int) $count; ?>][note]" value="" placeholder="Аварийно-спасательные работы"></td>
						<td></td>
					</tr>
				</tbody>
			</table>

			<p style="margin-top:1rem">
				<button type="submit" class="button button-primary">Сохранить</button>
				<a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=nvgso-settings' ) ); ?>">Настройки сайта</a>
				<a class="button" href="<?php echo esc_url( home_url( '/about/' ) ); ?>" target="_blank" rel="noopener">Открыть страницу</a>
			</p>
		</form>
	</div>

	<script>
	(function () {
		var table = document.getElementById('nvgso-zone-table');
		if (!table || !table.tBodies[0]) return;
		var body = table.tBodies[0];

		function renumber() {
			var rows = Array.prototype.slice.call(body.rows);
			rows.forEach(function (row, index) {
				Array.prototype.forEach.call(row.querySelectorAll('input'), function (input) {
					input.name = input.name.replace(/zone\[\d+\]/, 'zone[' + index + ']');
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
	'admin_post_nvgso_zone_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Недостаточно прав.' );
		}
		check_admin_referer( 'nvgso_zone_save' );

		$settings = get_option( 'nvgso_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		$settings['zone_eyebrow'] = sanitize_text_field( wp_unslash( $_POST['zone_eyebrow'] ?? '' ) );
		$settings['zone_title']   = sanitize_text_field( wp_unslash( $_POST['zone_title'] ?? '' ) );
		$settings['zone_lead']    = sanitize_textarea_field( wp_unslash( $_POST['zone_lead'] ?? '' ) );
		update_option( 'nvgso_settings', $settings );

		if ( isset( $_POST['zone'] ) && is_array( $_POST['zone'] ) ) {
			$rows = wp_unslash( $_POST['zone'] );
			$out  = array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || ! empty( $row['delete'] ) ) {
					continue;
				}
				$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
				$note = isset( $row['note'] ) ? sanitize_text_field( $row['note'] ) : '';
				if ( '' === $name ) {
					continue;
				}
				$out[] = array(
					'name' => $name,
					'note' => $note,
				);
			}
			update_option( 'nvgso_zone', $out );
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'options-general.php?page=nvgso-zone' );
		}
		wp_safe_redirect( add_query_arg( 'updated', '1', $back ) );
		exit;
	}
);
