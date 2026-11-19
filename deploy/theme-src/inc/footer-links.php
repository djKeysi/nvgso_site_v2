<?php
/**
 * Редактирование ссылок блока «Документы» в подвале.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function nvgso_default_footer_docs() {
	return array(
		array( 'label' => 'Политика обработки ПДн', 'url' => '/privacy-policy/' ),
		array( 'label' => 'Политика компании', 'url' => '/company-policy/' ),
		array( 'label' => 'Политика использования cookie', 'url' => '/cookie-policy/' ),
		array( 'label' => 'Устав и положения', 'url' => '/documents/' ),
		array( 'label' => 'Лицензии и разрешения', 'url' => '/documents/' ),
		array( 'label' => 'Памятки и инструкции', 'url' => '/documents/' ),
		array( 'label' => 'Настройки cookie', 'url' => '#cookie-settings' ),
	);
}

function nvgso_get_footer_docs() {
	$saved = get_option( 'nvgso_footer_docs', false );
	if ( false === $saved || ! is_array( $saved ) ) {
		return nvgso_default_footer_docs();
	}

	$out = array();
	foreach ( $saved as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$label = isset( $item['label'] ) ? trim( (string) $item['label'] ) : '';
		$url   = isset( $item['url'] ) ? trim( (string) $item['url'] ) : '';
		if ( '' === $label ) {
			continue;
		}
		$out[] = array(
			'label' => $label,
			'url'   => $url,
		);
	}
	return $out;
}

add_action(
	'admin_menu',
	function () {
		add_options_page(
			'Ссылки в подвале',
			'Ссылки в подвале',
			'manage_options',
			'nvgso-footer-links',
			'nvgso_footer_links_page'
		);
	}
);

function nvgso_footer_links_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$links = nvgso_get_footer_docs();
	$count = count( $links );
	?>
	<div class="wrap">
		<h1>Ссылки в подвале</h1>
		<p>Блок <strong>«Документы»</strong> в подвале сайта. Порядок строк — порядок ссылок; используйте стрелки ↑/↓, затем нажмите «Сохранить».</p>
		<p class="description">Специальная ссылка <code>#cookie-settings</code> выводит кнопку «Настройки cookie» вместо обычной ссылки.</p>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Ссылки сохранены.</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="nvgso_footer_links_save">
			<?php wp_nonce_field( 'nvgso_footer_links_save' ); ?>

			<table class="widefat striped" id="nvgso-footer-links" style="max-width:1000px">
				<thead>
					<tr>
						<th style="width:7rem">Порядок</th>
						<th style="min-width:20rem">Название</th>
						<th style="min-width:24rem">Ссылка</th>
						<th style="width:8rem">Удалить</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $links as $i => $link ) : ?>
						<tr>
							<td>
								<button type="button" class="button button-small" data-move="up" aria-label="Выше">↑</button>
								<button type="button" class="button button-small" data-move="down" aria-label="Ниже">↓</button>
							</td>
							<td><input type="text" class="large-text" name="links[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $link['label'] ); ?>" required></td>
							<td><input type="text" class="large-text" name="links[<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $link['url'] ); ?>" placeholder="/privacy-policy/ или https://..."></td>
							<td><label><input type="checkbox" name="links[<?php echo (int) $i; ?>][delete]" value="1"> Удалить</label></td>
						</tr>
					<?php endforeach; ?>

					<tr>
						<td><span class="description">новая</span></td>
						<td><input type="text" class="large-text" name="links[<?php echo (int) $count; ?>][label]" value="" placeholder="Новая ссылка"></td>
						<td><input type="text" class="large-text" name="links[<?php echo (int) $count; ?>][url]" value="" placeholder="/documents/"></td>
						<td></td>
					</tr>
				</tbody>
			</table>

			<p style="margin-top:1rem">
				<button type="submit" class="button button-primary">Сохранить</button>
				<a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=nvgso-settings' ) ); ?>">Настройки сайта</a>
			</p>
		</form>
	</div>

	<script>
	(function () {
		var table = document.getElementById('nvgso-footer-links');
		if (!table || !table.tBodies[0]) return;
		var body = table.tBodies[0];

		function renumber() {
			var rows = Array.prototype.slice.call(body.rows);
			rows.forEach(function (row, index) {
				Array.prototype.forEach.call(row.querySelectorAll('input'), function (input) {
					input.name = input.name.replace(/links\[\d+\]/, 'links[' + index + ']');
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
	'admin_post_nvgso_footer_links_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Недостаточно прав.' );
		}
		check_admin_referer( 'nvgso_footer_links_save' );

		$rows = isset( $_POST['links'] ) && is_array( $_POST['links'] ) ? wp_unslash( $_POST['links'] ) : array();
		$out  = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( ! empty( $row['delete'] ) ) {
				continue;
			}

			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			$url   = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';
			if ( '' === $label ) {
				continue;
			}

			if ( '#cookie-settings' === $url ) {
				$url = '#cookie-settings';
			} else {
				$url = esc_url_raw( $url );
			}
			if ( '' === $url ) {
				continue;
			}

			$out[] = array(
				'label' => $label,
				'url'   => $url,
			);
		}

		update_option( 'nvgso_footer_docs', $out );
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'options-general.php?page=nvgso-footer-links' );
		}
		wp_safe_redirect( add_query_arg( 'updated', '1', $back ) );
		exit;
	}
);
