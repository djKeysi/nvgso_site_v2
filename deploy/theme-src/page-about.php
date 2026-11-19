<?php
/**
 * Об отряде.
 */
get_header();
$s          = nvgso_settings();
$milestones = nvgso_get_milestones();
$zone       = nvgso_get_zone();

echo nvgso_page_hero(
	'Об отряде',
	$s['about_lead'],
	array( array( 'label' => 'О филиале' ), array( 'label' => 'Об отряде' ) ),
	array(
		array( 'icon' => 'history', 'text' => 'Основан в 1942 году' ),
		array( 'icon' => 'structure', 'text' => '5 взводов, 28 отделений' ),
		array( 'icon' => 'star', 'text' => 'Лучший отряд МЧС России (2013, 2014)' ),
	)
);
?>
<section class="section">
	<div class="container about-split">
		<div class="about-split__text">
			<div class="eyebrow">История и сегодняшний день</div>
			<h2 class="section__title">От взвода до крупнейшего отряда Кузбасса</h2>
			<?php foreach ( preg_split( '/\n{2,}/', $s['about_text'] ) as $paragraph ) : ?>
				<p style="margin-top:1rem"><?php echo esc_html( $paragraph ); ?></p>
			<?php endforeach; ?>
			<p style="margin-top:1rem">Отряд неоднократно привлекался к ликвидации последствий аварий на угольных предприятиях Кузбасса, а его личный состав направлялся для оказания помощи в другие регионы России и за рубеж. В 2013 и 2014 годах филиал «Новокузнецкий ВГСО» признавался лучшим горноспасательным отрядом МЧС России.</p>
			<div style="margin-top:1.5rem;display:flex;gap:.6rem;flex-wrap:wrap">
				<a class="btn btn-primary" href="<?php echo esc_url( nvgso_page_url( 'structure' ) ); ?>"><?php echo nvgso_icon( 'structure', '', 18 ); ?>Структура отряда</a>
				<a class="btn btn-ghost" href="<?php echo esc_url( nvgso_page_url( 'documents' ) ); ?>"><?php echo nvgso_icon( 'doc', '', 18 ); ?>Документы</a>
			</div>
		</div>
		<div class="about-media">
			<img class="about-media__main" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/about-1600.webp' ); ?>" alt="Горноспасатели отряда у специального автомобиля" loading="lazy">
			<img class="about-media__sub" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/about-900.webp' ); ?>" alt="Общий сбор личного состава отряда" loading="lazy">
			<div class="about-media__badge"><strong>1942</strong><span>год основания</span></div>
			<div class="about-media__credit">Фото: пресс-служба ФГУП «ВГСЧ» / МЧС России</div>
		</div>
	</div>
</section>

<section class="section section--soft">
	<div class="container">
		<?php echo nvgso_section_head( 'Ключевые факты', 'Отряд в цифрах и датах', '' ); ?>
		<div class="grid grid--4">
			<div class="structure-stat"><strong>84</strong><span>года со дня образования отряда в 2026 году</span></div>
			<div class="structure-stat"><strong>5</strong><span>взводов в Новокузнецке, Междуреченске и Калтане</span></div>
			<div class="structure-stat"><strong>28</strong><span>отделений в составе подразделений</span></div>
			<div class="structure-stat"><strong>2</strong><span>награды «Лучший горноспасательный отряд МЧС России»</span></div>
		</div>
	</div>
</section>

<?php if ( $milestones ) : ?>
<section class="section">
	<div class="container">
		<?php echo nvgso_section_head( 'История', 'Основные вехи', 'От первых взводов Араличевского месторождения до современного филиала ФГУП «ВГСЧ».' ); ?>
		<div class="timeline">
			<?php foreach ( $milestones as $item ) : ?>
				<div class="timeline__item">
					<div class="timeline__year"><?php echo esc_html( $item['year'] ); ?></div>
					<h3 class="timeline__title"><?php echo esc_html( $item['title'] ); ?></h3>
					<p class="timeline__text"><?php echo esc_html( $item['text'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $zone ) : ?>
<section class="section">
	<div class="container">
		<?php echo nvgso_section_head( $s['zone_eyebrow'], $s['zone_title'], $s['zone_lead'] ); ?>
		<div class="grid grid--3">
			<?php foreach ( $zone as $item ) : ?>
				<div class="contact-card">
					<div class="contact-card__icon"><?php echo nvgso_icon( 'mountain', '', 20 ); ?></div>
					<div><div class="contact-card__value"><?php echo esc_html( $item['name'] ); ?></div><div class="contact-card__note"><?php echo esc_html( $item['note'] ); ?></div></div>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="muted" style="font-size:.8125rem;margin-top:1rem">Перечень приведён по данным о работах последних десятилетий и может уточняться. Полный список обслуживаемых предприятий определяется договорами.</p>
	</div>
</section>
<?php endif; ?>

<?php echo nvgso_cta_band( 'Хотите узнать больше о работе отряда?', 'Свяжитесь с нами или посмотрите фотохронику учений и торжественных мероприятий.', nvgso_page_url( 'gallery' ), 'Фотогалерея', nvgso_page_url( 'contacts' ), 'Контакты' ); ?>
<?php get_footer(); ?>
