<?php
/**
 * Форма обратной связи: приём, сохранение обращения, вложение и письмо.
 * Вложение: один файл .pdf или .docx, не более 15 МБ.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const NVGSO_FEEDBACK_MAX_FILE = 15728640; // 15 МБ
const NVGSO_FEEDBACK_RATE_MAX = 5;          // не больше 5 обращений
const NVGSO_FEEDBACK_RATE_WINDOW = 3600;    // в час
const NVGSO_FEEDBACK_RATE_COOLDOWN = 20;    // и не чаще одного в 20 секунд

add_action(
	'init',
	function () {
		register_post_type(
			'feedback',
			array(
				'labels'          => array(
					'name'          => 'Обращения',
					'singular_name' => 'Обращение',
					'all_items'     => 'Все обращения',
					'edit_item'     => 'Просмотреть обращение',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-email-alt',
				'supports'        => array( 'title', 'editor' ),
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);
	},
	11
);

/**
 * Каталог приватных вложений (внутри uploads, но закрыт в Nginx).
 */
function nvgso_feedback_upload_dir() {
	$uploads = wp_upload_dir();
	return trailingslashit( $uploads['basedir'] ) . 'nvgso-feedback';
}

/**
 * Проверка и сохранение вложения.
 *
 * @return array|WP_Error Пустой массив, если файла нет; данные файла или ошибка.
 */
function nvgso_feedback_handle_attachment() {
	if ( empty( $_FILES['attachment'] ) || ! is_array( $_FILES['attachment'] ) ) {
		return array();
	}

	$file = $_FILES['attachment']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

	if ( UPLOAD_ERR_NO_FILE === (int) $file['error'] ) {
		return array();
	}
	if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
		return new WP_Error( 'file_error', 'Не удалось загрузить файл.' );
	}
	if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'file_error', 'Некорректная загрузка файла.' );
	}

	$size = (int) $file['size'];
	if ( $size <= 0 || $size > NVGSO_FEEDBACK_MAX_FILE ) {
		return new WP_Error( 'file_size', 'Размер файла больше 15 МБ.' );
	}

	$original = sanitize_file_name( (string) $file['name'] );
	$ext      = strtolower( pathinfo( $original, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'pdf', 'docx' ), true ) ) {
		return new WP_Error( 'file_type', 'Можно приложить только PDF или DOCX.' );
	}

	$mime = '';
	if ( class_exists( 'finfo' ) ) {
		$finfo = new finfo( FILEINFO_MIME_TYPE );
		$mime  = (string) $finfo->file( $file['tmp_name'] );
	} elseif ( function_exists( 'mime_content_type' ) ) {
		$mime = (string) mime_content_type( $file['tmp_name'] );
	}

	if ( 'pdf' === $ext ) {
		$head = (string) file_get_contents( $file['tmp_name'], false, null, 0, 5 );
		if ( 0 !== strpos( $head, '%PDF-' ) || ! in_array( $mime, array( 'application/pdf', 'application/x-pdf' ), true ) ) {
			return new WP_Error( 'file_type', 'Файл не является PDF.' );
		}
	}

	if ( 'docx' === $ext ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'file_error', 'На сервере нет модуля для проверки DOCX.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $file['tmp_name'] ) ) {
			return new WP_Error( 'file_type', 'Файл не является DOCX.' );
		}
		$has_types   = false !== $zip->locateName( '[Content_Types].xml' );
		$has_doc     = false !== $zip->locateName( 'word/document.xml' );
		$has_macros  = false !== $zip->locateName( 'word/vbaProject.bin' );
		$types_xml   = $zip->getFromName( '[Content_Types].xml' );
		$zip->close();
		if ( ! $has_types || ! $has_doc || $has_macros || ( is_string( $types_xml ) && false !== stripos( $types_xml, 'macroEnabled' ) ) ) {
			return new WP_Error( 'file_type', 'Файл не является документом Word (DOCX) без макросов.' );
		}
	}

	$scan = nvgso_feedback_scan_file( $file['tmp_name'] );
	if ( is_wp_error( $scan ) ) {
		return $scan;
	}

	$dir = nvgso_feedback_upload_dir();
	if ( ! wp_mkdir_p( $dir ) ) {
		return new WP_Error( 'file_error', 'Не удалось создать каталог для вложения.' );
	}

	$filename = wp_unique_filename( $dir, $original );
	$path     = trailingslashit( $dir ) . $filename;
	if ( ! move_uploaded_file( $file['tmp_name'], $path ) ) {
		return new WP_Error( 'file_error', 'Не удалось сохранить вложение.' );
	}
	@chmod( $path, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

	$uploads = wp_upload_dir();
	$rel     = ltrim( str_replace( trailingslashit( $uploads['basedir'] ), '', $path ), '/' );

	return array(
		'path' => $path,
		'rel'  => $rel,
		'name' => $original,
		'size' => $size,
		'mime' => $mime ? $mime : 'application/octet-stream',
		'av'   => 'clean',
	);
}


/**
 * Ключ лимита по IP (сам IP не сохраняем).
 */
function nvgso_feedback_rate_key() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
	return md5( $ip . '|' . wp_salt( 'auth' ) );
}

