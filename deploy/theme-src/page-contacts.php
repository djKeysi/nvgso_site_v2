<?php
/**
 * Контакты и обратная связь.
 */
get_header();
$s         = nvgso_settings();
$divisions = nvgso_get_divisions();
$phones    = nvgso_get_phones();
$status    = isset( $_GET['feedback'] ) ? sanitize_key( wp_unslash( $_GET['feedback'] ) ) : '';

echo nvgso_page_hero(
	'Контакты',
	$s['contacts_lead'],
	array( array( 'label' => 'Контакты' ) ),
	array(
		array( 'icon' => 'pin', 'text' => $s['address_short'] ),
		array( 'icon' => 'clock', 'text' => $s['hours_short'] ),
		array( 'icon' => 'siren', 'text' => 'Дежурная служба 24/7' ),
	)
);
?>
<section class="section">
	<div class="container">
		<div class="grid grid--3">
			<div class="contact-card contact-card--accent"><div class="contact-card__icon"><?php echo nvgso_icon( 'pin', '', 20 ); ?></div><div><div class="contact-card__label">Адрес</div><div class="contact-card__value"><?php echo esc_html( $s['address'] ); ?></div><div class="contact-card__note">Куйбышевский район, Новокузнецк</div></div></div>
			<div class="contact-card contact-card--accent"><div class="contact-card__icon"><?php echo nvgso_icon( 'phone', '', 20 ); ?></div><div><div class="contact-card__label">Дежурная служба</div><div class="contact-card__value"><a href="<?php echo esc_attr( nvgso_tel( $s['phone_duty'] ) ); ?>"><?php echo esc_html( $s['phone_duty'] ); ?></a></div><div class="contact-card__note">Круглосуточно, без выходных</div></div></div>
			<div class="contact-card contact-card--accent"><div class="contact-card__icon"><?php echo nvgso_icon( 'mail', '', 20 ); ?></div><div><div class="contact-card__label">Электронная почта</div><div class="contact-card__value"><a href="mailto:<?php echo esc_attr( $s['email'] ); ?>"><?php echo esc_html( $s['email'] ); ?></a></div><div class="contact-card__note">Для официальных обращений</div></div></div>
		</div>
		<div class="contact-card" style="margin-top:1rem">
			<div class="contact-card__icon"><?php echo nvgso_icon( 'clock', '', 20 ); ?></div>
			<div><div class="contact-card__label">Режим работы</div><div class="contact-card__value" style="font-size:1rem"><?php echo esc_html( $s['hours'] ); ?></div></div>
		</div>
	</div>
</section>

<section class="section section--soft" id="phones">
	<div class="container">
		<?php echo nvgso_section_head( 'Телефонный справочник', 'Телефоны подразделений и служб', 'При обращении по телефону уточните подразделение и должность.' ); ?>
		<?php if ( $phones ) : ?>
			<div class="table-wrap">
				<table class="data-table">
					<thead><tr><th>Подразделение</th><th>Должность</th><th>Телефон</th></tr></thead>
					<tbody>
						<?php foreach ( $phones as $phone ) : ?>
							<tr>
								<td><?php echo esc_html( $phone['department'] ?? '' ); ?></td>
								<td><?php echo esc_html( $phone['position'] ?? '' ); ?></td>
								<td><?php echo ! empty( $phone['phone'] ) ? '<a href="' . esc_attr( nvgso_tel( $phone['phone'] ) ) . '">' . esc_html( $phone['phone'] ) . '</a>' : '—'; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<div class="empty-state"><h3>Справочник заполняется</h3><p>Телефоны подразделений будут опубликованы в ближайшее время.</p></div>
		<?php endif; ?>
	</div>
</section>

