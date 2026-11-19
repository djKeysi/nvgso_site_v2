<?php
/**
 * Архив категории новостей.
 */
get_header();
$s = nvgso_settings();
echo nvgso_page_hero(
	single_cat_title( '', false ),
	$s['news_lead'],
	array( array( 'label' => 'Пресс-центр' ), array( 'label' => 'Новости', 'url' => nvgso_page_url( 'news' ) ), array( 'label' => single_cat_title( '', false ) ) )
);
?>
<section class="section">
	<div class="container">
		<?php if ( have_posts() ) : ?>
			<div class="news-grid">
				<?php while ( have_posts() ) : the_post(); ?>
					<?php echo nvgso_news_card( get_the_ID() ); ?>
				<?php endwhile; ?>
			</div>
			<?php echo nvgso_pagination(); ?>
		<?php else : ?>
			<div class="empty-state"><h3>В этой категории пока нет новостей</h3><p><a href="<?php echo esc_url( nvgso_page_url( 'news' ) ); ?>">Все новости</a></p></div>
		<?php endif; ?>
	</div>
</section>
<?php get_footer(); ?>
