<?php
/**
 * Документы.
 */
get_header();
$s        = nvgso_settings();
$cat      = isset( $_GET['doc_cat'] ) ? sanitize_title( wp_unslash( $_GET['doc_cat'] ) ) : 'all';
$terms    = get_terms( array( 'taxonomy' => 'doc_category', 'hide_empty' => true ) );
$docs     = nvgso_get_docs( -1, 'all' === $cat ? '' : $cat );

echo nvgso_page_hero(
	'Документы',
	$s['documents_lead'],
	array( array( 'label' => 'Пресс-центр' ), array( 'label' => 'Документы' ) ),
	array(
		array( 'icon' => 'doc', 'text' => 'Положения и устав' ),
		array( 'icon' => 'shield', 'text' => 'Лицензии и свидетельства' ),
		array( 'icon' => 'book', 'text' => 'Памятки и инструкции' ),
	)
);
?>
<section class="section">
	<div class="container">
		<div class="filter-chips" role="group" aria-label="Категории документов">
			<a class="chip<?php echo 'all' === $cat ? ' is-active' : ''; ?>" href="<?php echo esc_url( nvgso_page_url( 'documents' ) ); ?>">Все</a>
			<?php if ( ! is_wp_error( $terms ) ) : ?>
				<?php foreach ( $terms as $term ) : ?>
					<a class="chip<?php echo $cat === $term->slug ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'doc_cat', $term->slug, nvgso_page_url( 'documents' ) ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<?php if ( $docs->have_posts() ) : ?>
			<div class="docs-list">
				<?php
				while ( $docs->have_posts() ) {
					$docs->the_post();
					echo nvgso_doc_row( get_the_ID() );
				}
				wp_reset_postdata();
				?>
			</div>
		<?php else : ?>
			<div class="empty-state"><?php echo nvgso_icon( 'doc', '', 34 ); ?><h3>Документы не найдены</h3><p>В этой категории пока нет опубликованных документов.</p><a class="btn btn-ghost" href="<?php echo esc_url( nvgso_page_url( 'documents' ) ); ?>">Показать все документы</a></div>
		<?php endif; ?>

		<p class="muted" style="font-size:.8125rem;margin-top:1.5rem">Документы публикуются в формате PDF. Для просмотра может потребоваться программа чтения PDF. Официальные копии предоставляются по запросу в приёмную отряда.</p>
	</div>
</section>

<?php echo nvgso_cta_band( 'Не нашли нужный документ?', 'Направьте запрос в приёмную — мы подскажем, где получить официальную копию.', nvgso_page_url( 'contacts' ) . '#feedback', 'Направить запрос', nvgso_page_url( 'contacts' ), 'Контакты' ); ?>
<?php get_footer(); ?>
