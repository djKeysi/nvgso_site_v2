/* =========================================================================
   Рендеринг страниц публичной части.
   Каждая страница получает данные и возвращает HTML + функцию инициализации.
   ========================================================================= */
(function () {
  'use strict';

  const VGS = window.VGS;
  const icon = (name, cls, size) => (VGS.icon ? VGS.icon(name, cls, size) : '');
  const { esc, ruDate, ruDateLong, phoneHref } = VGS;

  const state = {
    news: { cat: 'all', q: '', from: '', to: '', page: 1, perPage: 6 },
    documents: { cat: 'all', q: '' },
    gallery: { album: 'all' },
    search: { q: '', type: 'all' }
  };

  /* =========================================================
     Общие блоки
     ========================================================= */
  function tel(value) {
    return 'tel:' + phoneHref(value);
  }

  function imageUrl(image, size, override) {
    const raw = override || image || '';
    if (/^(data:|https?:|assets\/|\/)/.test(raw)) return raw;
    return VGS.imagePath(raw, size);
  }

  function photo(image, alt, className, sizes, override) {
    const raw = override || image || 'photo-01';
    const isAsset = !/^(data:|https?:|assets\/|\/)/.test(raw);
    const src = imageUrl(image, 1400, override);
    const small = imageUrl(image, 700, override);
    return '<img class="' + (className || '') + '" src="' + esc(src) + '"' +
      (isAsset ? ' srcset="' + esc(small) + ' 700w, ' + esc(src) + ' 1400w"' : '') +
      ' sizes="' + esc(sizes || '(max-width: 720px) 100vw, 33vw') + '" alt="' + esc(alt) + '" loading="lazy" decoding="async">';
  }

  function crumb(label, href) {
    return '<a href="' + esc(href) + '">' + esc(label) + '</a>';
  }

  function pageHero(options) {
    const crumbs = options.crumbs || [];
    const crumbHtml = ['<a href="index.html">Главная</a>'].concat(crumbs.map((item) => {
      if (!item.href) return '<span aria-current="page">' + esc(item.label) + '</span>';
      return '<a href="' + esc(item.href) + '">' + esc(item.label) + '</a>';
    })).join(icon('chevronRight', '', 14));

    return '<section class="page-hero' + (options.compact ? ' page-hero--compact' : '') + '">' +
      '<div class="container">' +
        '<nav class="page-hero__crumbs" aria-label="Хлебные крошки">' + crumbHtml + '</nav>' +
        '<h1>' + esc(options.title) + '</h1>' +
        (options.lead ? '<p class="page-hero__lead">' + esc(options.lead) + '</p>' : '') +
        (options.meta ? '<div class="page-hero__meta">' + options.meta.map((m) => '<span>' + icon(m.icon || 'info', '', 15) + esc(m.text) + '</span>').join('') + '</div>' : '') +
        (options.actions ? '<div class="page-hero__actions">' + options.actions + '</div>' : '') +
      '</div>' +
    '</section>';
  }

  function sectionHead(eyebrow, title, lead, more) {
    return '<div class="section__head">' +
      '<div>' +
        (eyebrow ? '<div class="eyebrow">' + esc(eyebrow) + '</div>' : '') +
        '<h2 class="section__title">' + esc(title) + '</h2>' +
        (lead ? '<p class="section__lead">' + esc(lead) + '</p>' : '') +
      '</div>' +
      (more ? '<div class="section__more">' + more + '</div>' : '') +
    '</div>';
  }

  function moreLink(href, text) {
    return '<a class="text-link" href="' + esc(href) + '">' + esc(text) + ' ' + icon('arrowRight', '', 16) + '</a>';
  }

  function emptyState(title, text, action) {
    return '<div class="empty-state">' + icon('search', '', 34) +
      '<h3>' + esc(title) + '</h3><p>' + esc(text) + '</p>' +
      (action || '') + '</div>';
  }

  function newsCard(item) {
    return '<a class="news-card" href="article.html?id=' + encodeURIComponent(item.id) + '">' +
      '<span class="news-card__media">' +
        '<span class="badge news-card__badge">' + esc(item.category) + '</span>' +
        photo(item.image, item.title, '', '(max-width: 720px) 100vw, (max-width: 1100px) 50vw, 33vw', item.imageSrc) +
      '</span>' +
      '<span class="news-card__body">' +
        '<span class="news-card__date">' + icon('calendar', '', 14) + esc(ruDate(item.date)) + '</span>' +
        '<h3>' + esc(item.title) + '</h3>' +
        '<p>' + esc(VGS.truncate(item.excerpt, 150)) + '</p>' +
        '<span class="news-card__more">Читать новость ' + icon('arrowRight', '', 14) + '</span>' +
      '</span>' +
    '</a>';
  }

  function newsFeature(item) {
    return '<a class="news-feature" href="article.html?id=' + encodeURIComponent(item.id) + '">' +
      '<span class="news-feature__media">' + photo(item.image, item.title, '', '(max-width: 900px) 100vw, 55vw', item.imageSrc) + '</span>' +
      '<span class="news-feature__body">' +
        '<span class="badge badge--red" style="align-self:flex-start">Главная новость · ' + esc(item.category) + '</span>' +
        '<h3>' + esc(item.title) + '</h3>' +
        '<p>' + esc(VGS.truncate(item.excerpt, 220)) + '</p>' +
        '<span class="news-card__date" style="margin-top:1rem">' + icon('calendar', '', 14) + esc(ruDateLong(item.date)) + '</span>' +
        '<span class="text-link" style="margin-top:1rem">Читать полностью ' + icon('arrowRight', '', 16) + '</span>' +
      '</span>' +
    '</a>';
  }

  function docRow(doc) {
    const href = doc.url || (doc.fileData ? 'data:' + (doc.fileType || 'application/octet-stream') + ';base64,' + doc.fileData : '#');
    const ext = ((doc.origName || doc.url || 'PDF').split('.').pop() || 'PDF').toUpperCase().slice(0, 4);
    return '<a class="doc-row" id="' + esc(doc.id) + '" href="' + esc(href) + '" target="_blank" rel="noopener">' +
      '<span class="doc-row__icon">' + (ext === 'PDF' ? icon('doc', '', 22) : esc(ext)) + '</span>' +
      '<span class="doc-row__main">' +
        '<span class="doc-row__title">' + esc(doc.title) + '</span>' +
        '<span class="doc-row__meta">' +
          '<span>' + icon('calendar', '', 13) + esc(ruDate(doc.date)) + '</span>' +
          '<span>' + esc(doc.category) + '</span>' +
          (doc.pages ? '<span>' + esc(doc.pages) + ' ' + VGS.plural(doc.pages, ['страница', 'страницы', 'страниц']) + '</span>' : '<span>PDF</span>') +
          (doc.note ? '<span>' + esc(VGS.truncate(doc.note, 80)) + '</span>' : '') +
        '</span>' +
      '</span>' +
      '<span class="doc-row__download" aria-hidden="true">' + icon('download', '', 18) + '</span>' +
    '</a>';
  }

  function personCard(item) {
    return '<div class="contact-card">' +
      '<div class="contact-card__icon">' + icon('user', '', 20) + '</div>' +
      '<div>' +
        '<div class="contact-card__label">' + esc(item.position) + '</div>' +
        '<div class="contact-card__value">' + esc(item.name) + '</div>' +
        (item.rank ? '<div class="contact-card__note">' + esc(item.rank) + '</div>' : '') +
        (item.reception ? '<div class="contact-card__note">' + esc(item.reception) + '</div>' : '') +
        (item.phone ? '<div style="margin-top:.35rem"><a href="' + tel(item.phone) + '" style="font-weight:700">' + esc(item.phone) + '</a></div>' : '') +
      '</div>' +
    '</div>';
  }

  function ctaBand(data, title, text, primaryHref, primaryText, secondaryHref, secondaryText) {
    return '<section class="cta-band"><div class="container cta-band__in">' +
      '<div><h2>' + esc(title) + '</h2><p>' + esc(text) + '</p></div>' +
      '<div class="cta-band__actions">' +
        '<a class="btn btn-red" href="' + esc(primaryHref) + '">' + icon('send', '', 18) + esc(primaryText) + '</a>' +
        (secondaryHref ? '<a class="btn btn-outline" href="' + esc(secondaryHref) + '">' + esc(secondaryText) + '</a>' : '') +
      '</div>' +
    '</div></section>';
  }

  function setMeta(title, description, image) {
    document.title = title;
    const set = (selector, value) => {
      const node = document.querySelector(selector);
      if (node) node.setAttribute('content', value);
    };
    set('meta[name="description"]', description);
    set('meta[property="og:title"]', title);
    set('meta[property="og:description"]', description);
    if (image) set('meta[property="og:image"]', image);
    set('meta[name="twitter:title"]', title);
    set('meta[name="twitter:description"]', description);
    const canonical = document.querySelector('link[rel="canonical"]');
    if (canonical) set('meta[property="og:url"]', canonical.getAttribute('href'));
  }

  /* =========================================================
     Главная
     ========================================================= */
  function home(data) {
    const s = data.settings;
    const news = data.news.filter((item) => item.published).sort((a, b) => (b.date || '').localeCompare(a.date || ''));
    const docs = data.docs.filter((item) => item.published).sort((a, b) => (b.date || '').localeCompare(a.date || ''));
    const divisions = data.divisions.filter((item) => item.published && item.type !== 'Штаб');
    const activities = [
      { icon: 'helmet', title: 'Горноспасательные работы', text: 'Выезд на аварии, разведка горных выработок, спасение людей и ликвидация очагов возгорания.', anchor: 'rescue' },
      { icon: 'pulse', title: 'Профилактика аварий', text: 'Обследование предприятий, контроль проветривания и готовности аварийно-спасательных формирований.', anchor: 'prevention' },
      { icon: 'book', title: 'Обучение и подготовка', text: 'Тренировки, учебно-методические сборы и соревнования по горноспасательному делу.', anchor: 'training' },
      { icon: 'handshake', title: 'Взаимодействие с МЧС', text: 'Совместные учения, обмен информацией и работа в единой системе реагирования.', anchor: 'cooperation' }
    ];

    const featured = news[0];
    const restNews = news.slice(1, 4);

    return '<div>' +
      '<section class="hero">' +
        '<div class="container hero__in">' +
          '<div>' +
            '<div class="hero__tag">' + icon('shield', '', 15) + esc(s.hero_tag) + '</div>' +
            '<h1>' + esc(s.hero_title) + '</h1>' +
            '<p class="hero__lead">' + esc(s.hero_lead) + '</p>' +
            '<div class="hero__actions">' +
              '<a class="btn btn-red" href="contacts.html#feedback">' + icon('send', '', 18) + 'Написать в отряд</a>' +
              '<a class="btn btn-outline" href="about.html">' + icon('shield', '', 18) + 'Об отряде</a>' +
              '<a class="btn btn-outline" href="' + tel(s.phone_duty) + '">' + icon('phone', '', 18) + 'Дежурная служба</a>' +
            '</div>' +
            '<div class="hero__facts">' + s.stats.map((stat) => '<div class="hero__fact"><strong>' + esc(stat.value) + '</strong><span>' + esc(stat.label) + '</span></div>').join('') + '</div>' +
          '</div>' +
          '<aside class="duty-card" aria-label="Дежурная служба отряда">' +
            '<div class="duty-card__head"><span class="dot" aria-hidden="true"></span>Дежурная служба · 24/7</div>' +
            '<div class="duty-card__body">' +
              '<div class="duty-card__label">Телефон дежурного диспетчера</div>' +
              '<a class="duty-card__phone" href="' + tel(s.phone_duty) + '">' + esc(s.phone_duty) + '</a>' +
              '<p class="duty-card__note">Круглосуточно, без выходных. При аварии звоните немедленно.</p>' +
              '<ul class="duty-card__list">' +
                '<li>' + icon('pin', '', 16) + '<span>' + esc(s.address_short) + '</span></li>' +
                '<li>' + icon('mail', '', 16) + '<span><a href="mailto:' + esc(s.email) + '">' + esc(s.email) + '</a></span></li>' +
                '<li>' + icon('clock', '', 16) + '<span>' + esc(s.hours_short) + '</span></li>' +
              '</ul>' +
              '<div class="duty-card__112"><span>Единый номер<br><strong>экстренных служб</strong></span><a href="tel:112">112</a></div>' +
              '<a class="btn btn-ghost btn-block" style="margin-top:.75rem" href="contacts.html#feedback">' + icon('mail', '', 17) + 'Написать в отряд</a>' +
            '</div>' +
          '</aside>' +
        '</div>' +
      '</section>' +

      '<section class="photo-band" aria-label="Фотохроника отряда"><div class="container">' +
        '<div class="photo-band__grid">' +
          '<a class="photo-band__item" href="gallery.html?album=a1">' + photo('photo-01', 'Демонстрация аварийно-спасательного оборудования', '', '(max-width: 720px) 100vw, 33vw') +
            '<span class="photo-band__caption">' + icon('gallery', '', 14) + ' Учения и обмен опытом</span></a>' +
          '<a class="photo-band__item" href="gallery.html?album=a2">' + photo('photo-10', 'Вахта памяти', '', '(max-width: 720px) 100vw, 33vw') +
            '<span class="photo-band__caption">' + icon('star', '', 14) + ' Память и торжества</span></a>' +
          '<a class="photo-band__item" href="gallery.html?album=a3">' + photo('photo-15', 'Общий сбор личного состава', '', '(max-width: 720px) 100vw, 33vw') +
            '<span class="photo-band__caption">' + icon('users', '', 14) + ' Награждения и построения</span></a>' +
        '</div>' +
      '</div></section>' +

      '<section class="section section--tight"><div class="container">' +
        '<div class="quick-grid">' +
          '<a class="quick-card" href="' + tel(s.phone_duty) + '">' +
            '<span class="quick-card__arrow">' + icon('arrowRight', '', 18) + '</span>' +
            '<span class="quick-card__icon">' + icon('siren', '', 24) + '</span>' +
            '<span class="quick-card__title">Сообщить об аварии</span>' +
            '<span class="quick-card__text">Телефон дежурной службы и единый номер 112</span>' +
          '</a>' +
          '<a class="quick-card" href="documents.html">' +
            '<span class="quick-card__arrow">' + icon('arrowRight', '', 18) + '</span>' +
            '<span class="quick-card__icon">' + icon('doc', '', 24) + '</span>' +
            '<span class="quick-card__title">Найти документ</span>' +
            '<span class="quick-card__text">Положения, лицензии, памятки и нормативные акты</span>' +
          '</a>' +
          '<a class="quick-card" href="contacts.html#phones">' +
            '<span class="quick-card__arrow">' + icon('arrowRight', '', 18) + '</span>' +
            '<span class="quick-card__icon">' + icon('phone', '', 24) + '</span>' +
            '<span class="quick-card__title">Телефонный справочник</span>' +
            '<span class="quick-card__text">Контакты подразделений и должностных лиц</span>' +
          '</a>' +
          '<a class="quick-card" href="contacts.html#feedback">' +
            '<span class="quick-card__arrow">' + icon('arrowRight', '', 18) + '</span>' +
            '<span class="quick-card__icon">' + icon('mail', '', 24) + '</span>' +
            '<span class="quick-card__title">Задать вопрос</span>' +
            '<span class="quick-card__text">Обратная связь с отрядом и порядок обращения</span>' +
          '</a>' +
        '</div>' +
      '</div></section>' +

      '<section class="section" id="about"><div class="container about-split">' +
        '<div class="about-split__text">' +
          '<div class="eyebrow">О филиале</div>' +
          '<h2 class="section__title">' + esc(s.org_name) + '</h2>' +
          '<p style="margin-top:1rem">' + s.about_text.split(/\n{2,}/).map((p) => esc(p)).join('</p><p>') + '</p>' +
          '<ul class="about-facts">' +
            '<li>' + icon('checkCircle', '', 20) + '<span>Образован 12 июля 1942 года на базе Араличевского и Байдаевского взводов</span></li>' +
            '<li>' + icon('checkCircle', '', 20) + '<span>Пять взводов и 28 отделений в Новокузнецке, Междуреченске и Калтане</span></li>' +
            '<li>' + icon('checkCircle', '', 20) + '<span>Дважды признавался лучшим горноспасательным отрядом МЧС России</span></li>' +
          '</ul>' +
          '<a class="btn btn-primary" href="about.html">' + icon('arrowRight', '', 18) + 'История и командование</a>' +
        '</div>' +
        '<div class="about-media">' +
          '<img class="about-media__main" src="assets/img/about-1600.webp" srcset="assets/img/about-900.webp 900w, assets/img/about-1600.webp 1600w" sizes="(max-width: 900px) 100vw, 45vw" alt="Горноспасатели отряда демонстрируют оснащение" loading="lazy">' +
          '<img class="about-media__sub" src="assets/img/photo-05-700.webp" alt="Построение личного состава и выездная техника" loading="lazy">' +
          '<div class="about-media__badge"><strong>1942</strong><span>год основания</span></div>' +
          '<div class="about-media__credit">Фото: пресс-служба ФГУП «ВГСЧ» / МЧС России</div>' +
        '</div>' +
      '</div></section>' +

      '<section class="section section--soft" id="activity"><div class="container">' +
        sectionHead('Деятельность', 'Что делает отряд', 'Главные направления работы Новокузнецкого ВГСО — от экстренного реагирования до профилактики аварий.', moreLink('activity.html', 'Все направления')) +
        '<div class="activity-grid">' + activities.map((item) =>
          '<a class="activity-card" href="activity.html#' + item.anchor + '">' +
            '<span class="activity-card__icon">' + icon(item.icon, '', 26) + '</span>' +
            '<span><h3>' + esc(item.title) + '</h3><p>' + esc(item.text) + '</p><span class="activity-card__more">Подробнее ' + icon('arrowRight', '', 13) + '</span></span>' +
          '</a>'
        ).join('') + '</div>' +
      '</div></section>' +

      '<section class="section" id="structure"><div class="container">' +
        sectionHead('Структура', 'Пять взводов в трёх городах', 'Подразделения отряда дислоцируются в Новокузнецке, Междуреченске и Калтане и находятся в постоянной готовности.', moreLink('structure.html', 'Схема подразделений')) +
        '<div class="structure-panel">' +
          '<div class="structure-map">' +
            '<div class="structure-map__hq">' + icon('building', '', 26) + '<span><strong>Управление отряда</strong><span>' + esc(s.address_short) + '</span></span></div>' +
            '<div class="structure-map__line" aria-hidden="true"></div>' +
            '<div class="structure-map__list">' + divisions.map((item) =>
              '<div class="structure-map__item"><span><strong>' + esc(item.name) + '</strong> <span>· ' + esc(item.city) + '</span></span>' + icon('chevronRight', '', 15) + '</div>'
            ).join('') + '</div>' +
            '<div class="structure-map__foot">Всего в составе отряда — 28 отделений, включая оперативный пункт управления.</div>' +
          '</div>' +
          '<div class="structure-side">' +
            '<div class="structure-stat"><strong>5</strong><span>взводов обеспечивают горноспасательное обслуживание предприятий юга Кузбасса</span></div>' +
            '<div class="structure-stat"><strong>28</strong><span>отделений несут дежурство и выезжают на аварии</span></div>' +
            '<div class="structure-stat"><strong>3</strong><span>города: Новокузнецк, Междуреченск и Калтан</span></div>' +
            '<div class="structure-side__cta"><strong>Зона ответственности</strong>Предприятия угольной промышленности Кузбасса, включая шахты «Распадская», «Юбилейная», «Ульяновская» и другие.<br><a class="text-link" style="margin-top:.6rem" href="structure.html">Подробнее ' + icon('arrowRight', '', 14) + '</a></div>' +
          '</div>' +
        '</div>' +
      '</div></section>' +

      '<section class="section section--soft" id="news"><div class="container">' +
        sectionHead('Пресс-центр', 'Новости и события', 'Учения, профилактическая работа, награждения и памятные даты отряда.', moreLink('news.html', 'Все новости')) +
        (featured ? newsFeature(featured) : '') +
        (restNews.length ? '<div class="news-grid" style="margin-top:1.25rem">' + restNews.map(newsCard).join('') + '</div>' : '') +
      '</div></section>' +

      '<section class="section" id="documents"><div class="container">' +
        sectionHead('Документы', 'Официальные материалы', 'Положения, лицензии, памятки и документы, регламентирующие деятельность филиала.', moreLink('documents.html', 'Все документы')) +
        '<div class="docs-list">' + docs.slice(0, 4).map(docRow).join('') + '</div>' +
      '</div></section>' +

      '<section class="section section--navy" id="memory"><div class="container about-split">' +
        '<div>' +
          '<div class="eyebrow">Память</div>' +
          '<h2 class="section__title">Подвиг горноспасателей — часть истории Кузбасса</h2>' +
          '<p style="margin-top:1rem;color:#c6d3e7">Отряд участвовал в ликвидации последствий сложных аварий на шахтах «Распадская», «Ульяновская», «Юбилейная» и других предприятиях. Мы бережно храним память о погибших товарищах и поддерживаем ветеранов службы.</p>' +
          '<blockquote class="memory-quote">«Земля не прощает ошибок. Наша задача — сделать всё, чтобы люди вернулись домой».<footer>Из истории Новокузнецкого ВГСО</footer></blockquote>' +
          '<div style="margin-top:1.5rem;display:flex;gap:.6rem;flex-wrap:wrap">' +
            '<a class="btn btn-gold" href="veterans.html">' + icon('star', '', 18) + 'Ветеранам и память</a>' +
            '<a class="btn btn-outline" href="gallery.html?album=a2">' + icon('gallery', '', 18) + 'Фотохроника</a>' +
          '</div>' +
        '</div>' +
        '<div><img class="memory-photo" src="assets/img/memory-1600.webp" width="800" height="532" alt="Возложение цветов к мемориалу погибшим горноспасателям" loading="lazy"></div>' +
      '</div></section>' +

      '<section class="section section--tight"><div class="container">' +
        '<div class="grid grid--3">' +
          '<div class="contact-card contact-card--accent"><div class="contact-card__icon">' + icon('pin', '', 20) + '</div><div><div class="contact-card__label">Адрес отряда</div><div class="contact-card__value">' + esc(s.address) + '</div><div class="contact-card__note">Куйбышевский район, Новокузнецк</div></div></div>' +
          '<div class="contact-card contact-card--accent"><div class="contact-card__icon">' + icon('phone', '', 20) + '</div><div><div class="contact-card__label">Дежурная служба</div><div class="contact-card__value"><a href="' + tel(s.phone_duty) + '">' + esc(s.phone_duty) + '</a></div><div class="contact-card__note">Круглосуточно, без выходных</div></div></div>' +
          '<div class="contact-card contact-card--accent"><div class="contact-card__icon">' + icon('mail', '', 20) + '</div><div><div class="contact-card__label">Электронная почта</div><div class="contact-card__value"><a href="mailto:' + esc(s.email) + '">' + esc(s.email) + '</a></div><div class="contact-card__note">Для официальных обращений</div></div></div>' +
        '</div>' +
        '<div style="margin-top:1.25rem;display:flex;justify-content:center">' +
          '<a class="btn btn-primary" href="contacts.html">' + icon('route', '', 18) + 'Все контакты и схема проезда</a>' +
        '</div>' +
      '</div></section>' +
    '</div>';
  }

  /* =========================================================
     Об отряде
     ========================================================= */
  function about(data) {
    const s = data.settings;
    const page = data.pages.find((p) => p.slug === 'about');
    const command = data.command.filter((item) => item.published);
    const milestones = data.milestones || [];
    const zone = data.zone || [];

    return pageHero({
      title: page ? page.title : 'Об отряде',
      lead: page ? page.lead : s.about_lead,
      crumbs: [{ label: 'О филиале' }, { label: 'Об отряде' }],
      meta: [
        { icon: 'history', text: 'Основан в 1942 году' },
        { icon: 'structure', text: '5 взводов, 28 отделений' },
        { icon: 'star', text: 'Лучший отряд МЧС России (2013, 2014)' }
      ]
    }) +
    '<section class="section"><div class="container about-split">' +
      '<div class="about-split__text">' +
        '<div class="eyebrow">История и сегодняшний день</div>' +
        '<h2 class="section__title">От взвода до крупнейшего отряда Кузбасса</h2>' +
        s.about_text.split(/\n{2,}/).map((p) => '<p style="margin-top:1rem">' + esc(p) + '</p>').join('') +
        '<p style="margin-top:1rem">Отряд неоднократно привлекался к ликвидации последствий аварий на угольных предприятиях Кузбасса, а его личный состав направлялся для оказания помощи в другие регионы России и за рубеж. В 2013 и 2014 годах филиал «Новокузнецкий ВГСО» признавался лучшим горноспасательным отрядом МЧС России.</p>' +
        '<div style="margin-top:1.5rem;display:flex;gap:.6rem;flex-wrap:wrap">' +
          '<a class="btn btn-primary" href="structure.html">' + icon('structure', '', 18) + 'Структура отряда</a>' +
          '<a class="btn btn-ghost" href="documents.html">' + icon('doc', '', 18) + 'Документы</a>' +
        '</div>' +
      '</div>' +
      '<div class="about-media">' +
        '<img class="about-media__main" src="assets/img/about-1600.webp" srcset="assets/img/about-900.webp 900w, assets/img/about-1600.webp 1600w" sizes="(max-width: 900px) 100vw, 45vw" alt="Горноспасатели отряда у специального автомобиля" loading="lazy">' +
        '<img class="about-media__sub" src="assets/img/photo-15-700.webp" alt="Общий сбор личного состава отряда" loading="lazy">' +
        '<div class="about-media__badge"><strong>1942</strong><span>год основания</span></div>' +
        '<div class="about-media__credit">Фото: пресс-служба ФГУП «ВГСЧ» / МЧС России</div>' +
      '</div>' +
    '</div></section>' +

    '<section class="section section--soft"><div class="container">' +
      sectionHead('Ключевые факты', 'Отряд в цифрах и датах', '') +
      '<div class="grid grid--4">' +
        '<div class="structure-stat"><strong>84</strong><span>года со дня образования отряда в 2026 году</span></div>' +
        '<div class="structure-stat"><strong>5</strong><span>взводов в Новокузнецке, Междуреченске и Калтане</span></div>' +
        '<div class="structure-stat"><strong>28</strong><span>отделений в составе подразделений</span></div>' +
        '<div class="structure-stat"><strong>2</strong><span>награды «Лучший горноспасательный отряд МЧС России»</span></div>' +
      '</div>' +
    '</div></section>' +

    '<section class="section"><div class="container">' +
      sectionHead('История', 'Основные вехи', 'От первых взводов Араличевского месторождения до современного филиала ФГУП «ВГСЧ».') +
      '<div class="timeline">' + milestones.map((item) =>
        '<div class="timeline__item"><div class="timeline__year">' + esc(item.year) + '</div>' +
        '<h3 class="timeline__title">' + esc(item.title) + '</h3>' +
        '<p class="timeline__text">' + esc(item.text) + '</p></div>'
      ).join('') + '</div>' +
    '</div></section>' +

    '<section class="section section--soft"><div class="container">' +
      sectionHead('Командование', 'Руководство отряда', 'Приём по личным вопросам осуществляется по предварительной записи.') +
      '<div class="grid grid--2">' + command.map(personCard).join('') + '</div>' +
      '<p class="muted" style="font-size:.8125rem;margin-top:1rem">Контактные данные приёмной: <a href="' + tel(s.phone_office) + '">' + esc(s.phone_office) + '</a> · <a href="mailto:' + esc(s.email) + '">' + esc(s.email) + '</a></p>' +
    '</div></section>' +

    '<section class="section"><div class="container">' +
      sectionHead('Зона ответственности', 'Предприятия, на которых работал отряд', 'Горноспасательное обслуживание организаций, ведущих горные работы на опасных производственных объектах угольной промышленности юга Кузбасса.') +
      '<div class="grid grid--3">' + zone.map((item) =>
        '<div class="contact-card"><div class="contact-card__icon">' + icon('mountain', '', 20) + '</div><div><div class="contact-card__value">' + esc(item.name) + '</div><div class="contact-card__note">' + esc(item.note) + '</div></div></div>'
      ).join('') + '</div>' +
      '<p class="muted" style="font-size:.8125rem;margin-top:1rem">Перечень приведён по данным о работах последних десятилетий и может уточняться. Полный список обслуживаемых предприятий определяется договорами.</p>' +
    '</div></section>' +

    ctaBand(data, 'Хотите узнать больше о работе отряда?', 'Свяжитесь с нами или посмотрите фотохронику учений и торжественных мероприятий.', 'gallery.html', 'Фотогалерея', 'contacts.html', 'Контакты');
  }

  /* =========================================================
     Структура
     ========================================================= */
  function structure(data) {
    const s = data.settings;
    const page = data.pages.find((p) => p.slug === 'structure');
    const divisions = data.divisions.filter((item) => item.published);
    const units = divisions.filter((item) => item.type !== 'Штаб');
    const hq = divisions.find((item) => item.type === 'Штаб');

    return pageHero({
      title: page ? page.title : 'Структура',
      lead: page ? page.lead : s.structure_lead,
      crumbs: [{ label: 'О филиале' }, { label: 'Структура' }],
      meta: [
        { icon: 'structure', text: '5 взводов' },
        { icon: 'users', text: '28 отделений' },
        { icon: 'pin', text: 'Новокузнецк · Междуреченск · Калтан' }
      ]
    }) +
    '<section class="section"><div class="container">' +
      sectionHead('Организационная схема', 'Как устроен отряд', 'Управление отрядом осуществляет командир. В оперативном подчинении находятся взводы, дежурные отделения и вспомогательные службы.') +
      '<div class="org-chart">' +
        '<div class="org-node org-node--hq"><strong>Командир отряда</strong><span>' + esc(hq ? hq.name : 'Управление отряда') + ' · ' + esc(s.address_short) + '</span></div>' +
        '<div class="org-connector" aria-hidden="true"></div>' +
        '<div class="org-node"><strong>Заместители командира и службы</strong><span>Оперативная служба · медицинская служба · дежурная часть · обеспечивающие подразделения</span></div>' +
        '<div class="org-connector" aria-hidden="true"></div>' +
        '<div class="org-level">' + units.map((item) =>
          '<div class="org-node"><strong>' + esc(item.name) + '</strong><span>' + esc(item.city) + '</span><em>' + esc(item.units || 'отделения') + '</em></div>'
        ).join('') + '</div>' +
      '</div>' +
      '<div class="org-notes">' +
        '<span class="badge badge--soft">' + icon('users', '', 13) + ' 28 отделений</span>' +
        '<span class="badge badge--soft">' + icon('clock', '', 13) + ' Дежурство 24/7</span>' +
        '<span class="badge badge--soft">' + icon('pin', '', 13) + ' 3 города Кузбасса</span>' +
      '</div>' +
    '</div></section>' +

    '<section class="section section--soft"><div class="container">' +
      sectionHead('Дислокация', 'Подразделения отряда', 'Сведения о местах дислокации и контактных телефонах подразделений.') +
      '<div class="division-grid">' + divisions.map((item) =>
        '<article class="division-card" id="' + esc(item.id) + '">' +
          '<div class="division-card__top"><h3>' + esc(item.name) + '</h3><span class="badge badge--soft">' + esc(item.type) + '</span></div>' +
          '<div class="division-card__city">' + esc(item.city) + '</div>' +
          '<p>' + esc(item.description) + '</p>' +
          '<div class="division-card__meta">' +
            '<span>' + icon('pin', '', 14) + esc(item.address) + '</span>' +
            (item.phone ? '<span>' + icon('phone', '', 14) + '<a href="' + tel(item.phone) + '">' + esc(item.phone) + '</a></span>' : '') +
            (item.units ? '<span>' + icon('users', '', 14) + esc(item.units) + '</span>' : '') +
          '</div>' +
        '</article>'
      ).join('') + '</div>' +
    '</div></section>' +

    '<section class="section"><div class="container">' +
      sectionHead('Порядок реагирования', 'От сигнала до спасения', 'Дежурные отделения находятся в постоянной готовности и выезжают на аварию по сигналу диспетчера.') +
      '<div class="timeline">' +
        '<div class="timeline__item"><div class="timeline__year">01</div><h3 class="timeline__title">Получение сигнала</h3><p class="timeline__text">Дежурный диспетчер принимает сообщение, уточняет место и характер аварии, оповещает командование и дежурное отделение.</p></div>' +
        '<div class="timeline__item"><div class="timeline__year">02</div><h3 class="timeline__title">Выезд и развёртывание</h3><p class="timeline__text">Отделение выезжает на предприятие, развёртывает оперативный штаб и устанавливает связь с руководством шахты.</p></div>' +
        '<div class="timeline__item"><div class="timeline__year">03</div><h3 class="timeline__title">Разведка и спасение</h3><p class="timeline__text">Горноспасатели проводят разведку выработок, определяют границы опасной зоны, выводят людей и оказывают первую помощь.</p></div>' +
        '<div class="timeline__item"><div class="timeline__year">04</div><h3 class="timeline__title">Ликвидация аварии</h3><p class="timeline__text">Совместно с предприятием и подразделениями МЧС выполняются работы по локализации и ликвидации аварии, проводится разбор действий.</p></div>' +
      '</div>' +
    '</div></section>' +

    ctaBand(data, 'Вопросы по структуре и зоне обслуживания?', 'Обратитесь в приёмную отряда или дежурную службу — мы ответим на ваш запрос.', 'contacts.html#feedback', 'Написать в отряд', 'contacts.html', 'Контакты');
  }

  /* =========================================================
     Деятельность
     ========================================================= */
  function activity(data) {
    const s = data.settings;
    const page = data.pages.find((p) => p.slug === 'activity');
    const cards = [
      { id: 'rescue', icon: 'helmet', title: 'Горноспасательные работы', text: 'Выезд на аварии на опасных производственных объектах, разведка горных выработок, поиск и спасение людей, ликвидация очагов возгорания и последствий выбросов.', points: ['Круглосуточная готовность дежурных отделений', 'Работа в изолирующих дыхательных аппаратах', 'Оказание первой помощи и эвакуация пострадавших'] },
      { id: 'prevention', icon: 'pulse', title: 'Профилактика аварий', text: 'Плановые обследования предприятий, контроль состояния проветривания, противопожарной защиты и готовности персонала к действиям при аварии.', points: ['Обследование горных выработок', 'Проверка средств защиты и связи', 'Разработка мероприятий по устранению замечаний'] },
      { id: 'training', icon: 'book', title: 'Обучение и подготовка', text: 'Профессиональная подготовка личного состава, учебно-методические сборы, тренировки и соревнования по горноспасательному делу.', points: ['Практические занятия на полигоне', 'Изучение нормативных документов', 'Обмен опытом с подразделениями ВГСЧ'] },
      { id: 'cooperation', icon: 'handshake', title: 'Взаимодействие с МЧС России', text: 'Работа в единой системе предупреждения и ликвидации чрезвычайных ситуаций, совместные учения и информационный обмен.', points: ['Совместные тренировки и штабные учения', 'Привлечение аэромобильных групп', 'Международное сотрудничество'] },
      { id: 'medical', icon: 'pulse', title: 'Медицинская служба', text: 'Реанимационно-противошоковая группа и медицинское обеспечение горноспасательных работ. Оказание помощи пострадавшим на месте и при транспортировке.', points: ['Готовность к работе в шахте', 'Первая и реанимационная помощь', 'Взаимодействие с медицинскими организациями'] },
      { id: 'equipment', icon: 'shield', title: 'Техника и оснащение', text: 'Современные дыхательные аппараты, средства связи, аварийно-спасательный инструмент и специальный транспорт, поддерживаемые в постоянной готовности.', points: ['Изолирующие респираторы', 'Приборы контроля газовой среды', 'Аварийно-спасательные автомобили'] }
    ];

    return pageHero({
      title: page ? page.title : 'Деятельность',
      lead: page ? page.lead : s.activity_lead,
      crumbs: [{ label: 'О филиале' }, { label: 'Деятельность' }],
      meta: [
        { icon: 'siren', text: 'Аварийное реагирование 24/7' },
        { icon: 'pulse', text: 'Профилактика и обследования' },
        { icon: 'book', text: 'Обучение личного состава' }
      ]
    }) +
    '<section class="section"><div class="container">' +
      sectionHead('Направления работы', 'Чем занимается отряд', 'Основные задачи филиала — спасение людей, предупреждение аварий и поддержание готовности сил и средств.') +
      '<div class="grid grid--2">' + cards.map((card) =>
        '<article class="division-card" id="' + card.id + '" style="padding:1.5rem">' +
          '<div class="division-card__top"><span class="activity-card__icon">' + icon(card.icon, '', 26) + '</span><span class="badge badge--soft">Направление</span></div>' +
          '<h3 style="font-size:1.15rem">' + esc(card.title) + '</h3>' +
          '<p style="font-size:.9063rem">' + esc(card.text) + '</p>' +
          '<ul style="margin:.25rem 0 0;padding-left:1.1rem;font-size:.8438rem;color:var(--muted)">' + card.points.map((point) => '<li>' + esc(point) + '</li>').join('') + '</ul>' +
        '</article>'
      ).join('') + '</div>' +
    '</div></section>' +

    '<section class="section section--soft"><div class="container">' +
      sectionHead('Как это работает', 'От профилактики до ликвидации аварии', 'Работа строится на постоянном взаимодействии с предприятиями и органами управления.') +
      '<div class="activity-grid">' +
        '<div class="activity-card"><span class="activity-card__icon">' + icon('search', '', 26) + '</span><span><h3>Обследование</h3><p>Плановые проверки выработок, вентиляции, средств защиты и готовности персонала.</p></span></div>' +
        '<div class="activity-card"><span class="activity-card__icon">' + icon('doc', '', 26) + '</span><span><h3>Планирование</h3><p>Участие в разработке планов ликвидации аварий и мероприятий по повышению безопасности.</p></span></div>' +
        '<div class="activity-card"><span class="activity-card__icon">' + icon('book', '', 26) + '</span><span><h3>Подготовка</h3><p>Тренировки и учения, отработка действий в условиях, приближенных к реальным.</p></span></div>' +
        '<div class="activity-card"><span class="activity-card__icon">' + icon('siren', '', 26) + '</span><span><h3>Реагирование</h3><p>Круглосуточный выезд, спасение людей, ликвидация аварии и разбор действий.</p></span></div>' +
      '</div>' +
    '</div></section>' +

    '<section class="section"><div class="container">' +
      sectionHead('Оснащение', 'Техника и оборудование', 'Отряд оснащён современными средствами для выполнения горноспасательных работ любой сложности.') +
      '<div class="filter-chips">' + ['Изолирующие респираторы', 'Регенеративные патроны', 'Приборы контроля газов', 'Средства связи', 'Аварийно-спасательный инструмент', 'Осветительное оборудование', 'Специальный автотранспорт', 'Медицинское оборудование'].map((tag) => '<span class="chip">' + esc(tag) + '</span>').join('') + '</div>' +
      '<p class="muted" style="font-size:.875rem;max-width:48rem">Точный перечень техники и оборудования, а также порядок его применения определяются нормативными документами и условиями договоров на горноспасательное обслуживание.</p>' +
    '</div></section>' +

    ctaBand(data, 'Нужна консультация по горноспасательному обслуживанию?', 'Направьте официальный запрос в приёмную отряда — специалисты подготовят ответ.', 'contacts.html#feedback', 'Направить запрос', 'documents.html', 'Документы');
  }

  /* =========================================================
     Новости
     ========================================================= */
  function news(data) {
    const s = data.settings;
    const page = data.pages.find((p) => p.slug === 'news');
    return pageHero({
      title: 'Новости',
      lead: s.news_lead,
      crumbs: [{ label: 'Пресс-центр' }, { label: 'Новости' }],
      meta: [
        { icon: 'calendar', text: 'События отряда' },
        { icon: 'news', text: 'Официальные публикации' }
      ]
    }) +
    '<section class="section"><div class="container">' +
      '<div class="filter-panel">' +
        '<div class="filter-chips" id="news-cats"></div>' +
        '<div class="filter-fields">' +
          '<div class="field field--search"><label for="news-q">Поиск по новостям</label><input class="input" id="news-q" type="search" placeholder="Ключевое слово, событие, предприятие…" autocomplete="off"></div>' +
          '<div class="field"><label for="news-from">С даты</label><input class="input" id="news-from" type="date"></div>' +
          '<div class="field"><label for="news-to">По дату</label><input class="input" id="news-to" type="date"></div>' +
          '<div class="field"><span class="field__label">&nbsp;</span><button type="button" class="btn btn-ghost" id="news-reset">Сбросить</button></div>' +
        '</div>' +
      '</div>' +
      '<div class="filter-result"><span id="news-result-count"></span><span class="muted">Сортировка: сначала новые</span></div>' +
      '<div id="news-list"></div>' +
      '<div class="pagination" id="news-pagination"></div>' +
    '</div></section>' +

    '<section class="section section--soft"><div class="container">' +
      '<div class="grid grid--2" style="align-items:center">' +
        '<div>' +
          '<div class="eyebrow">Подписка</div>' +
          '<h2 class="section__title">Будьте в курсе событий отряда</h2>' +
          '<p class="section__lead">Оставьте адрес электронной почты, чтобы получать сообщения о новостях и мероприятиях. Рассылка не является средством экстренного оповещения.</p>' +
        '</div>' +
        '<form class="form-card" data-subscribe novalidate>' +
          '<h2>Подписка на новости</h2>' +
          '<p>Мы используем адрес только для отправки сообщений о деятельности филиала.</p>' +
          '<div class="field"><label for="subscribe-email">Электронная почта</label><input class="input" id="subscribe-email" type="email" name="email" placeholder="name@example.com" autocomplete="email"></div>' +
          '<label class="form-consent" style="margin-top:.9rem"><input type="checkbox" name="consent"> <span>Согласен на обработку персональных данных в соответствии с <a href="privacy-policy.html" target="_blank" rel="noopener">Политикой</a>.</span></label>' +
          '<div class="form-error" data-subscribe-status aria-live="polite"></div>' +
          '<button class="btn btn-primary btn-block" type="submit" style="margin-top:.75rem">Подписаться</button>' +
        '</form>' +
      '</div>' +
    '</div></section>';
  }

  function initNews(data) {
    const catsBox = document.getElementById('news-cats');
    const list = document.getElementById('news-list');
    const pagination = document.getElementById('news-pagination');
    const count = document.getElementById('news-result-count');
    const qInput = document.getElementById('news-q');
    const fromInput = document.getElementById('news-from');
    const toInput = document.getElementById('news-to');
    const reset = document.getElementById('news-reset');
    if (!catsBox || !list) return;

    const params = new URLSearchParams(location.search);
    state.news.cat = params.get('cat') || 'all';
    state.news.q = params.get('q') || '';
    state.news.from = params.get('from') || '';
    state.news.to = params.get('to') || '';
    state.news.page = Math.max(1, Number(params.get('page')) || 1);
    qInput.value = state.news.q;
    fromInput.value = state.news.from;
    toInput.value = state.news.to;

    const published = data.news.filter((item) => item.published);
    const categories = data.newsCategories;

    function counts() {
      const map = { all: published.length };
      categories.forEach((cat) => { map[cat] = published.filter((item) => item.category === cat).length; });
      return map;
    }

    function renderCats() {
      const c = counts();
      catsBox.innerHTML = [{ key: 'all', label: 'Все новости' }].concat(categories.map((cat) => ({ key: cat, label: cat })))
        .filter((item) => item.key === 'all' || c[item.key] > 0)
        .map((item) => '<button type="button" class="chip' + (state.news.cat === item.key ? ' is-active' : '') + '" data-cat="' + esc(item.key) + '">' + esc(item.label) + ' <span class="chip__count">' + (c[item.key] || 0) + '</span></button>')
        .join('');
      catsBox.querySelectorAll('[data-cat]').forEach((button) => {
        button.addEventListener('click', () => { state.news.cat = button.dataset.cat; state.news.page = 1; render(); });
      });
    }

    function filtered() {
      const q = String(state.news.q || '').toLowerCase().replace(/ё/g, 'е').trim();
      return published.filter((item) => {
        if (state.news.cat !== 'all' && item.category !== state.news.cat) return false;
        if (state.news.from && item.date < state.news.from) return false;
        if (state.news.to && item.date > state.news.to) return false;
        if (q) {
          const hay = [item.title, item.excerpt, item.body, item.category, (item.tags || []).join(' ')].join(' ').toLowerCase().replace(/ё/g, 'е');
          if (!q.split(/\s+/).every((token) => hay.indexOf(token) !== -1)) return false;
        }
        return true;
      }).sort((a, b) => (b.date || '').localeCompare(a.date || ''));
    }

    function render() {
      const items = filtered();
      const pages = Math.max(1, Math.ceil(items.length / state.news.perPage));
      if (state.news.page > pages) state.news.page = pages;
      const start = (state.news.page - 1) * state.news.perPage;
      const slice = items.slice(start, start + state.news.perPage);

      count.innerHTML = items.length
        ? 'Найдено <strong>' + items.length + '</strong> ' + VGS.plural(items.length, ['новость', 'новости', 'новостей']) + (state.news.cat !== 'all' ? ' в категории «' + esc(state.news.cat) + '»' : '')
        : '';

      list.innerHTML = slice.length
        ? '<div class="news-grid">' + slice.map(newsCard).join('') + '</div>'
        : emptyState('Новостей не найдено', 'Попробуйте изменить категорию, даты или поисковый запрос.', '<button type="button" class="btn btn-ghost" data-news-reset>Сбросить фильтры</button>');

      pagination.innerHTML = pages > 1
        ? '<button type="button" class="page-btn" data-page="' + (state.news.page - 1) + '"' + (state.news.page === 1 ? ' disabled' : '') + ' aria-label="Предыдущая страница">' + icon('chevronLeft', '', 16) + '</button>' +
          Array.from({ length: pages }, (_, i) => i + 1).map((page) =>
            '<button type="button" class="page-btn' + (page === state.news.page ? ' is-active' : '') + '" data-page="' + page + '"' + (page === state.news.page ? ' aria-current="page"' : '') + '>' + page + '</button>'
          ).join('') +
          '<button type="button" class="page-btn" data-page="' + (state.news.page + 1) + '"' + (state.news.page === pages ? ' disabled' : '') + ' aria-label="Следующая страница">' + icon('chevronRight', '', 16) + '</button>'
        : '';

      pagination.querySelectorAll('[data-page]').forEach((button) => {
        button.addEventListener('click', () => {
          state.news.page = Number(button.dataset.page);
          render();
          list.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      });
      const resetButton = list.querySelector('[data-news-reset]');
      if (resetButton) resetButton.addEventListener('click', resetFilters);

      renderCats();
      const url = new URL(location.href);
      ['cat', 'q', 'from', 'to', 'page'].forEach((key) => url.searchParams.delete(key));
      if (state.news.cat !== 'all') url.searchParams.set('cat', state.news.cat);
      if (state.news.q) url.searchParams.set('q', state.news.q);
      if (state.news.from) url.searchParams.set('from', state.news.from);
      if (state.news.to) url.searchParams.set('to', state.news.to);
      if (state.news.page > 1) url.searchParams.set('page', String(state.news.page));
      history.replaceState(null, '', url.pathname + (url.search ? url.search : ''));
    }

    function resetFilters() {
      state.news = { cat: 'all', q: '', from: '', to: '', page: 1, perPage: 6 };
      qInput.value = '';
      fromInput.value = '';
      toInput.value = '';
      render();
    }

    let timer = null;
    qInput.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => { state.news.q = qInput.value; state.news.page = 1; render(); }, 200);
    });
    fromInput.addEventListener('change', () => { state.news.from = fromInput.value; state.news.page = 1; render(); });
    toInput.addEventListener('change', () => { state.news.to = toInput.value; state.news.page = 1; render(); });
    reset.addEventListener('click', resetFilters);

    render();
  }

  /* =========================================================
     Новость
     ========================================================= */
  function article(data) {
    const params = new URLSearchParams(location.search);
    const id = params.get('id');
    const item = data.news.find((n) => n.id === String(id) && n.published);
    if (!item) return notFound(data, 'Новость не найдена', 'Возможно, публикация была удалена или ссылка устарела.');

    setMeta(item.title + ' — Новости Новокузнецкого ВГСО', item.excerpt || VGS.truncate(item.body, 160), 'https://nvgso.vgsch.ru/assets/img/' + item.image + '-1400.webp');
    const canonical = document.querySelector('link[rel="canonical"]');
    if (canonical) canonical.setAttribute('href', 'https://nvgso.vgsch.ru/article.html?id=' + encodeURIComponent(item.id));
    else {
      const link = document.createElement('link');
      link.rel = 'canonical';
      link.href = 'https://nvgso.vgsch.ru/article.html?id=' + encodeURIComponent(item.id);
      document.head.appendChild(link);
    }
    const ogUrl = document.querySelector('meta[property="og:url"]');
    if (ogUrl) ogUrl.setAttribute('content', 'https://nvgso.vgsch.ru/article.html?id=' + encodeURIComponent(item.id));

    const sorted = data.news.filter((n) => n.published).sort((a, b) => (b.date || '').localeCompare(a.date || ''));
    const index = sorted.findIndex((n) => n.id === item.id);
    const prev = sorted[index + 1];
    const next = sorted[index - 1];
    const related = sorted.filter((n) => n.id !== item.id && n.category === item.category).slice(0, 3);
    const others = related.length ? related : sorted.filter((n) => n.id !== item.id).slice(0, 3);
    const words = String(item.body || '').split(/\s+/).filter(Boolean).length;
    const reading = Math.max(1, Math.round(words / 180));

    const json = {
      '@context': 'https://schema.org',
      '@type': 'NewsArticle',
      headline: item.title,
      description: item.excerpt,
      datePublished: item.date,
      dateModified: item.date,
      image: ['https://nvgso.vgsch.ru/assets/img/' + item.image + '-1400.webp'],
      articleSection: item.category,
      publisher: { '@type': 'GovernmentOrganization', name: 'Филиал «Новокузнецкий ВГСО» ФГУП «ВГСЧ»' },
      mainEntityOfPage: location.href
    };
    const old = document.getElementById('article-jsonld');
    if (old) old.remove();
    const script = document.createElement('script');
    script.type = 'application/ld+json';
    script.id = 'article-jsonld';
    script.textContent = JSON.stringify(json);
    document.head.appendChild(script);

    return pageHero({
      title: item.title,
      crumbs: [{ label: 'Пресс-центр', href: 'news.html' }, { label: 'Новости', href: 'news.html' }, { label: 'Публикация' }],
      meta: [
        { icon: 'calendar', text: ruDateLong(item.date) },
        { icon: 'news', text: item.category },
        { icon: 'clock', text: reading + ' мин чтения' }
      ],
      compact: true
    }) +
    '<section class="section"><div class="container article-layout">' +
      '<article class="article">' +
        photo(item.image, item.title, 'article__cover', '(max-width: 900px) 100vw, 60vw', item.imageSrc) +
        '<div class="article__meta"><span class="badge">' + esc(item.category) + '</span><span>' + icon('calendar', '', 14) + ' ' + esc(ruDateLong(item.date)) + '</span><span>' + icon('clock', '', 14) + ' ' + reading + ' мин чтения</span></div>' +
        (item.excerpt ? '<p class="article__lead">' + esc(item.excerpt) + '</p>' : '') +
        '<div class="article__body">' + String(item.body || '').split(/\n{2,}/).map((p) => '<p>' + esc(p) + '</p>').join('') + '</div>' +
        (item.tags && item.tags.length ? '<div class="article__tags">' + item.tags.map((tag) => '<span class="badge">#' + esc(tag) + '</span>').join('') + '</div>' : '') +
        '<div class="article__share"><strong>Поделиться:</strong>' +
          '<button type="button" class="btn btn-ghost btn-sm" data-copy="' + esc(location.href) + '">' + icon('link', '', 15) + 'Скопировать ссылку</button>' +
          '<button type="button" class="btn btn-ghost btn-sm" data-print>' + icon('print', '', 15) + 'Печать</button>' +
          '<a class="btn btn-ghost btn-sm" href="https://vk.com/share.php?url=' + encodeURIComponent(location.href) + '" target="_blank" rel="noopener">ВКонтакте</a>' +
        '</div>' +
        '<nav class="article__nav" aria-label="Другие новости">' +
          (prev ? '<a href="article.html?id=' + encodeURIComponent(prev.id) + '"><span>Предыдущая новость</span>' + icon('chevronLeft', '', 14) + ' ' + esc(prev.title) + '</a>' : '<span></span>') +
          (next ? '<a href="article.html?id=' + encodeURIComponent(next.id) + '" style="text-align:right;margin-left:auto"><span>Следующая новость</span>' + esc(next.title) + ' ' + icon('chevronRight', '', 14) + '</a>' : '') +
        '</nav>' +
      '</article>' +
      '<aside>' +
        '<div class="side-card side-card--navy" style="margin-bottom:1rem">' +
          '<h3>Пресс-служба отряда</h3>' +
          '<p>По вопросам сотрудничества и аккредитации СМИ обращайтесь в приёмную.</p>' +
          '<p style="margin-top:.75rem"><a href="' + tel(data.settings.phone_office) + '">' + esc(data.settings.phone_office) + '</a><br><a href="mailto:' + esc(data.settings.email) + '">' + esc(data.settings.email) + '</a></p>' +
        '</div>' +
        '<div class="side-card">' +
          '<h3>Другие новости</h3>' +
          '<ul>' + others.map((n) => '<li><a href="article.html?id=' + encodeURIComponent(n.id) + '">' + esc(n.title) + '</a></li>').join('') + '</ul>' +
          '<p style="margin-top:.9rem">' + moreLink('news.html', 'Все новости') + '</p>' +
        '</div>' +
      '</aside>' +
    '</div></section>';
  }

  /* =========================================================
     Документы
     ========================================================= */
  function documents(data) {
    const s = data.settings;
    const published = data.docs.filter((item) => item.published);
    const categories = data.docCategories;

    return pageHero({
      title: 'Документы',
      lead: s.documents_lead,
      crumbs: [{ label: 'Пресс-центр' }, { label: 'Документы' }],
      meta: [
        { icon: 'doc', text: 'PDF-документы' },
        { icon: 'shield', text: 'Официальные материалы' }
      ]
    }) +
    '<section class="section"><div class="container docs-layout">' +
      '<div>' +
        '<div class="filter-panel">' +
          '<div class="filter-chips" id="doc-cats"></div>' +
          '<div class="filter-fields" style="grid-template-columns:1fr auto">' +
            '<div class="field field--search"><label for="doc-q">Поиск по документам</label><input class="input" id="doc-q" type="search" placeholder="Название или категория документа…" autocomplete="off"></div>' +
            '<div class="field"><span class="field__label">&nbsp;</span><button type="button" class="btn btn-ghost" id="doc-reset">Сбросить</button></div>' +
          '</div>' +
        '</div>' +
        '<div class="filter-result"><span id="doc-result-count"></span><span class="muted">Файлы открываются в новой вкладке</span></div>' +
        '<div class="docs-list" id="docs-list"></div>' +
      '</div>' +
      '<aside class="docs-side">' +
        '<div class="side-card">' +
          '<h3>Обязательные документы</h3>' +
          '<ul>' +
            '<li><a href="privacy-policy.html">' + icon('shield', '', 15) + 'Политика обработки ПДн</a></li>' +
            '<li><a href="company-policy.html">' + icon('building', '', 15) + 'Политика компании</a></li>' +
            '<li><a href="cookie-policy.html">' + icon('cookie', '', 15) + 'Политика использования cookie</a></li>' +
          '</ul>' +
        '</div>' +
        '<div class="side-card">' +
          '<h3>Доступность PDF</h3>' +
          '<p>HTML-версии политик доступны по ссылкам выше и корректно читаются программами экранного доступа. Для PDF-файлов обеспечена возможность масштабирования.</p>' +
        '</div>' +
        '<div class="side-card side-card--red">' +
          '<h3>Нужен документ?</h3>' +
          '<p>Направьте официальный запрос в приёмную отряда.</p>' +
          '<p style="margin-top:.6rem"><a href="contacts.html#feedback">' + icon('send', '', 15) + 'Форма обратной связи</a></p>' +
        '</div>' +
      '</aside>' +
    '</div></section>';
  }

  function initDocuments(data) {
    const catsBox = document.getElementById('doc-cats');
    const list = document.getElementById('docs-list');
    const count = document.getElementById('doc-result-count');
    const qInput = document.getElementById('doc-q');
    const reset = document.getElementById('doc-reset');
    if (!catsBox || !list) return;

    const published = data.docs.filter((item) => item.published).sort((a, b) => (b.date || '').localeCompare(a.date || ''));
    const params = new URLSearchParams(location.search);
    state.documents.cat = params.get('cat') || 'all';
    state.documents.q = params.get('q') || '';
    qInput.value = state.documents.q;

    function renderCats() {
      const cats = data.docCategories.filter((cat) => published.some((doc) => doc.category === cat));
      catsBox.innerHTML = '<button type="button" class="chip' + (state.documents.cat === 'all' ? ' is-active' : '') + '" data-cat="all">Все документы <span class="chip__count">' + published.length + '</span></button>' +
        cats.map((cat) => {
          const n = published.filter((doc) => doc.category === cat).length;
          return '<button type="button" class="chip' + (state.documents.cat === cat ? ' is-active' : '') + '" data-cat="' + esc(cat) + '">' + esc(cat) + ' <span class="chip__count">' + n + '</span></button>';
        }).join('');
      catsBox.querySelectorAll('[data-cat]').forEach((button) => button.addEventListener('click', () => {
        state.documents.cat = button.dataset.cat;
        render();
      }));
    }

    function filtered() {
      const q = String(state.documents.q || '').toLowerCase().replace(/ё/g, 'е').trim();
      return published.filter((doc) => {
        if (state.documents.cat !== 'all' && doc.category !== state.documents.cat) return false;
        if (!q) return true;
        return (doc.title + ' ' + doc.category + ' ' + (doc.note || '')).toLowerCase().replace(/ё/g, 'е').indexOf(q) !== -1;
      });
    }

    function render() {
      const items = filtered();
      count.innerHTML = 'Найдено <strong>' + items.length + '</strong> ' + VGS.plural(items.length, ['документ', 'документа', 'документов']);
      list.innerHTML = items.length ? items.map(docRow).join('') : emptyState('Документы не найдены', 'Попробуйте изменить запрос или выбрать другую категорию.');
      renderCats();
      const url = new URL(location.href);
      ['cat', 'q'].forEach((key) => url.searchParams.delete(key));
      if (state.documents.cat !== 'all') url.searchParams.set('cat', state.documents.cat);
      if (state.documents.q) url.searchParams.set('q', state.documents.q);
      history.replaceState(null, '', url.pathname + (url.search ? url.search : ''));
    }

    let timer = null;
    qInput.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => { state.documents.q = qInput.value; render(); }, 200);
    });
    reset.addEventListener('click', () => {
      state.documents = { cat: 'all', q: '' };
      qInput.value = '';
      render();
    });

    render();

    if (location.hash) {
      const target = document.getElementById(location.hash.slice(1));
      if (target) setTimeout(() => {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.style.outline = '3px solid var(--gold)';
        target.style.outlineOffset = '2px';
        setTimeout(() => { target.style.outline = ''; }, 2200);
      }, 300);
    }
  }

  /* =========================================================
     Фотогалерея
     ========================================================= */
  function gallery(data) {
    const s = data.settings;
    return pageHero({
      title: 'Фотогалерея',
      lead: s.gallery_lead,
      crumbs: [{ label: 'Пресс-центр' }, { label: 'Фотогалерея' }],
      meta: [
        { icon: 'gallery', text: 'Фото и видео' },
        { icon: 'star', text: 'Архив мероприятий' }
      ]
    }) +
    '<section class="section"><div class="container">' +
      '<div class="gallery-tabs" id="gallery-tabs"></div>' +
      '<div class="gallery-grid" id="gallery-grid"></div>' +
      '<p class="muted" style="font-size:.75rem;margin-top:1rem">Фото и видеоматериалы: пресс-служба ФГУП «ВГСЧ», МЧС России. Материалы публикуются в информационных целях.</p>' +
    '</div></section>';
  }

  function initGallery(data) {
    const tabs = document.getElementById('gallery-tabs');
    const grid = document.getElementById('gallery-grid');
    if (!tabs || !grid) return;

    const params = new URLSearchParams(location.search);
    state.gallery.album = params.get('album') || 'all';
    const openId = params.get('photo');

    const items = data.gallery.filter((item) => item.published);
    const albums = data.albums.filter((album) => items.some((item) => item.album === album.id));

    function filtered() {
      if (state.gallery.album === 'video') return items.filter((item) => item.type === 'video');
      if (state.gallery.album === 'all') return items.filter((item) => item.type === 'photo');
      return items.filter((item) => item.album === state.gallery.album);
    }

    function renderTabs() {
      const tabList = [{ id: 'all', title: 'Все фото' }].concat(albums.map((a) => ({ id: a.id, title: a.title })));
      if (items.some((item) => item.type === 'video')) tabList.push({ id: 'video', title: 'Видео' });
      tabs.innerHTML = tabList.map((tab) =>
        '<button type="button" class="chip' + (state.gallery.album === tab.id ? ' is-active' : '') + '" data-album="' + esc(tab.id) + '">' + esc(tab.title) + '</button>'
      ).join('');
      tabs.querySelectorAll('[data-album]').forEach((button) => button.addEventListener('click', () => {
        state.gallery.album = button.dataset.album;
        renderTabs();
        renderGrid();
      }));
    }

    function renderGrid() {
      const list = filtered();
      grid.innerHTML = list.length ? list.map((item) =>
        '<button type="button" class="gallery-item" data-gallery-id="' + esc(item.id) + '" aria-label="Открыть: ' + esc(item.title) + '">' +
          photo(item.image, item.title, '', '(max-width: 520px) 50vw, (max-width: 900px) 33vw, 25vw', item.src) +
          (item.type === 'video' ? '<span class="gallery-item__play">' + icon('play', '', 22) + '</span>' : '') +
          '<span class="gallery-item__caption">' + esc(item.title) + '<span class="gallery-item__date">' + esc(ruDate(item.date)) + '</span></span>' +
        '</button>'
      ).join('') : emptyState('Материалов пока нет', 'В этом альбоме ещё не опубликованы фотографии. Загляните позже.');
      grid.querySelectorAll('[data-gallery-id]').forEach((button) => button.addEventListener('click', () => {
        const current = filtered();
        const index = current.findIndex((item) => item.id === button.dataset.galleryId);
        window.SITE.openLightbox(current, index);
      }));
    }

    renderTabs();
    renderGrid();

    if (openId) {
      let list = filtered();
      let index = list.findIndex((item) => item.id === openId);
      if (index === -1) {
        const video = items.find((item) => item.id === openId && item.type === 'video');
        if (video) {
          state.gallery.album = 'video';
          renderTabs();
          renderGrid();
          list = filtered();
          index = list.findIndex((item) => item.id === openId);
        }
      }
      if (index !== -1) setTimeout(() => window.SITE.openLightbox(list, index), 350);
    }
  }

  /* =========================================================
     Контакты
     ========================================================= */
  function contacts(data) {
    const s = data.settings;
    const divisions = data.divisions.filter((item) => item.published);
    const phones = data.phones.filter((item) => item.published);
    const groups = {};
    phones.forEach((item) => {
      const dep = (item.department || 'Прочее').trim();
      (groups[dep] = groups[dep] || []).push(item);
    });

    const phoneGroupsHtml = Object.keys(groups).sort((a, b) => a.localeCompare(b, 'ru')).map((dep) => {
      const rows = groups[dep].slice().sort((a, b) => (a.position || '').localeCompare(b.position || '', 'ru'));
      return '<div class="phone-group"><h3 class="phone-group__title">' + esc(dep) + '</h3>' + rows.map((p) =>
        '<div class="phone-row">' +
          '<div><div class="phone-row__position">' + esc(p.position) + '</div>' + (p.name ? '<div class="phone-row__name">' + esc(p.name) + '</div>' : '') + '</div>' +
          '<div class="phone-row__contacts">' +
            (p.phone ? '<a class="phone-row__tel" href="' + tel(p.phone) + '">' + icon('phone', '', 14) + ' ' + esc(p.phone) + '</a>' : '') +
            (p.email ? '<a class="phone-row__mail" href="mailto:' + esc(p.email) + '">' + icon('mail', '', 13) + ' ' + esc(p.email) + '</a>' : '') +
          '</div>' +
        '</div>'
      ).join('') + '</div>';
    }).join('');

    return pageHero({
      title: 'Контакты',
      lead: s.contacts_lead,
      crumbs: [{ label: 'Контакты' }],
      meta: [
        { icon: 'phone', text: 'Дежурная служба 24/7' },
        { icon: 'mail', text: 'Обратная связь' },
        { icon: 'pin', text: 'Новокузнецк' }
      ]
    }) +
    '<section class="section"><div class="container contacts-grid">' +
      '<div>' +
        '<div class="eyebrow">Отряд</div>' +
        '<h2 class="section__title">Связаться с отрядом</h2>' +
        '<p class="section__lead" style="margin-bottom:1.25rem">' + esc(s.address) + '</p>' +
        '<div class="contact-cards">' +
          '<div class="contact-card contact-card--accent"><div class="contact-card__icon">' + icon('phone', '', 20) + '</div><div><div class="contact-card__label">Дежурная служба · 24/7</div><div class="contact-card__value"><a href="' + tel(s.phone_duty) + '">' + esc(s.phone_duty) + '</a></div><div class="contact-card__note">При авариях и чрезвычайных ситуациях</div></div></div>' +
          '<div class="contact-card"><div class="contact-card__icon">' + icon('phone', '', 20) + '</div><div><div class="contact-card__label">Приёмная</div><div class="contact-card__value"><a href="' + tel(s.phone_office) + '">' + esc(s.phone_office) + '</a></div><div class="contact-card__note">' + esc(s.hours) + '</div></div></div>' +
          '<div class="contact-card"><div class="contact-card__icon">' + icon('mail', '', 20) + '</div><div><div class="contact-card__label">Электронная почта</div><div class="contact-card__value"><a href="mailto:' + esc(s.email) + '">' + esc(s.email) + '</a></div><div class="contact-card__note">Для официальных обращений и запросов</div></div></div>' +
          '<div class="contact-card"><div class="contact-card__icon">' + icon('siren', '', 20) + '</div><div><div class="contact-card__label">Единый номер экстренных служб</div><div class="contact-card__value"><a href="tel:112">112</a></div><div class="contact-card__note">' + esc(s.emergency_text) + '</div></div></div>' +
        '</div>' +
        '<div class="requisites" style="margin-top:1.25rem">' +
          '<h3>Реквизиты головной организации</h3>' +
          '<dl>' +
            '<dt>Организация</dt><dd>ФГУП «ВГСЧ»</dd>' +
            '<dt>ИНН</dt><dd>' + esc(s.inn) + '</dd>' +
            '<dt>ОГРН</dt><dd>' + esc(s.ogrn) + '</dd>' +
            '<dt>Юр. адрес</dt><dd>' + esc(s.legal_address) + '</dd>' +
            '<dt>Телефон</dt><dd>' + esc(s.phone_parent) + '</dd>' +
            '<dt>Почта</dt><dd>' + esc(s.email_parent) + '</dd>' +
          '</dl>' +
        '</div>' +
      '</div>' +
      '<div>' +
        '<div class="map-card" data-map="' + esc(s.map_query) + '">' +
          '<div class="map-card__placeholder">' +
            '<div class="map-card__pin">' + icon('pin', '', 38) + '<strong>Новокузнецкий ВГСО</strong><span>' + esc(s.address) + '</span><div class="map-card__actions"><button type="button" class="btn btn-gold btn-sm" data-map-load>' + icon('route', '', 16) + 'Показать карту</button></div></div>' +
          '</div>' +
          '<div class="map-card__foot"><span>Карта загружается по вашему запросу</span><a href="https://yandex.ru/maps/?text=' + encodeURIComponent(s.map_query) + '" target="_blank" rel="noopener">Открыть в Яндекс.Картах ' + icon('external', '', 13) + '</a></div>' +
        '</div>' +
        '<div class="side-card" style="margin-top:1rem">' +
          '<h3>Подразделения</h3>' +
          '<ul>' + divisions.map((item) => '<li><a href="structure.html#' + esc(item.id) + '">' + icon('pin', '', 14) + esc(item.name) + ' — ' + esc(item.city) + '</a></li>').join('') + '</ul>' +
        '</div>' +
      '</div>' +
    '</div></section>' +

    '<section class="section section--soft" id="feedback"><div class="container">' +
      '<div class="grid grid--2" style="align-items:start">' +
        '<div>' +
          '<div class="eyebrow">Обратная связь</div>' +
          '<h2 class="section__title">Напишите в отряд</h2>' +
          '<p class="section__lead">Заполните форму, и мы подготовим ответ в установленном порядке. Поля, отмеченные звёздочкой, обязательны.</p>' +
          '<div class="side-card" style="margin-top:1rem">' +
            '<h3>Порядок обращения</h3>' +
            '<ul>' +
              '<li><a href="privacy-policy.html">' + icon('shield', '', 14) + 'Политика обработки персональных данных</a></li>' +
              '<li><a href="documents.html">' + icon('doc', '', 14) + 'Порядок рассмотрения обращений</a></li>' +
              '<li><a href="tel:112">' + icon('siren', '', 14) + 'Экстренные службы — 112</a></li>' +
            '</ul>' +
          '</div>' +
        '</div>' +
        '<form class="form-card" id="feedback-form" novalidate>' +
          '<div class="form-success hidden" tabindex="-1" data-form-success role="status" aria-live="polite"></div>' +
          '<h2>Форма обратной связи</h2>' +
          '<p>Сообщения рассматриваются в порядке, установленном законодательством об обращениях граждан.</p>' +
          '<div class="form-grid">' +
            '<div class="field"><label for="fb-name">Имя *</label><input class="input" id="fb-name" name="name" autocomplete="name" placeholder="Как к вам обращаться"></div>' +
            '<div class="field"><label for="fb-phone">Телефон</label><input class="input" id="fb-phone" name="phone" type="tel" autocomplete="tel" placeholder="+7 (___) ___-__-__"></div>' +
            '<div class="field"><label for="fb-email">Электронная почта</label><input class="input" id="fb-email" name="email" type="email" autocomplete="email" placeholder="name@example.com"></div>' +
            '<div class="field"><label for="fb-topic">Тема</label><select class="select" id="fb-topic" name="topic"><option value="">Выберите тему</option><option>Вопрос о деятельности отряда</option><option>Официальный запрос</option><option>Сотрудничество и СМИ</option><option>Трудоустройство</option><option>Ветераны и память</option><option>Другое</option></select></div>' +
            '<div class="field field--full"><label for="fb-message">Сообщение *</label><textarea class="textarea" id="fb-message" name="message" placeholder="Опишите вопрос подробно"></textarea></div>' +
          '</div>' +
          '<label class="form-consent"><input type="checkbox" name="consent"> <span>Я согласен на обработку персональных данных в соответствии с <a href="privacy-policy.html" target="_blank" rel="noopener">Политикой обработки персональных данных</a> и подтверждаю достоверность сведений. *</span></label>' +
          '<div class="honeypot" aria-hidden="true"><label for="fb-website">Website</label><input id="fb-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>' +
          '<div class="form-error" data-form-error role="alert" tabindex="-1"></div>' +
          '<button class="btn btn-primary btn-block" type="submit" style="margin-top:.75rem">' + icon('send', '', 18) + 'Отправить обращение</button>' +
          '<div class="form-note">Отправляя обращение, вы подтверждаете согласие на обработку персональных данных. Ответ направляется по указанному адресу электронной почты или в письменной форме.</div>' +
        '</form>' +
      '</div>' +
    '</div></section>' +

    '<section class="section" id="phones"><div class="container">' +
      sectionHead('Справочник', 'Телефонный справочник', 'Контакты подразделений и должностных лиц. Начните вводить должность, подразделение или телефон.') +
      '<div class="phone-search"><input class="input" id="phone-search" type="search" placeholder="Поиск по должности, подразделению, ФИО, телефону…" autocomplete="off" aria-label="Поиск по телефонному справочнику"></div>' +
      '<div id="phone-list">' + phoneGroupsHtml + '</div>' +
    '</div></section>';
  }

  function initContacts() {
    const input = document.getElementById('phone-search');
    const list = document.getElementById('phone-list');
    if (!input || !list) return;
    input.addEventListener('input', () => {
      const q = input.value.toLowerCase().trim();
      list.querySelectorAll('.phone-row').forEach((row) => {
        row.style.display = !q || row.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
      });
      list.querySelectorAll('.phone-group').forEach((group) => {
        const any = Array.from(group.querySelectorAll('.phone-row')).some((row) => row.style.display !== 'none');
        group.style.display = any ? '' : 'none';
      });
      let empty = list.querySelector('.empty-state');
      if (!empty) {
        empty = document.createElement('div');
        empty.className = 'empty-state';
        empty.innerHTML = '<h3>Ничего не найдено</h3><p>Измените поисковый запрос.</p>';
        list.appendChild(empty);
      }
      const visible = Array.from(list.querySelectorAll('.phone-row')).some((row) => row.style.display !== 'none');
      empty.style.display = visible ? 'none' : '';
    });
  }

  /* =========================================================
     Ветеранам и память
     ========================================================= */
  function veterans(data) {
    const s = data.settings;
    const page = data.pages.find((p) => p.slug === 'veterans');
    return pageHero({
      title: page ? page.title : 'Ветеранам и память',
      lead: page ? page.lead : s.veterans_lead,
      crumbs: [{ label: 'О филиале' }, { label: 'Ветеранам и память' }],
      meta: [
        { icon: 'star', text: 'Ветеранская организация' },
        { icon: 'book', text: 'Книга памяти' }
      ]
    }) +
    '<section class="memory-hero">' +
      '<img src="assets/img/memory-1600.webp" alt="Возложение цветов к мемориалу погибшим горноспасателям" loading="lazy">' +
      '<div class="container"><div class="memory-hero__in">' +
        '<div class="eyebrow" style="color:var(--gold-light)">Вечная память</div>' +
        '<h2>Они ушли, чтобы спасти других</h2>' +
        '<p>Горноспасатель идёт в шахту тогда, когда другие выходят на поверхность. Память о погибших товарищах — неотъемлемая часть истории отряда и горноспасательного дела Кузбасса.</p>' +
        '<blockquote class="memory-quote">«Земля не прощает ошибок. Наша задача — сделать всё, чтобы люди вернулись домой».<footer>Из истории Новокузнецкого ВГСО</footer></blockquote>' +
      '</div></div>' +
    '</section>' +

    '<section class="section"><div class="container grid grid--2" style="align-items:start">' +
      '<div>' +
        '<div class="eyebrow">Ветеранам</div>' +
        '<h2 class="section__title">Ветеранская организация</h2>' +
        '<p style="margin-top:1rem;color:var(--muted)">Ветераны отряда сохраняют традиции горноспасательной службы, участвуют в патриотических мероприятиях и передают опыт молодому поколению. Ветеранская организация взаимодействует с командованием отряда по вопросам социальной поддержки, памятных дат и увековечения памяти погибших товарищей.</p>' +
        '<ul class="about-facts" style="margin-top:1.2rem">' +
          '<li>' + icon('checkCircle', '', 20) + '<span>Участие в торжественных мероприятиях и юбилеях отряда</span></li>' +
          '<li>' + icon('checkCircle', '', 20) + '<span>Работа с молодёжью и профориентационные встречи</span></li>' +
          '<li>' + icon('checkCircle', '', 20) + '<span>Сохранение архивов, фотографий и воспоминаний</span></li>' +
        '</ul>' +
        '<div class="contact-card" style="margin-top:1.25rem">' +
          '<div class="contact-card__icon">' + icon('users', '', 20) + '</div>' +
          '<div><div class="contact-card__label">Связаться с советом ветеранов</div><div class="contact-card__value"><a href="' + tel(s.phone_office) + '">' + esc(s.phone_office) + '</a></div><div class="contact-card__note">Приёмная отряда, пн–пт · <a href="mailto:' + esc(s.email) + '">' + esc(s.email) + '</a></div></div>' +
        '</div>' +
      '</div>' +
      '<div>' +
        '<div class="eyebrow">Книга памяти</div>' +
        '<h2 class="section__title">Память о погибших горноспасателях</h2>' +
        '<p style="margin-top:1rem;color:var(--muted)">Сведения о погибших при исполнении служебного долга горноспасателях бережно собираются ветеранской организацией и командованием отряда. Материалы о памятных мероприятиях, биографии и фотографии публикуются на сайте по мере согласования с родными и близкими.</p>' +
        '<div class="memorial-callout" style="margin-top:1rem">' +
          '<span>' + icon('book', '', 26) + '</span>' +
          '<div><strong>Книга памяти горноспасателей</strong><p>Общая книга памяти ФГУП «ВГСЧ» доступна на официальном сайте предприятия.</p><p style="margin-top:.6rem"><a class="text-link" href="https://vgsch.organizations.mchs.gov.ru/ob-organizacii/pogibshie-gornospasateli" target="_blank" rel="noopener">Открыть книгу памяти ' + icon('external', '', 14) + '</a></p></div>' +
        '</div>' +
        '<div class="memorial-callout" style="margin-top:.75rem">' +
          '<span>' + icon('star', '', 26) + '</span>' +
          '<div><strong>Памятные мероприятия</strong><p>Вахта памяти, возложение цветов и встреча ветеранов проводятся ежегодно. Информация о датах публикуется в разделе «Новости».</p><p style="margin-top:.6rem"><a class="text-link" href="news.html?cat=Память">Новости о памятных событиях ' + icon('arrowRight', '', 14) + '</a></p></div>' +
        '</div>' +
      '</div>' +
    '</div></section>' +

    '<section class="section section--soft"><div class="container">' +
      sectionHead('Фотохроника', 'Памятные события', 'Кадры торжественных мероприятий, встреч ветеранов и вахты памяти.', moreLink('gallery.html?album=a2', 'Весь альбом')) +
      '<div class="gallery-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">' +
        data.gallery.filter((item) => item.published && item.type === 'photo' && ['g9', 'g10', 'g12'].indexOf(item.id) !== -1).map((item) =>
          '<a class="gallery-item" href="gallery.html?photo=' + esc(item.id) + '" aria-label="' + esc(item.title) + '">' + photo(item.image, item.title, '', '(max-width: 720px) 100vw, 33vw') + '<span class="gallery-item__caption">' + esc(item.title) + '</span></a>'
        ).join('') +
      '</div>' +
    '</div></section>' +

    ctaBand(data, 'Хотите поделиться историей или фотографией?', 'Мы бережно относимся к архивным материалам. Напишите нам — ветеранская организация свяжется с вами.', 'contacts.html#feedback', 'Написать в отряд', 'gallery.html', 'Фотогалерея');
  }

  /* =========================================================
     Поиск
     ========================================================= */
  function search(data) {
    return pageHero({
      title: 'Поиск по сайту',
      lead: 'Полнотекстовый поиск по новостям, документам, страницам и справочной информации.',
      crumbs: [{ label: 'Поиск' }],
      compact: true
    }) +
    '<section class="section"><div class="container" style="max-width:58rem">' +
      '<form class="search-page__form" id="search-page-form" role="search">' +
        '<input class="input" id="search-page-input" type="search" placeholder="Введите запрос: учения, политика, контакты…" autocomplete="off" aria-label="Поиск по сайту">' +
        '<button class="btn btn-primary" type="submit">' + icon('search', '', 18) + 'Найти</button>' +
      '</form>' +
      '<div class="search-filters" id="search-types"></div>' +
      '<div class="filter-result"><span id="search-count"></span><span class="muted">Поиск по всем разделам</span></div>' +
      '<div class="search-results" id="search-results"></div>' +
    '</div></section>';
  }

  function initSearchPage(data) {
    const form = document.getElementById('search-page-form');
    const input = document.getElementById('search-page-input');
    const types = document.getElementById('search-types');
    const results = document.getElementById('search-results');
    const count = document.getElementById('search-count');
    if (!form || !input) return;

    const params = new URLSearchParams(location.search);
    state.search.q = params.get('q') || '';
    state.search.type = params.get('type') || 'all';
    input.value = state.search.q;

    const typeList = [
      { id: 'all', label: 'Все результаты' },
      { id: 'news', label: 'Новости' },
      { id: 'doc', label: 'Документы' },
      { id: 'page', label: 'Страницы' },
      { id: 'division', label: 'Подразделения' },
      { id: 'gallery', label: 'Фотографии' }
    ];

    function highlight(text, query) {
      return VGS.highlight(text, query);
    }

    function renderTypes(found) {
      types.innerHTML = typeList
        .filter((type) => type.id === 'all' || found.some((item) => item.type === type.id))
        .map((type) => '<button type="button" class="chip' + (state.search.type === type.id ? ' is-active' : '') + '" data-type="' + type.id + '">' + type.label + '</button>')
        .join('');
      types.querySelectorAll('[data-type]').forEach((button) => button.addEventListener('click', () => {
        state.search.type = button.dataset.type;
        render();
      }));
    }

    function render() {
      const q = state.search.q.trim();
      const url = new URL(location.href);
      ['q', 'type'].forEach((key) => url.searchParams.delete(key));
      if (q) url.searchParams.set('q', q);
      if (state.search.type !== 'all') url.searchParams.set('type', state.search.type);
      history.replaceState(null, '', url.pathname + (url.search ? url.search : ''));

      if (!q) {
        types.innerHTML = '';
        count.textContent = '';
        results.innerHTML = emptyState('Начните поиск', 'Введите запрос — например, «учения», «политика» или «контакты».');
        return;
      }

      const found = VGS.search(q);
      const filtered = state.search.type === 'all' ? found : found.filter((item) => item.type === state.search.type);
      renderTypes(found);
      count.innerHTML = 'По запросу «' + esc(q) + '» найдено <strong>' + filtered.length + '</strong> ' + VGS.plural(filtered.length, ['результат', 'результата', 'результатов']);
      results.innerHTML = filtered.length
        ? filtered.map((item) => '<a class="search-result" href="' + esc(item.url) + '">' +
            '<div class="search-result__type">' + esc(item.typeLabel) + (item.badge ? ' · ' + esc(item.badge) : '') + (item.date ? ' · ' + esc(ruDate(item.date)) : '') + '</div>' +
            '<div class="search-result__title">' + highlight(item.title, q) + '</div>' +
            '<div class="search-result__text">' + highlight(VGS.truncate(item.text, 240), q) + '</div>' +
          '</a>').join('')
        : emptyState('Ничего не найдено', 'Попробуйте изменить формулировку или выбрать другой раздел.');
    }

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      state.search.q = input.value;
      state.search.type = 'all';
      render();
    });
    input.addEventListener('input', () => {
      state.search.q = input.value;
      if (!state.search.q.trim()) render();
    });
    render();
  }

  /* =========================================================
     Юридические страницы
     ========================================================= */
  function legal(data) {
    const map = { privacy: 'privacy-policy', company: 'company-policy', cookie: 'cookie-policy' };
    const id = map[document.body.dataset.page];
    const page = data.pages.find((item) => item.slug === id);
    if (!page) return notFound(data, 'Документ не найден', 'Возможно, страница была перемещена.');

    setMeta(page.title + ' — ' + data.settings.org_name, page.lead, 'https://nvgso.vgsch.ru/assets/img/og.jpg');

    const temp = document.createElement('div');
    temp.innerHTML = page.bodyHtml || '';
    const headings = Array.from(temp.querySelectorAll('h2'));
    const toc = headings.map((h, index) => {
      if (!h.id) h.id = 'section-' + (index + 1);
      return { id: h.id, text: h.textContent };
    });

    const body = temp.innerHTML;
    const pdfMap = {
      'privacy-policy': 'assets/docs/politika-pdn.pdf',
      'company-policy': 'assets/docs/politika-kompanii.pdf',
      'cookie-policy': 'assets/docs/politika-cookie.pdf'
    };
    const pdf = pdfMap[page.slug];

    return pageHero({
      title: page.title,
      lead: page.lead,
      crumbs: [{ label: 'Документы', href: 'documents.html' }, { label: page.short || page.title }],
      meta: [
        { icon: 'shield', text: 'Обязательный раздел сайта' }
      ],
      actions: '<button type="button" class="btn btn-outline btn-sm" data-print>' + icon('print', '', 16) + 'Печать</button>' +
        '<button type="button" class="btn btn-outline btn-sm" data-copy="' + esc(location.href) + '">' + icon('link', '', 16) + 'Скопировать ссылку</button>' +
        (pdf ? '<a class="btn btn-outline btn-sm" href="' + esc(pdf) + '" target="_blank" rel="noopener">' + icon('download', '', 16) + 'Скачать PDF</a>' : ''),
      compact: true
    }) +
    '<section class="section"><div class="container doc-layout">' +
      '<article class="prose" id="legal-content">' + body + '</article>' +
      '<aside>' +
        '<nav class="toc" aria-label="Содержание документа">' +
          '<h2>Содержание</h2>' +
          '<ol>' + toc.map((item) => '<li><a href="#' + esc(item.id) + '">' + esc(item.text) + '</a></li>').join('') + '</ol>' +
          '<div class="toc__actions">' +
            '<a href="documents.html">' + icon('doc', '', 15) + 'Все документы</a>' +
            '<a href="privacy-policy.html">' + icon('shield', '', 15) + 'Политика ПДн</a>' +
            '<a href="company-policy.html">' + icon('building', '', 15) + 'Политика компании</a>' +
            '<a href="cookie-policy.html">' + icon('cookie', '', 15) + 'Политика cookie</a>' +
            '<button type="button" data-cookie-open>' + icon('settings', '', 15) + 'Настройки cookie</button>' +
          '</div>' +
        '</nav>' +
      '</aside>' +
    '</div></section>';
  }

  function initLegal() {
    const links = Array.from(document.querySelectorAll('.toc a[href^="#"]'));
    if (links.length && 'IntersectionObserver' in window) {
      const byId = new Map(links.map((link) => [link.getAttribute('href').slice(1), link]));
      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          links.forEach((link) => link.classList.remove('is-active'));
          const link = byId.get(entry.target.id);
          if (link) link.classList.add('is-active');
        });
      }, { rootMargin: '-20% 0px -70% 0px' });
      byId.forEach((link, id) => {
        const target = document.getElementById(id);
        if (target) observer.observe(target);
      });
    }
  }

  /* =========================================================
     404
     ========================================================= */
  function notFound(data, title, text) {
    return pageHero({
      title: title || 'Страница не найдена',
      lead: text || 'Возможно, страница была перемещена или удалена.',
      crumbs: [{ label: '404' }],
      compact: true
    }) +
    '<section class="section"><div class="container" style="max-width:44rem;text-align:center">' +
      '<p style="font-size:4rem;font-weight:800;color:var(--navy);line-height:1;margin-bottom:1rem">404</p>' +
      '<p style="color:var(--muted);margin-bottom:1.5rem">Воспользуйтесь поиском или перейдите в один из основных разделов сайта.</p>' +
      '<div style="display:flex;gap:.6rem;flex-wrap:wrap;justify-content:center">' +
        '<a class="btn btn-primary" href="index.html">' + icon('home', '', 18) + 'На главную</a>' +
        '<a class="btn btn-ghost" href="search.html">' + icon('search', '', 18) + 'Поиск по сайту</a>' +
        '<a class="btn btn-ghost" href="contacts.html">' + icon('phone', '', 18) + 'Контакты</a>' +
      '</div>' +
    '</div></section>';
  }

  /* =========================================================
     Инициализация
     ========================================================= */
  const RENDERERS = {
    home: { render: home },
    about: { render: about },
    structure: { render: structure },
    activity: { render: activity },
    news: { render: news, after: initNews },
    article: { render: article },
    documents: { render: documents, after: initDocuments },
    gallery: { render: gallery, after: initGallery },
    contacts: { render: contacts, after: initContacts },
    veterans: { render: veterans },
    search: { render: search, after: initSearchPage },
    privacy: { render: legal, after: initLegal },
    company: { render: legal, after: initLegal },
    cookie: { render: legal, after: initLegal },
    'not-found': { render: notFound }
  };

  function init(data) {
    const main = document.getElementById('main');
    if (!main) return;
    const page = document.body.dataset.page || 'home';
    const config = RENDERERS[page] || RENDERERS['not-found'];
    try {
      main.innerHTML = config.render(data, page);
      if (config.after) config.after(data);
      if (page === 'not-found') document.title = 'Страница не найдена — Новокузнецкий ВГСО';
      if (location.hash && ['gallery', 'documents'].indexOf(page) === -1) {
        setTimeout(() => {
          const target = document.getElementById(location.hash.slice(1));
          if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 180);
      }
    } catch (error) {
      main.innerHTML = '<section class="section"><div class="container"><h1>Не удалось отобразить страницу</h1><p class="muted">Обновите страницу или вернитесь на <a href="index.html">главную</a>.</p></div></section>';
      if (window.console && console.error) console.error(error);
    }
  }

  window.PAGES = { init, renderers: RENDERERS };
})();
