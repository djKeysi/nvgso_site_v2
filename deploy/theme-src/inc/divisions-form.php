<?php
/**
 * Удобная форма редактирования подразделений отряда.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'edit.php?post_type=division',
			'Форма подразделений',
			'Форма подразделений',
			'edit_posts',
			'nvgso-divisions-form',
			'nvgso_divisions_form_page'
		);

		add_options_page(
			'Подразделения',
			'Подразделения',
			'manage_options',
			'nvgso-divisions-settings',
			'nvgso_divisions_form_page'
		);
	}
);

function nvgso_divisions_form_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$divisions = get_posts(
		array(
			'post_type'      => 'division',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
		)
	);
	?>
	<div class="wrap">
		<h1>Подразделения отряда</h1>
		<p>Эти данные выводятся на странице «Структура» в блоке «Подразделения отряда».</p>
		<p class="description">Новое подразделение добавляется через пустую строку внизу. Стрелки ↑/↓ меняют порядок сразу. Если снять «Опубликовано», карточка исчезнет с сайта.</p>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Подразделения сохранены.</p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['order_updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Порядок подразделений обновлён.</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="nvgso_divisions_save">
			<?php wp_nonce_field( 'nvgso_divisions_save' ); ?>

			<table class="widefat striped" style="max-width:1500px">
				<thead>
					<tr>
						<th style="min-width:16rem">Название</th>
						<th style="width:6.5rem">Порядок</th>
						<th>Тип</th>
						<th>Город</th>
						<th style="min-width:14rem">Адрес</th>
						<th>Телефон</th>
						<th>Состав / отделения</th>
						<th style="min-width:18rem">Описание</th>
						<th>Опубликовано</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $divisions as $i => $division ) : ?>
						<?php
						$type    = (string) get_post_meta( $division->ID, '_nvgso_type', true );
						$city    = (string) get_post_meta( $division->ID, '_nvgso_city', true );
						$address = (string) get_post_meta( $division->ID, '_nvgso_address', true );
						$phone   = (string) get_post_meta( $division->ID, '_nvgso_phone', true );
						$units   = (string) get_post_meta( $division->ID, '_nvgso_units', true );
						$desc    = trim( wp_strip_all_tags( $division->post_content ) );
						?>
						<tr>
							<td>
								<input type="hidden" name="divisions[<?php echo (int) $i; ?>][id]" value="<?php echo (int) $division->ID; ?>">
								<input type="text" class="regular-text" name="divisions[<?php echo (int) $i; ?>][title]" value="<?php echo esc_attr( get_the_title( $division ) ); ?>" required>
							</td>
							<td>
								<div style="display:flex;align-items:center;gap:.25rem">
									<?php
									$up_url   = wp_nonce_url(
										admin_url( 'admin-post.php?action=nvgso_division_move&post=' . $division->ID . '&dir=up' ),
										'nvgso_division_move_' . $division->ID
									);
									$down_url = wp_nonce_url(
										admin_url( 'admin-post.php?action=nvgso_division_move&post=' . $division->ID . '&dir=down' ),
										'nvgso_division_move_' . $division->ID
									);
									?>
									<?php if ( $i > 0 ) : ?>
										<a class="button button-small" href="<?php echo esc_url( $up_url ); ?>" title="Выше" aria-label="Переместить выше">↑</a>
									<?php else : ?>
										<span class="button button-small disabled" aria-hidden="true">↑</span>
									<?php endif; ?>
									<?php if ( $i < count( $divisions ) - 1 ) : ?>
										<a class="button button-small" href="<?php echo esc_url( $down_url ); ?>" title="Ниже" aria-label="Переместить ниже">↓</a>
									<?php else : ?>
										<span class="button button-small disabled" aria-hidden="true">↓</span>
									<?php endif; ?>
									<span class="description"><?php echo (int) ( $i + 1 ); ?></span>
								</div>
							</td>
							<td><input type="text" class="small-text" name="divisions[<?php echo (int) $i; ?>][type]" value="<?php echo esc_attr( $type ); ?>" placeholder="штаб / взвод"></td>
							<td><input type="text" class="regular-text" name="divisions[<?php echo (int) $i; ?>][city]" value="<?php echo esc_attr( $city ); ?>" placeholder="НОВОКУЗНЕЦК"></td>
							<td><input type="text" class="regular-text" name="divisions[<?php echo (int) $i; ?>][address]" value="<?php echo esc_attr( $address ); ?>" placeholder="ул. Горноспасательная, 5"></td>
							<td><input type="text" class="regular-text" name="divisions[<?php echo (int) $i; ?>][phone]" value="<?php echo esc_attr( $phone ); ?>" placeholder="+7 (3843) 00-00-00"></td>
							<td><input type="text" class="regular-text" name="divisions[<?php echo (int) $i; ?>][units]" value="<?php echo esc_attr( $units ); ?>" placeholder="отделения взвода"></td>
							<td><textarea name="divisions[<?php echo (int) $i; ?>][description]" rows="3" class="large-text"><?php echo esc_textarea( $desc ); ?></textarea></td>
							<td><label><input type="checkbox" name="divisions[<?php echo (int) $i; ?>][published]" value="1"<?php checked( 'publish' === $division->post_status ); ?>> Да</label></td>
						</tr>
					<?php endforeach; ?>

					<?php $new = count( $divisions ); ?>
					<tr>
						<td>
							<input type="hidden" name="divisions[<?php echo (int) $new; ?>][id]" value="0">
							<input type="text" class="regular-text" name="divisions[<?php echo (int) $new; ?>][title]" value="" placeholder="Название нового подразделения">
						</td>
						<td><span class="description">в конец</span></td>
						<td><input type="text" class="small-text" name="divisions[<?php echo (int) $new; ?>][type]" value="" placeholder="взвод"></td>
						<td><input type="text" class="regular-text" name="divisions[<?php echo (int) $new; ?>][city]" value="" placeholder="НОВОКУЗНЕЦК"></td>
						<td><input type="text" class="regular-text" name="divisions[<?php echo (int) $new; ?>][address]" value="" placeholder="адрес"></td>
						<td><input type="text" class="regular-text" name="divisions[<?php echo (int) $new; ?>][phone]" value="" placeholder="+7 (3843) 00-00-00"></td>
						<td><input type="text" class="regular-text" name="divisions[<?php echo (int) $new; ?>][units]" value="" placeholder="отделения взвода"></td>
						<td><textarea name="divisions[<?php echo (int) $new; ?>][description]" rows="3" class="large-text" placeholder="Краткое описание"></textarea></td>
						<td><label><input type="checkbox" name="divisions[<?php echo (int) $new; ?>][published]" value="1" checked> Да</label></td>
					</tr>
				</tbody>
			</table>

			<p style="margin-top:1rem">
				<button type="submit" class="button button-primary">Сохранить</button>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=division' ) ); ?>">Все подразделения</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=division' ) ); ?>">Добавить через стандартную форму</a>
			</p>
		</form>
	</div>
	<?php
}

add_action(
	'admin_post_nvgso_divisions_save',
	function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Недостаточно прав.' );
		}
		check_admin_referer( 'nvgso_divisions_save' );

		$rows = isset( $_POST['divisions'] ) && is_array( $_POST['divisions'] ) ? wp_unslash( $_POST['divisions'] ) : array();
		global $wpdb;
		$max_order = (int) $wpdb->get_var( "SELECT MAX(menu_order) FROM {$wpdb->posts} WHERE post_type = 'division'" );

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$id          = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$title       = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
			$type        = isset( $row['type'] ) ? sanitize_text_field( $row['type'] ) : '';
			$city        = isset( $row['city'] ) ? sanitize_text_field( $row['city'] ) : '';
			$address     = isset( $row['address'] ) ? sanitize_text_field( $row['address'] ) : '';
			$phone       = isset( $row['phone'] ) ? sanitize_text_field( $row['phone'] ) : '';
			$units       = isset( $row['units'] ) ? sanitize_text_field( $row['units'] ) : '';
			$description = isset( $row['description'] ) ? sanitize_textarea_field( $row['description'] ) : '';
			$published   = ! empty( $row['published'] );
			$status      = $published ? 'publish' : 'draft';

			if ( '' === $title ) {
				continue;
			}

			if ( $id ) {
				$post = get_post( $id );
				if ( ! $post || 'division' !== $post->post_type ) {
					continue;
				}
				wp_update_post(
					array(
						'ID'           => $id,
						'post_title'   => $title,
						'post_content' => $description,
						'post_status'  => $status,
					)
				);
			} else {
				$id = wp_insert_post(
					array(
						'post_type'    => 'division',
						'post_title'   => $title,
						'post_content' => $description,
						'post_status'  => $status,
						'menu_order'   => ++$max_order,
					),
					true
				);
				if ( is_wp_error( $id ) ) {
					continue;
				}
			}

			update_post_meta( $id, '_nvgso_type', $type );
			update_post_meta( $id, '_nvgso_city', $city );
			update_post_meta( $id, '_nvgso_address', $address );
			update_post_meta( $id, '_nvgso_phone', $phone );
			update_post_meta( $id, '_nvgso_units', $units );
			update_post_meta( $id, '_nvgso_description', $description );
		}

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'edit.php?post_type=division&page=nvgso-divisions-form' );
		}
		wp_safe_redirect( add_query_arg( 'updated', '1', $back ) );
		exit;
	}
);

/**
 * Перемещение подразделения вверх/вниз.
 */
