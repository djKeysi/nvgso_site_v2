<?php
/**
 * Главная страница.
 */
get_header();
$s          = nvgso_settings();
$news       = nvgso_get_news( 4 );
$docs       = nvgso_get_docs( 4 );
$divisions  = nvgso_get_divisions();
$photo_01   = nvgso_image_by_key( 'photo-01' );
$photo_10   = nvgso_image_by_key( 'photo-10' );
$photo_15   = nvgso_image_by_key( 'photo-15' );
$fallback   = get_stylesheet_directory_uri() . '/assets/img/about-1600.webp';
$activities = array(
	array( 'icon' => 'helmet', 'title' => 'Горноспасательные работы', 'text' => 'Выезд на аварии, разведка горных выработок, спасение людей и ликвидация очагов возгорания.', 'anchor' => 'rescue' ),
	array( 'icon' => 'pulse', 'title' => 'Профилактика аварий', 'text' => 'Обследование предприятий, контроль проветривания и готовности аварийно-спасательных формирований.', 'anchor' => 'prevention' ),
	array( 'icon' => 'book', 'title' => 'Обучение и подготовка', 'text' => 'Тренировки, учебно-методические сборы и соревнования по горноспасательному делу.', 'anchor' => 'training' ),
	array( 'icon' => 'handshake', 'title' => 'Взаимодействие с МЧС', 'text' => 'Совместные учения, обмен информацией и работа в единой системе реагирования.', 'anchor' => 'cooperation' ),
);
?>
<section class="hero">
	<div class="container hero__in">
		<div class="hero__content">
			<div class="hero__tag"><?php echo nvgso_icon( 'shield', '', 15 ); ?><?php echo esc_html( $s['hero_tag'] ); ?></div>
			<h1><?php echo esc_html( $s['hero_title'] ); ?></h1>
			<p class="hero__lead"><?php echo esc_html( $s['hero_lead'] ); ?></p>
			<div class="hero__actions">
				<a class="btn btn-red" href="<?php echo esc_url( nvgso_page_url( 'contacts' ) . '#feedback' ); ?>"><?php echo nvgso_icon( 'send', '', 18 ); ?>Написать в отряд</a>
				<a class="btn btn-outline" href="<?php echo esc_url( nvgso_page_url( 'about' ) ); ?>"><?php echo nvgso_icon( 'shield', '', 18 ); ?>Об отряде</a>
				<a class="btn btn-outline" href="<?php echo esc_attr( nvgso_tel( $s['phone_duty'] ) ); ?>"><?php echo nvgso_icon( 'phone', '', 18 ); ?>Дежурная служба</a>
			</div>
		</div>
		<div class="hero__facts" aria-label="Отряд в цифрах">
			<?php foreach ( $s['stats'] as $stat ) : ?>
				<div class="hero__fact"><strong><?php echo esc_html( $stat['value'] ); ?></strong><span><?php echo esc_html( $stat['label'] ); ?></span></div>
			<?php endforeach; ?>
		</div>
	</div>
</section>





<section class="section" id="about">
	<div class="container about-split">
		<div class="about-split__text">
			<div class="eyebrow">О филиале</div>
			<h2 class="section__title"><?php echo esc_html( $s['org_name'] ); ?></h2>
			<?php foreach ( preg_split( '/\n{2,}/', $s['about_text'] ) as $paragraph ) : ?>
				<p style="margin-top:1rem"><?php echo esc_html( $paragraph ); ?></p>
			<?php endforeach; ?>
			<ul class="about-facts">
				<li><?php echo nvgso_icon( 'checkCircle', '', 20 ); ?><span>Образован 12 июля 1942 года на базе Араличевского и Байдаевского взводов</span></li>
				<li><?php echo nvgso_icon( 'checkCircle', '', 20 ); ?><span>Пять взводов и 28 отделений в Новокузнецке, Междуреченске и Калтане</span></li>
				<li><?php echo nvgso_icon( 'checkCircle', '', 20 ); ?><span>Дважды признавался лучшим горноспасательным отрядом МЧС России</span></li>
			</ul>
			<a class="btn btn-primary" href="<?php echo esc_url( nvgso_page_url( 'about' ) ); ?>"><?php echo nvgso_icon( 'arrowRight', '', 18 ); ?>История и командование</a>
		</div>
		<div class="about-media">
			<img class="about-media__main" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/about-1600.webp' ); ?>" alt="Горноспасатели отряда демонстрируют оснащение" loading="lazy">
			<img class="about-media__sub" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/photo-05-700.webp' ); ?>" alt="Построение личного состава и выездная техника" loading="lazy">
			<div class="about-media__badge"><strong>1942</strong><span>год основания</span></div>
			<div class="about-media__credit">Фото: пресс-служба ФГУП «ВГСЧ» / МЧС России</div>
		</div>
	</div>
