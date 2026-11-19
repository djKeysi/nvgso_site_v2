<?php
/**
 * Страница настроек сайта в админке: Настройки → Сайт NVGSO.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_init',
	function () {
		register_setting(
			'nvgso_settings_group',
			'nvgso_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'nvgso_sanitize_settings',
				'default'           => nvgso_default_settings(),
			)
		);
	}
);

function nvgso_sanitize_settings( $input ) {
	$defaults = nvgso_default_settings();
	$current  = wp_parse_args( get_option( 'nvgso_settings', array() ), $defaults );
	$input    = is_array( $input ) ? $input : array();

	$text_fields = array(
		'org_name',
		'org_full_name',
		'org_full',
		'org_parent',
		'site_title',
		'address',
		'address_short',
		'map_query',
		'hotline_label',
		'phone_duty',
		'phone_office',
		'phone_parent',
		'hours',
		'hours_short',
		'hero_title',
		'hero_lead',
		'hero_tag',
		'about_short',
		'about_lead',
		'activity_lead',
		'structure_lead',
		'veterans_lead',
		'contacts_lead',
		'news_lead',
		'gallery_lead',
		'documents_lead',
		'leader_position',
		'leader_name',
		'leader_rank',
		'inn',
		'ogrn',
		'legal_address',
		'emergency_text',
		'footer_site_name',
		'footer_fio',
		'footer_position',
		'zone_eyebrow',
		'zone_title',
		'response_eyebrow',
		'response_title',
	);

	$out = $current;
	foreach ( $text_fields as $key ) {
		if ( isset( $input[ $key ] ) ) {
			$out[ $key ] = sanitize_text_field( $input[ $key ] );
		}
	}

	if ( isset( $input['about_text'] ) ) {
		$out['about_text'] = sanitize_textarea_field( $input['about_text'] );
	}
	if ( isset( $input['footer_description'] ) ) {
		$out['footer_description'] = sanitize_textarea_field( $input['footer_description'] );
	}
	if ( isset( $input['zone_lead'] ) ) {
		$out['zone_lead'] = sanitize_textarea_field( $input['zone_lead'] );
	}
	if ( isset( $input['response_lead'] ) ) {
		$out['response_lead'] = sanitize_textarea_field( $input['response_lead'] );
	}
	if ( isset( $input['footer_credit'] ) ) {
		$out['footer_credit'] = wp_kses_post( $input['footer_credit'] );
	}
	if ( isset( $input['footer_max_url'] ) ) {
		$out['footer_max_url'] = esc_url_raw( $input['footer_max_url'] );
	}
	if ( isset( $input['footer_vk_url'] ) ) {
		$out['footer_vk_url'] = esc_url_raw( $input['footer_vk_url'] );
	}
	if ( isset( $input['email'] ) ) {
		$out['email'] = sanitize_email( $input['email'] );
	}
	if ( isset( $input['hotline_email'] ) ) {
		$out['hotline_email'] = sanitize_email( $input['hotline_email'] );
	}
	if ( isset( $input['email_parent'] ) ) {
		$out['email_parent'] = sanitize_email( $input['email_parent'] );
	}
	if ( isset( $input['founded'] ) ) {
		$out['founded'] = (int) $input['founded'];
	}

	$out['stats'] = array();
	if ( isset( $input['stats'] ) && is_array( $input['stats'] ) ) {
		foreach ( $input['stats'] as $stat ) {
			if ( empty( $stat['value'] ) && empty( $stat['label'] ) ) {
				continue;
			}
			$out['stats'][] = array(
				'value' => sanitize_text_field( $stat['value'] ?? '' ),
				'label' => sanitize_text_field( $stat['label'] ?? '' ),
			);
		}
	}
	if ( ! $out['stats'] ) {
		$out['stats'] = $defaults['stats'];
	}

	$out['links'] = array();
	foreach ( array( 'parent', 'mchs', 'kuzbass', 'rutube', 'vk', 'telegram' ) as $key ) {
		$out['links'][ $key ] = isset( $input['links'][ $key ] ) ? esc_url_raw( $input['links'][ $key ] ) : ( $current['links'][ $key ] ?? '' );
	}

	if ( isset( $input['phones_raw'] ) ) {
		$phones = array();
		foreach ( preg_split( '/\R/u', (string) $input['phones_raw'] ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( count( $parts ) >= 3 && '' !== $parts[0] ) {
				$phones[] = array(
					'department' => sanitize_text_field( $parts[0] ),
					'position'   => sanitize_text_field( $parts[1] ),
					'phone'      => sanitize_text_field( $parts[2] ),
				);
			}
		}
		update_option( 'nvgso_phones', $phones );
	}

	if ( isset( $input['milestones_raw'] ) ) {
		$items = array();
		foreach ( preg_split( '/\R/u', (string) $input['milestones_raw'] ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( count( $parts ) >= 3 && '' !== $parts[0] ) {
				$items[] = array(
					'year'  => sanitize_text_field( $parts[0] ),
					'title' => sanitize_text_field( $parts[1] ),
					'text'  => sanitize_text_field( implode( ' | ', array_slice( $parts, 2 ) ) ),
				);
			}
		}
		update_option( 'nvgso_milestones', $items );
	}

	if ( isset( $input['zone_raw'] ) ) {
		$items = array();
		foreach ( preg_split( '/\R/u', (string) $input['zone_raw'] ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( count( $parts ) >= 1 && '' !== $parts[0] ) {
				$items[] = array(
					'name' => sanitize_text_field( $parts[0] ),
					'note' => sanitize_text_field( $parts[1] ?? '' ),
				);
			}
		}
		update_option( 'nvgso_zone', $items );
	}

	return $out;
}

add_action(
	'admin_menu',
	function () {
		add_options_page(
			'Сайт NVGSO',
			'Сайт NVGSO',
			'manage_options',
			'nvgso-settings',
			'nvgso_settings_page'
		);
	}
);

function nvgso_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = nvgso_settings();
	?>
	<div class="wrap">
		<h1>Настройки сайта NVGSO</h1>
		<p>Эти данные выводятся в шапке, подвале, на главной и в контактах. После сохранения проверьте сайт.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'nvgso_settings_group' ); ?>
			<h2 class="title">Организация и контакты</h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="org_name">Короткое название</label></th>
					<td><input class="regular-text" id="org_name" name="nvgso_settings[org_name]" value="<?php echo esc_attr( $s['org_name'] ); ?>"></td></tr>
				<tr><th><label for="org_full">Полное название</label></th>
					<td><textarea class="large-text" rows="2" id="org_full" name="nvgso_settings[org_full]"><?php echo esc_textarea( $s['org_full'] ); ?></textarea></td></tr>
				<tr><th><label for="address">Адрес</label></th>
					<td><input class="large-text" id="address" name="nvgso_settings[address]" value="<?php echo esc_attr( $s['address'] ); ?>"></td></tr>
				<tr><th><label for="address_short">Адрес (кратко)</label></th>
					<td><input class="regular-text" id="address_short" name="nvgso_settings[address_short]" value="<?php echo esc_attr( $s['address_short'] ); ?>"></td></tr>
				<tr><th><label for="hotline_label">Название дежурной службы</label></th>
					<td><input class="regular-text" id="hotline_label" name="nvgso_settings[hotline_label]" value="<?php echo esc_attr( $s['hotline_label'] ); ?>"><p class="description">Выводится в шапке, подвале и блоке дежурной службы.</p></td></tr>
				<tr><th><label for="hotline_email">Электронная почта в шапке</label></th>
					<td><input class="regular-text" type="email" id="hotline_email" name="nvgso_settings[hotline_email]" value="<?php echo esc_attr( $s['hotline_email'] ); ?>"><p class="description">Показывается под телефоном в блоке приёмной в шапке.</p></td></tr>
				<tr><th><label for="phone_duty">Телефон дежурной службы</label></th>
					<td><input class="regular-text" id="phone_duty" name="nvgso_settings[phone_duty]" value="<?php echo esc_attr( $s['phone_duty'] ); ?>"></td></tr>
				<tr><th><label for="phone_office">Телефон приёмной</label></th>
					<td><input class="regular-text" id="phone_office" name="nvgso_settings[phone_office]" value="<?php echo esc_attr( $s['phone_office'] ); ?>"></td></tr>
				<tr><th><label for="email">Электронная почта</label></th>
					<td><input class="regular-text" type="email" id="email" name="nvgso_settings[email]" value="<?php echo esc_attr( $s['email'] ); ?>"></td></tr>
				<tr><th><label for="hours">Режим работы</label></th>
					<td><textarea class="large-text" rows="2" id="hours" name="nvgso_settings[hours]"><?php echo esc_textarea( $s['hours'] ); ?></textarea></td></tr>
				<tr><th><label for="hours_short">Режим работы (кратко)</label></th>
					<td><input class="regular-text" id="hours_short" name="nvgso_settings[hours_short]" value="<?php echo esc_attr( $s['hours_short'] ); ?>"></td></tr>
			</table>

			<h2 class="title">Главная страница</h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="hero_title">Заголовок</label></th>
					<td><textarea class="large-text" rows="2" id="hero_title" name="nvgso_settings[hero_title]"><?php echo esc_textarea( $s['hero_title'] ); ?></textarea></td></tr>
				<tr><th><label for="hero_lead">Подзаголовок</label></th>
					<td><textarea class="large-text" rows="3" id="hero_lead" name="nvgso_settings[hero_lead]"><?php echo esc_textarea( $s['hero_lead'] ); ?></textarea></td></tr>
				<tr><th><label for="hero_tag">Метка над заголовком</label></th>
					<td><input class="large-text" id="hero_tag" name="nvgso_settings[hero_tag]" value="<?php echo esc_attr( $s['hero_tag'] ); ?>"></td></tr>
				<tr><th><label for="about_text">Текст «О филиале»</label></th>
					<td><textarea class="large-text" rows="5" id="about_text" name="nvgso_settings[about_text]"><?php echo esc_textarea( $s['about_text'] ); ?></textarea></td></tr>
			</table>

			<h2 class="title">Показатели на главной</h2>
			<table class="form-table" role="presentation">
				<?php for ( $i = 0; $i < 4; $i++ ) : ?>
					<?php $stat = $s['stats'][ $i ] ?? array( 'value' => '', 'label' => '' ); ?>
					<tr>
						<th>Показатель <?php echo (int) ( $i + 1 ); ?></th>
						<td>
							<input name="nvgso_settings[stats][<?php echo (int) $i; ?>][value]" value="<?php echo esc_attr( $stat['value'] ); ?>" placeholder="1942" style="width:8em">
							<input name="nvgso_settings[stats][<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $stat['label'] ); ?>" placeholder="год образования" style="width:24em">
						</td>
					</tr>
				<?php endfor; ?>
			</table>

			<h2 class="title">Телефонный справочник</h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="phones_raw">Строки справочника</label></th>
					<td>
						<textarea class="large-text" rows="8" id="phones_raw" name="nvgso_settings[phones_raw]"><?php
						$phone_lines = array();
						foreach ( nvgso_get_phones() as $phone ) {
							$phone_lines[] = ( $phone['department'] ?? '' ) . ' | ' . ( $phone['position'] ?? '' ) . ' | ' . ( $phone['phone'] ?? '' );
						}
						echo esc_textarea( implode( "\n", $phone_lines ) );
						?></textarea>
						<p class="description">Одна строка: <code>Подразделение | Должность | Телефон</code>. Пустые строки игнорируются.</p>
					</td>
				</tr>
			</table>

			<h2 class="title">История отряда (вехи)</h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="milestones_raw">Вехи</label></th>
					<td>
						<textarea class="large-text" rows="10" id="milestones_raw" name="nvgso_settings[milestones_raw]"><?php
						$milestone_lines = array();
						foreach ( nvgso_get_milestones() as $item ) {
							$milestone_lines[] = ( $item['year'] ?? '' ) . ' | ' . ( $item['title'] ?? '' ) . ' | ' . ( $item['text'] ?? '' );
						}
						echo esc_textarea( implode( "\n", $milestone_lines ) );
						?></textarea>
						<p class="description">Одна строка: <code>Год | Заголовок | Текст</code>.</p>
					</td>
				</tr>
			</table>

			<h2 class="title">Зона ответственности</h2>
			<p>Список предприятий редактируется в отдельной удобной форме: название, примечание, порядок, добавление и удаление.</p>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=nvgso-zone' ) ); ?>">Редактировать зону ответственности</a></p>

			<h2 class="title">Порядок реагирования</h2>
			<p>Заголовок и шаги блока «От сигнала до спасения» на странице «Структура».</p>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'options-general.php?page=nvgso-response' ) ); ?>">Редактировать порядок реагирования</a></p>

			<h2 class="title">Ссылки</h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="link_parent">Сайт ФГУП «ВГСЧ»</label></th>
					<td><input class="large-text" id="link_parent" name="nvgso_settings[links][parent]" value="<?php echo esc_attr( $s['links']['parent'] ); ?>"></td></tr>
				<tr><th><label for="link_mchs">Сайт МЧС России</label></th>
					<td><input class="large-text" id="link_mchs" name="nvgso_settings[links][mchs]" value="<?php echo esc_attr( $s['links']['mchs'] ); ?>"></td></tr>
			</table>

			<?php submit_button( 'Сохранить настройки' ); ?>
		</form>
	</div>
	<?php
}
