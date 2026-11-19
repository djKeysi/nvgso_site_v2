<?php
/**
 * Фотогалерея.
 */
get_header();
$s     = nvgso_settings();
$album = isset( $_GET['album'] ) ? sanitize_title( wp_unslash( $_GET['album'] ) ) : 'all';
$terms = get_terms( array( 'taxonomy' => 'album', 'hide_empty' => true ) );
$items = nvgso_get_gallery( -1, 'all' === $album ? '' : $album );

echo nvgso_page_hero(
	'Фотогалерея',
	$s['gallery_lead'],
	array( array( 'label' => 'Пресс-центр' ), array( 'label' => 'Фотогалерея' ) ),
	array(
		array( 'icon' => 'gallery', 'text' => 'Учения и обмен опытом' ),
		array( 'icon' => 'star', 'text' => 'Память и торжества' ),
		array( 'icon' => 'users', 'text' => 'Награждения' ),
	)
);
?>
<section class="section">
	<div class="container">
		<div class="gallery-tabs" role="group" aria-label="Альбомы">
			<a class="chip<?php echo 'all' === $album ? ' is-active' : ''; ?>" href="<?php echo esc_url( nvgso_page_url( 'gallery' ) ); ?>">Все</a>
			<?php if ( ! is_wp_error( $terms ) ) : ?>
				<?php foreach ( $terms as $term ) : ?>
					<a class="chip<?php echo $album === $term->slug ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'album', $term->slug, nvgso_page_url( 'gallery' ) ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<?php if ( $items->have_posts() ) : ?>
			<div class="gallery-grid" data-lightbox-group>
				<?php
				while ( $items->have_posts() ) :
					$items->the_post();
					$meta      = nvgso_gallery_meta( get_the_ID() );
					$src       = $meta['image_id'] ? wp_get_attachment_image_url( $meta['image_id'], 'large' ) : '';
					$full      = $meta['image_id'] ? wp_get_attachment_image_url( $meta['image_id'], 'full' ) : $src;
					$date      = $meta['date'] ? $meta['date'] : get_the_date( 'Y-m-d' );
					$video_url = (string) get_post_meta( get_the_ID(), '_nvgso_video_url', true );
					$is_video  = 'video' === $meta['type'] && $video_url;
					$image_html = $meta['image_id'] ? wp_get_attachment_image( $meta['image_id'], 'nvgso-card', false, array( 'alt' => get_the_title(), 'loading' => 'lazy' ) ) : '';
					?>
					<?php if ( $is_video ) : ?>
						<a class="gallery-item" href="<?php echo esc_url( $video_url ); ?>" target="_blank" rel="noopener" data-album="<?php echo esc_attr( $album ); ?>">
							<?php echo $image_html; ?>
							<span class="gallery-item__play"><?php echo nvgso_icon( 'play', '', 18 ); ?></span>
							<span class="gallery-item__caption"><?php echo esc_html( get_the_title() ); ?><span class="gallery-item__date"><?php echo esc_html( nvgso_date( $date ) ); ?> · видео</span></span>
						</a>
					<?php else : ?>
						<a class="gallery-item" href="<?php echo esc_url( $full ); ?>" data-lightbox="<?php echo esc_url( $full ); ?>" data-title="<?php echo esc_attr( get_the_title() ); ?>" data-caption="<?php echo esc_attr( $meta['caption'] ); ?>" data-album="<?php echo esc_attr( $album ); ?>">
							<?php echo $image_html; ?>
							<span class="gallery-item__caption"><?php echo esc_html( get_the_title() ); ?><span class="gallery-item__date"><?php echo esc_html( nvgso_date( $date ) ); ?></span></span>
						</a>
					<?php endif; ?>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
		<?php else : ?>
			<div class="empty-state"><?php echo nvgso_icon( 'gallery', '', 34 ); ?><h3>В этом альбоме пока нет фотографий</h3><p>Выберите другой альбом или вернитесь позже.</p><a class="btn btn-ghost" href="<?php echo esc_url( nvgso_page_url( 'gallery' ) ); ?>">Все альбомы</a></div>
		<?php endif; ?>
	</div>
</section>
<?php get_footer(); ?>
