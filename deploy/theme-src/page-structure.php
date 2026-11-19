<?php
/**
 * Структура отряда.
 */
get_header();
$s         = nvgso_settings();
$divisions = nvgso_get_divisions();
$hq        = null;
foreach ( $divisions as $division ) {
	$type = get_post_meta( $division->ID, '_nvgso_type', true );
	if ( 'Штаб' === $type ) {
		$hq = $division;
	}
}

$services = array(
	array(
		'title' => 'Оперативная служба',
		'items' => array( 'ВГСВ № 1', 'ВГСВ № 2', 'ВГСВ № 3', 'ВГСВ № 4' ),
	),
	array(
		'title' => 'Медицинская служба',
		'items' => array( 'МБЭР 1', 'МБЭР 2', 'МБЭР 3', 'МБЭР 4' ),
	),
	array(
		'title' => 'Учебный центр',
		'items' => array(),
	),
	array(
		'title' => 'Гражданская оборона',
		'items' => array(),
	),
);

echo nvgso_page_hero(
	'Структура',
	$s['structure_lead'],
	array( array( 'label' => 'О филиале' ), array( 'label' => 'Структура' ) ),
	array(
		array( 'icon' => 'structure', 'text' => '5 взводов' ),
		array( 'icon' => 'users', 'text' => '28 отделений' ),
		array( 'icon' => 'pin', 'text' => 'Новокузнецк · Междуреченск · Калтан' ),
	)
);
?>
<section class="section">
	<div class="container">
		<?php echo nvgso_section_head( 'Организационная схема', 'Как устроен отряд', 'Управление отрядом осуществляет командир. В оперативном подчинении находятся взводы, дежурные отделения и вспомогательные службы.' ); ?>
		<div class="org-chart">
			<div class="org-node org-node--hq"><strong>Командир отряда</strong><span><?php echo esc_html( $hq ? get_the_title( $hq ) : 'Управление отряда' ); ?> · <?php echo esc_html( $s['address_short'] ); ?></span></div>
			<div class="org-connector" aria-hidden="true"></div>
			<div class="org-node"><strong>Заместители командира и службы</strong><span>Оперативная и медицинская службы, учебный центр, гражданская оборона</span></div>
			<div class="org-connector" aria-hidden="true"></div>
			<div class="org-services">
				<?php foreach ( $services as $service ) : ?>
					<div class="org-service<?php echo empty( $service['items'] ) ? ' org-service--single' : ''; ?>">
						<div class="org-service__head"><?php echo esc_html( $service['title'] ); ?></div>
						<?php if ( ! empty( $service['items'] ) ) : ?>
							<div class="org-service__items">
								<?php foreach ( $service['items'] as $item ) : ?>
									<div class="org-subnode"><?php echo esc_html( $item ); ?></div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="org-notes">
			<span class="badge badge--soft"><?php echo nvgso_icon( 'users', '', 13 ); ?> 28 отделений</span>
			<span class="badge badge--soft"><?php echo nvgso_icon( 'clock', '', 13 ); ?> Дежурство 24/7</span>
			<span class="badge badge--soft"><?php echo nvgso_icon( 'pin', '', 13 ); ?> 3 города Кузбасса</span>
		</div>
	</div>
</section>

<section class="section section--soft">
	<div class="container">
		<?php echo nvgso_section_head( 'Дислокация', 'Подразделения отряда', 'Сведения о местах дислокации и контактных телефонах подразделений.' ); ?>
		<div class="division-grid">
			<?php foreach ( $divisions as $division ) : ?>
				<?php
				$city    = get_post_meta( $division->ID, '_nvgso_city', true );
				$address = get_post_meta( $division->ID, '_nvgso_address', true );
				$phone   = get_post_meta( $division->ID, '_nvgso_phone', true );
				$units_n = get_post_meta( $division->ID, '_nvgso_units', true );
				$type    = get_post_meta( $division->ID, '_nvgso_type', true );
				?>
				<article class="division-card">
					<div class="division-card__top"><h3><?php echo esc_html( get_the_title( $division ) ); ?></h3><span class="badge badge--soft"><?php echo esc_html( $type ); ?></span></div>
					<div class="division-card__city"><?php echo esc_html( $city ); ?></div>
					<p><?php echo esc_html( wp_strip_all_tags( $division->post_content ) ); ?></p>
					<div class="division-card__meta">
						<span><?php echo nvgso_icon( 'pin', '', 14 ); ?><?php echo esc_html( $address ); ?></span>
						<?php if ( $phone && ! nvgso_is_placeholder( $phone ) ) : ?>
							<span><?php echo nvgso_icon( 'phone', '', 14 ); ?><a href="<?php echo esc_attr( nvgso_tel( $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></span>
						<?php endif; ?>
						<?php if ( $units_n ) : ?>
							<span><?php echo nvgso_icon( 'users', '', 14 ); ?><?php echo esc_html( $units_n ); ?></span>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php $response_steps = nvgso_get_response_steps(); ?>
<?php if ( $response_steps ) : ?>
<section class="section">
	<div class="container">
		<?php echo nvgso_section_head( $s['response_eyebrow'], $s['response_title'], $s['response_lead'] ); ?>
		<div class="timeline">
			<?php foreach ( $response_steps as $step ) : ?>
				<div class="timeline__item">
					<div class="timeline__year"><?php echo esc_html( $step['number'] ); ?></div>
					<h3 class="timeline__title"><?php echo esc_html( $step['title'] ); ?></h3>
					<p class="timeline__text"><?php echo esc_html( $step['text'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php echo nvgso_cta_band( 'Вопросы по структуре и зоне обслуживания?', 'Обратитесь в приёмную отряда или дежурную службу — мы ответим на ваш запрос.', nvgso_page_url( 'contacts' ) . '#feedback', 'Написать в отряд', nvgso_page_url( 'contacts' ), 'Контакты' ); ?>
<?php get_footer(); ?>
