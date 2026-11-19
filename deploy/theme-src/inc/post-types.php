<?php
/**
 * Типы записей и таксономии сайта.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		register_post_type(
			'document',
			array(
				'labels'              => array(
					'name'          => 'Документы',
					'singular_name' => 'Документ',
					'add_new_item'  => 'Добавить документ',
					'edit_item'     => 'Редактировать документ',
					'all_items'     => 'Все документы',
				),
				'public'              => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'menu_icon'           => 'dashicons-media-document',
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
			)
		);

		register_taxonomy(
			'doc_category',
			array( 'document' ),
			array(
				'labels'            => array(
					'name'          => 'Категории документов',
					'singular_name' => 'Категория документа',
				),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'rewrite'           => false,
			)
		);

		register_post_type(
			'gallery_item',
			array(
				'labels'              => array(
					'name'          => 'Фотогалерея',
					'singular_name' => 'Фотоматериал',
					'add_new_item'  => 'Добавить фотоматериал',
					'edit_item'     => 'Редактировать фотоматериал',
					'all_items'     => 'Все фотоматериалы',
				),
				'public'              => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'menu_icon'           => 'dashicons-format-gallery',
				'supports'            => array( 'title', 'thumbnail', 'excerpt' ),
			)
		);

		register_taxonomy(
			'album',
			array( 'gallery_item' ),
			array(
				'labels'            => array(
					'name'          => 'Альбомы',
					'singular_name' => 'Альбом',
				),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'rewrite'           => false,
			)
		);

		register_post_type(
			'division',
			array(
				'labels'       => array(
					'name'          => 'Подразделения',
					'singular_name' => 'Подразделение',
					'add_new_item'  => 'Добавить подразделение',
					'edit_item'     => 'Редактировать подразделение',
					'all_items'     => 'Все подразделения',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-networking',
				'supports'     => array( 'title', 'editor', 'page-attributes' ),
			)
		);

		register_post_type(
			'staff_contact',
			array(
				'labels'       => array(
					'name'          => 'Командование',
					'singular_name' => 'Сотрудник',
					'add_new_item'  => 'Добавить сотрудника',
					'edit_item'     => 'Редактировать сотрудника',
					'all_items'     => 'Все сотрудники',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-groups',
				'supports'     => array( 'title', 'editor', 'page-attributes', 'thumbnail' ),
			)
		);
	}
);

/**
 * Колонки в админке для документов.
 */
add_filter(
	'manage_document_posts_columns',
	function ( $cols ) {
		$cols['nvgso_file'] = 'Файл';
		return $cols;
	}
);

add_action(
	'manage_document_posts_custom_column',
	function ( $col, $post_id ) {
		if ( 'nvgso_file' === $col ) {
			$file_id = (int) get_post_meta( $post_id, '_nvgso_file_id', true );
			if ( $file_id ) {
				$url = wp_get_attachment_url( $file_id );
				echo $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">PDF</a>' : '—';
			} else {
				echo '—';
			}
		}
	},
	10,
	2
);
