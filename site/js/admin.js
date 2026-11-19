/* =========================================================================
   Административная панель: контент, справочники, согласия, пользователи,
   журнал действий и резервные копии.
   Демонстрационная реализация на localStorage.
   ========================================================================= */
(function () {
  'use strict';

  const VGS = window.VGS;
  const {
    esc, ruDate, ruDateTime, uid, ROLES, can,
    loadData, saveData, loadUsers, saveUsers, hasAnyUser,
    applyPassword, verifyPassword, upgradeUserPassword,
    isLoginLocked, getThrottleRemainingMs, recordLoginFailure, resetLoginThrottle,
    setSession, clearSession, getCurrentUser,
    logAction, getAuditLog, getConsentLog, clearConsent, dataSize,
    pageUrl, plural
  } = VGS;

  const icon = (name, cls, size) => (VGS.icon ? VGS.icon(name, cls, size) : '');
  const $ = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

  const TAB_PERMS = {
    dashboard: null,
    news: 'content',
    pages: 'pages',
    media: 'media',
    docs: 'docs',
    divisions: 'directories',
    phones: 'directories',
    consents: 'consents',
    users: 'users',
    log: 'log',
    backup: 'backup',
    settings: 'settings',
    password: null
  };

  const TAB_LABELS = {
    dashboard: 'Обзор',
    news: 'Новости',
    pages: 'Страницы',
    media: 'Медиатека',
    docs: 'Документы',
    divisions: 'Подразделения',
    phones: 'Телефонный справочник',
    consents: 'Cookie-согласия',
    users: 'Пользователи',
    log: 'Журнал действий',
    backup: 'Резервные копии',
    settings: 'Настройки сайта',
    password: 'Смена пароля'
  };

  const NAV_GROUPS = [
    { title: 'Контент', items: ['dashboard', 'news', 'pages', 'media', 'docs'] },
    { title: 'Справочники', items: ['divisions', 'phones'] },
    { title: 'Система', items: ['consents', 'users', 'log', 'backup', 'settings', 'password'] }
  ];

  let DATA = loadData();
  let currentTab = (location.hash || '').replace('#', '') || 'dashboard';
  let busy = false;

  /* =========================================================
     Вспомогательные элементы
     ========================================================= */
  function toast(message, type) {
    let wrap = $('.admin-toasts');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'admin-toasts';
      wrap.setAttribute('aria-live', 'polite');
      document.body.appendChild(wrap);
    }
    const node = document.createElement('div');
    node.className = 'admin-toast' + (type === 'error' ? ' admin-toast--error' : '');
    node.innerHTML = icon(type === 'error' ? 'alert' : 'checkCircle', '', 17) + '<span>' + esc(message) + '</span>';
    wrap.appendChild(node);
    setTimeout(() => { node.style.opacity = '0'; setTimeout(() => node.remove(), 250); }, 3800);
  }

  function safeUrl(value) {
    const url = String(value || '');
    return /^(https?:|mailto:|tel:|assets\/|data:|\/|#)/i.test(url) ? url : '#';
  }

  function pageHead(title, subtitle, actions) {
    return '<div class="page-head"><div><h1>' + esc(title) + '</h1>' + (subtitle ? '<p>' + esc(subtitle) + '</p>' : '') + '</div>' +
      (actions ? '<div class="page-head__actions">' + actions + '</div>' : '') + '</div>';
  }

  function empty(title, text) {
    return '<div class="empty"><strong>' + esc(title) + '</strong>' + esc(text || '') + '</div>';
  }

  function roleBadge(role) {
    const r = ROLES[role] || { label: role, tag: 'viewer' };
    const tone = role === 'admin' ? 'badge--red' : role === 'editor' ? 'badge--yellow' : role === 'moderator' ? 'badge--gold' : 'badge--grey';
    return '<span class="badge ' + tone + '">' + esc(r.label) + '</span>';
  }

  function publishBadge(value) {
    return value ? '<span class="badge badge--green">Опубликовано</span>' : '<span class="badge badge--yellow">Черновик</span>';
  }

  function categoryOptions(list, selected) {
    return list.map((item) => '<option value="' + esc(item) + '"' + (item === selected ? ' selected' : '') + '>' + esc(item) + '</option>').join('');
  }

  function albumTitle(id) {
    const album = (DATA.albums || []).find((item) => item.id === id);
    return album ? album.title : 'Без альбома';
  }

  function mediaSrc(item, size) {
    if (!item) return '';
    if (item.src) return item.src;
    const raw = item.image || '';
    if (/^(data:|https?:|assets\/|\/)/.test(raw)) return raw;
    return VGS.imagePath(raw, size || 700);
  }

  function imageOptions(selected) {
    const items = (DATA.gallery || []).filter((item) => item.type !== 'video');
    const options = items.map((item) =>
      '<option value="' + esc(item.id) + '"' + (selected && selected.image === item.image && !selected.src ? ' selected' : '') + '>' + esc(item.title) + '</option>'
    ).join('');
    return '<option value="">Без изображения</option>' + options;
  }

  function fileToBase64(file, maxBytes) {
    return new Promise((resolve, reject) => {
      if (!file) return reject(new Error('Файл не выбран'));
      if (file.size > maxBytes) return reject(new Error('Файл больше допустимого размера'));
      const reader = new FileReader();
      reader.onload = () => resolve(String(reader.result).split(',')[1]);
      reader.onerror = () => reject(new Error('Не удалось прочитать файл'));
      reader.readAsDataURL(file);
    });
  }

  function download(name, content, type) {
    const blob = new Blob([content], { type: type || 'text/plain;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = name;
    link.click();
    setTimeout(() => URL.revokeObjectURL(link.href), 1000);
  }

  function csv(rows) {
    return '\uFEFF' + rows.map((row) => row.map((value) => '"' + String(value === null || value === undefined ? '' : value).replace(/"/g, '""') + '"').join(';')).join('\r\n');
  }

  function audit(action, details) {
    logAction(action, details);
  }

  function persist(action, details, message) {
    if (!saveData(DATA)) {
      toast('Не удалось сохранить данные: превышен лимит localStorage. Удалите крупные файлы или используйте ссылки.', 'error');
      return false;
    }
    audit(action, details);
    DATA = loadData();
    closeModal();
    refresh();
    toast(message || 'Сохранено.');
    return true;
  }

  function refresh() {
    const y = window.scrollY;
    renderApp();
    requestAnimationFrame(() => window.scrollTo(0, y));
  }

  /* =========================================================
     Модальные окна
     ========================================================= */
  function openModal(options) {
    closeModal();
    const wrap = document.createElement('div');
    wrap.className = 'admin-modal-backdrop';
    wrap.id = 'admin-modal';
    wrap.innerHTML = '<div class="admin-modal ' + (options.wide ? 'admin-modal--wide' : '') + '" role="dialog" aria-modal="true" aria-labelledby="admin-modal-title">' +
      '<div class="admin-modal__head"><div><h2 id="admin-modal-title">' + esc(options.title) + '</h2>' +
        (options.subtitle ? '<p>' + esc(options.subtitle) + '</p>' : '') + '</div>' +
        '<button type="button" class="admin-modal__close" data-modal-close aria-label="Закрыть">' + icon('close', '', 18) + '</button></div>' +
      '<div class="admin-modal__body">' + options.body + '</div>' +
      (options.footer ? '<div class="admin-modal__foot">' + options.footer + '</div>' : '') +
    '</div>';
    document.body.appendChild(wrap);
    document.body.style.overflow = 'hidden';
    wrap.addEventListener('click', (event) => { if (event.target === wrap) closeModal(); });
    $$('[data-modal-close]', wrap).forEach((button) => button.addEventListener('click', closeModal));
    requestAnimationFrame(() => wrap.classList.add('is-open'));
    setTimeout(() => {
      const field = wrap.querySelector('input:not([type="hidden"]), select, textarea, button');
      if (field) field.focus();
    }, 60);
    return wrap;
  }

  function closeModal() {
    const modal = $('#admin-modal');
    if (modal) modal.remove();
    document.body.style.overflow = '';
  }

  /* =========================================================
     Вход и первичная настройка
     ========================================================= */
  function loginShell(content) {
    const cryptoWarning = (!window.crypto || !window.crypto.subtle)
      ? '<div class="alert-box alert-box--red">' + icon('alert', '', 18) + '<span>Web Crypto недоступен. Откройте панель через локальный сервер (<code>http://localhost:8765/admin.html</code>) или по HTTPS — иначе создание и проверка пароля не работают.</span></div>'
      : '';
    return '<div class="login-page">' +
      '<div class="login-card">' +
        '<div class="login-card__brand">' +
          '<img src="assets/img/logo-shield.webp" alt="">' +
          '<div><strong>Новокузнецкий ВГСО</strong><span>ФГУП «ВГСЧ» · Панель управления</span></div>' +
        '</div>' +
        cryptoWarning +
        content +
        '<div class="login-card__hint"><a href="index.html">← Вернуться на сайт</a><span>Доступ для сотрудников</span></div>' +
      '</div>' +
    '</div>';
  }

  function renderSetup() {
    const root = $('#admin-root');
    root.innerHTML = loginShell(
      '<h1>Первичная настройка</h1><p>Создайте учётную запись администратора сайта. Пароль хранится в виде хеша PBKDF2.</p>' +
      '<form id="setup-form" novalidate>' +
        '<div class="field"><label for="setup-name">ФИО</label><input class="input" id="setup-name" autocomplete="name" placeholder="Иванов Иван Иванович" required></div>' +
        '<div class="field"><label for="setup-login">Логин</label><input class="input" id="setup-login" autocomplete="username" placeholder="admin" required></div>' +
        '<div class="field"><label for="setup-pw">Пароль</label><input class="input" id="setup-pw" type="password" autocomplete="new-password" placeholder="не менее 8 символов" required></div>' +
        '<div class="field"><label for="setup-pw2">Повторите пароль</label><input class="input" id="setup-pw2" type="password" autocomplete="new-password" required></div>' +
        '<div class="form-error" data-error role="alert"></div>' +
        '<button class="abtn abtn-primary" type="submit">Создать администратора</button>' +
      '</form>' +
      '<div class="login-card__note">При первом запуске создаётся единственная учётная запись администратора. Данные панели хранятся локально в браузере и предназначены для демонстрации интерфейса.</div>'
    );

    $('#setup-form').addEventListener('submit', async (event) => {
      event.preventDefault();
      if (busy) return;
      const name = $('#setup-name').value.trim();
      const login = $('#setup-login').value.trim();
      const pw = $('#setup-pw').value;
      const pw2 = $('#setup-pw2').value;
      const error = $('[data-error]');
      const fail = (message) => { error.textContent = message; };
      if (name.length < 2) return fail('Укажите ФИО.');
      if (login.length < 3) return fail('Логин — не менее 3 символов.');
      if (pw.length < 8) return fail('Пароль — не менее 8 символов.');
      if (pw !== pw2) return fail('Пароли не совпадают.');
      busy = true;
      const button = event.target.querySelector('button[type="submit"]');
      button.disabled = true;
      button.textContent = 'Создание…';
      try {
        const user = { id: uid('u'), name, login, role: 'admin', createdAt: new Date().toISOString() };
        await applyPassword(user, pw);
        if (!saveUsers([user])) throw new Error('Не удалось сохранить пользователя.');
        setSession({ id: user.id });
        logAction('Первичная настройка', 'Создан администратор: ' + login);
        DATA = loadData();
        busy = false;
        toast('Администратор создан. Добро пожаловать!');
        currentTab = 'dashboard';
        renderApp();
      } catch (err) {
        busy = false;
        button.disabled = false;
        button.textContent = 'Создать администратора';
        fail(err && err.message ? err.message : 'Не удалось создать пользователя.');
      }
    });
  }

  function renderLogin() {
    const root = $('#admin-root');
    root.innerHTML = loginShell(
      '<h1>Вход в панель управления</h1><p>Введите логин и пароль учётной записи.</p>' +
      '<form id="login-form" novalidate>' +
        '<div class="field"><label for="login-name">Логин</label><input class="input" id="login-name" autocomplete="username" required autofocus></div>' +
        '<div class="field"><label for="login-pw">Пароль</label><input class="input" id="login-pw" type="password" autocomplete="current-password" required></div>' +
        '<div class="form-error" data-error role="alert"></div>' +
        '<button class="abtn abtn-primary" type="submit">Войти</button>' +
      '</form>' +
      '<div class="login-card__note">Доступ только для уполномоченных сотрудников филиала. После пяти неудачных попыток вход блокируется на 5 минут.</div>'
    );

    $('#login-form').addEventListener('submit', async (event) => {
      event.preventDefault();
      if (busy) return;
      const login = $('#login-name').value.trim();
      const password = $('#login-pw').value;
      const error = $('[data-error]');
      if (isLoginLocked(login)) {
        const seconds = Math.ceil(getThrottleRemainingMs(login) / 1000);
        error.textContent = 'Слишком много попыток. Повторите через ' + seconds + ' с.';
        return;
      }
      if (!login || !password) {
        error.textContent = 'Введите логин и пароль.';
        return;
      }
      const user = loadUsers().find((item) => item.login.toLowerCase() === login.toLowerCase());
      const button = event.target.querySelector('button[type="submit"]');
      busy = true;
      button.disabled = true;
      button.textContent = 'Проверка…';
      try {
        const result = user ? await verifyPassword(password, user) : { ok: false };
        if (!result.ok) {
          recordLoginFailure(login);
          error.textContent = 'Неверный логин или пароль.';
          busy = false;
          button.disabled = false;
          button.textContent = 'Войти';
          return;
        }
        if (result.needsRehash) await upgradeUserPassword(user.id, password);
        resetLoginThrottle(login);
        setSession({ id: user.id });
        DATA = loadData();
        logAction('Вход в панель', 'Пользователь: ' + user.login);
        busy = false;
        currentTab = (location.hash || '').replace('#', '') || 'dashboard';
        if (!allowed(currentTab)) currentTab = 'dashboard';
        renderApp();
        toast('Добро пожаловать, ' + user.name + '!');
      } catch (err) {
        busy = false;
        button.disabled = false;
        button.textContent = 'Войти';
        error.textContent = err && err.message ? err.message : 'Ошибка проверки пароля.';
      }
    });
  }

  function renderAuth() {
    if (!hasAnyUser()) renderSetup();
    else renderLogin();
  }

  /* =========================================================
     Оболочка панели
     ========================================================= */
  function allowed(tab) {
    const me = getCurrentUser();
    if (!me) return false;
    const perm = TAB_PERMS[tab];
    return !perm || can(perm);
  }

  function visibleTabs() {
    return NAV_GROUPS.map((group) => ({
      title: group.title,
      items: group.items.filter((tab) => allowed(tab))
    })).filter((group) => group.items.length);
  }

  function counts() {
    return {
      news: DATA.news.length,
      docs: DATA.docs.length,
      media: DATA.gallery.length,
      consents: getConsentLog().length,
      users: loadUsers().length,
      feedback: (DATA.feedback || []).filter((item) => item.status === 'new').length
    };
  }

  function navBadge(tab) {
    const c = counts();
    if (tab === 'news') return c.news;
    if (tab === 'docs') return c.docs;
    if (tab === 'media') return c.media;
    if (tab === 'consents') return c.consents;
    if (tab === 'users') return c.users;
    return 0;
  }

  function renderApp() {
    const me = getCurrentUser();
    if (!me) { renderAuth(); return; }
    if (!allowed(currentTab)) currentTab = 'dashboard';
    const groups = visibleTabs();
    const totalBadge = counts();
    const role = ROLES[me.role] || { label: me.role };
    document.title = TAB_LABELS[currentTab] + ' — Панель управления Новокузнецкого ВГСО';

    const nav = groups.map((group) =>
      '<div class="admin-nav__group">' + esc(group.title) + '</div>' +
      group.items.map((tab) => {
        const badge = navBadge(tab);
        return '<a class="admin-nav__link' + (currentTab === tab ? ' is-active' : '') + '" href="#' + tab + '" data-tab="' + tab + '">' +
          icon(tabIcon(tab), '', 18) + '<span>' + esc(TAB_LABELS[tab]) + '</span>' +
          (badge ? '<span class="admin-nav__badge">' + badge + '</span>' : '') + '</a>';
      }).join('')
    ).join('');

    $('#admin-root').innerHTML =
      '<div class="admin-shell">' +
        '<aside class="admin-sidebar" id="admin-sidebar">' +
          '<div class="admin-brand"><img src="assets/img/logo-shield.webp" alt=""><div><strong>Новокузнецкий ВГСО</strong><span>Панель управления</span></div></div>' +
          '<nav class="admin-nav" aria-label="Разделы панели">' + nav + '</nav>' +
          '<div class="admin-sidebar__foot">' +
            '<a href="index.html" target="_blank" rel="noopener">' + icon('external', '', 14) + 'Открыть сайт</a>' +
            '<div class="admin-sidebar__role">' + roleBadge(me.role) + '<span>' + esc(me.name) + '</span></div>' +
          '</div>' +
        '</aside>' +
        '<div class="admin-backdrop" id="admin-backdrop"></div>' +
        '<div class="admin-main">' +
          '<header class="admin-topbar">' +
            '<div class="admin-topbar__left">' +
              '<button type="button" class="abtn abtn-ghost abtn-sm mobile-admin-bar" id="admin-burger" aria-label="Открыть меню" aria-expanded="false">' + icon('menu', '', 18) + '</button>' +
              '<div class="admin-topbar__title">' + esc(TAB_LABELS[currentTab]) + '</div>' +
            '</div>' +
            '<div class="admin-topbar__right">' +
              (totalBadge.feedback ? '<span class="badge badge--red" title="Новые обращения">' + totalBadge.feedback + ' новых</span>' : '') +
              '<div class="admin-user"><div class="admin-user__avatar">' + esc(initials(me.name)) + '</div><div class="admin-user__meta"><div class="admin-user__name">' + esc(me.name) + '</div><div class="admin-user__role">' + esc(role.label) + '</div></div></div>' +
              '<button type="button" class="abtn abtn-ghost abtn-sm" id="logout-btn">' + icon('logout', '', 16) + 'Выйти</button>' +
            '</div>' +
          '</header>' +
          '<main class="admin-content" id="admin-content"></main>' +
        '</div>' +
      '</div>';

    bindShell();
    renderTab();
  }

  function initials(name) {
    return String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
  }

  function tabIcon(tab) {
    return ({
      dashboard: 'dashboard', news: 'news', pages: 'pages', media: 'media', docs: 'doc',
      divisions: 'structure', phones: 'phone', consents: 'cookie', users: 'users',
      log: 'journal', backup: 'backup', settings: 'settings', password: 'key'
    })[tab] || 'dashboard';
  }

  function bindShell() {
    $$('[data-tab]').forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        go(link.dataset.tab);
      });
    });
    $('#logout-btn').addEventListener('click', () => {
      logAction('Выход из панели', 'Пользователь завершил сеанс');
      clearSession();
      closeModal();
      renderAuth();
    });
    const burger = $('#admin-burger');
    const sidebar = $('#admin-sidebar');
    const backdrop = $('#admin-backdrop');
    const closeSidebar = () => {
      sidebar.classList.remove('is-open');
      backdrop.classList.remove('is-open');
      burger.setAttribute('aria-expanded', 'false');
    };
    burger.addEventListener('click', () => {
      const open = !sidebar.classList.contains('is-open');
      sidebar.classList.toggle('is-open', open);
      backdrop.classList.toggle('is-open', open);
      burger.setAttribute('aria-expanded', String(open));
    });
    backdrop.addEventListener('click', closeSidebar);
  }

  function go(tab) {
    if (!allowed(tab)) { toast('Недостаточно прав для этого раздела.', 'error'); return; }
    currentTab = tab;
    if (location.hash !== '#' + tab) history.replaceState(null, '', '#' + tab);
    renderApp();
    $('#admin-sidebar').classList.remove('is-open');
    $('#admin-backdrop').classList.remove('is-open');
    window.scrollTo(0, 0);
  }

  function renderTab() {
    const content = $('#admin-content');
    if (!content) return;
    if (!TAB_LABELS[currentTab]) currentTab = 'dashboard';
    switch (currentTab) {
      case 'dashboard': tabDashboard(content); break;
      case 'news': tabNews(content); break;
      case 'pages': tabPages(content); break;
      case 'media': tabMedia(content); break;
      case 'docs': tabDocs(content); break;
      case 'divisions': tabDivisions(content); break;
      case 'phones': tabPhones(content); break;
      case 'consents': tabConsents(content); break;
      case 'users': tabUsers(content); break;
      case 'log': tabLog(content); break;
      case 'backup': tabBackup(content); break;
      case 'settings': tabSettings(content); break;
      case 'password': tabPassword(content); break;
      default: tabDashboard(content);
    }
  }

  /* =========================================================
     Обзор
     ========================================================= */
  function tabDashboard(content) {
    const me = getCurrentUser();
    const log = getAuditLog().slice(0, 8);
    const feedback = DATA.feedback || [];
    const consents = getConsentLog();
    const c = counts();

    content.innerHTML =
      pageHead('Обзор', 'Сводка по контенту и состоянию системы.', '') +
      '<div class="alert-box">' + icon('info', '', 18) +
        '<div><strong>Демонстрационная версия панели.</strong> Контент хранится в localStorage браузера. В рабочей версии на WordPress данные сохраняются в локальной MariaDB на том же сервере, а медиафайлы — в <code>wp-content/uploads/</code>.</div>' +
      '</div>' +
      '<div class="stats-grid">' +
        '<div class="stat-card"><div class="stat-card__icon">' + icon('news', '', 20) + '</div><div><div class="stat-card__value">' + c.news + '</div><div class="stat-card__label">новостей · ' + DATA.news.filter((n) => n.published).length + ' опубликовано</div></div></div>' +
        '<div class="stat-card"><div class="stat-card__icon">' + icon('doc', '', 20) + '</div><div><div class="stat-card__value">' + c.docs + '</div><div class="stat-card__label">документов в разделе</div></div></div>' +
        '<div class="stat-card"><div class="stat-card__icon">' + icon('gallery', '', 20) + '</div><div><div class="stat-card__value">' + c.media + '</div><div class="stat-card__label">материалов в медиатеке</div></div></div>' +
        '<div class="stat-card"><div class="stat-card__icon">' + icon('cookie', '', 20) + '</div><div><div class="stat-card__value">' + consents.length + '</div><div class="stat-card__label">записей в журнале согласий</div></div></div>' +
      '</div>' +
      '<div class="dashboard-grid">' +
        '<div class="dashboard-stack">' +
          '<div class="admin-card">' +
            '<div class="admin-card__head"><div><h2>Последние действия</h2><p>Журнал изменений в системе</p></div><a class="abtn abtn-ghost abtn-sm" href="#log" data-tab="log">Весь журнал</a></div>' +
            (log.length ? '<ul class="activity-list">' + log.map((item) =>
              '<li>' + icon('journal', '', 17) + '<div><strong>' + esc(item.action) + '</strong>' + esc(item.details || '') + '<time>' + esc(ruDateTime(item.timestamp)) + ' · ' + esc(item.userName || 'Система') + '</time></div></li>'
            ).join('') + '</ul>' : empty('Журнал пока пуст', 'Действия появятся после изменения контента.')) +
          '</div>' +
          '<div class="admin-card">' +
            '<div class="admin-card__head"><div><h2>Обращения и подписки</h2><p>Последние поступления из форм сайта</p></div><span class="badge badge--grey">' + feedback.length + '</span></div>' +
            (feedback.length ? '<div class="table-wrap"><table class="tbl"><thead><tr><th>Дата</th><th>Тип</th><th>Контакт</th><th>Тема</th></tr></thead><tbody>' +
              feedback.slice(0, 5).map((item) => '<tr><td>' + esc(ruDateTime(item.createdAt)) + '</td><td>' + (item.type === 'subscription' ? '<span class="badge badge--gold">Подписка</span>' : '<span class="badge">Обращение</span>') + '</td><td>' + esc(item.email || item.phone || item.name || '—') + '</td><td>' + esc(item.topic || '—') + '</td></tr>').join('') +
            '</tbody></table></div>' : empty('Обращений пока нет', 'Сообщения, отправленные через форму на сайте, появятся здесь.')) +
          '</div>' +
        '</div>' +
        '<div class="dashboard-stack">' +
          '<div class="admin-card">' +
            '<div class="admin-card__head"><h2>Быстрые действия</h2></div>' +
            '<div class="quick-actions">' +
              '<button type="button" class="abtn abtn-soft" data-quick="news">' + icon('plus', '', 16) + 'Новость</button>' +
              '<button type="button" class="abtn abtn-soft" data-quick="media">' + icon('plus', '', 16) + 'Медиаматериал</button>' +
              '<button type="button" class="abtn abtn-soft" data-quick="doc">' + icon('plus', '', 16) + 'Документ</button>' +
              '<button type="button" class="abtn abtn-soft" data-quick="page">' + icon('edit', '', 16) + 'Страницы</button>' +
            '</div>' +
          '</div>' +
          '<div class="admin-card">' +
            '<div class="admin-card__head"><h2>Состояние данных</h2></div>' +
            '<dl class="kv">' +
              '<dt>Хранилище</dt><dd>localStorage</dd>' +
              '<dt>Объём</dt><dd>' + Math.round(dataSize() / 1024) + ' КБ</dd>' +
              '<dt>Пользователей</dt><dd>' + c.users + '</dd>' +
              '<dt>Текущая роль</dt><dd>' + esc((ROLES[me.role] || {}).label || me.role) + '</dd>' +
              '<dt>Согласий</dt><dd>' + consents.length + '</dd>' +
            '</dl>' +
            '<div class="alert-box alert-box--warn" style="margin:1rem 0 0">' + icon('alert', '', 17) + '<span>Резервная копия контента доступна в разделе «Резервные копии».</span></div>' +
          '</div>' +
        '</div>' +
      '</div>';

    $$('[data-quick]').forEach((button) => {
      button.addEventListener('click', () => {
        if (button.dataset.quick === 'news') editNews(null);
        if (button.dataset.quick === 'media') editMedia(null);
        if (button.dataset.quick === 'doc') editDoc(null);
        if (button.dataset.quick === 'page') go('pages');
      });
    });
  }

  /* =========================================================
     Новости
     ========================================================= */
  function tabNews(content) {
    const rows = DATA.news.slice().sort((a, b) => (b.date || '').localeCompare(a.date || ''));
    content.innerHTML =
      pageHead('Новости', 'Управление публикациями, категориями и статусом публикации.', '<button class="abtn abtn-primary" id="add-news">' + icon('plus', '', 16) + 'Добавить новость</button>') +
      '<div class="toolbar">' +
        '<div class="field field--grow"><label for="news-search">Поиск</label><input class="input" id="news-search" type="search" placeholder="Заголовок, текст, категория…"></div>' +
        '<div class="field"><label for="news-cat">Категория</label><select class="select" id="news-cat"><option value="all">Все категории</option>' + categoryOptions(DATA.newsCategories, '') + '</select></div>' +
        '<div class="field"><label for="news-status">Статус</label><select class="select" id="news-status"><option value="all">Все</option><option value="1">Опубликовано</option><option value="0">Черновик</option></select></div>' +
      '</div>' +
      (rows.length ? '<div class="table-wrap"><table class="tbl" id="news-table"><thead><tr><th>Дата</th><th>Публикация</th><th>Статус</th><th>Действия</th></tr></thead><tbody>' +
        rows.map((item) => '<tr data-row data-title="' + esc(item.title.toLowerCase()) + '" data-text="' + esc((item.excerpt + ' ' + item.body).toLowerCase().slice(0, 2000)) + '" data-cat="' + esc(item.category) + '" data-status="' + (item.published ? '1' : '0') + '">' +
          '<td>' + esc(ruDate(item.date)) + '</td>' +
          '<td><div class="tbl__title">' + esc(item.title) + '</div><div class="tbl__meta">' + esc(item.category) + (item.featured ? ' · главная новость' : '') + '</div></td>' +
          '<td>' + publishBadge(item.published) + '</td>' +
          '<td><div class="tbl__actions">' +
            '<button class="abtn abtn-ghost abtn-sm" data-news-edit="' + esc(item.id) + '">Изменить</button>' +
            '<button class="abtn abtn-soft abtn-sm" data-news-toggle="' + esc(item.id) + '">' + (item.published ? 'Снять' : 'Опубликовать') + '</button>' +
            '<button class="abtn abtn-ghost abtn-sm" data-news-delete="' + esc(item.id) + '">Удалить</button>' +
          '</div></td>' +
        '</tr>').join('') +
      '</tbody></table></div><p class="form-hint" id="news-filter-empty" style="display:none;margin-top:.7rem">Публикаций по выбранным условиям не найдено.</p>' : empty('Новостей пока нет', 'Создайте первую новость, чтобы она появилась на сайте.'));

    $('#add-news').addEventListener('click', () => editNews(null));
    $$('[data-news-edit]').forEach((button) => button.addEventListener('click', () => editNews(button.dataset.newsEdit)));
    $$('[data-news-delete]').forEach((button) => button.addEventListener('click', () => {
      const item = DATA.news.find((n) => n.id === button.dataset.newsDelete);
      if (!item) return;
      if (!confirm('Удалить новость «' + item.title + '»? Действие необратимо.')) return;
      DATA.news = DATA.news.filter((n) => n.id !== item.id);
      persist('Новость удалена', item.title, 'Новость удалена.');
    }));
    $$('[data-news-toggle]').forEach((button) => button.addEventListener('click', () => {
      const item = DATA.news.find((n) => n.id === button.dataset.newsToggle);
      if (!item) return;
      item.published = item.published ? 0 : 1;
      persist('Новость ' + (item.published ? 'опубликована' : 'снята с публикации'), item.title, 'Статус публикации изменён.');
    }));

    const search = $('#news-search');
    const cat = $('#news-cat');
    const status = $('#news-status');
    const filter = () => {
      const q = search.value.toLowerCase().trim();
      const c = cat.value;
      const s = status.value;
      let visible = 0;
      $$('#news-table tbody tr').forEach((row) => {
        const ok = (!q || (row.dataset.title + ' ' + row.dataset.text).indexOf(q) !== -1) &&
          (c === 'all' || row.dataset.cat === c) &&
          (s === 'all' || row.dataset.status === s);
        row.style.display = ok ? '' : 'none';
        if (ok) visible += 1;
      });
      $('#news-filter-empty') && ($('#news-filter-empty').style.display = visible ? 'none' : '');
    };
    search.addEventListener('input', filter);
    cat.addEventListener('change', filter);
    status.addEventListener('change', filter);
  }

  function editNews(id) {
    const existing = id ? DATA.news.find((item) => item.id === id) : null;
    const item = existing || {
      id: '', title: '', date: VGS.todayISO(), category: DATA.newsCategories[0] || 'События',
      excerpt: '', body: '', image: 'photo-01', imageSrc: '', featured: 0, published: 1, tags: []
    };
    const currentGallery = DATA.gallery.find((g) => g.type !== 'video' && (g.image === item.image && (g.src || '') === (item.imageSrc || '')));
    const currentOption = currentGallery ? '' : '<option value="__current" selected>Текущее изображение</option>';

    openModal({
      title: existing ? 'Редактирование новости' : 'Новая новость',
      subtitle: 'Поля со звёздочкой обязательны. Дата публикации влияет на сортировку.',
      wide: true,
      body:
        '<div class="form-grid">' +
          '<div class="field full"><label for="n-title">Заголовок *</label><input class="input" id="n-title" maxlength="200" value="' + esc(item.title) + '"></div>' +
          '<div class="field"><label for="n-date">Дата</label><input class="input" id="n-date" type="date" value="' + esc(item.date) + '"></div>' +
          '<div class="field"><label for="n-category">Категория</label><select class="select" id="n-category">' + categoryOptions(DATA.newsCategories, item.category) + '</select></div>' +
          '<div class="field full"><label for="n-image">Изображение</label><select class="select" id="n-image">' + currentOption + imageOptions(item) + '</select></div>' +
          '<div class="field full"><div id="n-image-preview" style="border:1px solid var(--line);border-radius:6px;overflow:hidden;max-width:22rem;aspect-ratio:16/9;background:var(--bg-soft)">' +
            '<img src="' + esc(item.imageSrc || VGS.imagePath(item.image || 'photo-01', 700)) + '" alt="" style="width:100%;height:100%;object-fit:cover">' +
          '</div></div>' +
          '<div class="field full"><label for="n-excerpt">Краткое описание</label><textarea class="textarea" id="n-excerpt" maxlength="500" style="min-height:5rem">' + esc(item.excerpt) + '</textarea></div>' +
          '<div class="field full"><label for="n-body">Текст новости</label><textarea class="textarea" id="n-body" maxlength="20000" style="min-height:14rem">' + esc(item.body) + '</textarea></div>' +
          '<div class="field full"><div class="form-row"><label class="check"><input type="checkbox" id="n-featured"' + (item.featured ? ' checked' : '') + '> Главная новость на сайте</label><label class="check"><input type="checkbox" id="n-pub"' + (item.published ? ' checked' : '') + '> Опубликовать</label></div></div>' +
        '</div>' +
        '<div class="form-error" data-error role="alert"></div>',
      footer: '<button class="abtn abtn-ghost" data-modal-close>Отмена</button><button class="abtn abtn-primary" id="n-save">Сохранить</button>'
    });

    const imageSelect = $('#n-image');
    imageSelect.addEventListener('change', () => {
      const gallery = DATA.gallery.find((g) => g.id === imageSelect.value);
      const src = gallery ? mediaSrc(gallery, 700) : (item.imageSrc || VGS.imagePath(item.image || 'photo-01', 700));
      $('#n-image-preview img').src = src;
    });

    $('#n-save').addEventListener('click', () => {
      const title = $('#n-title').value.trim();
      const date = $('#n-date').value || VGS.todayISO();
      const category = $('#n-category').value;
      if (!title) { $('[data-error]').textContent = 'Укажите заголовок.'; return; }
      if (!date) { $('[data-error]').textContent = 'Укажите дату.'; return; }
      let image = item.image;
      let imageSrc = item.imageSrc;
      const gallery = DATA.gallery.find((g) => g.id === imageSelect.value);
      if (gallery) { image = gallery.image; imageSrc = gallery.src || ''; }
      if (!imageSelect.value) { image = 'photo-01'; imageSrc = ''; }
      const next = {
        id: existing ? existing.id : uid('n'),
        title, date, category,
        excerpt: $('#n-excerpt').value.trim(),
        body: $('#n-body').value.trim(),
        image, imageSrc,
        featured: $('#n-featured').checked ? 1 : 0,
        published: $('#n-pub').checked ? 1 : 0,
        tags: existing ? existing.tags : []
      };
      if (existing) DATA.news = DATA.news.map((n) => n.id === existing.id ? next : n);
      else DATA.news.push(next);
      persist(existing ? 'Новость изменена' : 'Новость создана', title, 'Новость сохранена.');
    });
  }

  /* =========================================================
     Страницы
     ========================================================= */
  function tabPages(content) {
    const pages = DATA.pages || [];
    content.innerHTML =
      pageHead('Страницы', 'Управление юридическими и информационными страницами сайта.', '') +
      '<div class="alert-box">' + icon('info', '', 18) + '<span>Юридические тексты публикуются в HTML-редакторе. Структурированные разделы («Об отряде», «Структура», «Деятельность») используют общие данные и настройки, поэтому для них редактируются заголовок и краткое описание.</span></div>' +
      '<div class="table-wrap"><table class="tbl"><thead><tr><th>Страница</th><th>Тип</th><th>Обновлена</th><th>Статус</th><th>Действия</th></tr></thead><tbody>' +
        pages.map((page) => '<tr>' +
          '<td><div class="tbl__title">' + esc(page.title) + '</div><div class="tbl__meta">/' + esc(page.slug) + '/</div></td>' +
          '<td>' + (page.info ? '<span class="badge badge--grey">Информационная</span>' : '<span class="badge badge--red">Юридическая</span>') + '</td>' +
          '<td>' + esc(ruDate(page.updatedAt)) + '</td>' +
          '<td>' + publishBadge(page.published) + '</td>' +
          '<td><div class="tbl__actions">' +
            '<a class="abtn abtn-ghost abtn-sm" href="' + esc(pageUrl(page)) + '" target="_blank" rel="noopener">Открыть</a>' +
            '<button class="abtn abtn-soft abtn-sm" data-page-edit="' + esc(page.id) + '">Изменить</button>' +
            '<button class="abtn abtn-ghost abtn-sm" data-page-toggle="' + esc(page.id) + '">' + (page.published ? 'Снять' : 'Опубликовать') + '</button>' +
          '</div></td>' +
        '</tr>').join('') +
      '</tbody></table></div>';

    $$('[data-page-edit]').forEach((button) => button.addEventListener('click', () => editPage(button.dataset.pageEdit)));
    $$('[data-page-toggle]').forEach((button) => button.addEventListener('click', () => {
      const page = DATA.pages.find((p) => p.id === button.dataset.pageToggle);
      if (!page) return;
      page.published = page.published ? 0 : 1;
      persist('Страница ' + (page.published ? 'опубликована' : 'снята с публикации'), page.title, 'Статус страницы изменён.');
    }));
  }

  function editPage(id) {
    const page = (DATA.pages || []).find((p) => p.id === id);
    if (!page) return;
    const isInfo = !!page.info;
    openModal({
      title: 'Редактирование страницы',
      subtitle: '/' + page.slug + '/',
      wide: true,
      body:
        '<div class="form-grid">' +
          '<div class="field full"><label for="p-title">Заголовок</label><input class="input" id="p-title" value="' + esc(page.title) + '"></div>' +
          '<div class="field full"><label for="p-lead">Краткое описание</label><textarea class="textarea" id="p-lead" style="min-height:5rem">' + esc(page.lead || '') + '</textarea></div>' +
          (isInfo
            ? '<div class="field full"><div class="alert-box">' + icon('info', '', 18) + '<span>Содержимое этой страницы формируется автоматически из настроек, истории и справочников. Здесь редактируются только заголовок и краткое описание.</span></div></div>'
            : '<div class="field full"><label for="p-body">HTML-содержимое документа</label><textarea class="textarea textarea--code" id="p-body">' + esc(page.bodyHtml || '') + '</textarea><div class="form-hint">Допустима HTML-разметка: заголовки h2/h3 с id, абзацы, списки, таблицы, ссылки. Стили задаются автоматически.</div></div>') +
          '<div class="field full"><label class="check"><input type="checkbox" id="p-pub"' + (page.published ? ' checked' : '') + '> Опубликовать страницу</label></div>' +
        '</div>' +
        '<div class="form-error" data-error role="alert"></div>',
      footer: '<button class="abtn abtn-ghost" data-modal-close>Отмена</button><button class="abtn abtn-primary" id="p-save">Сохранить</button>'
    });

    $('#p-save').addEventListener('click', () => {
      const title = $('#p-title').value.trim();
      if (!title) { $('[data-error]').textContent = 'Укажите заголовок.'; return; }
      page.title = title;
      page.lead = $('#p-lead').value.trim();
      page.published = $('#p-pub').checked ? 1 : 0;
      page.updatedAt = VGS.todayISO();
      if (!isInfo) page.bodyHtml = $('#p-body').value;
      persist('Страница изменена', page.title, 'Страница сохранена.');
    });
  }

  /* =========================================================
     Медиатека
     ========================================================= */
  function tabMedia(content) {
    const items = DATA.gallery.slice().sort((a, b) => (b.date || '').localeCompare(a.date || ''));
    const albums = DATA.albums || [];
    content.innerHTML =
      pageHead('Медиатека', 'Фотографии, видео и альбомы. Материалы используются в новостях и галерее.', '<button class="abtn abtn-primary" id="add-media">' + icon('plus', '', 16) + 'Добавить материал</button>') +
      '<div class="toolbar">' +
        '<div class="field field--grow"><label for="media-search">Поиск</label><input class="input" id="media-search" type="search" placeholder="Название или альбом…"></div>' +
        '<div class="field"><label for="media-album">Альбом</label><select class="select" id="media-album"><option value="all">Все альбомы</option>' + albums.map((a) => '<option value="' + esc(a.id) + '">' + esc(a.title) + '</option>').join('') + '</select></div>' +
      '</div>' +
      (items.length ? '<div class="media-grid" id="media-grid">' + items.map((item) =>
        '<div class="media-item" data-media-item data-title="' + esc((item.title + ' ' + albumTitle(item.album)).toLowerCase()) + '" data-album="' + esc(item.album) + '">' +
          '<div class="media-item__preview"><img src="' + esc(mediaSrc(item, 700)) + '" alt="' + esc(item.title) + '" loading="lazy"></div>' +
          '<div class="media-item__body">' +
            '<div class="media-item__title">' + esc(item.title) + '</div>' +
            '<div class="media-item__meta">' + esc(albumTitle(item.album)) + ' · ' + esc(ruDate(item.date)) + (item.type === 'video' ? ' · видео' : '') + '</div>' +
            '<div class="media-item__actions">' +
              '<button class="abtn abtn-ghost" data-media-edit="' + esc(item.id) + '">Изменить</button>' +
              '<button class="abtn abtn-ghost" data-media-delete="' + esc(item.id) + '">Удалить</button>' +
            '</div>' +
          '</div>' +
        '</div>').join('') + '</div>' : empty('Медиатека пуста', 'Добавьте первый фото- или видеоматериал.')) +
      '<div class="admin-card" style="margin-top:1rem">' +
        '<div class="admin-card__head"><div><h2>Альбомы</h2><p>Группировка материалов в фотогалерее</p></div><button class="abtn abtn-soft abtn-sm" id="add-album">' + icon('plus', '', 15) + 'Новый альбом</button></div>' +
        '<div class="table-wrap"><table class="tbl"><thead><tr><th>Альбом</th><th>Описание</th><th>Материалов</th><th></th></tr></thead><tbody>' +
          albums.map((album) => '<tr><td><strong>' + esc(album.title) + '</strong></td><td>' + esc(album.description || '—') + '</td><td>' + items.filter((i) => i.album === album.id).length + '</td><td><button class="abtn abtn-ghost abtn-sm" data-album-delete="' + esc(album.id) + '">Удалить</button></td></tr>').join('') +
        '</tbody></table></div>' +
      '</div>';

    $('#add-media').addEventListener('click', () => editMedia(null));
    $('#add-album').addEventListener('click', addAlbum);
    $$('[data-media-edit]').forEach((button) => button.addEventListener('click', () => editMedia(button.dataset.mediaEdit)));
    $$('[data-media-delete]').forEach((button) => button.addEventListener('click', () => {
      const item = DATA.gallery.find((g) => g.id === button.dataset.mediaDelete);
      if (!item) return;
      if (!confirm('Удалить материал «' + item.title + '»?')) return;
      DATA.gallery = DATA.gallery.filter((g) => g.id !== item.id);
      persist('Медиаматериал удалён', item.title, 'Материал удалён.');
    }));
    $$('[data-album-delete]').forEach((button) => button.addEventListener('click', () => {
      const album = DATA.albums.find((a) => a.id === button.dataset.albumDelete);
      if (!album) return;
      const used = DATA.gallery.filter((g) => g.album === album.id).length;
      if (used) { toast('Нельзя удалить альбом: в нём ' + used + ' материалов.', 'error'); return; }
      if (!confirm('Удалить альбом «' + album.title + '»?')) return;
      DATA.albums = DATA.albums.filter((a) => a.id !== album.id);
      persist('Альбом удалён', album.title, 'Альбом удалён.');
    }));

    const search = $('#media-search');
    const albumSelect = $('#media-album');
    const filter = () => {
      const q = search.value.toLowerCase().trim();
      const album = albumSelect.value;
      $$('[data-media-item]').forEach((item) => {
        const ok = (!q || item.dataset.title.indexOf(q) !== -1) && (album === 'all' || item.dataset.album === album);
        item.style.display = ok ? '' : 'none';
      });
    };
    search.addEventListener('input', filter);
    albumSelect.addEventListener('change', filter);
  }

  function addAlbum() {
    openModal({
      title: 'Новый альбом',
      body: '<div class="form-grid"><div class="field full"><label for="album-title">Название</label><input class="input" id="album-title" maxlength="120"></div>' +
        '<div class="field full"><label for="album-desc">Описание</label><textarea class="textarea" id="album-desc" style="min-height:5rem"></textarea></div></div><div class="form-error" data-error></div>',
      footer: '<button class="abtn abtn-ghost" data-modal-close>Отмена</button><button class="abtn abtn-primary" id="album-save">Создать</button>'
    });
    $('#album-save').addEventListener('click', () => {
      const title = $('#album-title').value.trim();
      if (!title) { $('[data-error]').textContent = 'Укажите название.'; return; }
      DATA.albums.push({ id: uid('a'), title, description: $('#album-desc').value.trim() });
      persist('Альбом создан', title, 'Альбом создан.');
    });
  }

  function editMedia(id) {
    const existing = id ? DATA.gallery.find((g) => g.id === id) : null;
    const item = existing || {
      id: '', album: (DATA.albums[0] || {}).id || '', title: '', caption: '', date: VGS.todayISO(),
      image: 'photo-01', src: '', type: 'photo', videoUrl: '', published: 1
    };
    const assets = ['photo-01', 'photo-02', 'photo-03', 'photo-04', 'photo-05', 'photo-06', 'photo-07', 'photo-08', 'photo-09', 'photo-10', 'photo-11', 'photo-12', 'photo-13', 'photo-14', 'photo-15', 'photo-16'];

    openModal({
      title: existing ? 'Редактирование материала' : 'Новый материал',
      subtitle: 'Изображение можно выбрать из архива, указать ссылку или загрузить файл.',
      wide: true,
      body:
        '<div class="form-grid">' +
          '<div class="field full"><label for="m-title">Название *</label><input class="input" id="m-title" maxlength="200" value="' + esc(item.title) + '"></div>' +
          '<div class="field"><label for="m-album">Альбом</label><select class="select" id="m-album">' + (DATA.albums || []).map((a) => '<option value="' + esc(a.id) + '"' + (a.id === item.album ? ' selected' : '') + '>' + esc(a.title) + '</option>').join('') + '</select></div>' +
          '<div class="field"><label for="m-date">Дата</label><input class="input" id="m-date" type="date" value="' + esc(item.date) + '"></div>' +
          '<div class="field"><label for="m-type">Тип</label><select class="select" id="m-type"><option value="photo"' + (item.type !== 'video' ? ' selected' : '') + '>Фотография</option><option value="video"' + (item.type === 'video' ? ' selected' : '') + '>Видео</option></select></div>' +
          '<div class="field"><label for="m-pub">Статус</label><label class="check"><input type="checkbox" id="m-pub"' + (item.published ? ' checked' : '') + '> Опубликовано</label></div>' +
          '<div class="field full"><label for="m-asset">Изображение из архива</label><select class="select" id="m-asset"><option value="">— не выбрано —</option>' +
            assets.map((asset) => '<option value="' + asset + '"' + (item.image === asset && !item.src ? ' selected' : '') + '>' + asset.replace('photo-', 'Фото № ') + '</option>').join('') +
          '</select></div>' +
          '<div class="field full"><label for="m-url">…или ссылка на изображение</label><input class="input" id="m-url" value="' + esc(/^(data:|https?:|assets\/)/.test(item.src || '') ? (item.src.indexOf('data:') === 0 ? '' : item.src) : '') + '" placeholder="https://…"></div>' +
          '<div class="field full"><label for="m-file">…или загрузить файл (до 1,2 МБ)</label><input class="input" id="m-file" type="file" accept="image/*"></div>' +
          '<div class="field full"><div id="m-preview" style="border:1px solid var(--line);border-radius:6px;overflow:hidden;max-width:20rem;aspect-ratio:4/3;background:var(--bg-soft)"><img src="' + esc(mediaSrc(item, 700)) + '" alt="" style="width:100%;height:100%;object-fit:cover"></div></div>' +
          '<div class="field full"><label for="m-caption">Подпись</label><textarea class="textarea" id="m-caption" style="min-height:4.5rem">' + esc(item.caption || '') + '</textarea></div>' +
          '<div class="field full"><label for="m-video">Ссылка на видео (для типа «Видео»)</label><input class="input" id="m-video" value="' + esc(item.videoUrl || '') + '" placeholder="https://…mp4"></div>' +
        '</div>' +
        '<div class="alert-box alert-box--warn">' + icon('alert', '', 17) + '<span>Демонстрационное хранилище ограничено (~5 МБ). Для больших файлов указывайте ссылку; в рабочей версии медиафайлы сохраняются в <code>wp-content/uploads/</code> и оптимизируются плагином EWWW Image Optimizer.</span></div>' +
        '<div class="form-error" data-error role="alert"></div>',
      footer: '<button class="abtn abtn-ghost" data-modal-close>Отмена</button><button class="abtn abtn-primary" id="m-save">Сохранить</button>'
    });

    let uploaded = '';
    const preview = $('#m-preview img');
    const updatePreview = () => {
      if (uploaded) { preview.src = uploaded; return; }
      const url = $('#m-url').value.trim();
      if (url) { preview.src = url; return; }
      const asset = $('#m-asset').value;
      if (asset) preview.src = VGS.imagePath(asset, 700);
    };
    $('#m-asset').addEventListener('change', () => { uploaded = ''; updatePreview(); });
    $('#m-url').addEventListener('input', () => { uploaded = ''; updatePreview(); });
    $('#m-file').addEventListener('change', async (event) => {
      const file = event.target.files[0];
      if (!file) return;
      try {
        const base64 = await fileToBase64(file, 1.2 * 1024 * 1024);
        uploaded = 'data:' + (file.type || 'image/jpeg') + ';base64,' + base64;
        preview.src = uploaded;
        $('#m-asset').value = '';
        $('#m-url').value = '';
      } catch (error) {
        toast(error.message, 'error');
      }
    });

    $('#m-save').addEventListener('click', () => {
      const title = $('#m-title').value.trim();
      if (!title) { $('[data-error]').textContent = 'Укажите название.'; return; }
      if (!DATA.albums.length) { $('[data-error]').textContent = 'Сначала создайте альбом.'; return; }
      const type = $('#m-type').value;
      if (type === 'video' && !$('#m-video').value.trim()) { $('[data-error]').textContent = 'Укажите ссылку на видео.'; return; }
      const asset = $('#m-asset').value;
      const url = $('#m-url').value.trim();
      let image = asset || (existing && existing.image) || 'photo-01';
      let src = existing && existing.src ? existing.src : '';
      if (uploaded) { image = 'photo-01'; src = uploaded; }
      else if (url) { image = 'photo-01'; src = url; }
      else if (asset) { image = asset; src = ''; }
      const next = {
        id: existing ? existing.id : uid('g'),
        album: $('#m-album').value,
        title,
        caption: $('#m-caption').value.trim(),
        date: $('#m-date').value || VGS.todayISO(),
        image,
        src,
        type,
        videoUrl: type === 'video' ? $('#m-video').value.trim() : '',
        published: $('#m-pub').checked ? 1 : 0
      };
      if (existing) DATA.gallery = DATA.gallery.map((g) => g.id === existing.id ? next : g);
      else DATA.gallery.push(next);
      persist(existing ? 'Медиаматериал изменён' : 'Медиаматериал добавлен', title, 'Материал сохранён.');
    });
  }

  /* =========================================================
     Документы
     ========================================================= */
  function tabDocs(content) {
    const rows = DATA.docs.slice().sort((a, b) => (b.date || '').localeCompare(a.date || ''));
    content.innerHTML =
      pageHead('Документы', 'PDF-файлы, нормативные акты и памятки.', '<button class="abtn abtn-primary" id="add-doc">' + icon('plus', '', 16) + 'Добавить документ</button>') +
      '<div class="alert-box">' + icon('info', '', 18) + '<span>Для PDF рекомендуется создавать тегированные документы (ГОСТ Р 70176-2022) или дублировать содержание в HTML-версии. Файлы до 2 МБ можно загрузить в демо-хранилище.</span></div>' +
      (rows.length ? '<div class="table-wrap"><table class="tbl"><thead><tr><th>Дата</th><th>Документ</th><th>Категория</th><th>Статус</th><th>Действия</th></tr></thead><tbody>' +
        rows.map((doc) => '<tr>' +
          '<td>' + esc(ruDate(doc.date)) + '</td>' +
          '<td><div class="tbl__title">' + esc(doc.title) + '</div><div class="tbl__meta">' + (doc.url ? '<a href="' + esc(safeUrl(doc.url)) + '" target="_blank" rel="noopener">Открыть файл</a>' : doc.fileData ? 'Файл загружен: ' + esc(doc.origName || 'документ') : 'Без файла') + (doc.pages ? ' · ' + doc.pages + ' стр.' : '') + '</div></td>' +
          '<td>' + esc(doc.category) + '</td>' +
          '<td>' + publishBadge(doc.published) + '</td>' +
          '<td><div class="tbl__actions"><button class="abtn abtn-soft abtn-sm" data-doc-edit="' + esc(doc.id) + '">Изменить</button><button class="abtn abtn-ghost abtn-sm" data-doc-delete="' + esc(doc.id) + '">Удалить</button></div></td>' +
        '</tr>').join('') + '</tbody></table></div>' : empty('Документов пока нет', 'Добавьте первый документ.'));

    $('#add-doc').addEventListener('click', () => editDoc(null));
    $$('[data-doc-edit]').forEach((button) => button.addEventListener('click', () => editDoc(button.dataset.docEdit)));
    $$('[data-doc-delete]').forEach((button) => button.addEventListener('click', () => {
      const doc = DATA.docs.find((d) => d.id === button.dataset.docDelete);
      if (!doc) return;
      if (!confirm('Удалить документ «' + doc.title + '»?')) return;
      DATA.docs = DATA.docs.filter((d) => d.id !== doc.id);
      persist('Документ удалён', doc.title, 'Документ удалён.');
    }));
  }

  function editDoc(id) {
    const existing = id ? DATA.docs.find((d) => d.id === id) : null;
    const doc = existing || { id: '', title: '', date: VGS.todayISO(), category: DATA.docCategories[0] || 'Нормативные акты', url: '', pages: 1, note: '', published: 1 };
    openModal({
      title: existing ? 'Редактирование документа' : 'Новый документ',
      wide: true,
      body:
        '<div class="form-grid">' +
          '<div class="field full"><label for="d-title">Название *</label><input class="input" id="d-title" maxlength="200" value="' + esc(doc.title) + '"></div>' +
          '<div class="field"><label for="d-date">Дата</label><input class="input" id="d-date" type="date" value="' + esc(doc.date) + '"></div>' +
          '<div class="field"><label for="d-cat">Категория</label><select class="select" id="d-cat">' + categoryOptions(DATA.docCategories, doc.category) + '</select></div>' +
          '<div class="field"><label for="d-pages">Страниц</label><input class="input" id="d-pages" type="number" min="1" max="999" value="' + esc(doc.pages || 1) + '"></div>' +
          '<div class="field"><label for="d-url">Ссылка на файл</label><input class="input" id="d-url" value="' + esc(doc.url || '') + '" placeholder="assets/docs/…pdf"></div>' +
          '<div class="field full"><label for="d-file">Или загрузить файл (до 2 МБ)</label><input class="input" id="d-file" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip"></div>' +
          '<div class="field full"><label for="d-note">Примечание</label><textarea class="textarea" id="d-note" style="min-height:4.5rem">' + esc(doc.note || '') + '</textarea></div>' +
          '<div class="field full"><label class="check"><input type="checkbox" id="d-pub"' + (doc.published ? ' checked' : '') + '> Опубликовать</label></div>' +
        '</div>' +
        '<div class="alert-box">' + icon('info', '', 18) + '<span>Если загружен файл, он имеет приоритет над ссылкой. Для больших документов используйте ссылку на внешнее хранилище.</span></div>' +
        '<div class="form-error" data-error role="alert"></div>',
      footer: '<button class="abtn abtn-ghost" data-modal-close>Отмена</button><button class="abtn abtn-primary" id="d-save">Сохранить</button>'
    });

    $('#d-save').addEventListener('click', async () => {
      const title = $('#d-title').value.trim();
      if (!title) { $('[data-error]').textContent = 'Укажите название.'; return; }
      const file = $('#d-file').files[0];
      const next = {
        id: existing ? existing.id : uid('d'),
        title,
        date: $('#d-date').value || VGS.todayISO(),
        category: $('#d-cat').value,
        pages: Number($('#d-pages').value) || 1,
        note: $('#d-note').value.trim(),
        url: $('#d-url').value.trim(),
        published: $('#d-pub').checked ? 1 : 0
      };
      if (file) {
        try {
          const base64 = await fileToBase64(file, 2 * 1024 * 1024);
          next.fileData = base64;
          next.fileType = file.type || 'application/octet-stream';
          next.origName = file.name;
          next.url = '';
        } catch (error) {
          $('[data-error]').textContent = error.message;
          return;
        }
      } else if (existing && existing.fileData && !next.url) {
        next.fileData = existing.fileData;
        next.fileType = existing.fileType;
        next.origName = existing.origName;
      }
      if (!next.url && !next.fileData) { $('[data-error]').textContent = 'Укажите ссылку или загрузите файл.'; return; }
      if (existing) DATA.docs = DATA.docs.map((d) => d.id === existing.id ? next : d);
      else DATA.docs.push(next);
      persist(existing ? 'Документ изменён' : 'Документ добавлен', title, 'Документ сохранён.');
    });
  }

  /* =========================================================
     Подразделения
     ========================================================= */
  function tabDivisions(content) {
    const rows = DATA.divisions.slice().sort((a, b) => (a.city || '').localeCompare(b.city || '', 'ru'));
    content.innerHTML =
      pageHead('Подразделения', 'Структура отряда и дислокация подразделений.', '<button class="abtn abtn-primary" id="add-division">' + icon('plus', '', 16) + 'Добавить подразделение</button>') +
      (rows.length ? '<div class="table-wrap"><table class="tbl"><thead><tr><th>Подразделение</th><th>Тип</th><th>Город</th><th>Адрес</th><th>Статус</th><th>Действия</th></tr></thead><tbody>' +
        rows.map((item) => '<tr>' +
          '<td><div class="tbl__title">' + esc(item.name) + '</div><div class="tbl__meta">' + esc(item.units || '') + '</div></td>' +
          '<td>' + esc(item.type) + '</td>' +
          '<td>' + esc(item.city) + '</td>' +
          '<td>' + esc(item.address) + '</td>' +
          '<td>' + publishBadge(item.published) + '</td>' +
          '<td><div class="tbl__actions"><button class="abtn abtn-soft abtn-sm" data-division-edit="' + esc(item.id) + '">Изменить</button><button class="abtn abtn-ghost abtn-sm" data-division-delete="' + esc(item.id) + '">Удалить</button></div></td>' +
        '</tr>').join('') + '</tbody></table></div>' : empty('Подразделений нет', 'Добавьте первое подразделение.'));

    $('#add-division').addEventListener('click', () => editDivision(null));
    $$('[data-division-edit]').forEach((button) => button.addEventListener('click', () => editDivision(button.dataset.divisionEdit)));
    $$('[data-division-delete]').forEach((button) => button.addEventListener('click', () => {
      const item = DATA.divisions.find((d) => d.id === button.dataset.divisionDelete);
      if (!item) return;
      if (!confirm('Удалить подразделение «' + item.name + '»?')) return;
      DATA.divisions = DATA.divisions.filter((d) => d.id !== item.id);
      persist('Подразделение удалено', item.name, 'Подразделение удалено.');
    }));
  }

  function editDivision(id) {
    const existing = id ? DATA.divisions.find((d) => d.id === id) : null;
    const item = existing || { id: '', name: '', type: 'Взвод', city: 'Новокузнецк', address: '', phone: '', units: '', description: '', published: 1 };
    openModal({
      title: existing ? 'Редактирование подразделения' : 'Новое подразделение',
      body:
        '<div class="form-grid">' +
          '<div class="field full"><label for="v-name">Название *</label><input class="input" id="v-name" value="' + esc(item.name) + '"></div>' +
          '<div class="field"><label for="v-type">Тип</label><select class="select" id="v-type">' + ['Штаб', 'Взвод', 'Отделение', 'Оперативный пункт'].map((t) => '<option' + (t === item.type ? ' selected' : '') + '>' + t + '</option>').join('') + '</select></div>' +
          '<div class="field"><label for="v-city">Город</label><input class="input" id="v-city" value="' + esc(item.city) + '"></div>' +
          '<div class="field"><label for="v-address">Адрес</label><input class="input" id="v-address" value="' + esc(item.address) + '"></div>' +
          '<div class="field"><label for="v-phone">Телефон</label><input class="input" id="v-phone" value="' + esc(item.phone) + '"></div>' +
          '<div class="field full"><label for="v-units">Отделения / состав</label><input class="input" id="v-units" value="' + esc(item.units || '') + '"></div>' +
          '<div class="field full"><label for="v-desc">Описание</label><textarea class="textarea" id="v-desc" style="min-height:5rem">' + esc(item.description) + '</textarea></div>' +
          '<div class="field full"><label class="check"><input type="checkbox" id="v-pub"' + (item.published ? ' checked' : '') + '> Показывать на сайте</label></div>' +
        '</div><div class="form-error" data-error></div>',
      footer: '<button class="abtn abtn-ghost" data-modal-close>Отмена</button><button class="abtn abtn-primary" id="v-save">Сохранить</button>'
    });
    $('#v-save').addEventListener('click', () => {
      const name = $('#v-name').value.trim();
      if (!name) { $('[data-error]').textContent = 'Укажите название.'; return; }
      const next = {
        id: existing ? existing.id : uid('v'),
        name, type: $('#v-type').value, city: $('#v-city').value.trim(),
        address: $('#v-address').value.trim(), phone: $('#v-phone').value.trim(),
        units: $('#v-units').value.trim(), description: $('#v-desc').value.trim(),
        published: $('#v-pub').checked ? 1 : 0
      };
      if (existing) DATA.divisions = DATA.divisions.map((d) => d.id === existing.id ? next : d);
      else DATA.divisions.push(next);
      persist(existing ? 'Подразделение изменено' : 'Подразделение добавлено', name, 'Подразделение сохранено.');
    });
  }

  /* =========================================================
     Телефонный справочник
     ========================================================= */
  function tabPhones(content) {
    const rows = DATA.phones.slice().sort((a, b) => (a.department || '').localeCompare(b.department || '', 'ru') || (a.position || '').localeCompare(b.position || '', 'ru'));
    content.innerHTML =
      pageHead('Телефонный справочник', 'Контакты подразделений и должностных лиц.', '<button class="abtn abtn-primary" id="add-phone">' + icon('plus', '', 16) + 'Добавить запись</button>') +
      (rows.length ? '<div class="table-wrap"><table class="tbl"><thead><tr><th>Подразделение</th><th>Должность / ФИО</th><th>Контакты</th><th>Статус</th><th>Действия</th></tr></thead><tbody>' +
        rows.map((item) => '<tr>' +
          '<td>' + esc(item.department || '—') + '</td>' +
          '<td><div class="tbl__title">' + esc(item.position || '—') + '</div><div class="tbl__meta">' + esc(item.name || '') + '</div></td>' +
          '<td>' + esc(item.phone || '') + (item.email ? '<div class="tbl__meta">' + esc(item.email) + '</div>' : '') + '</td>' +
          '<td>' + publishBadge(item.published) + '</td>' +
          '<td><div class="tbl__actions"><button class="abtn abtn-soft abtn-sm" data-phone-edit="' + esc(item.id) + '">Изменить</button><button class="abtn abtn-ghost abtn-sm" data-phone-delete="' + esc(item.id) + '">Удалить</button></div></td>' +
        '</tr>').join('') + '</tbody></table></div>' : empty('Записей нет', 'Добавьте первую запись справочника.'));

    $('#add-phone').addEventListener('click', () => editPhone(null));
    $$('[data-phone-edit]').forEach((button) => button.addEventListener('click', () => editPhone(button.dataset.phoneEdit)));
    $$('[data-phone-delete]').forEach((button) => button.addEventListener('click', () => {
      const item = DATA.phones.find((p) => p.id === button.dataset.phoneDelete);
      if (!item) return;
      if (!confirm('Удалить запись «' + (item.position || item.name || '') + '»?')) return;
      DATA.phones = DATA.phones.filter((p) => p.id !== item.id);
      persist('Запись справочника удалена', item.position || item.name, 'Запись удалена.');
    }));
  }

  function editPhone(id) {
    const existing = id ? DATA.phones.find((p) => p.id === id) : null;
    const item = existing || { id: '', department: '', position: '', name: '', phone: '', email: '', published: 1 };
    openModal({
      title: existing ? 'Редактирование записи' : 'Новая запись справочника',
      body:
        '<div class="form-grid">' +
          '<div class="field full"><label for="ph-dept">Подразделение</label><input class="input" id="ph-dept" value="' + esc(item.department) + '" placeholder="Командование"></div>' +
          '<div class="field full"><label for="ph-pos">Должность</label><input class="input" id="ph-pos" value="' + esc(item.position) + '" placeholder="Командир отряда"></div>' +
          '<div class="field full"><label for="ph-name">ФИО</label><input class="input" id="ph-name" value="' + esc(item.name) + '"></div>' +
          '<div class="field"><label for="ph-phone">Телефон *</label><input class="input" id="ph-phone" value="' + esc(item.phone) + '"></div>' +
          '<div class="field"><label for="ph-email">Email</label><input class="input" id="ph-email" type="email" value="' + esc(item.email) + '"></div>' +
          '<div class="field full"><label class="check"><input type="checkbox" id="ph-pub"' + (item.published ? ' checked' : '') + '> Показывать в справочнике</label></div>' +
        '</div><div class="form-error" data-error></div>',
      footer: '<button class="abtn abtn-ghost" data-modal-close>Отмена</button><button class="abtn abtn-primary" id="ph-save">Сохранить</button>'
    });
    $('#ph-save').addEventListener('click', () => {
      const position = $('#ph-pos').value.trim();
      const name = $('#ph-name').value.trim();
      const phone = $('#ph-phone').value.trim();
      if (!position && !name) { $('[data-error]').textContent = 'Укажите должность или ФИО.'; return; }
      if (!phone) { $('[data-error]').textContent = 'Укажите телефон.'; return; }
      const next = {
        id: existing ? existing.id : uid('p'),
        department: $('#ph-dept').value.trim(),
        position, name, phone,
        email: $('#ph-email').value.trim(),
        published: $('#ph-pub').checked ? 1 : 0
      };
      if (existing) DATA.phones = DATA.phones.map((p) => p.id === existing.id ? next : p);
      else DATA.phones.push(next);
      persist(existing ? 'Запись справочника изменена' : 'Запись справочника добавлена', position || name, 'Запись сохранена.');
    });
  }

  /* =========================================================
     Cookie-согласия
     ========================================================= */
  function tabConsents(content) {
    const log = getConsentLog();
    const analytics = log.filter((item) => item.categories && item.categories.analytics).length;
    const functional = log.filter((item) => item.categories && item.categories.functional).length;
    content.innerHTML =
      pageHead('Cookie-согласия', 'Журнал согласий и статистика по категориям файлов cookie.', '<button class="abtn abtn-ghost" id="export-consents">' + icon('download', '', 16) + 'Экспорт CSV</button>') +
      '<div class="alert-box">' + icon('info', '', 18) + '<span>Журнал формируется при выборе пользователя в cookie-баннере. В демонстрационной версии записи хранятся локально; в рабочей версии — в защищённом хранилище на сервере в РФ.</span></div>' +
      '<div class="consent-stats">' +
        '<div class="consent-stat"><strong>' + log.length + '</strong><span>всего записей</span></div>' +
        '<div class="consent-stat"><strong>' + functional + '</strong><span>разрешили функциональные</span></div>' +
        '<div class="consent-stat"><strong>' + analytics + '</strong><span>разрешили аналитические</span></div>' +
        '<div class="consent-stat"><strong>' + (log[0] ? esc(ruDateTime(log[0].timestamp)) : '—') + '</strong><span>последнее согласие</span></div>' +
      '</div>' +
      '<div class="admin-card">' +
        '<div class="admin-card__head"><div><h2>Журнал согласий</h2><p>Последние 200 записей</p></div><div class="form-row"><button class="abtn abtn-ghost abtn-sm" id="reset-my-consent">Сбросить моё согласие</button><button class="abtn abtn-soft abtn-sm" id="refresh-consents">Обновить</button></div></div>' +
        (log.length ? '<div class="table-wrap"><table class="tbl"><thead><tr><th>Дата и время</th><th>Категории</th><th>Способ</th><th>Страница</th><th>ID записи</th></tr></thead><tbody>' +
          log.map((item) => '<tr>' +
            '<td>' + esc(ruDateTime(item.timestamp)) + '</td>' +
            '<td>' + ['necessary', 'functional', 'analytics', 'marketing'].map((key) => '<span class="badge ' + (item.categories && item.categories[key] ? (key === 'necessary' ? 'badge--grey' : 'badge--green') : 'badge--grey') + '" style="margin:0 .15rem .15rem 0">' + (VGS.CATEGORY_LABELS[key] || key) + '</span>').join('') + '</td>' +
            '<td>' + esc(item.method || '—') + '</td>' +
            '<td class="tbl__meta">' + esc(item.page || '—') + '</td>' +
            '<td><span class="code">' + esc(item.id || '—') + '</span></td>' +
          '</tr>').join('') + '</tbody></table></div>' : empty('Журнал пока пуст', 'Согласия появятся после выбора пользователя в cookie-баннере на сайте.')) +
      '</div>';

    $('#export-consents').addEventListener('click', () => {
      const rows = [['Дата и время', 'Необходимые', 'Функциональные', 'Аналитические', 'Маркетинговые', 'Способ', 'Страница', 'ID']];
      log.forEach((item) => rows.push([
        ruDateTime(item.timestamp), item.categories.necessary ? 'да' : 'нет', item.categories.functional ? 'да' : 'нет',
        item.categories.analytics ? 'да' : 'нет', item.categories.marketing ? 'да' : 'нет', item.method || '', item.page || '', item.id || ''
      ]));
      download('cookie-consents-' + VGS.todayISO() + '.csv', csv(rows), 'text/csv;charset=utf-8');
    });
    $('#reset-my-consent').addEventListener('click', () => {
      clearConsent();
      toast('Согласие сброшено. Откройте сайт, чтобы снова увидеть cookie-баннер.');
      refresh();
    });
    $('#refresh-consents').addEventListener('click', refresh);
  }

  /* =========================================================
     Пользователи
     ========================================================= */
  function tabUsers(content) {
    const users = loadUsers().slice().sort((a, b) => (a.name || '').localeCompare(b.name || '', 'ru'));
    const me = getCurrentUser();
    content.innerHTML =
      pageHead('Пользователи', 'Роли администратора, редактора и модератора. Пароли хранятся в виде хеша PBKDF2.', '<button class="abtn abtn-primary" id="add-user">' + icon('plus', '', 16) + 'Добавить пользователя</button>') +
      '<div class="alert-box">' + icon('shield', '', 18) + '<span>Рекомендуется включить двухфакторную аутентификацию и ограничить доступ к /wp-admin на уровне веб-сервера. В демонстрационной версии используется локальная аутентификация.</span></div>' +
      (users.length ? '<div class="table-wrap"><table class="tbl"><thead><tr><th>Пользователь</th><th>Логин</th><th>Роль</th><th>Создан</th><th>Действия</th></tr></thead><tbody>' +
        users.map((user) => '<tr>' +
          '<td><div class="tbl__title">' + esc(user.name) + '</div>' + (me && user.id === me.id ? '<div class="tbl__meta">это вы</div>' : '') + '</td>' +
          '<td><span class="code">' + esc(user.login) + '</span></td>' +
          '<td>' + roleBadge(user.role) + '</td>' +
          '<td>' + esc(ruDate((user.createdAt || '').slice(0, 10))) + '</td>' +
          '<td><div class="tbl__actions"><button class="abtn abtn-soft abtn-sm" data-user-edit="' + esc(user.id) + '">Изменить</button><button class="abtn abtn-ghost abtn-sm" data-user-delete="' + esc(user.id) + '"' + (me && user.id === me.id ? ' disabled' : '') + '>Удалить</button></div></td>' +
        '</tr>').join('') + '</tbody></table></div>' : empty('Пользователей нет', 'Создайте первую учётную запись.'));
    $('#add-user').addEventListener('click', () => editUser(null));
    $$('[data-user-edit]').forEach((button) => button.addEventListener('click', () => editUser(button.dataset.userEdit)));
    $$('[data-user-delete]').forEach((button) => button.addEventListener('click', () => deleteUser(button.dataset.userDelete)));
  }

  function editUser(id) {
    const isNew = !id;
    const users = loadUsers();
    const user = isNew ? { name: '', login: '', role: 'editor' } : users.find((u) => u.id === id);
    if (!user) return;
    openModal({
      title: isNew ? 'Новый пользователь' : 'Редактирование пользователя',
      body:
        '<div class="form-grid">' +
          '<div class="field full"><label for="u-name">ФИО *</label><input class="input" id="u-name" value="' + esc(user.name) + '"></div>' +
          '<div class="field"><label for="u-login">Логин *</label><input class="input" id="u-login" value="' + esc(user.login) + '" autocomplete="username"></div>' +
          '<div class="field"><label for="u-role">Роль</label><select class="select" id="u-role">' + Object.keys(ROLES).map((role) => '<option value="' + role + '"' + (role === user.role ? ' selected' : '') + '>' + esc(ROLES[role].label) + '</option>').join('') + '</select></div>' +
          '<div class="field full"><label for="u-pw">' + (isNew ? 'Пароль *' : 'Новый пароль (пусто — не менять)') + '</label><input class="input" id="u-pw" type="password" autocomplete="new-password" placeholder="не менее 8 символов"></div>' +
        '</div>' +
        '<div class="alert-box">' + icon('info', '', 18) + '<span>Администратор — полный доступ; редактор — контент и справочники; модератор — новости, согласия и журнал.</span></div>' +
        '<div class="form-error" data-error></div>',
      footer: '<button class="abtn abtn-ghost" data-modal-close>Отмена</button><button class="abtn abtn-primary" id="u-save">Сохранить</button>'
    });
    $('#u-save').addEventListener('click', async () => {
      if (busy) return;
      const name = $('#u-name').value.trim();
      const login = $('#u-login').value.trim();
      const role = $('#u-role').value;
      const password = $('#u-pw').value;
      const error = $('[data-error]');
      if (!name) { error.textContent = 'Укажите ФИО.'; return; }
      if (login.length < 3) { error.textContent = 'Логин — не менее 3 символов.'; return; }
      const fresh = loadUsers();
      if (fresh.some((u) => u.login.toLowerCase() === login.toLowerCase() && (!isNew ? u.id !== user.id : true))) {
        error.textContent = 'Пользователь с таким логином уже существует.';
        return;
      }
      if (isNew && password.length < 8) { error.textContent = 'Пароль — не менее 8 символов.'; return; }
      if (!isNew && password && password.length < 8) { error.textContent = 'Новый пароль — не менее 8 символов.'; return; }
      if (!isNew && user.role === 'admin' && role !== 'admin' && fresh.filter((u) => u.role === 'admin').length <= 1) {
        error.textContent = 'Нельзя изменить роль последнего администратора.';
        return;
      }
      busy = true;
      const button = $('#u-save');
      button.disabled = true;
      button.textContent = 'Сохранение…';
      try {
        if (isNew) {
          const next = { id: uid('u'), name, login, role, createdAt: new Date().toISOString() };
          await applyPassword(next, password);
          fresh.push(next);
        } else {
          const target = fresh.find((u) => u.id === user.id);
          target.name = name;
          target.login = login;
          target.role = role;
          if (password) await applyPassword(target, password);
        }
        if (!saveUsers(fresh)) throw new Error('Не удалось сохранить пользователей.');
        logAction(isNew ? 'Пользователь создан' : 'Пользователь изменён', name + ' (' + login + ', ' + role + ')');
        busy = false;
        closeModal();
        refresh();
        toast('Пользователь сохранён.');
      } catch (err) {
        busy = false;
        button.disabled = false;
        button.textContent = 'Сохранить';
        error.textContent = err && err.message ? err.message : 'Не удалось сохранить пользователя.';
      }
    });
  }

  function deleteUser(id) {
    const users = loadUsers();
    const user = users.find((u) => u.id === id);
    if (!user) return;
    const me = getCurrentUser();
    if (me && me.id === user.id) { toast('Нельзя удалить свою учётную запись.', 'error'); return; }
    if (user.role === 'admin' && users.filter((u) => u.role === 'admin').length <= 1) {
      toast('Нельзя удалить последнего администратора.', 'error');
      return;
    }
    if (!confirm('Удалить пользователя «' + user.name + '»?')) return;
    saveUsers(users.filter((u) => u.id !== id));
    logAction('Пользователь удалён', user.name + ' (' + user.login + ')');
    refresh();
    toast('Пользователь удалён.');
  }

  /* =========================================================
     Журнал действий
     ========================================================= */
  function tabLog(content) {
    const log = getAuditLog().slice(0, 300);
    content.innerHTML =
      pageHead('Журнал действий', 'Логирование изменений в системе.', '<button class="abtn abtn-ghost" id="export-log">' + icon('download', '', 16) + 'Экспорт CSV</button>' + (can('users') ? '<button class="abtn abtn-ghost" id="clear-log">' + icon('trash', '', 16) + 'Очистить</button>' : '')) +
      '<div class="toolbar"><div class="field field--grow"><label for="log-search">Поиск по журналу</label><input class="input" id="log-search" type="search" placeholder="Действие, пользователь, детали…"></div></div>' +
      (log.length ? '<div class="table-wrap"><table class="tbl" id="log-table"><thead><tr><th>Дата и время</th><th>Пользователь</th><th>Действие</th><th>Детали</th></tr></thead><tbody>' +
        log.map((item) => '<tr data-log-row data-text="' + esc((item.action + ' ' + item.userName + ' ' + (item.details || '')).toLowerCase()) + '"><td>' + esc(ruDateTime(item.timestamp)) + '</td><td>' + esc(item.userName || 'Система') + '</td><td><strong>' + esc(item.action) + '</strong></td><td class="tbl__meta">' + esc(item.details || '') + '</td></tr>').join('') +
      '</tbody></table></div>' : empty('Журнал пуст', 'Действия появятся после изменения контента.'));
    if (log.length) {
      $('#log-search').addEventListener('input', (event) => {
        const q = event.target.value.toLowerCase().trim();
        $$('[data-log-row]').forEach((row) => { row.style.display = !q || row.dataset.text.indexOf(q) !== -1 ? '' : 'none'; });
      });
    }
    $('#export-log').addEventListener('click', () => {
      const rows = [['Дата и время', 'Пользователь', 'Действие', 'Детали']];
      log.forEach((item) => rows.push([ruDateTime(item.timestamp), item.userName || 'Система', item.action, item.details || '']));
      download('audit-log-' + VGS.todayISO() + '.csv', csv(rows), 'text/csv;charset=utf-8');
    });
    const clear = $('#clear-log');
    if (clear) clear.addEventListener('click', () => {
      if (!confirm('Очистить журнал действий? Действие будет зафиксировано новой записью.')) return;
      DATA.auditLog = [];
      saveData(DATA);
      logAction('Журнал действий очищен', 'Администратор очистил журнал');
      DATA = loadData();
      refresh();
      toast('Журнал очищен.');
    });
  }

  /* =========================================================
     Резервные копии
     ========================================================= */
  function tabBackup(content) {
    const c = counts();
    content.innerHTML =
      pageHead('Резервные копии', 'Экспорт и импорт контента, независимая копия данных.', '') +
      '<div class="dashboard-grid">' +
        '<div class="dashboard-stack">' +
          '<div class="admin-card">' +
            '<div class="admin-card__head"><div><h2>Резервная копия контента</h2><p>Настройки, новости, страницы, документы, справочники, галерея</p></div></div>' +
            '<p class="form-hint" style="margin-bottom:.8rem">Файл JSON содержит текущий контент сайта. Пользователи и журналы экспортируются отдельно.</p>' +
            '<div class="form-row"><button class="abtn abtn-primary" id="export-json">' + icon('download', '', 16) + 'Скачать JSON</button><button class="abtn abtn-ghost" id="import-json">' + icon('backup', '', 16) + 'Загрузить JSON</button><input type="file" id="import-file" accept=".json,application/json" hidden></div>' +
          '</div>' +
          '<div class="admin-card">' +
            '<div class="admin-card__head"><div><h2>Журналы и согласия</h2><p>Выгрузка для передачи или контроля</p></div></div>' +
            '<div class="form-row"><button class="abtn abtn-ghost" id="export-consents-backup">' + icon('cookie', '', 16) + 'Согласия (CSV)</button><button class="abtn abtn-ghost" id="export-log-backup">' + icon('journal', '', 16) + 'Журнал действий (CSV)</button></div>' +
          '</div>' +
          '<div class="admin-card">' +
            '<div class="admin-card__head"><div><h2>Сброс контента</h2><p>Вернуть исходные демонстрационные данные</p></div></div>' +
            '<div class="alert-box alert-box--red">' + icon('alert', '', 18) + '<span>Действие необратимо. Пользователи сохраняются. Перед сбросом рекомендуется скачать резервную копию.</span></div>' +
            '<button class="abtn abtn-red" id="reset-content">' + icon('trash', '', 16) + 'Сбросить контент к исходному</button>' +
          '</div>' +
        '</div>' +
        '<div class="admin-card">' +
          '<div class="admin-card__head"><h2>Текущее состояние</h2></div>' +
          '<dl class="kv">' +
            '<dt>Новостей</dt><dd>' + c.news + '</dd>' +
            '<dt>Документов</dt><dd>' + c.docs + '</dd>' +
            '<dt>Медиаматериалов</dt><dd>' + c.media + '</dd>' +
            '<dt>Пользователей</dt><dd>' + c.users + '</dd>' +
            '<dt>Записей согласий</dt><dd>' + c.consents + '</dd>' +
            '<dt>Занято в браузере</dt><dd>' + Math.round(dataSize() / 1024) + ' КБ</dd>' +
          '</dl>' +
          '<div class="alert-box alert-box--warn" style="margin:1rem 0 0">' + icon('alert', '', 17) + '<span>В рабочей версии MariaDB резервируется ежедневно через <code>mariadb-dump</code>/<code>mariabackup</code>, изображения — из <code>wp-content/uploads/</code>; копии дублируются плагином UpdraftPlus на собственное внешнее хранилище.</span></div>' +
        '</div>' +
      '</div>';

    $('#export-json').addEventListener('click', () => {
      download('vgs-backup-' + VGS.todayISO() + '.json', JSON.stringify(DATA, null, 2), 'application/json;charset=utf-8');
    });
    $('#import-json').addEventListener('click', () => $('#import-file').click());
    $('#import-file').addEventListener('change', (event) => {
      const file = event.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = () => {
        try {
          const parsed = JSON.parse(reader.result);
          if (!parsed || !parsed.settings) throw new Error('Некорректный формат');
          DATA = VGS.normalizeData(parsed);
          persist('Импорт контента', file.name, 'Данные импортированы.');
        } catch (error) {
          toast('Некорректный JSON-файл.', 'error');
        }
      };
      reader.readAsText(file);
    });
    $('#export-consents-backup').addEventListener('click', () => {
      const rows = [['Дата', 'Функциональные', 'Аналитические', 'Маркетинговые', 'Способ', 'Страница']];
      getConsentLog().forEach((item) => rows.push([ruDateTime(item.timestamp), item.categories.functional ? 'да' : 'нет', item.categories.analytics ? 'да' : 'нет', item.categories.marketing ? 'да' : 'нет', item.method || '', item.page || '']));
      download('cookie-consents-' + VGS.todayISO() + '.csv', csv(rows), 'text/csv;charset=utf-8');
    });
    $('#export-log-backup').addEventListener('click', () => {
      const rows = [['Дата', 'Пользователь', 'Действие', 'Детали']];
      getAuditLog().forEach((item) => rows.push([ruDateTime(item.timestamp), item.userName || '', item.action, item.details || '']));
      download('audit-log-' + VGS.todayISO() + '.csv', csv(rows), 'text/csv;charset=utf-8');
    });
    $('#reset-content').addEventListener('click', () => {
      if (!confirm('Сбросить весь контент к исходному состоянию? Пользователи сохранятся.')) return;
      DATA = VGS.resetData();
      logAction('Сброс контента', 'Восстановлены исходные данные');
      DATA = loadData();
      toast('Контент сброшен к исходному.');
      refresh();
    });
  }

  /* =========================================================
     Настройки
     ========================================================= */
  function tabSettings(content) {
    const s = DATA.settings;
    if (!can('settings')) { content.innerHTML = empty('Недостаточно прав', 'Раздел доступен администратору.'); return; }
    const field = (key, label, type) => '<div class="field"><label for="s-' + key + '">' + esc(label) + '</label><input class="input" id="s-' + key + '" data-setting="' + key + '" ' + (type === 'tel' ? 'type="tel"' : type === 'email' ? 'type="email"' : '') + ' value="' + esc(s[key] || '') + '"></div>';
    const area = (key, label, rows) => '<div class="field full"><label for="s-' + key + '">' + esc(label) + '</label><textarea class="textarea" id="s-' + key + '" data-setting="' + key + '"' + (rows ? ' style="min-height:' + rows + 'rem"' : '') + '>' + esc(s[key] || '') + '</textarea></div>';
    const link = (key, label) => '<div class="field"><label for="l-' + key + '">' + esc(label) + '</label><input class="input" id="l-' + key + '" data-link="' + key + '" value="' + esc((s.links && s.links[key]) || '') + '"></div>';

    content.innerHTML =
      pageHead('Настройки сайта', 'Общие сведения, контакты, тексты и ссылки. Изменения сразу отражаются на сайте.', '<button class="abtn abtn-primary" id="save-settings">' + icon('check', '', 16) + 'Сохранить настройки</button>') +
      '<div class="admin-card">' +
        '<div class="admin-card__head"><h2>Организация</h2></div>' +
        '<div class="form-grid">' +
          field('org_name', 'Краткое название') + field('org_parent', 'Головная организация') +
          '<div class="field full"><label for="s-org_full">Полное наименование</label><textarea class="textarea" id="s-org_full" data-setting="org_full" style="min-height:5rem">' + esc(s.org_full) + '</textarea></div>' +
          field('site_title', 'Полное название сайта') + field('founded', 'Год основания') +
        '</div>' +
      '</div>' +
      '<div class="admin-card">' +
        '<div class="admin-card__head"><h2>Контакты и режим работы</h2></div>' +
        '<div class="form-grid">' +
          '<div class="field full"><label for="s-address">Адрес</label><textarea class="textarea" id="s-address" data-setting="address" style="min-height:4.5rem">' + esc(s.address) + '</textarea></div>' +
          field('address_short', 'Адрес (кратко)') + field('map_query', 'Запрос для карты') +
          field('phone_duty', 'Телефон дежурной службы', 'tel') + field('phone_office', 'Телефон приёмной', 'tel') +
          field('phone_parent', 'Телефон головной организации', 'tel') + field('email', 'Электронная почта', 'email') +
          field('email_parent', 'Почта головной организации', 'email') + field('emergency_number', 'Номер экстренных служб') +
          '<div class="field full"><label for="s-hours">Режим работы</label><textarea class="textarea" id="s-hours" data-setting="hours" style="min-height:4rem">' + esc(s.hours) + '</textarea></div>' +
          '<div class="field full"><label for="s-emergency_text">Текст аварийного уведомления</label><textarea class="textarea" id="s-emergency_text" data-setting="emergency_text" style="min-height:4rem">' + esc(s.emergency_text) + '</textarea></div>' +
        '</div>' +
      '</div>' +
      '<div class="admin-card">' +
        '<div class="admin-card__head"><h2>Тексты главной страницы и разделов</h2></div>' +
        '<div class="form-grid">' +
          '<div class="field full"><label for="s-hero_title">Заголовок на главной</label><input class="input" id="s-hero_title" data-setting="hero_title" value="' + esc(s.hero_title) + '"></div>' +
          '<div class="field full"><label for="s-hero_lead">Подзаголовок на главной</label><textarea class="textarea" id="s-hero_lead" data-setting="hero_lead" style="min-height:5rem">' + esc(s.hero_lead) + '</textarea></div>' +
          '<div class="field full"><label for="s-hero_tag">Метка над заголовком</label><input class="input" id="s-hero_tag" data-setting="hero_tag" value="' + esc(s.hero_tag) + '"></div>' +
          area('about_text', 'Текст «О филиале»', 8) +
          area('about_lead', 'Краткое описание раздела «Об отряде»', 4) +
          area('activity_lead', 'Краткое описание раздела «Деятельность»', 4) +
          area('structure_lead', 'Краткое описание раздела «Структура»', 4) +
          area('veterans_lead', 'Краткое описание раздела «Ветеранам и память»', 4) +
          area('contacts_lead', 'Краткое описание раздела «Контакты»', 4) +
          area('news_lead', 'Краткое описание раздела «Новости»', 4) +
          area('gallery_lead', 'Краткое описание «Фотогалереи»', 4) +
          area('documents_lead', 'Краткое описание «Документов»', 4) +
        '</div>' +
      '</div>' +
      '<div class="admin-card">' +
        '<div class="admin-card__head"><h2>Показатели на главной</h2></div>' +
        '<div class="form-grid">' +
          (s.stats || []).map((stat, index) => '<div class="field"><label>Показатель ' + (index + 1) + ' — значение</label><input class="input" data-stat-value="' + index + '" value="' + esc(stat.value) + '"></div>' +
            '<div class="field"><label>Показатель ' + (index + 1) + ' — подпись</label><input class="input" data-stat-label="' + index + '" value="' + esc(stat.label) + '"></div>').join('') +
        '</div>' +
      '</div>' +
      '<div class="admin-card">' +
        '<div class="admin-card__head"><h2>Реквизиты и ссылки</h2></div>' +
        '<div class="form-grid">' +
          field('inn', 'ИНН') + field('ogrn', 'ОГРН') +
          field('legal_address', 'Юридический адрес') + '<div class="field"></div>' +
          link('parent', 'Сайт ФГУП «ВГСЧ»') + link('mchs', 'Сайт МЧС России') +
          link('kuzbass', 'Главное управление по Кузбассу') + link('rutube', 'Rutube') +
          link('vk', 'ВКонтакте') + link('telegram', 'Telegram') +
        '</div>' +
      '</div>';

    $('#save-settings').addEventListener('click', () => {
      $$('[data-setting]').forEach((input) => { DATA.settings[input.dataset.setting] = input.value.trim(); });
      $$('[data-link]').forEach((input) => { if (!DATA.settings.links) DATA.settings.links = {}; DATA.settings.links[input.dataset.link] = input.value.trim(); });
      $$('[data-stat-value]').forEach((input) => { const index = Number(input.dataset.statValue); if (DATA.settings.stats[index]) DATA.settings.stats[index].value = input.value.trim(); });
      $$('[data-stat-label]').forEach((input) => { const index = Number(input.dataset.statLabel); if (DATA.settings.stats[index]) DATA.settings.stats[index].label = input.value.trim(); });
      DATA.settings.founded = Number(DATA.settings.founded) || DATA.settings.founded;
      persist('Настройки изменены', 'Обновлены общие настройки сайта', 'Настройки сохранены.');
    });
  }

  /* =========================================================
     Смена пароля
     ========================================================= */
  function tabPassword(content) {
    const me = getCurrentUser();
    content.innerHTML =
      pageHead('Смена пароля', 'Пароль хранится в виде хеша PBKDF2 с солью.', '') +
      '<div class="admin-card" style="max-width:32rem">' +
        '<div class="field"><label for="p-old">Текущий пароль</label><input class="input" id="p-old" type="password" autocomplete="current-password"></div>' +
        '<div class="field" style="margin-top:.8rem"><label for="p-new">Новый пароль</label><input class="input" id="p-new" type="password" autocomplete="new-password" placeholder="не менее 8 символов"></div>' +
        '<div class="field" style="margin-top:.8rem"><label for="p-new2">Повторите новый пароль</label><input class="input" id="p-new2" type="password" autocomplete="new-password"></div>' +
        '<div class="form-error" data-error style="margin-top:.6rem"></div>' +
        '<button class="abtn abtn-primary" id="save-password" style="margin-top:.8rem">Изменить пароль</button>' +
        '<p class="form-hint" style="margin-top:.8rem">Для учётной записи: <strong>' + esc(me.login) + '</strong>. После смены пароля текущая сессия сохраняется.</p>' +
      '</div>';
    $('#save-password').addEventListener('click', async () => {
      if (busy) return;
      const oldPassword = $('#p-old').value;
      const newPassword = $('#p-new').value;
      const confirmPassword = $('#p-new2').value;
      const error = $('[data-error]');
      if (newPassword.length < 8) { error.textContent = 'Новый пароль — не менее 8 символов.'; return; }
      if (newPassword !== confirmPassword) { error.textContent = 'Пароли не совпадают.'; return; }
      const users = loadUsers();
      const user = users.find((u) => u.id === me.id);
      if (!user) { error.textContent = 'Пользователь не найден.'; return; }
      busy = true;
      const button = $('#save-password');
      button.disabled = true;
      button.textContent = 'Проверка…';
      try {
        const result = await verifyPassword(oldPassword, user);
        if (!result.ok) {
          error.textContent = 'Текущий пароль неверный.';
          busy = false;
          button.disabled = false;
          button.textContent = 'Изменить пароль';
          return;
        }
        await applyPassword(user, newPassword);
        saveUsers(users);
        logAction('Смена пароля', 'Пользователь ' + user.login);
        busy = false;
        button.disabled = false;
        button.textContent = 'Изменить пароль';
        $('#p-old').value = $('#p-new').value = $('#p-new2').value = '';
        error.textContent = '';
        toast('Пароль изменён.');
      } catch (err) {
        busy = false;
        button.disabled = false;
        button.textContent = 'Изменить пароль';
        error.textContent = err && err.message ? err.message : 'Не удалось изменить пароль.';
      }
    });
  }

  /* =========================================================
     Старт
     ========================================================= */
  function init() {
    if (location.hash) {
      const tab = location.hash.replace('#', '');
      if (TAB_LABELS[tab]) currentTab = tab;
    }
    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      if ($('#admin-modal')) closeModal();
      const sidebar = $('#admin-sidebar');
      const backdrop = $('#admin-backdrop');
      const burger = $('#admin-burger');
      if (sidebar) sidebar.classList.remove('is-open');
      if (backdrop) backdrop.classList.remove('is-open');
      if (burger) burger.setAttribute('aria-expanded', 'false');
    });
    renderApp();
    window.addEventListener('hashchange', () => {
      if (!getCurrentUser()) return;
      const tab = location.hash.replace('#', '');
      if (TAB_LABELS[tab] && allowed(tab)) {
        currentTab = tab;
        renderApp();
      }
    });
  }

  init();
})();
