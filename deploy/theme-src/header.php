<?php
/**
 * Шапка сайта: верхняя полоса, логотип, меню, панель доступности, поиск.
 */
$s     = nvgso_settings();
$items = nvgso_nav_items();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#071a33">
<?php wp_head(); ?>
<script>
(function () {
	try {
		var on = localStorage.getItem('nvgso_a11y_visually_impaired') === '1';
		var r = document.documentElement;
		if (on) {
			r.style.setProperty('--a11y-scale', '1.25');
			r.setAttribute('data-a11y-large', '1');
			r.setAttribute('data-a11y-contrast', 'hc');
			r.setAttribute('data-a11y-links', '1');
			r.setAttribute('data-a11y-motion', 'off');
		}
	} catch (e) {}
})();
</script>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Перейти к содержанию</a>

<header class="masthead" id="masthead">
	<div class="container">
		<div class="masthead__main">
			<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( $s['org_name'] ); ?> — на главную">
				<img class="brand__logo" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/logo-shield.webp' ); ?>" width="480" height="625" alt="Эмблема филиала «Новокузнецкий ВГСО»">
				<span class="brand__text">
					<span class="brand__parent">МЧС России · ФГУП «ВГСЧ»</span>
					<span class="brand__name"><?php echo esc_html( $s['org_name'] ); ?></span>
					<span class="brand__sub">Официальный сайт филиала</span>
				</span>
			</a>
			<div class="masthead__actions">
				<div class="hotline">
					<span class="hotline__icon"><?php echo nvgso_icon( 'phone', '', 19 ); ?></span>
					<span><span class="hotline__label"><?php echo esc_html( $s['hotline_label'] ); ?></span><span class="hotline__num"><?php echo esc_html( $s['phone_duty'] ); ?></span><?php if ( ! empty( $s['hotline_email'] ) ) : ?><span class="hotline__email"><?php echo nvgso_icon( 'mail', '', 12 ); ?><a href="mailto:<?php echo esc_attr( $s['hotline_email'] ); ?>"><?php echo esc_html( $s['hotline_email'] ); ?></a></span><?php endif; ?></span>
				</div>
				<button type="button" class="icon-btn burger" data-mobile-open aria-label="Открыть меню" aria-expanded="false" aria-controls="mobile-nav"><?php echo nvgso_icon( 'menu', '', 22 ); ?></button>
			</div>
		</div>
		<div class="masthead__nav">
			<div class="masthead__nav-in">
				<nav class="main-nav" aria-label="Основная навигация">
					<ul>
						<?php foreach ( $items as $index => $item ) : ?>
							<?php if ( empty( $item['children'] ) ) : ?>
								<li><a class="nav-link" href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $item['match'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $item['label'] ); ?></a></li>
							<?php else : ?>
								<li class="nav-item nav-item--sub">
									<button type="button" class="nav-link" data-submenu="nav-sub-<?php echo (int) $index; ?>" aria-expanded="false" aria-controls="nav-sub-<?php echo (int) $index; ?>">
										<?php echo esc_html( $item['label'] ); ?><?php echo nvgso_icon( 'chevronDown', '', 16 ); ?>
									</button>
									<ul class="submenu" id="nav-sub-<?php echo (int) $index; ?>">
										<?php foreach ( $item['children'] as $child ) : ?>
											<li><a href="<?php echo esc_url( $child['url'] ); ?>"><?php echo nvgso_icon( $child['icon'], '', 20 ); ?><span><strong><?php echo esc_html( $child['label'] ); ?></strong><span><?php echo esc_html( $child['text'] ); ?></span></span></a></li>
										<?php endforeach; ?>
									</ul>
								</li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				</nav>
				<div class="nav-extra nav-extra__desktop">
					<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( nvgso_page_url( 'contacts' ) . '#phones' ); ?>"><?php echo nvgso_icon( 'phone', '', 15 ); ?>Телефонный справочник</a>
					<a class="btn btn-red btn-sm" href="<?php echo esc_url( nvgso_page_url( 'contacts' ) . '#feedback' ); ?>"><?php echo nvgso_icon( 'send', '', 15 ); ?>Задать вопрос</a>
					<button type="button" class="icon-btn icon-btn--text" data-search-open><?php echo nvgso_icon( 'search', '', 18 ); ?><span>Поиск</span></button>
					<button type="button" class="icon-btn icon-btn--text" data-a11y-toggle aria-pressed="false" aria-label="Включить версию для слабовидящих"><?php echo nvgso_icon( 'accessibility', '', 18 ); ?><span data-a11y-label>Для слабовидящих</span></button>
				</div>
			</div>
		</div>
	</div>
