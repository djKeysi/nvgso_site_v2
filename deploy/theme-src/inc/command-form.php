<?php
/**
 * Единая форма заполнения командования отряда.
 * Значения хранятся в записях staff_contact и выводятся в блоке «Руководство отряда».
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'edit.php?post_type=staff_contact',
			'Форма заполнения',
			'Форма заполнения',
			'manage_options',
			'nvgso-command-form',
			'nvgso_command_form_page'
		);

		add_options_page(
			'Командование',
			'Командование',
			'manage_options',
			'nvgso-command-settings',
			'nvgso_command_form_page'
		);
	}
);

function nvgso_command_form_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$posts = get_posts(
		array(
			'post_type'      => 'staff_contact',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'menu_order date',
			'order'          => 'ASC',
		)
	);

	$fields = array(
		'title'     => 'Должность',
		'name'      => 'ФИО',
		'rank'      => 'Звание / должность по штату',
		'phone'     => 'Телефон',
		'reception' => 'Приём / режим',
		'email'     => 'E-mail',
		'order'     => 'Порядок',
	);
	?>
	<div class="wrap">
		<h1>Командование отряда</h1>
		<p>Эти данные выводятся на странице <strong>«О филиале»</strong> в блоке <strong>«Руководство отряда»</strong>.</p>
		<p class="description">«Порядок»: 0 — командир отряда, 1 — заместитель, далее по возрастанию. Пустая строка внизу позволяет добавить нового сотрудника.</p>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Данные командования сохранены.</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="nvgso_command_save">
			<?php wp_nonce_field( 'nvgso_command_save' ); ?>

			<table class="widefat striped" style="max-width:1200px">
				<thead>
					<tr>
						<?php foreach ( $fields as $label ) : ?>
							<th><?php echo esc_html( $label ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $posts as $i => $post ) : ?>
						<?php
						$row = array(
							'title'     => get_the_title( $post ),
							'name'      => (string) get_post_meta( $post->ID, '_nvgso_name', true ),
							'rank'      => (string) get_post_meta( $post->ID, '_nvgso_rank', true ),
							'phone'     => (string) get_post_meta( $post->ID, '_nvgso_phone', true ),
							'reception' => (string) get_post_meta( $post->ID, '_nvgso_reception', true ),
							'email'     => (string) get_post_meta( $post->ID, '_nvgso_email', true ),
							'order'     => (int) $post->menu_order,
						);
						?>
						<tr>
							<td>
								<input type="hidden" name="staff[<?php echo (int) $i; ?>][id]" value="<?php echo (int) $post->ID; ?>">
								<input type="text" class="regular-text" name="staff[<?php echo (int) $i; ?>][title]" value="<?php echo esc_attr( $row['title'] ); ?>" required>
							</td>
							<td><input type="text" class="regular-text" name="staff[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $row['name'] ); ?>" placeholder="Фамилия И. О."></td>
							<td><input type="text" class="regular-text" name="staff[<?php echo (int) $i; ?>][rank]" value="<?php echo esc_attr( $row['rank'] ); ?>" placeholder="полковник внутренней службы"></td>
							<td><input type="text" class="regular-text" name="staff[<?php echo (int) $i; ?>][phone]" value="<?php echo esc_attr( $row['phone'] ); ?>" placeholder="+7 (3843) 00-00-00"></td>
							<td><input type="text" class="regular-text" name="staff[<?php echo (int) $i; ?>][reception]" value="<?php echo esc_attr( $row['reception'] ); ?>" placeholder="Приёмная — пн–пт, 08:30–17:30"></td>
							<td><input type="email" class="regular-text" name="staff[<?php echo (int) $i; ?>][email]" value="<?php echo esc_attr( $row['email'] ); ?>"></td>
							<td><input type="number" class="small-text" min="0" step="1" name="staff[<?php echo (int) $i; ?>][order]" value="<?php echo (int) $row['order']; ?>"></td>
						</tr>
					<?php endforeach; ?>

					<?php $new = count( $posts ); ?>
					<tr>
						<td>
							<input type="hidden" name="staff[<?php echo (int) $new; ?>][id]" value="0">
							<input type="text" class="regular-text" name="staff[<?php echo (int) $new; ?>][title]" value="" placeholder="Новая должность">
						</td>
						<td><input type="text" class="regular-text" name="staff[<?php echo (int) $new; ?>][name]" value="" placeholder="Фамилия И. О."></td>
						<td><input type="text" class="regular-text" name="staff[<?php echo (int) $new; ?>][rank]" value="" placeholder="звание / должность"></td>
						<td><input type="text" class="regular-text" name="staff[<?php echo (int) $new; ?>][phone]" value="" placeholder="+7 (3843) 00-00-00"></td>
						<td><input type="text" class="regular-text" name="staff[<?php echo (int) $new; ?>][reception]" value="" placeholder="Приём / режим"></td>
						<td><input type="email" class="regular-text" name="staff[<?php echo (int) $new; ?>][email]" value=""></td>
						<td><input type="number" class="small-text" min="0" step="1" name="staff[<?php echo (int) $new; ?>][order]" value="<?php echo (int) $new; ?>"></td>
					</tr>
				</tbody>
			</table>

			<p style="margin-top:1rem">
				<button type="submit" class="button button-primary">Сохранить</button>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=staff_contact' ) ); ?>">Все сотрудники</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=staff_contact' ) ); ?>">Добавить через стандартную форму</a>
			</p>
		</form>
	</div>
	<?php
}

add_action(
	'admin_post_nvgso_command_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Недостаточно прав.' );
		}
		check_admin_referer( 'nvgso_command_save' );

		$rows = isset( $_POST['staff'] ) && is_array( $_POST['staff'] ) ? wp_unslash( $_POST['staff'] ) : array();
		$i    = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$id        = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$title     = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
			$name      = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			$rank      = isset( $row['rank'] ) ? sanitize_text_field( $row['rank'] ) : '';
			$phone     = isset( $row['phone'] ) ? sanitize_text_field( $row['phone'] ) : '';
			$reception = isset( $row['reception'] ) ? sanitize_text_field( $row['reception'] ) : '';
			$email     = isset( $row['email'] ) ? sanitize_email( $row['email'] ) : '';
			$order     = isset( $row['order'] ) ? max( 0, (int) $row['order'] ) : $i;

			if ( ! $id && '' === $title ) {
				continue;
			}

			if ( $id ) {
				$post = get_post( $id );
				if ( ! $post || 'staff_contact' !== $post->post_type ) {
					continue;
				}
				$post_title = '' !== $title ? $title : $post->post_title;
				wp_update_post(
					array(
						'ID'         => $id,
						'post_title' => $post_title,
						'menu_order' => $order,
					)
				);
			} else {
				$id = wp_insert_post(
					array(
						'post_type'   => 'staff_contact',
						'post_status' => 'publish',
						'post_title'  => $title,
						'menu_order'  => $order,
					),
					true
				);
				if ( is_wp_error( $id ) ) {
					continue;
				}
			}

			update_post_meta( $id, '_nvgso_name', $name );
			update_post_meta( $id, '_nvgso_rank', $rank );
			update_post_meta( $id, '_nvgso_phone', $phone );
			update_post_meta( $id, '_nvgso_reception', $reception );
			update_post_meta( $id, '_nvgso_email', $email );

			$i++;
		}

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'edit.php?post_type=staff_contact&page=nvgso-command-form' );
		}
		wp_safe_redirect( add_query_arg( 'updated', '1', $back ) );
		exit;
	}
);
