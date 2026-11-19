<?php
/**
 * Метабоксы для документов, галереи, подразделений и командования.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'add_meta_boxes',
	function () {
		add_meta_box( 'nvgso_document', 'Файл и реквизиты документа', 'nvgso_document_box', 'document', 'normal', 'high' );
		add_meta_box( 'nvgso_gallery', 'Изображение и подпись', 'nvgso_gallery_box', 'gallery_item', 'normal', 'high' );
		add_meta_box( 'nvgso_division', 'Данные подразделения', 'nvgso_division_box', 'division', 'normal', 'high' );
		add_meta_box( 'nvgso_staff', 'Данные сотрудника', 'nvgso_staff_box', 'staff_contact', 'normal', 'high' );
	}
);

function nvgso_media_field( $name, $value, $label, $mime = '' ) {
	$id   = (int) $value;
	$file = $id ? get_attached_file( $id ) : '';
	$name_file = $file ? basename( $file ) : 'файл не выбран';
	echo '<p><label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label><br>';
	echo '<input type="hidden" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $id ) . '">';
	echo '<span id="label-' . esc_attr( $name ) . '">' . esc_html( $name_file ) . '</span> ';
	echo '<button type="button" class="button nvgso-media-select" data-target="' . esc_attr( $name ) . '" data-mime="' . esc_attr( $mime ) . '">Выбрать файл</button>';
	if ( $id ) {
		echo ' <a href="' . esc_url( get_edit_post_link( $id ) ) . '" target="_blank">открыть в медиатеке</a>';
	}
	echo '</p>';
}

function nvgso_document_box( $post ) {
	wp_nonce_field( 'nvgso_meta', 'nvgso_meta_nonce' );
	$file_id = (int) get_post_meta( $post->ID, '_nvgso_file_id', true );
	$pages   = (int) get_post_meta( $post->ID, '_nvgso_pages', true );
	$date    = (string) get_post_meta( $post->ID, '_nvgso_doc_date', true );
	$note    = (string) get_post_meta( $post->ID, '_nvgso_note', true );
	nvgso_media_field( '_nvgso_file_id', $file_id, 'PDF-файл', 'application/pdf' );
	echo '<p><label for="_nvgso_doc_date"><strong>Дата документа</strong></label><br>';
	echo '<input type="date" id="_nvgso_doc_date" name="_nvgso_doc_date" value="' . esc_attr( $date ) . '"></p>';
	echo '<p><label for="_nvgso_pages"><strong>Количество страниц</strong></label><br>';
	echo '<input type="number" min="0" id="_nvgso_pages" name="_nvgso_pages" value="' . esc_attr( $pages ) . '"></p>';
	echo '<p><label for="_nvgso_note"><strong>Примечание</strong></label><br>';
	echo '<textarea class="large-text" rows="2" id="_nvgso_note" name="_nvgso_note">' . esc_textarea( $note ) . '</textarea></p>';
	echo '<p class="description">Файл загружается в медиатеку. Категория документа задаётся справа.</p>';
}

function nvgso_gallery_box( $post ) {
	wp_nonce_field( 'nvgso_meta', 'nvgso_meta_nonce' );
	$image_id = (int) get_post_meta( $post->ID, '_nvgso_image_id', true );
	$caption  = (string) get_post_meta( $post->ID, '_nvgso_caption', true );
	$date     = (string) get_post_meta( $post->ID, '_nvgso_date', true );
	$type     = (string) get_post_meta( $post->ID, '_nvgso_type', true );
	$video    = (string) get_post_meta( $post->ID, '_nvgso_video_url', true );
	nvgso_media_field( '_nvgso_image_id', $image_id, 'Изображение', 'image' );
	echo '<p><label for="_nvgso_caption"><strong>Подпись</strong></label><br>';
	echo '<textarea class="large-text" rows="2" id="_nvgso_caption" name="_nvgso_caption">' . esc_textarea( $caption ) . '</textarea></p>';
	echo '<p><label for="_nvgso_date"><strong>Дата съёмки</strong></label><br>';
	echo '<input type="date" id="_nvgso_date" name="_nvgso_date" value="' . esc_attr( $date ) . '"></p>';
	echo '<p><label for="_nvgso_type"><strong>Тип</strong></label><br><select id="_nvgso_type" name="_nvgso_type">';
	foreach ( array( 'photo' => 'Фото', 'video' => 'Видео' ) as $value => $label ) {
		echo '<option value="' . esc_attr( $value ) . '"' . selected( $type, $value, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select></p>';
	echo '<p><label for="_nvgso_video_url"><strong>Ссылка на видео (для типа «Видео»)</strong></label><br>';
	echo '<input type="url" class="large-text" id="_nvgso_video_url" name="_nvgso_video_url" value="' . esc_attr( $video ) . '" placeholder="https://rutube.ru/video/..."></p>';
	echo '<p class="description">Альбом задаётся справа. Для видео изображение используется как обложка.</p>';
}

function nvgso_division_box( $post ) {
	wp_nonce_field( 'nvgso_meta', 'nvgso_meta_nonce' );
	$fields = array(
		'_nvgso_type'        => array( 'Тип', 'text' ),
		'_nvgso_city'        => array( 'Город', 'text' ),
		'_nvgso_address'     => array( 'Адрес', 'text' ),
		'_nvgso_phone'       => array( 'Телефон', 'text' ),
		'_nvgso_units'       => array( 'Состав / отделения', 'text' ),
		'_nvgso_description' => array( 'Описание', 'textarea' ),
	);
	foreach ( $fields as $key => $field ) {
		$value = (string) get_post_meta( $post->ID, $key, true );
		echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $field[0] ) . '</strong></label><br>';
		if ( 'textarea' === $field[1] ) {
			echo '<textarea class="large-text" rows="3" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '">' . esc_textarea( $value ) . '</textarea>';
		} else {
			echo '<input type="text" class="large-text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
		}
		echo '</p>';
	}
	echo '<p class="description">Порядок вывода задаётся атрибутом «Порядок» в блоке «Атрибуты страницы».</p>';
}

function nvgso_staff_box( $post ) {
	wp_nonce_field( 'nvgso_meta', 'nvgso_meta_nonce' );
	$fields = array(
		'_nvgso_name'      => array( 'ФИО', 'text' ),
		'_nvgso_rank'      => array( 'Звание / должность по штату', 'text' ),
		'_nvgso_phone'     => array( 'Телефон', 'text' ),
		'_nvgso_email'     => array( 'E-mail', 'email' ),
		'_nvgso_reception' => array( 'Приём / режим', 'text' ),
	);
	foreach ( $fields as $key => $field ) {
		$value = (string) get_post_meta( $post->ID, $key, true );
		echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $field[0] ) . '</strong></label><br>';
		echo '<input type="' . esc_attr( $field[1] ) . '" class="large-text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
		echo '</p>';
	}
	echo '<p class="description">Должность указывается в заголовке записи. Порядок — в «Атрибутах страницы».</p>';
}

add_action(
	'save_post',
	function ( $post_id ) {
		if ( ! isset( $_POST['nvgso_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nvgso_meta_nonce'] ) ), 'nvgso_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$text_fields = array(
			'_nvgso_doc_date',
			'_nvgso_note',
			'_nvgso_caption',
			'_nvgso_date',
			'_nvgso_type',
			'_nvgso_video_url',
			'_nvgso_type',
			'_nvgso_city',
			'_nvgso_address',
			'_nvgso_phone',
			'_nvgso_units',
			'_nvgso_description',
			'_nvgso_name',
			'_nvgso_rank',
			'_nvgso_email',
			'_nvgso_reception',
		);
		foreach ( $text_fields as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}

		foreach ( array( '_nvgso_file_id', '_nvgso_image_id', '_nvgso_pages' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, (int) $_POST[ $key ] );
			}
		}
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( 'document', 'gallery_item' ), true ) ) {
			return;
		}
		wp_enqueue_media();
	}
);


add_action(
	'admin_print_footer_scripts',
	function () {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( 'document', 'gallery_item' ), true ) ) {
			return;
		}
		?>
		<script>
		jQuery(function ($) {
			$('.nvgso-media-select').on('click', function (e) {
				e.preventDefault();
				var target = $(this).data('target');
				var mime = $(this).data('mime');
				var frame = wp.media({ title: 'Выбрать файл', library: { type: mime || '' }, multiple: false });
				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					$('#' + target).val(attachment.id);
					$('#label-' + target).text(attachment.filename);
				});
				frame.open();
			});
		});
		</script>
		<?php
	}
);
