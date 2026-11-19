<?php
/**
 * Отдельная новость.
 */
get_header();
$s = nvgso_settings();
while ( have_posts() ) :
	the_post();
	$cat  = nvgso_post_category_label( get_the_ID() );
	$date = get_the_date( 'Y-m-d' );
	$img  = nvgso_thumb_url( get_the_ID(), 'nvgso-wide' );
	?>
	<article class="section">
		<div class="container" style="max-width:56rem">
			<nav class="page-hero__crumbs" aria-label="Хлебные крошки">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a><?php echo nvgso_icon( 'chevronRight', '', 14 ); ?>
				<a href="<?php echo esc_url( nvgso_page_url( 'news' ) ); ?>">Новости</a><?php echo nvgso_icon( 'chevronRight', '', 14 ); ?>
				<span aria-current="page"><?php the_title(); ?></span>
			</nav>
			<div class="article-meta" style="margin-top:1.2rem">
				<span class="badge"><?php echo esc_html( $cat ); ?></span>
				<span class="news-card__date"><?php echo nvgso_icon( 'calendar', '', 14 ); ?><?php echo esc_html( nvgso_date( $date, true ) ); ?></span>
			</div>
			<h1 style="margin:.8rem 0 1rem;font-size:clamp(1.7rem,3.5vw,2.4rem);line-height:1.2"><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?><p class="page-hero__lead" style="margin-bottom:1.5rem"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
			<?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt="<?php the_title_attribute(); ?>" style="width:100%;height:auto;border-radius:8px;margin-bottom:1.5rem" loading="eager"><?php endif; ?>
			<div class="prose"><?php the_content(); ?></div>
			<div style="margin-top:2rem;display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap">
				<a class="btn btn-ghost" href="<?php echo esc_url( nvgso_page_url( 'news' ) ); ?>"><?php echo nvgso_icon( 'chevronLeft', '', 16 ); ?>Все новости</a>
				<a class="btn btn-primary" href="<?php echo esc_url( nvgso_page_url( 'contacts' ) . '#feedback' ); ?>"><?php echo nvgso_icon( 'mail', '', 16 ); ?>Написать в отряд</a>
			</div>
		</div>
	</article>
	<?php
	$related = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 3,
			'post__not_in'        => array( get_the_ID() ),
			'category__in'        => wp_get_post_categories( get_the_ID() ),
			'ignore_sticky_posts' => true,
		)
	);
	if ( $related->have_posts() ) :
		?>
		<section class="section section--soft">
			<div class="container">
				<?php echo nvgso_section_head( 'Читайте также', 'Другие новости', '' ); ?>
				<div class="news-grid">
					<?php while ( $related->have_posts() ) : $related->the_post(); ?>
						<?php echo nvgso_news_card( get_the_ID() ); ?>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
			</div>
		</section>
	<?php endif; ?>
<?php endwhile; ?>
<?php get_footer(); ?>
