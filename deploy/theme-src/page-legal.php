<?php
/**
 * Template Name: Правовая страница
 * Юридические страницы с оглавлением.
 */
get_header();
$s = nvgso_settings();
the_post();
$content = get_the_content();
echo nvgso_page_hero( get_the_title(), get_the_excerpt(), array( array( 'label' => get_the_title() ) ) );
?>
<section class="section">
	<div class="container legal-layout">
		<div class="prose">
			<?php echo apply_filters( 'the_content', $content ); ?>
			<p class="muted" style="font-size:.8125rem;margin-top:2rem">Редакция документа подлежит утверждению Заказчиком. По вопросам обработки персональных данных: <a href="mailto:<?php echo esc_attr( $s['email'] ); ?>"><?php echo esc_html( $s['email'] ); ?></a>.</p>
		</div>
		<?php echo nvgso_toc( $content ); ?>
	</div>
</section>
<?php get_footer(); ?>
