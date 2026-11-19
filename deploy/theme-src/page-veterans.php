<?php
/**
 * Ветеранам и память.
 */
get_header();
$s          = nvgso_settings();
$milestones = nvgso_get_milestones();

echo nvgso_page_hero(
	'Ветеранам и память',
	$s['veterans_lead'],
	array( array( 'label' => 'О филиале' ), array( 'label' => 'Ветеранам и память' ) ),
	array(
		array( 'icon' => 'star', 'text' => 'Ветеранская организация' ),
		array( 'icon' => 'history', 'text' => 'Книга памяти' ),
		array( 'icon' => 'users', 'text' => 'Поддержка ветеранов' ),
	)
);
?>
<section class="section">
	<div class="container about-split">
		<div class="about-split__text">
			<div class="eyebrow">Память и преемственность</div>
			<h2 class="section__title">Подвиг горноспасателей — часть истории Кузбасса</h2>
			<p style="margin-top:1rem">Отряд участвовал в ликвидации последствий сложных аварий на шахтах «Распадская», «Ульяновская», «Юбилейная» и других предприятиях. Мы бережно храним память о погибших товарищах и поддерживаем ветеранов службы.</p>
			<p style="margin-top:1rem">Ветеранская организация отряда участвует в памятных мероприятиях, работе с молодёжью и сохранении истории горноспасательного дела. Материалы о ветеранах публикуются в разделе «Фотогалерея» и в книге памяти.</p>
			<blockquote class="memory-quote">«Земля не прощает ошибок. Наша задача — сделать всё, чтобы люди вернулись домой».<footer>Из истории Новокузнецкого ВГСО</footer></blockquote>
			<div style="margin-top:1.5rem;display:flex;gap:.6rem;flex-wrap:wrap">
				<a class="btn btn-primary" href="<?php echo esc_url( nvgso_page_url( 'gallery' ) ); ?>"><?php echo nvgso_icon( 'gallery', '', 18 ); ?>Фотохроника</a>
				<a class="btn btn-ghost" href="<?php echo esc_url( nvgso_page_url( 'contacts' ) . '#feedback' ); ?>"><?php echo nvgso_icon( 'mail', '', 18 ); ?>Связаться с отрядом</a>
			</div>
		</div>
		<div><img class="memory-photo" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/memory-1600.webp' ); ?>" width="800" height="532" alt="Возложение цветов к мемориалу погибшим горноспасателям" loading="lazy"></div>
	</div>
</section>

<?php if ( $milestones ) : ?>
<section class="section section--soft">
	<div class="container">
		<?php echo nvgso_section_head( 'История', 'Вехи отряда', 'Основные события в истории Новокузнецкого ВГСО.' ); ?>
		<div class="timeline">
			<?php foreach ( array_slice( $milestones, 0, 6 ) as $item ) : ?>
				<div class="timeline__item"><div class="timeline__year"><?php echo esc_html( $item['year'] ); ?></div><h3 class="timeline__title"><?php echo esc_html( $item['title'] ); ?></h3><p class="timeline__text"><?php echo esc_html( $item['text'] ); ?></p></div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="section section--navy">
	<div class="container">
		<div class="grid grid--3">
			<div class="contact-card"><div class="contact-card__icon"><?php echo nvgso_icon( 'star', '', 20 ); ?></div><div><div class="contact-card__label">Ветеранская организация</div><div class="contact-card__value">Совет ветеранов отряда</div><div class="contact-card__note">Памятные мероприятия и работа с молодёжью</div></div></div>
			<div class="contact-card"><div class="contact-card__icon"><?php echo nvgso_icon( 'history', '', 20 ); ?></div><div><div class="contact-card__label">Книга памяти</div><div class="contact-card__value">Сохранение истории</div><div class="contact-card__note">Материалы о ветеранах и погибших товарищах</div></div></div>
			<div class="contact-card"><div class="contact-card__icon"><?php echo nvgso_icon( 'users', '', 20 ); ?></div><div><div class="contact-card__label">Поддержка</div><div class="contact-card__value">Взаимодействие с ветеранами</div><div class="contact-card__note">Обращения — через приёмную отряда</div></div></div>
		</div>
	</div>
</section>

<?php echo nvgso_cta_band( 'Хотите поделиться материалами о ветеранах?', 'Свяжитесь с нами — поможем сохранить историю отряда.', nvgso_page_url( 'contacts' ) . '#feedback', 'Написать в отряд', nvgso_page_url( 'gallery' ), 'Фотогалерея' ); ?>
<?php get_footer(); ?>