</section>



<section class="section section--soft" id="activity">
	<div class="container">
		<?php echo nvgso_section_head( 'Деятельность', 'Что делает отряд', 'Главные направления работы Новокузнецкого ВГСО — от экстренного реагирования до профилактики аварий.', nvgso_more_link( nvgso_page_url( 'activity' ), 'Все направления' ) ); ?>
		<div class="activity-grid">
			<?php foreach ( $activities as $item ) : ?>
				<a class="activity-card" href="<?php echo esc_url( nvgso_page_url( 'activity' ) . '#' . $item['anchor'] ); ?>">
					<span class="activity-card__icon"><?php echo nvgso_icon( $item['icon'], '', 26 ); ?></span>
					<span><h3><?php echo esc_html( $item['title'] ); ?></h3><p><?php echo esc_html( $item['text'] ); ?></p><span class="activity-card__more">Подробнее <?php echo nvgso_icon( 'arrowRight', '', 13 ); ?></span></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="section" id="structure">
	<div class="container">
		<?php echo nvgso_section_head( 'Структура', 'Пять взводов в трёх городах', 'Подразделения отряда дислоцируются в Новокузнецке, Междуреченске и Калтане и находятся в постоянной готовности.', nvgso_more_link( nvgso_page_url( 'structure' ), 'Схема подразделений' ) ); ?>
		<div class="structure-panel">
			<div class="structure-map">
				<div class="structure-map__hq"><?php echo nvgso_icon( 'building', '', 26 ); ?><span><strong>Управление отряда</strong><span><?php echo esc_html( $s['address_short'] ); ?></span></span></div>
				<div class="structure-map__line" aria-hidden="true"></div>
				<div class="structure-map__list">
					<?php if ( $divisions ) : ?>
						<?php foreach ( $divisions as $division ) : ?>
							<div class="structure-map__item"><span><strong><?php echo esc_html( get_the_title( $division ) ); ?></strong> <span>· <?php echo esc_html( get_post_meta( $division->ID, '_nvgso_city', true ) ); ?></span></span></div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				<div class="structure-map__foot">Всего в составе отряда — 28 отделений, включая оперативный пункт управления.</div>
			</div>
			<div class="structure-side">
				<div class="structure-stat"><strong>5</strong><span>взводов обеспечивают горноспасательное обслуживание предприятий юга Кузбасса</span></div>
				<div class="structure-stat"><strong>28</strong><span>отделений несут дежурство и выезжают на аварии</span></div>
				<div class="structure-stat"><strong>3</strong><span>города: Новокузнецк, Междуреченск и Калтан</span></div>
				<div class="structure-side__cta"><strong>Зона ответственности</strong>Предприятия угольной промышленности Кузбасса, включая шахты «Распадская», «Юбилейная», «Ульяновская» и другие.<br><a class="text-link" style="margin-top:.6rem" href="<?php echo esc_url( nvgso_page_url( 'structure' ) ); ?>">Подробнее <?php echo nvgso_icon( 'arrowRight', '', 14 ); ?></a></div>
			</div>
		</div>
	</div>
</section>

<?php if ( $news->have_posts() ) : ?>
<section class="section section--soft" id="news">
	<div class="container">
		<?php echo nvgso_section_head( 'Пресс-центр', 'Новости и события', 'Учения, профилактическая работа, награждения и памятные даты отряда.', nvgso_more_link( nvgso_page_url( 'news' ), 'Все новости' ) ); ?>
		<?php
		$first = true;
		while ( $news->have_posts() ) :
			$news->the_post();
			if ( $first ) {
				echo nvgso_news_feature( get_the_ID() );
				echo '<div class="news-grid" style="margin-top:1.25rem">';
				$first = false;
			} else {
				echo nvgso_news_card( get_the_ID() );
			}
		endwhile;
		if ( ! $first ) {
			echo '</div>';
		}
		wp_reset_postdata();
		?>
	</div>
