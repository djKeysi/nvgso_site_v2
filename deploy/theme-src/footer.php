<?php
/**
 * Подвал, cookie-баннер и модальные окна.
 */
$s = nvgso_settings();
?>
</main>

<footer class="footer" id="footer">
	<div class="container">
		<div class="footer__grid">
			<div>
				<div class="footer__brand">
					<img class="footer__logo" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/logo-shield.webp' ); ?>" width="480" height="625" alt="">
					<div><div class="footer__org"><?php echo esc_html( $s['org_name'] ); ?><span><?php echo esc_html( $s['org_parent'] ); ?></span></div></div>
				</div>
				<p class="footer__text"><?php echo esc_html( $s['org_full'] ); ?></p>
				<?php if ( ! empty( $s['footer_description'] ) ) : ?>
					<p class="footer__text" style="margin-top:.8rem"><?php echo nl2br( esc_html( $s['footer_description'] ) ); ?></p>
				<?php endif; ?>
				<?php
				$responsible = (string) $s['footer_fio'];
				if ( $responsible && ! empty( $s['footer_position'] ) ) {
					$responsible .= ', ' . $s['footer_position'];
				} elseif ( ! $responsible ) {
					$responsible = (string) $s['footer_position'];
				}
				?>
				<?php if ( $responsible ) : ?>
					<p class="footer__text footer__responsible"><strong>Ответственный за сайт:</strong> <?php echo esc_html( $responsible ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $s['footer_max_url'] ) || ! empty( $s['footer_vk_url'] ) ) : ?>
					<div class="footer__socials">
						<?php if ( ! empty( $s['footer_max_url'] ) ) : ?>
							<a class="footer__max" href="<?php echo esc_url( $s['footer_max_url'] ); ?>" target="_blank" rel="noopener">
								<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/max.svg' ); ?>" width="28" height="28" alt="MAX">
								<span>Написать в MAX</span>
							</a>
						<?php endif; ?>
						<?php if ( ! empty( $s['footer_vk_url'] ) ) : ?>
							<a class="footer__max footer__vk" href="<?php echo esc_url( $s['footer_vk_url'] ); ?>" target="_blank" rel="noopener">
								<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/vk.svg' ); ?>" width="28" height="28" alt="VK">
								<span>ВКонтакте</span>
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
			<div>
				<h3>Разделы</h3>
				<ul>
					<li><a href="<?php echo esc_url( nvgso_page_url( 'about' ) ); ?>">Об отряде</a></li>
					<li><a href="<?php echo esc_url( nvgso_page_url( 'structure' ) ); ?>">Структура</a></li>
					<li><a href="<?php echo esc_url( nvgso_page_url( 'activity' ) ); ?>">Деятельность</a></li>
					<li><a href="<?php echo esc_url( nvgso_page_url( 'news' ) ); ?>">Новости</a></li>
					<li><a href="<?php echo esc_url( nvgso_page_url( 'gallery' ) ); ?>">Фотогалерея</a></li>
					<li><a href="<?php echo esc_url( nvgso_page_url( 'documents' ) ); ?>">Документы</a></li>
					<li><a href="<?php echo esc_url( nvgso_page_url( 'veterans' ) ); ?>">Ветеранам и память</a></li>
					<li><a href="<?php echo esc_url( nvgso_page_url( 'contacts' ) ); ?>">Контакты</a></li>
					<li><a href="<?php echo esc_url( home_url( '/?s=' ) ); ?>">Поиск по сайту</a></li>
				</ul>
			</div>
			<div>
				<h3>Документы</h3>
				<ul>
					<?php foreach ( nvgso_get_footer_docs() as $doc_link ) : ?>
						<?php
						$doc_url = (string) $doc_link['url'];
						if ( '#cookie-settings' === $doc_url ) {
							$doc_href = '';
						} elseif ( preg_match( '#^(https?://|mailto:|tel:)#i', $doc_url ) ) {
							$doc_href = $doc_url;
						} elseif ( str_starts_with( $doc_url, '#' ) ) {
							$doc_href = $doc_url;
						} else {
							$doc_href = home_url( '/' . ltrim( $doc_url, '/' ) );
						}
						?>
						<?php if ( '#cookie-settings' === $doc_url ) : ?>
							<li><button type="button" class="footer__settings" data-cookie-open><?php echo esc_html( $doc_link['label'] ); ?></button></li>
						<?php else : ?>
							<li><a href="<?php echo esc_url( $doc_href ); ?>"><?php echo esc_html( $doc_link['label'] ); ?></a></li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			</div>
			<div>
				<h3>Контакты</h3>
				<div class="footer__contact"><?php echo nvgso_icon( 'pin', '', 17 ); ?><span><strong>Адрес</strong><?php echo esc_html( $s['address'] ); ?></span></div>
				<div class="footer__contact"><?php echo nvgso_icon( 'phone', '', 17 ); ?><span><strong><?php echo esc_html( $s['hotline_label'] ); ?></strong><a href="<?php echo esc_attr( nvgso_tel( $s['phone_duty'] ) ); ?>"><?php echo esc_html( $s['phone_duty'] ); ?></a></span></div>
				<div class="footer__contact"><?php echo nvgso_icon( 'phone', '', 17 ); ?><span><strong>Приёмная</strong><a href="<?php echo esc_attr( nvgso_tel( $s['phone_office'] ) ); ?>"><?php echo esc_html( $s['phone_office'] ); ?></a></span></div>
				<div class="footer__contact"><?php echo nvgso_icon( 'mail', '', 17 ); ?><span><strong>Электронная почта</strong><a href="mailto:<?php echo esc_attr( $s['email'] ); ?>"><?php echo esc_html( $s['email'] ); ?></a></span></div>
			</div>
		</div>
		<div class="footer__bottom">
			<div>© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $s['org_name'] ); ?> — филиал <?php echo esc_html( $s['org_parent'] ); ?>. Все права защищены.</div>
			<div class="footer__legal">
				<a href="<?php echo esc_url( nvgso_page_url( 'privacy-policy' ) ); ?>">Политика обработки ПДн</a>
				<a href="<?php echo esc_url( nvgso_page_url( 'company-policy' ) ); ?>">Политика компании</a>
				<a href="<?php echo esc_url( nvgso_page_url( 'cookie-policy' ) ); ?>">Политика cookie</a>
				<a href="<?php echo esc_url( home_url( '/sitemap_index.xml' ) ); ?>">Карта сайта</a>
				<a href="<?php echo esc_url( admin_url() ); ?>">Вход для сотрудников</a>
			</div>
		</div>
		<div class="footer__bottom" style="border-top:0;padding-top:0">
			<div class="footer__text"><?php echo wp_kses_post( $s['footer_credit'] ); ?></div>
			<div class="footer__text">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $s['footer_site_name'] ? $s['footer_site_name'] : 'Официальный сайт' ); ?></a>
				· <a href="<?php echo esc_url( $s['links']['parent'] ); ?>" target="_blank" rel="noopener">ФГУП «ВГСЧ»</a>
				· <a href="<?php echo esc_url( $s['links']['mchs'] ); ?>" target="_blank" rel="noopener">МЧС России</a>
			</div>
		</div>
	</div>
