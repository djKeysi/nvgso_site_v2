/* =========================================================================
   Оболочка сайта и интерактивные модули:
   шапка, подвал, меню, поиск, cookie-согласие, доступность, формы, лайтбокс.
   ========================================================================= */
(function () {
  'use strict';

  const VGS = window.VGS;
  const icon = (name, cls, size) => (VGS.icon ? VGS.icon(name, cls, size) : '');
  const { esc, ruDate, phoneHref } = VGS;

  const SITE = {
    data: null,
    navItems: [],
    lightboxItems: [],
    lightboxIndex: 0,
    focusTraps: new Map()
  };

  const NAV = [
    { id: 'home', label: 'Главная', href: 'index.html' },
    {
      id: 'about',
      label: 'О филиале',
      href: 'about.html',
      children: [
        { id: 'about', label: 'Об отряде', href: 'about.html', icon: 'shield', text: 'История, командование и зона ответственности' },
        { id: 'structure', label: 'Структура', href: 'structure.html', icon: 'structure', text: 'Взводы, отделения и дислокация' },
        { id: 'activity', label: 'Деятельность', href: 'activity.html', icon: 'helmet', text: 'Горноспасательные работы и профилактика' },
        { id: 'veterans', label: 'Ветеранам и память', href: 'veterans.html', icon: 'star', text: 'Ветеранская организация и книга памяти' }
      ]
    },
    {
      id: 'press',
      label: 'Пресс-центр',
      href: 'news.html',
      children: [
        { id: 'news', label: 'Новости', href: 'news.html', icon: 'news', text: 'События и учения отряда' },
        { id: 'gallery', label: 'Фотогалерея', href: 'gallery.html', icon: 'gallery', text: 'Фото и видео из жизни отряда' },
        { id: 'documents', label: 'Документы', href: 'documents.html', icon: 'doc', text: 'Положения, лицензии и памятки' }
      ]
    },
    { id: 'contacts', label: 'Контакты', href: 'contacts.html' }
  ];

  const PAGE_PARENT = {
    home: 'home',
    about: 'about',
    structure: 'about',
    activity: 'about',
    veterans: 'about',
    news: 'press',
    article: 'press',
    gallery: 'press',
    documents: 'press',
    contacts: 'contacts',
    search: 'home',
    privacy: 'home',
    company: 'home',
    cookie: 'home',
    'not-found': 'home'
  };

  const CATEGORY_TONES = {
    'Учения': 'badge',
    'Профилактика': 'badge badge--gold',
    'Награждения': 'badge badge--soft',
    'Юбилеи': 'badge badge--gold',
    'Память': 'badge badge--soft',
    'События': 'badge'
  };

  function pageId() {
    return document.body.dataset.page || 'home';
  }

  function currentYear() {
    return new Date().getFullYear();
  }

  function tel(value) {
    return 'tel:' + phoneHref(value);
  }

  function activeFor(id) {
    return PAGE_PARENT[pageId()] === id;
  }

  function iconMarkup(name, className, size) {
    return icon(name, className, size);
  }

  /* =========================================================
     Шапка
     ========================================================= */
  function renderHeader(data) {
    const s = data.settings;
    const navHtml = NAV.map((item, index) => {
      if (!item.children) {
        return '<li><a class="nav-link" href="' + esc(item.href) + '"' + (activeFor(item.id) ? ' aria-current="page"' : '') + '>' + esc(item.label) + '</a></li>';
      }
      const sub = item.children.map((child) =>
        '<li><a href="' + esc(child.href) + '">' + iconMarkup(child.icon, '', 20) +
        '<span><strong>' + esc(child.label) + '</strong><span>' + esc(child.text) + '</span></span></a></li>'
      ).join('');
      return '<li class="nav-item nav-item--sub">' +
        '<button type="button" class="nav-link" data-submenu="nav-sub-' + index + '" aria-expanded="false" aria-controls="nav-sub-' + index + '">' +
        esc(item.label) + iconMarkup('chevronDown', '', 16) + '</button>' +
        '<ul class="submenu" id="nav-sub-' + index + '">' + sub + '</ul></li>';
    }).join('');

    return '' +
      '<div class="topbar"><div class="container topbar__in">' +
        '<div class="topbar__left">' +
          '<span class="topbar__badge">' + iconMarkup('shield', '', 15) + 'Официальный сайт</span>' +
          '<span class="topbar__sep" aria-hidden="true"></span>' +
          '<span class="topbar__item topbar__address">' + iconMarkup('pin', '', 15) + esc(s.address_short) + '</span>' +
        '</div>' +
        '<div class="topbar__right">' +
          '<span class="topbar__item topbar__item--phone">' + iconMarkup('phone', '', 15) + '<span>Дежурная служба:</span> <a href="' + tel(s.phone_duty) + '">' + esc(s.phone_duty) + '</a></span>' +
          '<button type="button" class="topbar__btn" data-a11y-open>' + iconMarkup('eye', '', 16) + '<span>Для слабовидящих</span></button>' +
          '<a class="topbar__item topbar__admin" href="admin.html">' + iconMarkup('lock', '', 15) + '<span>Вход для сотрудников</span></a>' +
        '</div>' +
      '</div></div>' +

      '<header class="masthead" id="masthead"><div class="container">' +
        '<div class="masthead__main">' +
          '<a class="brand" href="index.html" aria-label="' + esc(s.org_name) + ' — на главную">' +
            '<img class="brand__logo" src="assets/img/logo-shield.webp" width="480" height="625" alt="Эмблема филиала «Новокузнецкий ВГСО»">' +
            '<span class="brand__text">' +
              '<span class="brand__parent">МЧС России · ФГУП «ВГСЧ»</span>' +
              '<span class="brand__name">' + esc(s.org_name) + '</span>' +
              '<span class="brand__sub">Официальный сайт филиала</span>' +
            '</span>' +
          '</a>' +
          '<div class="masthead__actions">' +
            '<div class="hotline">' +
              '<span class="hotline__icon">' + iconMarkup('phone', '', 19) + '</span>' +
              '<span><span class="hotline__label">Дежурная служба · 24/7</span><a class="hotline__num" href="' + tel(s.phone_duty) + '">' + esc(s.phone_duty) + '</a></span>' +
            '</div>' +
            '<button type="button" class="icon-btn" data-search-open aria-label="Открыть поиск по сайту" title="Поиск по сайту">' + iconMarkup('search', '', 20) + '</button>' +
            '<button type="button" class="icon-btn burger" data-mobile-open aria-label="Открыть меню" aria-expanded="false" aria-controls="mobile-nav">' + iconMarkup('menu', '', 22) + '</button>' +
          '</div>' +
        '</div>' +
        '<div class="masthead__nav"><div class="masthead__nav-in">' +
          '<nav class="main-nav" aria-label="Основная навигация"><ul>' + navHtml + '</ul></nav>' +
          '<div class="nav-extra nav-extra__desktop">' +
            '<button type="button" class="icon-btn icon-btn--text" data-a11y-open aria-label="Настройки доступности сайта">' + iconMarkup('accessibility', '', 18) + '<span>Доступность</span></button>' +
            '<button type="button" class="icon-btn icon-btn--text" data-search-open>' + iconMarkup('search', '', 18) + '<span>Поиск</span></button>' +
          '</div>' +
        '</div></div>' +
      '</div></header>' +

      '<div class="alert-strip"><div class="container alert-strip__in">' +
        iconMarkup('siren', '', 20) +
        '<span><strong>При аварии</strong> немедленно сообщите в дежурную службу отряда: <a href="' + tel(s.phone_duty) + '">' + esc(s.phone_duty) + '</a> или по единому номеру <a href="tel:112">112</a>.</span>' +
      '</div></div>';
  }

  /* =========================================================
     Мобильное меню
     ========================================================= */
  function renderMobileNav(data) {
    const s = data.settings;
    const items = NAV.map((item, index) => {
      if (!item.children) {
        return '<li><a class="mobile-nav__link" href="' + esc(item.href) + '">' + esc(item.label) + iconMarkup('chevronRight', '', 18) + '</a></li>';
      }
      const sub = item.children.map((child) =>
        '<li><a href="' + esc(child.href) + '">' + esc(child.label) + '</a></li>'
      ).join('');
      return '<li><button type="button" class="mobile-nav__link" data-mobile-sub="mnav-' + index + '" aria-expanded="false">' +
        esc(item.label) + iconMarkup('chevronDown', '', 18) + '</button>' +
        '<ul class="mobile-nav__sub" id="mnav-' + index + '">' + sub + '</ul></li>';
    }).join('');

    return '<div class="mobile-nav" id="mobile-nav" role="dialog" aria-modal="true" aria-label="Меню сайта">' +
      '<div class="mobile-nav__head">' +
        '<a class="mobile-nav__brand" href="index.html"><img src="assets/img/logo-shield.webp" width="480" height="625" alt="">' + esc(s.org_name) + '</a>' +
        '<button type="button" class="icon-btn" data-mobile-close aria-label="Закрыть меню">' + iconMarkup('close', '', 22) + '</button>' +
      '</div>' +
      '<div class="mobile-nav__body">' +
        '<ul>' + items + '</ul>' +
        '<div class="mobile-nav__contacts">' +
          '<div class="hotline"><span class="hotline__icon">' + iconMarkup('phone', '', 19) + '</span><span><span class="hotline__label">Дежурная служба · 24/7</span><a class="hotline__num" href="' + tel(s.phone_duty) + '">' + esc(s.phone_duty) + '</a></span></div>' +
          '<p>' + iconMarkup('pin', '', 16) + ' ' + esc(s.address) + '</p>' +
          '<p>' + iconMarkup('mail', '', 16) + ' <a href="mailto:' + esc(s.email) + '">' + esc(s.email) + '</a></p>' +
        '</div>' +
        '<div class="mobile-nav__actions">' +
          '<button type="button" class="btn btn-ghost" data-search-open>' + iconMarkup('search', '', 18) + 'Поиск по сайту</button>' +
          '<button type="button" class="btn btn-ghost" data-a11y-open>' + iconMarkup('accessibility', '', 18) + 'Настройки доступности</button>' +
          '<a class="btn btn-primary" href="contacts.html#feedback">' + iconMarkup('send', '', 18) + 'Написать в отряд</a>' +
          '<a class="btn btn-ghost" href="admin.html">' + iconMarkup('lock', '', 18) + 'Вход для сотрудников</a>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  /* =========================================================
     Подвал
     ========================================================= */
  function renderFooter(data) {
    const s = data.settings;
    return '<footer class="footer" id="footer"><div class="container">' +
      '<div class="footer__grid">' +
        '<div>' +
          '<div class="footer__brand">' +
            '<img class="footer__logo" src="assets/img/logo-shield.webp" width="480" height="625" alt="">' +
            '<div><div class="footer__org">' + esc(s.org_name) + '<span>' + esc(s.org_parent) + '</span></div></div>' +
          '</div>' +
          '<p class="footer__text">' + esc(s.org_full) + '</p>' +
          '<p class="footer__text" style="margin-top:.8rem">Профессиональное аварийно-спасательное формирование, обеспечивающее горноспасательное обслуживание предприятий юга Кузбасса с 1942 года.</p>' +
        '</div>' +
        '<div>' +
          '<h3>Разделы</h3>' +
          '<ul>' +
            '<li><a href="about.html">Об отряде</a></li>' +
            '<li><a href="structure.html">Структура</a></li>' +
            '<li><a href="activity.html">Деятельность</a></li>' +
            '<li><a href="news.html">Новости</a></li>' +
            '<li><a href="gallery.html">Фотогалерея</a></li>' +
            '<li><a href="documents.html">Документы</a></li>' +
            '<li><a href="veterans.html">Ветеранам и память</a></li>' +
            '<li><a href="contacts.html">Контакты</a></li>' +
            '<li><a href="search.html">Поиск по сайту</a></li>' +
          '</ul>' +
        '</div>' +
        '<div>' +
          '<h3>Документы</h3>' +
          '<ul>' +
            '<li><a href="privacy-policy.html">Политика обработки ПДн</a></li>' +
            '<li><a href="company-policy.html">Политика компании</a></li>' +
            '<li><a href="cookie-policy.html">Политика использования cookie</a></li>' +
            '<li><a href="documents.html">Устав и положения</a></li>' +
            '<li><a href="documents.html">Лицензии и разрешения</a></li>' +
            '<li><a href="documents.html">Памятки и инструкции</a></li>' +
            '<li><button type="button" class="footer__settings" data-cookie-open>Настройки cookie</button></li>' +
          '</ul>' +
        '</div>' +
        '<div>' +
          '<h3>Контакты</h3>' +
          '<div class="footer__contact">' + iconMarkup('pin', '', 17) + '<span><strong>Адрес</strong>' + esc(s.address) + '</span></div>' +
          '<div class="footer__contact">' + iconMarkup('phone', '', 17) + '<span><strong>Дежурная служба · 24/7</strong><a href="' + tel(s.phone_duty) + '">' + esc(s.phone_duty) + '</a></span></div>' +
          '<div class="footer__contact">' + iconMarkup('phone', '', 17) + '<span><strong>Приёмная</strong><a href="' + tel(s.phone_office) + '">' + esc(s.phone_office) + '</a></span></div>' +
          '<div class="footer__contact">' + iconMarkup('mail', '', 17) + '<span><strong>Электронная почта</strong><a href="mailto:' + esc(s.email) + '">' + esc(s.email) + '</a></span></div>' +
          '<div class="footer__contact">' + iconMarkup('siren', '', 17) + '<span><strong>Экстренные службы</strong><a href="tel:112">112</a></span></div>' +
        '</div>' +
      '</div>' +
      '<div class="footer__bottom">' +
        '<div>© ' + currentYear() + ' ' + esc(s.org_name) + ' — филиал ' + esc(s.org_parent) + '. Все права защищены.</div>' +
        '<div class="footer__legal">' +
          '<a href="privacy-policy.html">Политика обработки ПДн</a>' +
          '<a href="company-policy.html">Политика компании</a>' +
          '<a href="cookie-policy.html">Политика cookie</a>' +
          '<a href="sitemap.xml">Карта сайта</a>' +
          '<a href="admin.html">Вход для сотрудников</a>' +
        '</div>' +
      '</div>' +
      '<div class="footer__bottom" style="border-top:0;padding-top:0">' +
        '<div class="footer__text">Фото и видеоматериалы: пресс-служба ФГУП «ВГСЧ», МЧС России. Материалы сайта доступны по лицензии <a href="https://creativecommons.org/licenses/by/4.0/deed.ru" target="_blank" rel="noopener">Creative Commons Attribution 4.0</a>.</div>' +
        '<div class="footer__text"><a href="' + esc(s.links.parent) + '" target="_blank" rel="noopener">Официальный сайт ФГУП «ВГСЧ»</a> · <a href="' + esc(s.links.mchs) + '" target="_blank" rel="noopener">МЧС России</a></div>' +
      '</div>' +
    '</div></footer>';
  }

  /* =========================================================
     Cookie-баннер и согласия
     ========================================================= */
  const CONSENT_COPY = {
    all: { functional: true, analytics: true, marketing: true },
    necessary: { functional: false, analytics: false, marketing: false }
  };

  function consentBannerHtml() {
    return '<div class="cookie-banner" id="cookie-banner" role="region" aria-label="Использование файлов cookie">' +
      '<div class="container cookie-banner__in">' +
        '<div class="cookie-banner__text">' +
          '<strong>Мы используем файлы cookie.</strong> Строго необходимые обеспечивают работу сайта. ' +
          'Аналитические и маркетинговые подключаются только с вашего согласия. ' +
          'Подробнее — в <a href="privacy-policy.html">Политике обработки персональных данных</a> и <a href="cookie-policy.html">Политике использования cookie</a>.' +
        '</div>' +
        '<div class="cookie-banner__actions">' +
          '<button type="button" class="btn btn-ghost btn-sm" data-cookie="settings">Настроить</button>' +
          '<button type="button" class="btn btn-ghost btn-sm" data-cookie="necessary">Только необходимые</button>' +
          '<button type="button" class="btn btn-primary btn-sm" data-cookie="all">Принять все</button>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  function cookieModalHtml() {
    const cat = (key, title, text, required) => '' +
      '<div class="cookie-cat">' +
        '<div><h3>' + title + (required ? ' <span class="cookie-cat__required">Всегда включены</span>' : '') + '</h3><p>' + text + '</p></div>' +
        '<label class="switch"><input type="checkbox" data-consent-cat="' + key + '"' + (required ? ' checked disabled' : '') + ' aria-label="' + esc(title.replace(/<[^>]+>/g, '')) + '"><span aria-hidden="true"></span></label>' +
      '</div>';

    return '<div class="modal-backdrop" id="cookie-modal" aria-hidden="true">' +
      '<div class="modal" role="dialog" aria-modal="true" aria-labelledby="cookie-modal-title" tabindex="-1">' +
        '<div class="modal__head">' +
          '<h2 id="cookie-modal-title">Настройки файлов cookie</h2>' +
          '<button type="button" class="modal__close" data-cookie-close aria-label="Закрыть">' + iconMarkup('close', '', 20) + '</button>' +
        '</div>' +
        '<p class="modal__lead">Выберите категории, которые разрешено использовать. Строго необходимые файлы cookie обеспечивают работу сайта и не могут быть отключены. ' +
        'Подробнее — в <a href="cookie-policy.html" target="_blank" rel="noopener">Политике использования cookie</a>.</p>' +
        cat('functional', 'Функциональные', 'Запоминают настройки интерфейса: масштаб текста, контраст, выбранные фильтры.', false) +
        cat('analytics', 'Аналитические', 'Обезличенная статистика посещаемости (например, Яндекс.Метрика). Скрипты не загружаются до согласия.', false) +
        cat('marketing', 'Маркетинговые', 'Ретаргетинг и рекламные сети. На сайте по умолчанию не используются.', false) +
        '<div class="modal__actions">' +
          '<button type="button" class="btn btn-primary" data-cookie-save>Сохранить выбор</button>' +
          '<button type="button" class="btn btn-ghost" data-cookie="all">Принять все</button>' +
          '<button type="button" class="btn btn-ghost" data-cookie="necessary">Только необходимые</button>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  function applyConsent(categories, method, source) {
    const record = VGS.saveConsent(categories, method, source);
    if (!record) {
      toast('Не удалось сохранить настройки cookie. Проверьте настройки браузера.', 'error');
      return;
    }
    activateConsentScripts(record);
    const banner = document.getElementById('cookie-banner');
    if (banner) {
      banner.classList.remove('is-visible');
      setTimeout(() => {
        banner.remove();
        document.body.style.paddingBottom = '';
      }, 350);
    }
    closeModal('cookie-modal');
    syncConsentSwitches();
    toast('Настройки cookie сохранены.');
  }

  function activateConsentScripts(record) {
    window.NVGSO_CONSENT = record;
    const categories = record.categories;
    document.querySelectorAll('script[type="text/plain"][data-consent]').forEach((node) => {
      const need = node.getAttribute('data-consent');
      const allowed = need === 'necessary' || categories[need];
      if (!allowed || node.dataset.activated === '1') return;
      const script = document.createElement('script');
      Array.from(node.attributes).forEach((attr) => {
        if (['type', 'data-consent', 'data-activated'].indexOf(attr.name) === -1) script.setAttribute(attr.name, attr.value);
      });
      script.text = node.textContent;
      node.dataset.activated = '1';
      node.parentNode.insertBefore(script, node.nextSibling);
    });

    if (categories.analytics) activateAnalytics();
    if (categories.marketing) activateMarketing();
  }

  function activateAnalytics() {
    if (window.__nvgsoAnalyticsLoaded) return;
    window.__nvgsoAnalyticsLoaded = true;
    // В production здесь размещается код Яндекс.Метрики (или иного сертифицированного сервиса).
    // Загрузка допускается только после явного согласия пользователя (opt-in).
    document.dispatchEvent(new CustomEvent('nvgso:consent-analytics'));
    if (window.console && console.info) console.info('[cookie] Аналитические скрипты активированы после согласия пользователя.');
  }

  function activateMarketing() {
    if (window.__nvgsoMarketingLoaded) return;
    window.__nvgsoMarketingLoaded = true;
    document.dispatchEvent(new CustomEvent('nvgso:consent-marketing'));
  }

  function showCookieBanner() {
    if (!document.getElementById('cookie-banner')) {
      document.body.insertAdjacentHTML('beforeend', consentBannerHtml());
    }
    const banner = document.getElementById('cookie-banner');
    requestAnimationFrame(() => {
      banner.classList.add('is-visible');
      const updatePadding = () => { document.body.style.paddingBottom = banner.offsetHeight + 'px'; };
      updatePadding();
      window.addEventListener('resize', updatePadding, { passive: true });
    });
  }

  function syncConsentSwitches() {
    const consent = VGS.getConsent();
    const current = consent ? consent.categories : { functional: false, analytics: false, marketing: false };
    document.querySelectorAll('[data-consent-cat]').forEach((input) => {
      if (input.disabled) return;
      input.checked = !!current[input.dataset.consentCat];
    });
  }

  function openCookieModal() {
    const modal = document.getElementById('cookie-modal');
    if (!modal) return;
    syncConsentSwitches();
    openModal('cookie-modal');
  }

  function initCookie() {
    document.body.insertAdjacentHTML('beforeend', cookieModalHtml());

    const consent = VGS.getConsent();
    if (consent) {
      activateConsentScripts(consent);
    } else {
      setTimeout(showCookieBanner, 500);
    }

    document.addEventListener('click', (event) => {
      const action = event.target.closest('[data-cookie]');
      if (action) {
        const value = action.dataset.cookie;
        if (value === 'all') applyConsent(CONSENT_COPY.all, 'accept_all', 'banner');
        else if (value === 'necessary') applyConsent(CONSENT_COPY.necessary, 'necessary', 'banner');
        else if (value === 'settings') openCookieModal();
        return;
      }
      if (event.target.closest('[data-cookie-open]')) { openCookieModal(); return; }
      if (event.target.closest('[data-cookie-close]')) { closeModal('cookie-modal'); return; }
      if (event.target.closest('[data-cookie-save]')) {
        const categories = {};
        document.querySelectorAll('[data-consent-cat]').forEach((input) => { categories[input.dataset.consentCat] = input.checked; });
        applyConsent(categories, 'custom', 'modal');
      }
    });

    document.getElementById('cookie-modal').addEventListener('click', (event) => {
      if (event.target.id === 'cookie-modal') closeModal('cookie-modal');
    });
  }

  /* =========================================================
     Панель доступности
     ========================================================= */
  function a11yPanelHtml() {
    return '<aside class="a11y-panel" id="a11y-panel" role="dialog" aria-modal="true" aria-labelledby="a11y-title" tabindex="-1">' +
      '<div class="a11y-panel__head">' +
        '<h2 id="a11y-title">Настройки доступности</h2>' +
        '<button type="button" class="icon-btn" data-a11y-close aria-label="Закрыть панель доступности">' + iconMarkup('close', '', 22) + '</button>' +
      '</div>' +
      '<div class="a11y-panel__body">' +
        '<div class="a11y-block">' +
          '<h3>Размер текста</h3>' +
          '<div class="a11y-font">' +
            '<button type="button" class="icon-btn" data-a11y-font="-10" aria-label="Уменьшить текст">' + iconMarkup('minus', '', 20) + '</button>' +
            '<output id="a11y-font-value" aria-live="polite">100%</output>' +
            '<button type="button" class="icon-btn" data-a11y-font="10" aria-label="Увеличить текст">' + iconMarkup('plus', '', 20) + '</button>' +
          '</div>' +
          '<input class="input" type="range" id="a11y-font-range" min="100" max="200" step="10" value="100" aria-label="Масштаб текста в процентах" style="margin-top:.6rem;padding:.25rem">' +
        '</div>' +
        '<div class="a11y-block">' +
          '<h3>Цветовая схема</h3>' +
          '<div class="segmented" role="group" aria-label="Цветовая схема">' +
            '<button type="button" data-a11y-contrast="normal" aria-pressed="true">Обычная</button>' +
            '<button type="button" data-a11y-contrast="hc" aria-pressed="false">Высокий контраст</button>' +
          '</div>' +
        '</div>' +
        '<div class="a11y-block">' +
          '<h3>Отображение</h3>' +
          '<label class="a11y-toggle"><span>Подчёркивать ссылки</span><input type="checkbox" data-a11y-option="links"></label>' +
          '<label class="a11y-toggle"><span>Большой курсор</span><input type="checkbox" data-a11y-option="cursor"></label>' +
          '<label class="a11y-toggle"><span>Отключить анимацию</span><input type="checkbox" data-a11y-option="motion"></label>' +
          '<label class="a11y-toggle"><span>Увеличить межбуквенный интервал</span><input type="checkbox" data-a11y-option="spacing"></label>' +
        '</div>' +
        '<p class="a11y-note">Настройки сохраняются в браузере и применяются ко всем страницам сайта. ' +
        'Масштаб текста до 200% не влияет на доступность функций.</p>' +
      '</div>' +
      '<div class="a11y-panel__foot">' +
        '<button type="button" class="btn btn-ghost" data-a11y-reset>Сбросить настройки</button>' +
      '</div>' +
    '</aside>';
  }

  function applyA11y(settings, persist) {
    const root = document.documentElement;
    const font = Math.min(200, Math.max(100, Number(settings.font) || 100));
    root.style.setProperty('--a11y-scale', String(font / 100));
    root.dataset.a11yContrast = settings.contrast === 'hc' ? 'hc' : 'normal';
    root.dataset.a11yFont = String(font);
    if (font >= 150) root.dataset.a11yLarge = '1'; else delete root.dataset.a11yLarge;
    if (settings.links) root.dataset.a11yLinks = '1'; else delete root.dataset.a11yLinks;
    if (settings.cursor) root.dataset.a11yCursor = 'big'; else delete root.dataset.a11yCursor;
    if (settings.motion) root.dataset.a11yMotion = 'off'; else delete root.dataset.a11yMotion;
    if (settings.spacing) root.dataset.a11ySpacing = '1'; else delete root.dataset.a11ySpacing;

    const value = document.getElementById('a11y-font-value');
    const range = document.getElementById('a11y-font-range');
    if (value) value.textContent = font + '%';
    if (range) range.value = String(font);

    document.querySelectorAll('[data-a11y-contrast]').forEach((button) => {
      button.setAttribute('aria-pressed', String(button.dataset.a11yContrast === (settings.contrast === 'hc' ? 'hc' : 'normal')));
    });
    document.querySelectorAll('[data-a11y-option]').forEach((input) => {
      input.checked = !!settings[input.dataset.a11yOption];
    });

    if (persist) VGS.saveA11y(settings);
    window.NVGSO_A11Y = settings;
  }

  function openA11y() {
    const panel = document.getElementById('a11y-panel');
    if (!panel) return;
    const mobile = document.getElementById('mobile-nav');
    if (mobile && mobile.classList.contains('is-open')) closeMobile();
    const settings = VGS.getA11y();
    applyA11y(settings, false);
    panel.classList.add('is-open');
    document.body.classList.add('no-scroll');
    trapFocus(panel, closeA11y);
    announce('Открыта панель настройки доступности');
  }

  function closeA11y() {
    const panel = document.getElementById('a11y-panel');
    if (!panel) return;
    panel.classList.remove('is-open');
    document.body.classList.remove('no-scroll');
    releaseFocus(panel);
    const trigger = document.querySelector('[data-a11y-open]');
    if (trigger) trigger.focus();
  }

  function initA11y() {
    document.body.insertAdjacentHTML('beforeend', a11yPanelHtml());
    applyA11y(VGS.getA11y(), false);

    document.addEventListener('click', (event) => {
      if (event.target.closest('[data-a11y-open]')) { openA11y(); return; }
      if (event.target.closest('[data-a11y-close]')) { closeA11y(); return; }
      const contrast = event.target.closest('[data-a11y-contrast]');
      if (contrast) {
        const settings = VGS.getA11y();
        settings.contrast = contrast.dataset.a11yContrast;
        applyA11y(settings, true);
        announce(settings.contrast === 'hc' ? 'Включён высокий контраст' : 'Включена обычная цветовая схема');
        return;
      }
      const fontBtn = event.target.closest('[data-a11y-font]');
      if (fontBtn) {
        const settings = VGS.getA11y();
        settings.font = Math.min(200, Math.max(100, Number(settings.font) + Number(fontBtn.dataset.a11yFont)));
        applyA11y(settings, true);
        announce('Размер текста ' + settings.font + ' процентов');
        return;
      }
      if (event.target.closest('[data-a11y-reset]')) {
        const defaults = Object.assign({}, VGS.A11Y_DEFAULTS);
        applyA11y(defaults, true);
        announce('Настройки доступности сброшены');
        toast('Настройки доступности сброшены.');
      }
    });

    document.getElementById('a11y-panel').addEventListener('change', (event) => {
      const input = event.target.closest('[data-a11y-option]');
      if (input) {
        const settings = VGS.getA11y();
        settings[input.dataset.a11yOption] = input.checked;
        applyA11y(settings, true);
      }
    });

    document.getElementById('a11y-font-range').addEventListener('input', (event) => {
      const settings = VGS.getA11y();
      settings.font = Number(event.target.value);
      applyA11y(settings, true);
    });
  }

  function announce(message) {
    let live = document.getElementById('a11y-live');
    if (!live) {
      live = document.createElement('div');
      live.id = 'a11y-live';
      live.className = 'sr-only';
      live.setAttribute('aria-live', 'assertive');
      live.setAttribute('aria-atomic', 'true');
      document.body.appendChild(live);
    }
    live.textContent = '';
    setTimeout(() => { live.textContent = message; }, 30);
  }

  /* =========================================================
     Модальные окна и фокус
     ========================================================= */
  function getFocusable(container) {
    return Array.from(container.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
      .filter((el) => el.offsetParent !== null || el === document.activeElement);
  }

  function trapFocus(container, onEscape) {
    releaseFocus(container);
    const previous = document.activeElement;
    const handler = (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        if (onEscape) onEscape();
        return;
      }
      if (event.key !== 'Tab') return;
      const focusable = getFocusable(container);
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };
    container.addEventListener('keydown', handler);
    SITE.focusTraps.set(container, { handler, previous });
    setTimeout(() => {
      const focusable = getFocusable(container);
      if (focusable.length) focusable[0].focus();
      else container.focus();
    }, 40);
  }

  function releaseFocus(container) {
    const state = SITE.focusTraps.get(container);
    if (!state) return;
    container.removeEventListener('keydown', state.handler);
    SITE.focusTraps.delete(container);
    if (state.previous && document.contains(state.previous)) {
      try { state.previous.focus(); } catch (error) { /* noop */ }
    }
  }

  function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('no-scroll');
    trapFocus(modal, () => closeModal(id));
    const dialog = modal.querySelector('.modal');
    if (dialog && !dialog.contains(document.activeElement)) dialog.focus();
  }

  function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('no-scroll');
    releaseFocus(modal);
  }

  /* =========================================================
     Поиск
     ========================================================= */
  function normalize(text) {
    return String(text || '').toLowerCase().replace(/ё/g, 'е');
  }

  function highlight(text, query) {
    return VGS.highlight(text, query);
  }

  function resultHtml(item, query) {
    return '<a class="search-result" href="' + esc(item.url) + '">' +
      '<div class="search-result__type">' + esc(item.typeLabel) + (item.badge ? ' · ' + esc(item.badge) : '') + (item.date ? ' · ' + esc(ruDate(item.date)) : '') + '</div>' +
      '<div class="search-result__title">' + highlight(item.title, query) + '</div>' +
      '<div class="search-result__text">' + highlight(VGS.truncate(item.text, 180), query) + '</div>' +
    '</a>';
  }

  function searchModalHtml() {
    return '<div class="search-modal" id="search-modal" role="dialog" aria-modal="true" aria-labelledby="search-modal-title">' +
      '<div class="search-modal__box">' +
        '<div class="search-modal__head">' +
          '<h2 id="search-modal-title" class="sr-only">Поиск по сайту</h2>' +
          '<input class="input" type="search" id="site-search-input" placeholder="Поиск по новостям, документам и разделам…" autocomplete="off" aria-label="Поиск по сайту">' +
          '<button type="button" class="icon-btn" data-search-close aria-label="Закрыть поиск">' + iconMarkup('close', '', 20) + '</button>' +
        '</div>' +
        '<div class="search-modal__results" id="search-modal-results">' +
          '<div class="search-modal__empty">Введите запрос — например, «учения», «политика» или «контакты».</div>' +
        '</div>' +
        '<div class="search-modal__foot">' +
          '<span>Enter — все результаты</span>' +
          '<a href="search.html">Расширенный поиск ' + iconMarkup('arrowRight', '', 14) + '</a>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  function openSearch() {
    const modal = document.getElementById('search-modal');
    if (!modal) return;
    const mobile = document.getElementById('mobile-nav');
    if (mobile && mobile.classList.contains('is-open')) closeMobile();
    modal.classList.add('is-open');
    document.body.classList.add('no-scroll');
    trapFocus(modal, closeSearch);
    const input = document.getElementById('site-search-input');
    setTimeout(() => { if (input) { input.focus(); input.select(); } }, 50);
  }

  function closeSearch() {
    const modal = document.getElementById('search-modal');
    if (!modal) return;
    modal.classList.remove('is-open');
    document.body.classList.remove('no-scroll');
    releaseFocus(modal);
  }

  function initSearch() {
    document.body.insertAdjacentHTML('beforeend', searchModalHtml());
    const modal = document.getElementById('search-modal');
    const input = document.getElementById('site-search-input');
    const results = document.getElementById('search-modal-results');
    let timer = null;

    document.addEventListener('click', (event) => {
      if (event.target.closest('[data-search-open]')) { event.preventDefault(); openSearch(); return; }
      if (event.target.closest('[data-search-close]')) { closeSearch(); }
    });

    modal.addEventListener('click', (event) => {
      if (event.target === modal) closeSearch();
    });

    input.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        const query = input.value.trim();
        if (!query) {
          results.innerHTML = '<div class="search-modal__empty">Введите запрос — например, «учения», «политика» или «контакты».</div>';
          return;
        }
        const found = VGS.search(query, 6);
        results.innerHTML = found.length
          ? found.map((item) => resultHtml(item, query)).join('')
          : '<div class="search-modal__empty">По запросу «' + esc(query) + '» ничего не найдено. Попробуйте изменить формулировку.</div>';
      }, 140);
    });

    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        const query = input.value.trim();
        location.href = 'search.html' + (query ? '?q=' + encodeURIComponent(query) : '');
      }
    });
  }

  /* =========================================================
     Лайтбокс
     ========================================================= */
  function lightboxHtml() {
    return '<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Просмотр изображения">' +
      '<button type="button" class="lightbox__close" data-lightbox-close aria-label="Закрыть">' + iconMarkup('close', '', 24) + '</button>' +
      '<button type="button" class="lightbox__prev" data-lightbox-prev aria-label="Предыдущее изображение">' + iconMarkup('chevronLeft', '', 24) + '</button>' +
      '<button type="button" class="lightbox__next" data-lightbox-next aria-label="Следующее изображение">' + iconMarkup('chevronRight', '', 24) + '</button>' +
      '<figure class="lightbox__figure">' +
        '<div id="lightbox-media"></div>' +
        '<figcaption class="lightbox__caption" id="lightbox-caption"></figcaption>' +
      '</figure>' +
      '<div class="lightbox__counter" id="lightbox-counter"></div>' +
    '</div>';
  }

  function openLightbox(items, index) {
    SITE.lightboxItems = items || [];
    SITE.lightboxIndex = Math.max(0, Math.min(index || 0, SITE.lightboxItems.length - 1));
    const box = document.getElementById('lightbox');
    if (!box || !SITE.lightboxItems.length) return;
    box.classList.add('is-open');
    document.body.classList.add('no-scroll');
    renderLightbox();
    trapFocus(box, closeLightbox);
  }

  function renderLightbox() {
    const item = SITE.lightboxItems[SITE.lightboxIndex];
    if (!item) return;
    const media = document.getElementById('lightbox-media');
    const caption = document.getElementById('lightbox-caption');
    const counter = document.getElementById('lightbox-counter');
    if (item.type === 'video') {
      const poster = item.poster ? VGS.imagePath(item.poster, 1400) : '';
      media.innerHTML = '<video class="lightbox__img" controls preload="metadata"' + (poster ? ' poster="' + esc(poster) + '"' : '') + '>' +
        '<source src="' + esc(item.videoUrl) + '" type="video/mp4">Ваш браузер не поддерживает воспроизведение видео.</video>';
    } else {
      const raw = item.src || item.image;
      const isAsset = raw && !/^(data:|https?:|assets\/|\/)/.test(raw);
      const src = isAsset ? VGS.imagePath(raw, 1400) : raw;
      const small = isAsset ? VGS.imagePath(raw, 700) : raw;
      media.innerHTML = '<img class="lightbox__img" src="' + esc(src) + '"' +
        (isAsset ? ' srcset="' + esc(small) + ' 700w, ' + esc(src) + ' 1400w"' : '') +
        ' alt="' + esc(item.title) + '">';
    }
    caption.innerHTML = '<strong>' + esc(item.title) + '</strong>' + esc(item.caption || '') +
      (item.date ? '<span class="muted"> · ' + esc(ruDate(item.date)) + '</span>' : '');
    counter.textContent = (SITE.lightboxIndex + 1) + ' / ' + SITE.lightboxItems.length;
    document.querySelector('[data-lightbox-prev]').disabled = SITE.lightboxItems.length < 2;
    document.querySelector('[data-lightbox-next]').disabled = SITE.lightboxItems.length < 2;
  }

  function moveLightbox(direction) {
    if (!SITE.lightboxItems.length) return;
    SITE.lightboxIndex = (SITE.lightboxIndex + direction + SITE.lightboxItems.length) % SITE.lightboxItems.length;
    renderLightbox();
  }

  function closeLightbox() {
    const box = document.getElementById('lightbox');
    if (!box) return;
    const video = box.querySelector('video');
    if (video) video.pause();
    box.classList.remove('is-open');
    document.body.classList.remove('no-scroll');
    releaseFocus(box);
  }

  function initLightbox() {
    document.body.insertAdjacentHTML('beforeend', lightboxHtml());
    const box = document.getElementById('lightbox');
    box.addEventListener('click', (event) => {
      if (event.target.closest('[data-lightbox-close]') || event.target === box) closeLightbox();
      if (event.target.closest('[data-lightbox-prev]')) moveLightbox(-1);
      if (event.target.closest('[data-lightbox-next]')) moveLightbox(1);
    });
    document.addEventListener('keydown', (event) => {
      if (!box.classList.contains('is-open')) return;
      if (event.key === 'ArrowLeft') moveLightbox(-1);
      if (event.key === 'ArrowRight') moveLightbox(1);
    });
  }

  /* =========================================================
     Формы
     ========================================================= */
  function initForms() {
    const feedback = document.getElementById('feedback-form');
    if (feedback) {
      feedback.addEventListener('submit', (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const get = (name) => form.elements[name];
        const name = String(get('name').value || '').trim();
        const email = String(get('email').value || '').trim();
        const phone = String(get('phone').value || '').trim();
        const topic = String(get('topic').value || '').trim();
        const message = String(get('message').value || '').trim();
        const consent = get('consent').checked;
        const honey = get('website').value;
        const errorBox = form.querySelector('[data-form-error]');
        const setError = (msg) => {
          if (errorBox) { errorBox.textContent = msg; errorBox.focus(); }
          toast(msg, 'error');
        };

        if (honey) return;
        if (name.length < 2) return setError('Укажите имя (не менее 2 символов).');
        if (phone.length < 5 && !VGS.isValidEmail(email)) return setError('Укажите корректный телефон или адрес электронной почты.');
        if (message.length < 10) return setError('Опишите вопрос подробнее (не менее 10 символов).');
        if (!consent) return setError('Необходимо согласие на обработку персональных данных.');

        const button = form.querySelector('button[type="submit"]');
        if (button) { button.disabled = true; button.textContent = 'Отправка…'; }

        setTimeout(() => {
          const item = VGS.addFeedback({
            type: 'feedback',
            name: name,
            email: email,
            phone: phone,
            topic: topic || 'Обращение через сайт',
            message: message,
            page: location.pathname
          });
          form.reset();
          if (button) { button.disabled = false; button.textContent = 'Отправить обращение'; }
          if (errorBox) errorBox.textContent = '';
          const success = form.querySelector('[data-form-success]');
          if (success) {
            success.classList.remove('hidden');
            success.innerHTML = iconMarkup('checkCircle', '', 22) + '<span><strong>Обращение зарегистрировано' + (item ? ' № ' + esc(item.id.slice(-6).toUpperCase()) : '') + '.</strong>' +
              'Ответ будет направлен на указанный адрес. Для срочных вопросов звоните в дежурную службу: <a href="' + tel(SITE.data.settings.phone_duty) + '">' + esc(SITE.data.settings.phone_duty) + '</a>.</span>';
            success.focus();
          }
          toast('Спасибо! Обращение отправлено.');
        }, 550);
      });
    }

    document.querySelectorAll('form[data-subscribe]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        const email = String(form.elements.email.value || '').trim();
        const consent = form.elements.consent.checked;
        const status = form.querySelector('[data-subscribe-status]');
        if (!VGS.isValidEmail(email)) {
          if (status) status.textContent = 'Укажите корректный адрес электронной почты.';
          return;
        }
        if (!consent) {
          if (status) status.textContent = 'Необходимо согласие на обработку персональных данных.';
          return;
        }
        VGS.addFeedback({ type: 'subscription', name: '', email: email, topic: 'Подписка на новости', message: 'Согласие на получение новостей', page: location.pathname });
        form.reset();
        if (status) status.textContent = 'Готово: адрес добавлен в список подписки.';
        toast('Подписка оформлена.');
      });
    });

    document.querySelectorAll('[data-print]').forEach((button) => {
      button.addEventListener('click', () => window.print());
    });

    document.querySelectorAll('[data-copy]').forEach((button) => {
      button.addEventListener('click', async () => {
        const value = button.getAttribute('data-copy') || location.href;
        try {
          await navigator.clipboard.writeText(value);
          toast('Ссылка скопирована.');
        } catch (error) {
          toast('Не удалось скопировать ссылку.', 'error');
        }
      });
    });

    document.addEventListener('click', (event) => {
      const mapButton = event.target.closest('[data-map-load]');
      if (mapButton) {
        const container = mapButton.closest('[data-map]');
        if (!container) return;
        const query = container.getAttribute('data-map');
        const mapUrl = 'https://yandex.ru/map-widget/v1/?text=' + encodeURIComponent(query) + '&z=16';
        container.innerHTML = '<iframe src="' + esc(mapUrl) + '" title="Карта проезда" loading="lazy" allowfullscreen></iframe>' +
          '<div class="map-card__foot"><span>Карта загружена по вашему запросу. Сервис Яндекс.Карты может использовать файлы cookie.</span>' +
          '<a href="https://yandex.ru/maps/?text=' + encodeURIComponent(query) + '" target="_blank" rel="noopener">Открыть в Яндекс.Картах</a></div>';
      }
    });
  }

  /* =========================================================
     Навигация
     ========================================================= */
  function initNav() {
    const masthead = document.getElementById('masthead');

    document.addEventListener('click', (event) => {
      const trigger = event.target.closest('[data-submenu]');
      if (trigger) {
        event.preventDefault();
        const id = trigger.dataset.submenu;
        const submenu = document.getElementById(id);
        const isOpen = trigger.getAttribute('aria-expanded') === 'true';
        document.querySelectorAll('.submenu.is-open').forEach((el) => {
          el.classList.remove('is-open');
          const btn = document.querySelector('[data-submenu="' + el.id + '"]');
          if (btn) btn.setAttribute('aria-expanded', 'false');
        });
        if (!isOpen && submenu) {
          submenu.classList.add('is-open');
          trigger.setAttribute('aria-expanded', 'true');
        }
        return;
      }

      if (event.target.closest('[data-mobile-open]')) { openMobile(); return; }
      if (event.target.closest('[data-mobile-close]')) { closeMobile(); return; }

      const mobileSub = event.target.closest('[data-mobile-sub]');
      if (mobileSub) {
        const sub = document.getElementById(mobileSub.dataset.mobileSub);
        const expanded = mobileSub.getAttribute('aria-expanded') === 'true';
        mobileSub.setAttribute('aria-expanded', String(!expanded));
        if (sub) sub.classList.toggle('is-open', !expanded);
        return;
      }

      if (!event.target.closest('.nav-item--sub')) {
        document.querySelectorAll('.submenu.is-open').forEach((el) => {
          el.classList.remove('is-open');
          const btn = document.querySelector('[data-submenu="' + el.id + '"]');
          if (btn) btn.setAttribute('aria-expanded', 'false');
        });
      }

      if (event.target.closest('.mobile-nav a')) closeMobile();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      const openSub = document.querySelector('.submenu.is-open');
      if (openSub) {
        const btn = document.querySelector('[data-submenu="' + openSub.id + '"]');
        openSub.classList.remove('is-open');
        if (btn) { btn.setAttribute('aria-expanded', 'false'); btn.focus(); }
      }
      if (document.getElementById('mobile-nav').classList.contains('is-open')) closeMobile();
    });

    let lastY = window.scrollY;
    window.addEventListener('scroll', () => {
      if (!masthead) return;
      const y = window.scrollY;
      masthead.classList.toggle('is-scrolled', y > 10);
      lastY = y;
    }, { passive: true });
  }

  function openMobile() {
    const nav = document.getElementById('mobile-nav');
    if (!nav) return;
    nav.classList.add('is-open');
    document.body.classList.add('no-scroll');
    const btn = document.querySelector('[data-mobile-open]');
    if (btn) btn.setAttribute('aria-expanded', 'true');
    trapFocus(nav, closeMobile);
  }

  function closeMobile() {
    const nav = document.getElementById('mobile-nav');
    if (!nav) return;
    nav.classList.remove('is-open');
    document.body.classList.remove('no-scroll');
    const btn = document.querySelector('[data-mobile-open]');
    if (btn) btn.setAttribute('aria-expanded', 'false');
    releaseFocus(nav);
  }

  /* =========================================================
     Уведомления
     ========================================================= */
  function toast(message, type) {
    let wrap = document.querySelector('.toast-wrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'toast-wrap';
      wrap.setAttribute('aria-live', 'polite');
      document.body.appendChild(wrap);
    }
    const node = document.createElement('div');
    node.className = 'toast' + (type === 'error' ? ' toast--error' : '');
    node.innerHTML = iconMarkup(type === 'error' ? 'alert' : 'checkCircle', '', 18) + '<span>' + esc(message) + '</span>';
    wrap.appendChild(node);
    setTimeout(() => {
      node.style.opacity = '0';
      node.style.transform = 'translateY(8px)';
      setTimeout(() => node.remove(), 300);
    }, 4000);
  }

  /* =========================================================
     Инициализация
     ========================================================= */
  function init() {
    SITE.data = VGS.loadData();
    const data = SITE.data;
    const header = document.getElementById('site-header');
    const footer = document.getElementById('site-footer');
    if (header) header.innerHTML = renderHeader(data);
    if (footer) footer.innerHTML = renderFooter(data);
    document.body.insertAdjacentHTML('beforeend', renderMobileNav(data));

    initNav();
    initCookie();
    initA11y();
    initSearch();
    initLightbox();

    if (window.PAGES && typeof window.PAGES.init === 'function') {
      window.PAGES.init(data);
    }

    // Формы и кнопки создаются в pages.js, поэтому привязываем их после рендера.
    initForms();
  }

  SITE.toast = toast;
  SITE.openLightbox = openLightbox;
  SITE.closeLightbox = closeLightbox;
  SITE.openCookieModal = openCookieModal;
  SITE.openSearch = openSearch;
  SITE.applyA11y = applyA11y;
  SITE.announce = announce;
  SITE.icon = icon;
  SITE.categoryTone = (category) => CATEGORY_TONES[category] || 'badge';

  window.SITE = SITE;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