</header>

<button type="button" class="a11y-exit" data-a11y-toggle aria-pressed="true" aria-label="Вернуться к обычной версии сайта"><?php echo nvgso_icon( 'close', '', 17 ); ?><span data-a11y-label>Обычная версия</span></button>

<div class="mobile-nav" id="mobile-nav" role="dialog" aria-modal="true" aria-label="Меню сайта">
	<div class="mobile-nav__head">
		<a class="mobile-nav__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/logo-shield.webp' ); ?>" width="480" height="625" alt=""><?php echo esc_html( $s['org_name'] ); ?></a>
		<button type="button" class="icon-btn" data-mobile-close aria-label="Закрыть меню"><?php echo nvgso_icon( 'close', '', 22 ); ?></button>
	</div>
	<div class="mobile-nav__body">
		<ul>
			<?php foreach ( $items as $index => $item ) : ?>
				<?php if ( empty( $item['children'] ) ) : ?>
					<li><a class="mobile-nav__link" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?><?php echo nvgso_icon( 'chevronRight', '', 18 ); ?></a></li>
				<?php else : ?>
					<li>
						<button type="button" class="mobile-nav__link" data-mobile-sub="mnav-<?php echo (int) $index; ?>" aria-expanded="false"><?php echo esc_html( $item['label'] ); ?><?php echo nvgso_icon( 'chevronDown', '', 18 ); ?></button>
						<ul class="mobile-nav__sub" id="mnav-<?php echo (int) $index; ?>">
							<?php foreach ( $item['children'] as $child ) : ?>
								<li><a href="<?php echo esc_url( $child['url'] ); ?>"><?php echo esc_html( $child['label'] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>
		<div class="mobile-nav__contacts">
			<div class="hotline"><span class="hotline__icon"><?php echo nvgso_icon( 'phone', '', 19 ); ?></span><span><span class="hotline__label"><?php echo esc_html( $s['hotline_label'] ); ?></span><span class="hotline__num"><?php echo esc_html( $s['phone_duty'] ); ?></span><?php if ( ! empty( $s['hotline_email'] ) ) : ?><span class="hotline__email"><?php echo nvgso_icon( 'mail', '', 12 ); ?><a href="mailto:<?php echo esc_attr( $s['hotline_email'] ); ?>"><?php echo esc_html( $s['hotline_email'] ); ?></a></span><?php endif; ?></span></div>
			<p><?php echo nvgso_icon( 'pin', '', 16 ); ?> <?php echo esc_html( $s['address'] ); ?></p>
			<p><?php echo nvgso_icon( 'mail', '', 16 ); ?> <a href="mailto:<?php echo esc_attr( $s['email'] ); ?>"><?php echo esc_html( $s['email'] ); ?></a></p>
		</div>
		<div class="mobile-nav__actions">
			<a class="btn btn-ghost" href="<?php echo esc_url( nvgso_page_url( 'contacts' ) . '#phones' ); ?>"><?php echo nvgso_icon( 'phone', '', 18 ); ?>Телефонный справочник</a>
			<a class="btn btn-red" href="<?php echo esc_url( nvgso_page_url( 'contacts' ) . '#feedback' ); ?>"><?php echo nvgso_icon( 'send', '', 18 ); ?>Задать вопрос</a>
			<button type="button" class="btn btn-ghost" data-search-open><?php echo nvgso_icon( 'search', '', 18 ); ?>Поиск по сайту</button>
			<button type="button" class="btn btn-ghost" data-a11y-toggle aria-pressed="false"><?php echo nvgso_icon( 'accessibility', '', 18 ); ?><span data-a11y-label>Для слабовидящих</span></button>
		</div>
	</div>
</div>

<div class="search-modal" id="search-modal" role="dialog" aria-modal="true" aria-labelledby="search-modal-title">
	<div class="search-modal__box">
		<div class="search-modal__head">
			<h2 id="search-modal-title" class="sr-only">Поиск по сайту</h2>
			<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:flex;gap:.5rem;flex:1">
				<label class="sr-only" for="search-modal-input">Поиск по сайту</label>
				<input id="search-modal-input" class="input" type="search" name="s" placeholder="Учения, политика, контакты…" autocomplete="off">
				<button class="btn btn-primary" type="submit">Найти</button>
			</form>
			<button type="button" class="icon-btn" data-search-close aria-label="Закрыть поиск"><?php echo nvgso_icon( 'close', '', 20 ); ?></button>
		</div>
		<div class="search-modal__empty">Введите запрос — например, «учения», «политика» или «контакты».</div>
	</div>
</div>



<main id="main">