</footer>

<div class="cookie-banner" id="cookie-banner" role="region" aria-label="Использование файлов cookie">
	<div class="container cookie-banner__in">
		<div class="cookie-banner__text">
			<strong>Мы используем файлы cookie.</strong> Строго необходимые обеспечивают работу сайта.
			Аналитические и маркетинговые подключаются только с вашего согласия.
			Подробнее — в <a href="<?php echo esc_url( nvgso_page_url( 'privacy-policy' ) ); ?>">Политике обработки персональных данных</a> и <a href="<?php echo esc_url( nvgso_page_url( 'cookie-policy' ) ); ?>">Политике использования cookie</a>.
		</div>
		<div class="cookie-banner__actions">
			<button type="button" class="btn btn-ghost btn-sm" data-cookie="settings">Настроить</button>
			<button type="button" class="btn btn-ghost btn-sm" data-cookie="necessary">Только необходимые</button>
			<button type="button" class="btn btn-primary btn-sm" data-cookie="all">Принять все</button>
		</div>
	</div>
</div>

<div class="modal-backdrop" id="cookie-modal" aria-hidden="true">
	<div class="modal" role="dialog" aria-modal="true" aria-labelledby="cookie-modal-title" tabindex="-1">
		<div class="modal__head">
			<h2 id="cookie-modal-title">Настройки файлов cookie</h2>
			<button type="button" class="modal__close" data-cookie-close aria-label="Закрыть"><?php echo nvgso_icon( 'close', '', 20 ); ?></button>
		</div>
		<p class="modal__lead">Выберите категории, которые разрешено использовать. Строго необходимые файлы cookie обеспечивают работу сайта и не могут быть отключены. Подробнее — в <a href="<?php echo esc_url( nvgso_page_url( 'cookie-policy' ) ); ?>" target="_blank" rel="noopener">Политике использования cookie</a>.</p>
		<div class="cookie-cat">
			<div><h3>Функциональные <span class="cookie-cat__required">Всегда включены</span></h3><p>Запоминают настройки интерфейса: масштаб текста, контраст, выбранные фильтры.</p></div>
			<label class="switch"><input type="checkbox" data-consent-cat="functional" checked disabled aria-label="Функциональные"><span aria-hidden="true"></span></label>
		</div>
		<div class="cookie-cat">
			<div><h3>Аналитические</h3><p>Обезличенная статистика посещаемости (например, Яндекс.Метрика). Скрипты не загружаются до согласия.</p></div>
			<label class="switch"><input type="checkbox" data-consent-cat="analytics" aria-label="Аналитические"><span aria-hidden="true"></span></label>
		</div>
		<div class="cookie-cat">
			<div><h3>Маркетинговые</h3><p>Ретаргетинг и рекламные сети. На сайте по умолчанию не используются.</p></div>
			<label class="switch"><input type="checkbox" data-consent-cat="marketing" aria-label="Маркетинговые"><span aria-hidden="true"></span></label>
		</div>
		<div class="modal__actions">
			<button type="button" class="btn btn-primary" data-cookie-save>Сохранить выбор</button>
			<button type="button" class="btn btn-ghost" data-cookie="all">Принять все</button>
			<button type="button" class="btn btn-ghost" data-cookie="necessary">Только необходимые</button>
		</div>
	</div>
</div>

<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Просмотр изображения">
	<button type="button" class="lightbox__close" data-lightbox-close aria-label="Закрыть"><?php echo nvgso_icon( 'close', '', 24 ); ?></button>
	<button type="button" class="lightbox__prev" data-lightbox-prev aria-label="Предыдущее изображение"><?php echo nvgso_icon( 'chevronLeft', '', 24 ); ?></button>
	<button type="button" class="lightbox__next" data-lightbox-next aria-label="Следующее изображение"><?php echo nvgso_icon( 'chevronRight', '', 24 ); ?></button>
	<figure class="lightbox__figure">
		<div id="lightbox-media"></div>
		<figcaption class="lightbox__caption" id="lightbox-caption"></figcaption>
	</figure>
	<div class="lightbox__counter" id="lightbox-counter"></div>
</div>

<?php wp_footer(); ?>
</body>
</html>
