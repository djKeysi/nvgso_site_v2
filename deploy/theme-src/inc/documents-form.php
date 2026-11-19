<?php
/**
 * Удобная форма добавления и редактирования документов.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'edit.php?post_type=document',
			'Форма документов',
			'Форма документов',
			'edit_posts',
			'nvgso-documents-form',
			'nvgso_documents_form_page'
		);

		add_options_page(
			'Документы',
			'Документы',
			'manage_options',
			'nvgso-documents-settings',
			'nvgso_documents_form_page'
		);
	}
);

function nvgso_documents_form_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	$docs = get_posts(
		array(
			'post_type'      => 'document',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
		)
	);

	$terms = get_terms(
		array(
			'taxonomy'   => 'doc_category',
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) ) {
		$terms = array();
	}

	$today = current_time( 'Y-m-d' );
	?>
	<div class="wrap">
		<h1>Документы</h1>
		<p>Заполните таблицу и нажмите «Сохранить». PDF загружается в медиатеку, категория выбирается из списка.</p>
		<p class="description">Новый документ добавляется через пустую строку внизу. Стрелки ↑/↓ меняют порядок сразу. Если снять «Опубликован», документ сохранится как черновик и не появится на сайте.</p>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Документы сохранены.</p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['docs_error'] ) ) : ?>
			<div class="notice notice-error is-dismissible"><p>Не все данные удалось сохранить. Проверьте, что выбран PDF и заполнено название.</p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['order_updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Порядок документов обновлён.</p></div>
		<?php endif; ?>

		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="nvgso_documents_save">
			<?php wp_nonce_field( 'nvgso_documents_save' ); ?>

			<table class="widefat striped" style="max-width:1400px">
				<thead>
					<tr>
						<th style="min-width:18rem">Название</th>
						<th style="width:6.5rem">Порядок</th>
						<th>Категория</th>
						<th>Дата</th>
						<th>Стр.</th>
						<th style="min-width:16rem">Примечание</th>
						<th style="min-width:16rem">PDF</th>
						<th>Опубликован</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $docs as $i => $doc ) : ?>
						<?php
						$file_id  = (int) get_post_meta( $doc->ID, '_nvgso_file_id', true );
						$file_url = $file_id ? wp_get_attachment_url( $file_id ) : '';
						$file     = $file_id ? get_attached_file( $file_id ) : '';
						$doc_date = (string) get_post_meta( $doc->ID, '_nvgso_doc_date', true );
						$pages    = (int) get_post_meta( $doc->ID, '_nvgso_pages', true );
						$note     = (string) get_post_meta( $doc->ID, '_nvgso_note', true );
						$doc_cats = wp_get_object_terms( $doc->ID, 'doc_category', array( 'fields' => 'ids' ) );
						$cat_id   = ( ! is_wp_error( $doc_cats ) && $doc_cats ) ? (int) $doc_cats[0] : 0;
						?>
						<tr>
							<td>
								<input type="hidden" name="docs[<?php echo (int) $i; ?>][id]" value="<?php echo (int) $doc->ID; ?>">
								<input type="text" class="regular-text" name="docs[<?php echo (int) $i; ?>][title]" value="<?php echo esc_attr( get_the_title( $doc ) ); ?>" required>
							</td>
							<td>
								<div style="display:flex;align-items:center;gap:.25rem">
									<?php
									$up_url   = wp_nonce_url(
										admin_url( 'admin-post.php?action=nvgso_doc_move&post=' . $doc->ID . '&dir=up' ),
										'nvgso_doc_move_' . $doc->ID
									);
									$down_url = wp_nonce_url(
										admin_url( 'admin-post.php?action=nvgso_doc_move&post=' . $doc->ID . '&dir=down' ),
										'nvgso_doc_move_' . $doc->ID
									);
									?>
									<?php if ( $i > 0 ) : ?>
										<a class="button button-small" href="<?php echo esc_url( $up_url ); ?>" title="Выше" aria-label="Переместить выше">↑</a>
									<?php else : ?>
										<span class="button button-small disabled" aria-hidden="true">↑</span>
									<?php endif; ?>
									<?php if ( $i < count( $docs ) - 1 ) : ?>
										<a class="button button-small" href="<?php echo esc_url( $down_url ); ?>" title="Ниже" aria-label="Переместить ниже">↓</a>
									<?php else : ?>
										<span class="button button-small disabled" aria-hidden="true">↓</span>
									<?php endif; ?>
									<span class="description"><?php echo (int) ( $i + 1 ); ?></span>
								</div>
							</td>
							<td>
								<select name="docs[<?php echo (int) $i; ?>][category]">
									<option value="0">Без категории</option>
									<?php foreach ( $terms as $term ) : ?>
										<option value="<?php echo (int) $term->term_id; ?>"<?php selected( $cat_id, $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><input type="date" name="docs[<?php echo (int) $i; ?>][date]" value="<?php echo esc_attr( $doc_date ); ?>"></td>
							<td><input type="number" min="0" class="small-text" name="docs[<?php echo (int) $i; ?>][pages]" value="<?php echo (int) $pages; ?>"></td>
							<td><textarea name="docs[<?php echo (int) $i; ?>][note]" rows="2" class="large-text"><?php echo esc_textarea( $note ); ?></textarea></td>
							<td>
								<?php if ( $file_url ) : ?>
									<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $file ? basename( $file ) : 'текущий PDF' ); ?></a><br>
								<?php else : ?>
									<em>файл не выбран</em><br>
								<?php endif; ?>
								<input type="file" name="docs_file[<?php echo (int) $i; ?>]" accept=".pdf,application/pdf">
								<?php if ( $file_url ) : ?><br><span class="description">выберите новый файл, чтобы заменить</span><?php endif; ?>
							</td>
							<td><label><input type="checkbox" name="docs[<?php echo (int) $i; ?>][published]" value="1"<?php checked( 'publish' === $doc->post_status ); ?>> Да</label></td>
						</tr>
					<?php endforeach; ?>

					<?php $new = count( $docs ); ?>
					<tr>
						<td>
							<input type="hidden" name="docs[<?php echo (int) $new; ?>][id]" value="0">
							<input type="text" class="regular-text" name="docs[<?php echo (int) $new; ?>][title]" value="" placeholder="Название нового документа">
						</td>
						<td><span class="description">в конец</span></td>
						<td>
							<select name="docs[<?php echo (int) $new; ?>][category]">
								<option value="0">Без категории</option>
								<?php foreach ( $terms as $term ) : ?>
									<option value="<?php echo (int) $term->term_id; ?>"><?php echo esc_html( $term->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="date" name="docs[<?php echo (int) $new; ?>][date]" value="<?php echo esc_attr( $today ); ?>"></td>
						<td><input type="number" min="0" class="small-text" name="docs[<?php echo (int) $new; ?>][pages]" value="0"></td>
						<td><textarea name="docs[<?php echo (int) $new; ?>][note]" rows="2" class="large-text" placeholder="Краткое описание"></textarea></td>
						<td><input type="file" name="docs_file[<?php echo (int) $new; ?>]" accept=".pdf,application/pdf"></td>
						<td><label><input type="checkbox" name="docs[<?php echo (int) $new; ?>][published]" value="1" checked> Да</label></td>
					</tr>
				</tbody>
			</table>

			<p style="margin-top:1rem">
				<button type="submit" class="button button-primary">Сохранить</button>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=document' ) ); ?>">Все документы</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=doc_category&post_type=document' ) ); ?>">Категории</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>">Медиатека</a>
			</p>
		</form>
	</div>
	<?php
}

add_action(
	'admin_post_nvgso_documents_save',
	function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Недостаточно прав.' );
		}
		check_admin_referer( 'nvgso_documents_save' );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$rows   = isset( $_POST['docs'] ) && is_array( $_POST['docs'] ) ? wp_unslash( $_POST['docs'] ) : array();
		$errors = 0;
		global $wpdb;
		$max_order = (int) $wpdb->get_var( "SELECT MAX(menu_order) FROM {$wpdb->posts} WHERE post_type = 'document'" );

		foreach ( $rows as $i => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$i          = (int) $i;
			$id         = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$title      = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
			$cat_id     = isset( $row['category'] ) ? absint( $row['category'] ) : 0;
			$date       = isset( $row['date'] ) ? sanitize_text_field( $row['date'] ) : '';
			$pages      = isset( $row['pages'] ) ? absint( $row['pages'] ) : 0;
			$note       = isset( $row['note'] ) ? sanitize_textarea_field( $row['note'] ) : '';
			$is_new     = ! $id;
			$published  = ! empty( $row['published'] );
			$file_id    = $id ? (int) get_post_meta( $id, '_nvgso_file_id', true ) : 0;
			$upload_id  = 0;

			$has_upload = isset( $_FILES['docs_file']['name'][ $i ] ) && '' !== (string) $_FILES['docs_file']['name'][ $i ];

			if ( $is_new && '' === $title && ! $has_upload ) {
				continue;
			}
			if ( $is_new && '' === $title && $has_upload ) {
				$title = sanitize_text_field( pathinfo( (string) $_FILES['docs_file']['name'][ $i ], PATHINFO_FILENAME ) );
			}
			if ( '' === $title ) {
				$errors++;
				continue;
			}

			if ( $id ) {
				$post = get_post( $id );
				if ( ! $post || 'document' !== $post->post_type ) {
					continue;
				}
			}

			if ( $published && ! $file_id && ! $has_upload ) {
				$errors++;
				$published = false;
			}

			$status  = $published ? 'publish' : 'draft';
			$post_id = $id;

			if ( $post_id ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_title'  => $title,
						'post_status' => $status,
					)
				);
			} else {
				$post_id = wp_insert_post(
					array(
						'post_type'   => 'document',
						'post_title'  => $title,
						'post_status' => $status,
						'menu_order'  => ++$max_order,
					),
					true
				);
				if ( is_wp_error( $post_id ) ) {
					$errors++;
					continue;
				}
			}

			if ( $has_upload ) {
				$_FILES['nvgso_doc_upload'] = array(
					'name'     => (string) $_FILES['docs_file']['name'][ $i ],
					'type'     => (string) ( $_FILES['docs_file']['type'][ $i ] ?? '' ),
					'tmp_name' => (string) ( $_FILES['docs_file']['tmp_name'][ $i ] ?? '' ),
					'error'    => (int) ( $_FILES['docs_file']['error'][ $i ] ?? UPLOAD_ERR_NO_FILE ),
					'size'     => (int) ( $_FILES['docs_file']['size'][ $i ] ?? 0 ),
				);

				$upload_id = media_handle_upload( 'nvgso_doc_upload', $post_id, array(), array( 'test_form' => false ) );
				if ( is_wp_error( $upload_id ) ) {
					$errors++;
					$upload_id = 0;
					if ( 'publish' === $status ) {
						wp_update_post(
							array(
								'ID'          => $post_id,
								'post_status' => 'draft',
							)
						);
					}
				} else {
					update_post_meta( $post_id, '_nvgso_file_id', (int) $upload_id );
				}
			}

			update_post_meta( $post_id, '_nvgso_doc_date', $date );
			update_post_meta( $post_id, '_nvgso_pages', $pages );
			update_post_meta( $post_id, '_nvgso_note', $note );

			if ( $cat_id && term_exists( $cat_id, 'doc_category' ) ) {
				wp_set_object_terms( $post_id, array( $cat_id ), 'doc_category' );
			} else {
				wp_set_object_terms( $post_id, array(), 'doc_category' );
			}
		}

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'edit.php?post_type=document&page=nvgso-documents-form' );
		}
		$back = add_query_arg( $errors ? 'docs_error' : 'updated', '1', $back );
		wp_safe_redirect( $back );
		exit;
	}
);

add_action(
	'admin_notices',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-document' !== $screen->id ) {
			return;
		}
		$url = admin_url( 'edit.php?post_type=document&page=nvgso-documents-form' );
		echo '<div class="notice notice-info is-dismissible"><p><strong>Документы:</strong> добавить или отредактировать название, категорию, дату и PDF можно в <a href="' . esc_url( $url ) . '">форме документов</a>.</p></div>';
	}
);

/**
 * Перемещение документа вверх/вниз и сохранение порядка.
 */
add_action(
	'admin_post_nvgso_doc_move',
	function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Недостаточно прав.' );
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$dir     = isset( $_GET['dir'] ) ? sanitize_key( wp_unslash( $_GET['dir'] ) ) : '';
		if ( ! $post_id || ! in_array( $dir, array( 'up', 'down' ), true ) ) {
			wp_die( 'Некорректный запрос.' );
		}

		check_admin_referer( 'nvgso_doc_move_' . $post_id );

		$post = get_post( $post_id );
		if ( ! $post || 'document' !== $post->post_type ) {
			wp_die( 'Документ не найден.' );
		}

		$ids = get_posts(
			array(
				'post_type'      => 'document',
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
				$tmp             = $ids[ $index - 1 ];
				$ids[ $index - 1 ] = $ids[ $index ];
				$ids[ $index ]     = $tmp;
			} elseif ( 'down' === $dir && $index < count( $ids ) - 1 ) {
				$tmp             = $ids[ $index + 1 ];
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
			$back = admin_url( 'edit.php?post_type=document&page=nvgso-documents-form' );
		}
		wp_safe_redirect( add_query_arg( 'order_updated', '1', $back ) );
		exit;
	}
);