function nvgso_feedback_rate_allowed( $key ) {
	if ( get_transient( 'nvgso_fb_last_' . $key ) ) {
		return false;
	}
	return (int) get_transient( 'nvgso_fb_count_' . $key ) < NVGSO_FEEDBACK_RATE_MAX;
}

function nvgso_feedback_rate_touch( $key ) {
	set_transient( 'nvgso_fb_last_' . $key, time(), NVGSO_FEEDBACK_RATE_COOLDOWN );
	$count = (int) get_transient( 'nvgso_fb_count_' . $key );
	set_transient( 'nvgso_fb_count_' . $key, $count + 1, NVGSO_FEEDBACK_RATE_WINDOW );
}

/**
 * Проверка файла ClamAV. Чисто — true, вирус/ошибка — WP_Error.
 */
function nvgso_feedback_scan_file( $path ) {
	$bin = '/usr/bin/clamscan';
	if ( ! is_executable( $bin ) ) {
		return new WP_Error( 'file_av_unavailable', 'Антивирусная проверка временно недоступна.' );
	}
	$db  = '/var/lib/clamav';
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	$cmd = escapeshellcmd( $bin ) . ' --database=' . escapeshellarg( $db ) . ' --no-summary --infected ' . escapeshellarg( $path ) . ' 2>&1';
	$out = array();
	$rc  = 0;
	exec( $cmd, $out, $rc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	if ( 1 === $rc ) {
		return new WP_Error( 'file_virus', 'Файл не прошёл антивирусную проверку.' );
	}
	if ( 0 !== $rc ) {
		return new WP_Error( 'file_av_unavailable', 'Антивирусная проверка временно недоступна.' );
	}
	return true;
}

add_action( 'admin_post_nvgso_feedback', 'nvgso_feedback_handler' );
add_action( 'admin_post_nopriv_nvgso_feedback', 'nvgso_feedback_handler' );

function nvgso_feedback_handler() {
	$redirect = wp_get_referer() ? wp_get_referer() : nvgso_page_url( 'contacts' );
	$redirect = remove_query_arg( array( 'feedback' ), $redirect );

	if ( ! isset( $_POST['nvgso_feedback_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nvgso_feedback_nonce'] ) ), 'nvgso_feedback' ) ) {
		wp_safe_redirect( add_query_arg( 'feedback', 'error', $redirect ) . '#feedback' );
		exit;
	}

	if ( ! empty( $_POST['website'] ) ) {
		wp_safe_redirect( add_query_arg( 'feedback', 'ok', $redirect ) . '#feedback' );
		exit;
	}

	$rate_key = nvgso_feedback_rate_key();
	if ( ! nvgso_feedback_rate_allowed( $rate_key ) ) {
		wp_safe_redirect( add_query_arg( 'feedback', 'rate_limit', $redirect ) . '#feedback' );
		exit;
	}

	$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
	$topic   = sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) );
	$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
	$consent = ! empty( $_POST['consent'] );

	if ( ! $name || ! is_email( $email ) || ! $message || ! $consent ) {
		wp_safe_redirect( add_query_arg( 'feedback', 'error', $redirect ) . '#feedback' );
		exit;
	}

	nvgso_feedback_rate_touch( $rate_key );

	$attachment = nvgso_feedback_handle_attachment();
	if ( is_wp_error( $attachment ) ) {
		wp_safe_redirect( add_query_arg( 'feedback', $attachment->get_error_code(), $redirect ) . '#feedback' );
		exit;
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'feedback',
			'post_status'  => 'publish',
			'post_title'   => 'Обращение: ' . $name . ' — ' . nvgso_excerpt( $message, 60 ),
			'post_content' => $message,
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		if ( ! empty( $attachment['path'] ) && is_file( $attachment['path'] ) ) {
			@unlink( $attachment['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		wp_safe_redirect( add_query_arg( 'feedback', 'error', $redirect ) . '#feedback' );
		exit;
	}

	update_post_meta( $post_id, '_nvgso_name', $name );
	update_post_meta( $post_id, '_nvgso_email', $email );
	update_post_meta( $post_id, '_nvgso_phone', $phone );
	update_post_meta( $post_id, '_nvgso_topic', $topic );
	update_post_meta( $post_id, '_nvgso_consent', current_time( 'mysql' ) );

	if ( ! empty( $attachment['path'] ) ) {
		update_post_meta( $post_id, '_nvgso_attachment_rel', $attachment['rel'] );
		update_post_meta( $post_id, '_nvgso_attachment_name', $attachment['name'] );
		update_post_meta( $post_id, '_nvgso_attachment_mime', $attachment['mime'] );
		update_post_meta( $post_id, '_nvgso_attachment_size', (int) $attachment['size'] );
		update_post_meta( $post_id, '_nvgso_av_status', 'clean' );
	}

	$s     = nvgso_settings();
	$to    = $s['email'] ? $s['email'] : get_option( 'admin_email' );
	$body  = "Имя: {$name}\nE-mail: {$email}\nТелефон: {$phone}\nТема: {$topic}\n\n{$message}\n\nСогласие на обработку ПДн: да, " . current_time( 'mysql' );
	$files = array();
	if ( ! empty( $attachment['path'] ) ) {
		$body .= "\nВложение: " . $attachment['name'];
		$files[] = $attachment['path'];
	}

	wp_mail( $to, 'Обращение с сайта: ' . $topic, $body, array(), $files );

	wp_safe_redirect( add_query_arg( 'feedback', 'ok', $redirect ) . '#feedback' );
	exit;
}

/**
 * Карточка обращения в админке: контакты, согласие, вложение.
 */
add_action(
	'add_meta_boxes',
	function () {
		add_meta_box( 'nvgso_feedback_data', 'Данные обращения', 'nvgso_feedback_data_box', 'feedback', 'normal', 'high' );
	}
);

function nvgso_feedback_data_box( $post ) {
	$name      = (string) get_post_meta( $post->ID, '_nvgso_name', true );
	$email     = (string) get_post_meta( $post->ID, '_nvgso_email', true );
	$phone     = (string) get_post_meta( $post->ID, '_nvgso_phone', true );
	$topic     = (string) get_post_meta( $post->ID, '_nvgso_topic', true );
	$consent   = (string) get_post_meta( $post->ID, '_nvgso_consent', true );
	$file_rel  = (string) get_post_meta( $post->ID, '_nvgso_attachment_rel', true );
	$file_name = (string) get_post_meta( $post->ID, '_nvgso_attachment_name', true );
	$file_size = (int) get_post_meta( $post->ID, '_nvgso_attachment_size', true );
	?>
	<table class="widefat striped" style="max-width:48rem">
		<tbody>
			<tr><th style="width:10rem">Имя</th><td><?php echo esc_html( $name ); ?></td></tr>
			<tr><th>E-mail</th><td><?php echo $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '—'; ?></td></tr>
			<tr><th>Телефон</th><td><?php echo $phone ? '<a href="tel:' . esc_attr( preg_replace( '/\D+/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>' : '—'; ?></td></tr>
			<tr><th>Тема</th><td><?php echo esc_html( $topic ); ?></td></tr>
			<tr><th>Согласие на ПДн</th><td><?php echo $consent ? esc_html( $consent ) : 'нет данных'; ?></td></tr>
			<tr><th>Вложение</th><td>
				<?php if ( $file_rel ) : ?>
					<?php
					$url = wp_nonce_url(
						admin_url( 'admin-post.php?action=nvgso_feedback_file&post=' . $post->ID ),
						'nvgso_feedback_file_' . $post->ID
					);
					?>
					<a class="button" href="<?php echo esc_url( $url ); ?>">Скачать</a>
					<?php echo esc_html( $file_name ? $file_name : basename( $file_rel ) ); ?>
					<?php echo $file_size ? ' (' . esc_html( size_format( $file_size ) ) . ')' : ''; ?>
				<?php else : ?>
					—
				<?php endif; ?>
			</td></tr>
			<tr><th>Антивирус</th><td><?php echo $file_rel ? 'проверено, чисто (ClamAV)' : '—'; ?></td></tr>
		</tbody>
	</table>
	<?php
}

/**
 * Защищённая выдача вложения (только пользователям с правом редактировать обращение).
 */
add_action(
	'admin_post_nvgso_feedback_file',
	function () {
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( 'Недостаточно прав.' );
		}
		check_admin_referer( 'nvgso_feedback_file_' . $post_id );

		$rel = (string) get_post_meta( $post_id, '_nvgso_attachment_rel', true );
		if ( ! $rel ) {
			wp_die( 'Вложение не найдено.' );
		}

		$uploads = wp_upload_dir();
		$base    = realpath( trailingslashit( $uploads['basedir'] ) . 'nvgso-feedback' );
		$path    = realpath( trailingslashit( $uploads['basedir'] ) . $rel );

		if ( ! $base || ! $path || 0 !== strpos( $path, trailingslashit( $base ) ) || ! is_file( $path ) ) {
			wp_die( 'Вложение не найдено.' );
		}

		$mime = (string) get_post_meta( $post_id, '_nvgso_attachment_mime', true );
		$name = (string) get_post_meta( $post_id, '_nvgso_attachment_name', true );
		$name = $name ? sanitize_file_name( $name ) : basename( $path );

		nocache_headers();
		header( 'Content-Type: ' . ( $mime ? $mime : 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $name ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path );
		exit;
	}
);

add_filter(
	'manage_feedback_posts_columns',
	function ( $cols ) {
		return array(
			'cb'          => $cols['cb'],
			'title'       => 'Обращение',
			'nvgso_mail'  => 'E-mail',
			'nvgso_topic' => 'Тема',
			'date'        => 'Дата',
		);
	}
);

add_action(
	'manage_feedback_posts_custom_column',
	function ( $col, $post_id ) {
		if ( 'nvgso_mail' === $col ) {
			echo esc_html( get_post_meta( $post_id, '_nvgso_email', true ) );
		}
		if ( 'nvgso_topic' === $col ) {
			echo esc_html( get_post_meta( $post_id, '_nvgso_topic', true ) );
		}
	},
	10,
	2
);