add_action(
	'admin_post_nvgso_division_move',
	function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Недостаточно прав.' );
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$dir     = isset( $_GET['dir'] ) ? sanitize_key( wp_unslash( $_GET['dir'] ) ) : '';
		if ( ! $post_id || ! in_array( $dir, array( 'up', 'down' ), true ) ) {
			wp_die( 'Некорректный запрос.' );
		}

		check_admin_referer( 'nvgso_division_move_' . $post_id );

		$post = get_post( $post_id );
		if ( ! $post || 'division' !== $post->post_type ) {
			wp_die( 'Подразделение не найдено.' );
		}

		$ids = get_posts(
			array(
				'post_type'      => 'division',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'date'       => 'DESC',
				),
				'fields'         => 'ids',
			)
		);

		$index = array_search( $post_id, $ids, true );
		if ( false !== $index ) {
			if ( 'up' === $dir && $index > 0 ) {
				$tmp               = $ids[ $index - 1 ];
				$ids[ $index - 1 ] = $ids[ $index ];
				$ids[ $index ]     = $tmp;
			} elseif ( 'down' === $dir && $index < count( $ids ) - 1 ) {
				$tmp               = $ids[ $index + 1 ];
				$ids[ $index + 1 ] = $ids[ $index ];
				$ids[ $index ]     = $tmp;
			}

			foreach ( $ids as $order => $id ) {
				wp_update_post(
					array(
						'ID'         => (int) $id,
						'menu_order' => (int) $order,
					)
				);
			}
		}

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'edit.php?post_type=division&page=nvgso-divisions-form' );
		}
		wp_safe_redirect( add_query_arg( 'order_updated', '1', $back ) );
		exit;
	}
);

add_action(
	'admin_notices',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-division' !== $screen->id ) {
			return;
		}
		$url = admin_url( 'edit.php?post_type=division&page=nvgso-divisions-form' );
		echo '<div class="notice notice-info is-dismissible"><p><strong>Подразделения:</strong> редактировать все поля и порядок удобнее в <a href="' . esc_url( $url ) . '">форме подразделений</a>.</p></div>';
	}
);
