<?php
/**
 * Страница новостей (страница записей).
 */
get_header();
$s        = nvgso_settings();
$page_id  = (int) get_option( 'page_for_posts' );
$title    = $page_id ? get_the_title( $page_id ) : 'Новости';
$current  = isset( $_GET['cat'] ) ? (int) $_GET['cat'] : 0;
$cats     = nvgso_news_categories();

echo nvgso_page_hero(
	$title,
	$s['news_lead'],
	array( array( 'label' => 'Пресс-центр' ), array( 'label' => 'Новости' ) ),
	array(
		array( 'icon' => 'news', 'text' => 'События и учения' ),
		array( 'icon' => 'calendar', 'text' => 'Архив новостей' ),
	)
);
?>
<section class="section">
	<div class="container">
		<div class="filter-chips" role="group" aria-label="Категории новостей">
			<a class="chip<?php echo ! $current ? ' is-active' : ''; ?>" href="<?php echo esc_url( nvgso_page_url( 'news' ) ); ?>">Все</a>
			<?php foreach ( $cats as $cat ) : ?>
				<a class="chip<?php echo $current === $cat->term_id ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'cat', $cat->term_id, nvgso_page_url( 'news' ) ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
			<?php endforeach; ?>
		</div>

		<?php if ( have_posts() ) : ?>
			<div class="news-grid">
				<?php while ( have_posts() ) : the_post(); ?>
					<?php echo nvgso_news_card( get_the_ID() ); ?>
				<?php endwhile; ?>
			</div>
			<?php echo nvgso_pagination(); ?>
		<?php else : ?>
			<div class="empty-state"><?php echo nvgso_icon( 'news', '', 34 ); ?><h3>Новостей пока нет</h3><p>События и учения отряда будут публиковаться в этом разделе.</p></div>
		<?php endif; ?>
	</div>
</section>
<?php get_footer(); ?>
