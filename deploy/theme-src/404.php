<?php
/**
 * Страница 404: аккуратная навигация вместо тупика.
 */
get_header();
?>
<section class="page-hero error-hero">
	<div class="container error-hero__in">
		<div class="error-hero__code" aria-hidden="true">404</div>
		<div class="error-hero__text">
			<div class="eyebrow">Страница не найдена</div>
			<h1>Такой страницы нет</h1>
			<p class="page-hero__lead">Возможно, она была перемещена, удалена или в адресе есть опечатка. Найдите нужный раздел через поиск или перейдите по ссылкам ниже.</p>
			<form class="error-hero__search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="sr-only" for="s404">Поиск по сайту</label>
				<input class="input" id="s404" type="search" name="s" placeholder="Например: документы, контакты, новости">
				<button class="btn btn-primary" type="submit"><?php echo nvgso_icon( 'search', '', 18 ); ?>Найти</button>
			</form>
			<div class="error-hero__actions">
				<a class="btn btn-gold" href="<?php echo esc_url( home_url( '/' ) ); ?>">На главную</a>
				<a class="btn btn-outline" href="<?php echo esc_url( nvgso_page_url( 'contacts' ) ); ?>">Связаться с нами</a>
			</div>
		</div>
	</div>
</section>

<section class="section">
	<div class="container">
		<?php echo nvgso_section_head( 'Навигация', 'Куда перейти', 'Основные разделы сайта — всегда под рукой.' ); ?>
		<div class="grid grid--3">
			<a class="quick-card" href="<?php echo esc_url( nvgso_page_url( 'about' ) ); ?>">
				<span class="quick-card__icon"><?php echo nvgso_icon( 'building', '', 22 ); ?></span>
				<span class="quick-card__title">Об отряде</span>
				<span class="quick-card__text">История, структура и командование</span>
				<span class="quick-card__arrow"><?php echo nvgso_icon( 'arrowRight', '', 18 ); ?></span>
			</a>
			<a class="quick-card" href="<?php echo esc_url( nvgso_page_url( 'documents' ) ); ?>">
				<span class="quick-card__icon"><?php echo nvgso_icon( 'doc', '', 22 ); ?></span>
				<span class="quick-card__title">Документы</span>
				<span class="quick-card__text">Устав, лицензии и памятки</span>
				<span class="quick-card__arrow"><?php echo nvgso_icon( 'arrowRight', '', 18 ); ?></span>
			</a>
			<a class="quick-card" href="<?php echo esc_url( nvgso_page_url( 'news' ) ); ?>">
				<span class="quick-card__icon"><?php echo nvgso_icon( 'news', '', 22 ); ?></span>
				<span class="quick-card__title">Новости</span>
				<span class="quick-card__text">События, учения и жизнь отряда</span>
				<span class="quick-card__arrow"><?php echo nvgso_icon( 'arrowRight', '', 18 ); ?></span>
			</a>
			<a class="quick-card" href="<?php echo esc_url( nvgso_page_url( 'gallery' ) ); ?>">
				<span class="quick-card__icon"><?php echo nvgso_icon( 'gallery', '', 22 ); ?></span>
				<span class="quick-card__title">Фотогалерея</span>
				<span class="quick-card__text">Фотохроника и памятные события</span>
				<span class="quick-card__arrow"><?php echo nvgso_icon( 'arrowRight', '', 18 ); ?></span>
			</a>
			<a class="quick-card" href="<?php echo esc_url( nvgso_page_url( 'contacts' ) ); ?>">
				<span class="quick-card__icon"><?php echo nvgso_icon( 'phone', '', 22 ); ?></span>
				<span class="quick-card__title">Контакты</span>
				<span class="quick-card__text">Адреса, телефоны и обратная связь</span>
				<span class="quick-card__arrow"><?php echo nvgso_icon( 'arrowRight', '', 18 ); ?></span>
			</a>
			<a class="quick-card" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<span class="quick-card__icon"><?php echo nvgso_icon( 'home', '', 22 ); ?></span>
				<span class="quick-card__title">Главная</span>
				<span class="quick-card__text">Вернуться на главную страницу</span>
				<span class="quick-card__arrow"><?php echo nvgso_icon( 'arrowRight', '', 18 ); ?></span>
			</a>
		</div>
	</div>
</section>
<?php get_footer(); ?>
