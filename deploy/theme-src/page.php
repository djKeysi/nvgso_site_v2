<?php
/**
 * Обычная страница.
 */
get_header();
$s = nvgso_settings();
echo nvgso_page_hero( get_the_title(), get_the_excerpt(), array( array( 'label' => get_the_title() ) ) );
?>
<section class="section">
	<div class="container">
		<div class="prose">
			<?php
			while ( have_posts() ) :
				the_post();
				the_content();
			endwhile;
			?>
		</div>
	</div>
</section>
<?php get_footer(); ?>