<?php if ( $divisions ) : ?>
<section class="section">
	<div class="container">
		<?php echo nvgso_section_head( 'Подразделения', 'Контакты подразделений', 'Адреса и телефоны взводов и отдельных постов.' ); ?>
		<div class="division-grid">
			<?php foreach ( $divisions as $division ) : ?>
				<?php $phone = get_post_meta( $division->ID, '_nvgso_phone', true ); ?>
				<article class="division-card">
					<div class="division-card__top"><h3><?php echo esc_html( get_the_title( $division ) ); ?></h3><span class="badge badge--soft"><?php echo esc_html( get_post_meta( $division->ID, '_nvgso_type', true ) ); ?></span></div>
					<div class="division-card__city"><?php echo esc_html( get_post_meta( $division->ID, '_nvgso_city', true ) ); ?></div>
					<div class="division-card__meta">
						<span><?php echo nvgso_icon( 'pin', '', 14 ); ?><?php echo esc_html( get_post_meta( $division->ID, '_nvgso_address', true ) ); ?></span>
						<?php if ( $phone && ! nvgso_is_placeholder( $phone ) ) : ?><span><?php echo nvgso_icon( 'phone', '', 14 ); ?><a href="<?php echo esc_attr( nvgso_tel( $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></span><?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="section section--soft" id="feedback">
	<div class="container">
		<div class="about-split">
			<div class="about-split__text">
				<div class="eyebrow">Обратная связь</div>
				<h2 class="section__title">Написать в отряд</h2>
				<p style="margin-top:1rem">Заполните форму — обращение поступит в приёмную отряда. Поля, отмеченные звёздочкой, обязательны. Отправляя форму, вы соглашаетесь на обработку персональных данных в соответствии с <a href="<?php echo esc_url( nvgso_page_url( 'privacy-policy' ) ); ?>">Политикой обработки персональных данных</a>.</p>
				<ul class="about-facts" style="margin-top:1.2rem">
					<li><?php echo nvgso_icon( 'clock', '', 20 ); ?><span>Срок рассмотрения обращения — до 30 календарных дней.</span></li>
					<li><?php echo nvgso_icon( 'phone', '', 20 ); ?><span>При аварии звоните в дежурную службу: <a href="<?php echo esc_attr( nvgso_tel( $s['phone_duty'] ) ); ?>"><?php echo esc_html( $s['phone_duty'] ); ?>.</span></li>
					<li><?php echo nvgso_icon( 'lock', '', 20 ); ?><span>Персональные данные используются только для рассмотрения обращения.</span></li>
				</ul>
			</div>
			<div>
				<?php
				$status_messages = array(
					'ok'         => array( 'success', 'Обращение отправлено', 'Спасибо! Мы свяжемся с вами.' ),
					'error'      => array( 'error', 'Не удалось отправить обращение', 'Проверьте обязательные поля и попробуйте ещё раз.' ),
					'file_type'  => array( 'error', 'Неподходящий файл', 'Можно приложить только файл PDF или DOCX.' ),
					'file_size'  => array( 'error', 'Файл слишком большой', 'Размер вложения не должен превышать 15 МБ.' ),
					'file_error' => array( 'error', 'Ошибка загрузки', 'Не удалось загрузить вложение. Попробуйте ещё раз.' ),
					'file_virus' => array( 'error', 'Файл не прошёл проверку', 'Антивирус обнаружил угрозу. Приложите другой файл.' ),
					'file_av_unavailable' => array( 'error', 'Проверка вложений недоступна', 'Попробуйте позже или отправьте обращение без вложения.' ),
					'rate_limit' => array( 'error', 'Слишком много обращений', 'С этого IP уже отправлено несколько обращений. Попробуйте позже.' ),
				);
				if ( isset( $status_messages[ $status ] ) ) :
					$msg = $status_messages[ $status ];
					?>
					<?php if ( 'success' === $msg[0] ) : ?>
						<div class="form-success"><?php echo nvgso_icon( 'checkCircle', '', 22 ); ?><span><strong><?php echo esc_html( $msg[1] ); ?></strong><?php echo esc_html( $msg[2] ); ?></span></div>
					<?php else : ?>
						<div class="form-error" role="alert" style="font-size:.9375rem;margin-bottom:1rem"><strong><?php echo esc_html( $msg[1] ); ?>.</strong> <?php echo esc_html( $msg[2] ); ?></div>
					<?php endif; ?>
				<?php endif; ?>
				<form class="form-card" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="feedback-form">
					<input type="hidden" name="action" value="nvgso_feedback">
					<?php wp_nonce_field( 'nvgso_feedback', 'nvgso_feedback_nonce' ); ?>
					<div class="form-grid">
						<div class="field"><label for="fb-name">Ваше имя *</label><input class="input" id="fb-name" type="text" name="name" required autocomplete="name"></div>
						<div class="field"><label for="fb-email">E-mail *</label><input class="input" id="fb-email" type="email" name="email" required autocomplete="email"></div>
						<div class="field"><label for="fb-phone">Телефон</label><input class="input" id="fb-phone" type="tel" name="phone" autocomplete="tel"></div>
						<div class="field"><label for="fb-topic">Тема обращения *</label>
							<select class="input" id="fb-topic" name="topic" required>
								<option value="Общий вопрос">Общий вопрос</option>
								<option value="Горноспасательное обслуживание">Горноспасательное обслуживание</option>
								<option value="Документы и сведения">Документы и сведения</option>
								<option value="Обращение гражданина">Обращение гражданина</option>
								<option value="Сотрудничество">Сотрудничество</option>
								<option value="Другое">Другое</option>
							</select>
						</div>
						<div class="field field--full"><label for="fb-message">Текст обращения *</label><textarea class="input" id="fb-message" name="message" rows="6" required></textarea></div>
						<div class="field field--full">
							<label for="fb-attachment">Вложение (PDF или DOCX, до 15 МБ)</label>
							<input class="input" type="file" id="fb-attachment" name="attachment" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
							<p class="form-note">Необязательно. Один файл: PDF или DOCX, не больше 15 МБ. Проверка файла может занять несколько секунд.</p>
							<p class="form-note" id="fb-attachment-info" aria-live="polite"></p>
							<p class="form-error" id="fb-attachment-error" aria-live="polite"></p>
						</div>
					</div>
					<label class="form-consent"><input type="checkbox" name="consent" value="1" required><span>Согласен(на) на обработку персональных данных в соответствии с <a href="<?php echo esc_url( nvgso_page_url( 'privacy-policy' ) ); ?>" target="_blank" rel="noopener">Политикой</a> *</span></label>
					<div class="honeypot" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
					<button class="btn btn-primary" type="submit" style="margin-top:1rem"><?php echo nvgso_icon( 'send', '', 18 ); ?>Отправить обращение</button>
					<p class="form-note">Нажимая кнопку, вы подтверждаете достоверность указанных сведений.</p>
				</form>
			</div>
		</div>
	</div>
</section>

<section class="section">
	<div class="container">
		<div class="grid" style="grid-template-columns:1fr">
			<div class="contact-card"><div class="contact-card__icon"><?php echo nvgso_icon( 'route', '', 20 ); ?></div><div><div class="contact-card__label">Схема проезда</div><div class="contact-card__value"><a href="https://yandex.ru/maps/?text=<?php echo rawurlencode( $s['map_query'] ); ?>" target="_blank" rel="noopener">Открыть на карте</a></div><div class="contact-card__note"><?php echo esc_html( $s['address'] ); ?></div></div></div>
		</div>
	</div>
</section>
<?php get_footer(); ?>
