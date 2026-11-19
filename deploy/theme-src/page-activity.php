<?php
/**
 * Деятельность.
 */
get_header();
$s     = nvgso_settings();
$cards = array(
	array( 'id' => 'rescue', 'icon' => 'helmet', 'title' => 'Горноспасательные работы', 'text' => 'Выезд на аварии на опасных производственных объектах, разведка горных выработок, поиск и спасение людей, ликвидация очагов возгорания и последствий выбросов.', 'points' => array( 'Круглосуточная готовность дежурных отделений', 'Работа в изолирующих дыхательных аппаратах', 'Оказание первой помощи и эвакуация пострадавших' ) ),
	array( 'id' => 'prevention', 'icon' => 'pulse', 'title' => 'Профилактика аварий', 'text' => 'Плановые обследования предприятий, контроль состояния проветривания, противопожарной защиты и готовности персонала к действиям при аварии.', 'points' => array( 'Обследование горных выработок', 'Проверка средств защиты и связи', 'Разработка мероприятий по устранению замечаний' ) ),
	array( 'id' => 'training', 'icon' => 'book', 'title' => 'Обучение и подготовка', 'text' => 'Профессиональная подготовка личного состава, учебно-методические сборы, тренировки и соревнования по горноспасательному делу.', 'points' => array( 'Практические занятия на полигоне', 'Изучение нормативных документов', 'Обмен опытом с подразделениями ВГСЧ' ) ),
	array( 'id' => 'cooperation', 'icon' => 'handshake', 'title' => 'Взаимодействие с МЧС России', 'text' => 'Работа в единой системе предупреждения и ликвидации чрезвычайных ситуаций, совместные учения и информационный обмен.', 'points' => array( 'Совместные тренировки и штабные учения', 'Привлечение аэромобильных групп', 'Международное сотрудничество' ) ),
	array( 'id' => 'medical', 'icon' => 'pulse', 'title' => 'Медицинская служба', 'text' => 'Реанимационно-противошоковая группа и медицинское обеспечение горноспасательных работ. Оказание помощи пострадавшим на месте и при транспортировке.', 'points' => array( 'Готовность к работе в шахте', 'Первая и реанимационная помощь', 'Взаимодействие с медицинскими организациями' ) ),
	array( 'id' => 'equipment', 'icon' => 'shield', 'title' => 'Техника и оснащение', 'text' => 'Современные дыхательные аппараты, средства связи, аварийно-спасательный инструмент и специальный транспорт, поддерживаемые в постоянной готовности.', 'points' => array( 'Изолирующие респираторы', 'Приборы контроля газовой среды', 'Аварийно-спасательные автомобили' ) ),
);

echo nvgso_page_hero(
	'Деятельность',
	$s['activity_lead'],
	array( array( 'label' => 'О филиале' ), array( 'label' => 'Деятельность' ) ),
	array(
		array( 'icon' => 'siren', 'text' => 'Аварийное реагирование 24/7' ),
		array( 'icon' => 'pulse', 'text' => 'Профилактика и обследования' ),
		array( 'icon' => 'book', 'text' => 'Обучение личного состава' ),
	)
);
?>
<section class="section">
	<div class="container">
		<?php echo nvgso_section_head( 'Направления работы', 'Чем занимается отряд', 'Основные задачи филиала — спасение людей, предупреждение аварий и поддержание готовности сил и средств.' ); ?>
		<div class="grid grid--2">
			<?php foreach ( $cards as $card ) : ?>
				<article class="division-card" id="<?php echo esc_attr( $card['id'] ); ?>" style="padding:1.5rem">
					<div class="division-card__top"><span class="activity-card__icon"><?php echo nvgso_icon( $card['icon'], '', 26 ); ?></span><span class="badge badge--soft">Направление</span></div>
					<h3 style="font-size:1.15rem"><?php echo esc_html( $card['title'] ); ?></h3>
					<p style="font-size:.9063rem"><?php echo esc_html( $card['text'] ); ?></p>
					<ul style="margin:.25rem 0 0;padding-left:1.1rem;font-size:.8438rem;color:var(--muted)">
						<?php foreach ( $card['points'] as $point ) : ?><li><?php echo esc_html( $point ); ?></li><?php endforeach; ?>
					</ul>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="section section--soft">
	<div class="container">
		<?php echo nvgso_section_head( 'Как это работает', 'От профилактики до ликвидации аварии', 'Работа строится на постоянном взаимодействии с предприятиями и органами управления.' ); ?>
		<div class="activity-grid">
			<div class="activity-card"><span class="activity-card__icon"><?php echo nvgso_icon( 'search', '', 26 ); ?></span><span><h3>Обследование</h3><p>Плановые проверки выработок, вентиляции, средств защиты и готовности персонала.</p></span></div>
			<div class="activity-card"><span class="activity-card__icon"><?php echo nvgso_icon( 'doc', '', 26 ); ?></span><span><h3>Планирование</h3><p>Участие в разработке планов ликвидации аварий и мероприятий по повышению безопасности.</p></span></div>
			<div class="activity-card"><span class="activity-card__icon"><?php echo nvgso_icon( 'book', '', 26 ); ?></span><span><h3>Подготовка</h3><p>Тренировки и учения, отработка действий в условиях, приближенных к реальным.</p></span></div>
			<div class="activity-card"><span class="activity-card__icon"><?php echo nvgso_icon( 'siren', '', 26 ); ?></span><span><h3>Реагирование</h3><p>Круглосуточный выезд, спасение людей, ликвидация аварии и разбор действий.</p></span></div>
		</div>
	</div>
</section>

<section class="section">
	<div class="container">
		<?php echo nvgso_section_head( 'Оснащение', 'Техника и оборудование', 'Отряд оснащён современными средствами для выполнения горноспасательных работ любой сложности.' ); ?>
		<div class="filter-chips">
			<?php foreach ( array( 'Изолирующие респираторы', 'Регенеративные патроны', 'Приборы контроля газов', 'Средства связи', 'Аварийно-спасательный инструмент', 'Осветительное оборудование', 'Специальный автотранспорт', 'Медицинское оборудование' ) as $tag ) : ?>
				<span class="chip"><?php echo esc_html( $tag ); ?></span>
			<?php endforeach; ?>
		</div>
		<p class="muted" style="font-size:.875rem;max-width:48rem">Точный перечень техники и оборудования, а также порядок его применения определяются нормативными документами и условиями договоров на горноспасательное обслуживание.</p>
	</div>
</section>

<?php echo nvgso_cta_band( 'Нужна консультация по горноспасательному обслуживанию?', 'Направьте официальный запрос в приёмную отряда — специалисты подготовят ответ.', nvgso_page_url( 'contacts' ) . '#feedback', 'Направить запрос', nvgso_page_url( 'documents' ), 'Документы' ); ?>
<?php get_footer(); ?>
