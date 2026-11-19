<?php
/**
 * Результаты поиска.
 */
get_header();
$s     = nvgso_settings();
$query = get_search_query();
echo nvgso_page_hero( 'Поиск по сайту', $query ? 'Результаты по запросу «' . $query . '»' : 'Введите запрос в поле поиска.', array( array( 'label' => 'Поиск' ) ) );
?>
<section class="section">
	<div class="container">
		<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:flex;gap:.5rem;max-width:40rem;margin-bottom:2rem">
			<label class="sr-only" for="s-page">Поиск</label>
			<input class="input" id="s-page" type="search" name="s" value="<?php echo esc_attr( $query ); ?>" placeholder="Что найти?">
			<button class="btn btn-primary" type="submit">Найти</button>
		</form>

		<?php if ( have_posts() ) : ?>
			<p class="muted" style="margin-bottom:1rem">Найдено результатов: <strong><?php echo (int) $wp_query->found_posts; ?></strong></p>
			<div class="search-results" style="display:grid;gap:.75rem">
				<?php while ( have_posts() ) : the_post(); ?>
					<a class="doc-row" href="<?php the_permalink(); ?>" style="align-items:flex-start">
						<span class="doc-row__icon"><?php echo nvgso_icon( 'search', '', 20 ); ?></span>
						<span class="doc-row__main">
							<span class="doc-row__title"><?php the_title(); ?></span>
							<span class="doc-row__meta"><span><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ?? 'Материал' ); ?></span><span><?php echo esc_html( nvgso_date( get_the_date( 'Y-m-d' ) ) ); ?></span></span>
							<span class="muted" style="font-size:.875rem"><?php echo esc_html( nvgso_excerpt( get_the_excerpt(), 220 ) ); ?></span>
						</span>
					</a>
				<?php endwhile; ?>
			</div>
			<?php echo nvgso_pagination(); ?>
		<?php else : ?>
			<div class="empty-state"><?php echo nvgso_icon( 'search', '', 34 ); ?><h3>Ничего не найдено</h3><p>Попробуйте изменить запрос или воспользуйтесь разделами сайта.</p><a class="btn btn-ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">На главную</a></div>
		<?php endif; ?>
	</div>
</section>
<?php get_footer(); ?>