</section>
<?php endif; ?>

<?php if ( $docs->have_posts() ) : ?>
<section class="section" id="documents">
	<div class="container">
		<?php echo nvgso_section_head( 'Документы', 'Официальные материалы', 'Положения, лицензии, памятки и документы, регламентирующие деятельность филиала.', nvgso_more_link( nvgso_page_url( 'documents' ), 'Все документы' ) ); ?>
		<div class="docs-list">
			<?php
			while ( $docs->have_posts() ) {
				$docs->the_post();
				echo nvgso_doc_row( get_the_ID() );
			}
			wp_reset_postdata();
			?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="section section--navy" id="memory">
	<div class="container about-split">
		<div>
			<div class="eyebrow">Память</div>
			<h2 class="section__title">Подвиг горноспасателей — часть истории Кузбасса</h2>
			<p style="margin-top:1rem;color:#c6d3e7">Отряд участвовал в ликвидации последствий сложных аварий на шахтах «Распадская», «Ульяновская», «Юбилейная» и других предприятиях. Мы бережно храним память о погибших товарищах и поддерживаем ветеранов службы.</p>
			<blockquote class="memory-quote">«Земля не прощает ошибок. Наша задача — сделать всё, чтобы люди вернулись домой».<footer>Из истории Новокузнецкого ВГСО</footer></blockquote>
			<div style="margin-top:1.5rem;display:flex;gap:.6rem;flex-wrap:wrap">
				<a class="btn btn-gold" href="<?php echo esc_url( nvgso_page_url( 'veterans' ) ); ?>"><?php echo nvgso_icon( 'star', '', 18 ); ?>Ветеранам и память</a>
				<a class="btn btn-outline" href="<?php echo esc_url( nvgso_page_url( 'gallery' ) ); ?>"><?php echo nvgso_icon( 'gallery', '', 18 ); ?>Фотохроника</a>
			</div>
		</div>
		<div><img class="memory-photo" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/memory-1600.webp' ); ?>" width="800" height="532" alt="Возложение цветов к мемориалу погибшим горноспасателям" loading="lazy"></div>
	</div>
</section>

<section class="section section--tight" id="contacts-short">
	<div class="container">
		<div><div class="quick-grid quick-grid--3">
			<a class="quick-card" href="<?php echo esc_url( nvgso_page_url( 'documents' ) ); ?>">
				<span class="quick-card__arrow"><?php echo nvgso_icon( 'arrowRight', '', 18 ); ?></span>
				<span class="quick-card__icon"><?php echo nvgso_icon( 'doc', '', 24 ); ?></span>
				<span class="quick-card__title">Найти документ</span>
				<span class="quick-card__text">Положения, лицензии, памятки и нормативные акты</span>
			</a>
			<a class="quick-card" href="<?php echo esc_url( nvgso_page_url( 'contacts' ) . '#phones' ); ?>">
				<span class="quick-card__arrow"><?php echo nvgso_icon( 'arrowRight', '', 18 ); ?></span>
				<span class="quick-card__icon"><?php echo nvgso_icon( 'phone', '', 24 ); ?></span>
				<span class="quick-card__title">Телефонный справочник</span>
				<span class="quick-card__text">Контакты подразделений и должностных лиц</span>
			</a>
			<a class="quick-card" href="<?php echo esc_url( nvgso_page_url( 'contacts' ) . '#feedback' ); ?>">
				<span class="quick-card__arrow"><?php echo nvgso_icon( 'arrowRight', '', 18 ); ?></span>
				<span class="quick-card__icon"><?php echo nvgso_icon( 'mail', '', 24 ); ?></span>
				<span class="quick-card__title">Задать вопрос</span>
				<span class="quick-card__text">Обратная связь с отрядом и порядок обращения</span>
			</a>
		</div></div>
		<div style="margin-top:1.25rem;display:flex;justify-content:center">
			<a class="btn btn-primary contacts-cta" href="<?php echo esc_url( nvgso_page_url( 'contacts' ) ); ?>"><?php echo nvgso_icon( 'route', '', 19 ); ?>Все контакты и схема проезда</a>
		</div>
	</div>
</section>

<?php get_footer(); ?>
