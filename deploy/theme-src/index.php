<?php
/**
 * Резервный шаблон.
 */
get_header();
echo nvgso_page_hero( 'Сайт Новокузнецкого ВГСО' );
?>
<section class="section"><div class="container">
	<?php if ( have_posts() ) : ?>
		<div class="news-grid">
			<?php while ( have_posts() ) : the_post(); ?>
				<?php if ( 'post' === get_post_type() ) : ?>
					<?php echo nvgso_news_card( get_the_ID() ); ?>
				<?php else : ?>
					<div class="contact-card"><div><div class="contact-card__label"><?php echo esc_html( get_post_type() ); ?></div><div class="contact-card__value"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></div></div></div>
				<?php endif; ?>
			<?php endwhile; ?>
		</div>
		<?php echo nvgso_pagination(); ?>
	<?php else : ?>
		<div class="empty-state"><h3>Ничего не найдено</h3><p>Воспользуйтесь поиском по сайту.</p></div>
	<?php endif; ?>
</div></section>
<?php get_footer(); ?>
