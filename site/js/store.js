/* =========================================================
   Слой данных сайта: контент, пользователи, согласия, журнал.
   Демонстрационная реализация на localStorage.
   В production данные хранятся в WordPress + локальной MariaDB на том же сервере.
   ========================================================= */
(function (global) {
  'use strict';

  const LS_DATA = 'nvgso_data_v1';
  const LS_USERS = 'nvgso_users_v1';
  const SESSION_KEY = 'nvgso_admin_session';
  const THROTTLE_KEY = 'nvgso_login_throttle_v1';
  const CONSENT_KEY = 'nvgso_consent_v1';
  const CONSENT_LOG_KEY = 'nvgso_consent_log_v1';
  const A11Y_KEY = 'nvgso_a11y_v1';

  const CONSENT_VERSION = '1.0';
  const PBKDF2_HASH = 'SHA-256';
  const PBKDF2_KEYLEN = 32;
  const PBKDF2_ITERATIONS = 600000;
  const SALT_BYTES = 16;

  const THROTTLE_MAX_ATTEMPTS = 5;
  const THROTTLE_WINDOW_MS = 5 * 60 * 1000;
  const THROTTLE_LOCK_MS = 5 * 60 * 1000;

  const ROLES = {
    admin: {
      label: 'Администратор',
      tag: 'admin',
      can: { content: true, pages: true, media: true, docs: true, directories: true, settings: true, users: true, backup: true, consents: true, log: true }
    },
    editor: {
      label: 'Редактор',
      tag: 'editor',
      can: { content: true, pages: true, media: true, docs: true, directories: true, settings: false, users: false, backup: false, consents: false, log: false }
    },
    moderator: {
      label: 'Модератор',
      tag: 'moderator',
      can: { content: true, pages: false, media: true, docs: false, directories: false, settings: false, users: false, backup: false, consents: true, log: true }
    }
  };

  const CATEGORY_LABELS = {
    necessary: 'Строго необходимые',
    functional: 'Функциональные',
    analytics: 'Аналитические',
    marketing: 'Маркетинговые'
  };

  const PAGE_FILES = {
    about: 'about.html',
    structure: 'structure.html',
    activity: 'activity.html',
    veterans: 'veterans.html',
    'privacy-policy': 'privacy-policy.html',
    'company-policy': 'company-policy.html',
    'cookie-policy': 'cookie-policy.html'
  };

  const $ = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

  const esc = (value) => String(value === null || value === undefined ? '' : value)
    .replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));

  const stripTags = (html) => String(html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();

  const ruDate = (iso) => {
    if (!iso) return '';
    const date = new Date(String(iso).length > 10 ? iso : iso + 'T00:00:00');
    return isNaN(date) ? String(iso) : date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
  };

  const ruDateLong = (iso) => {
    if (!iso) return '';
    const date = new Date(String(iso).length > 10 ? iso : iso + 'T00:00:00');
    if (isNaN(date)) return String(iso);
    return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' });
  };

  const ruDateTime = (value) => {
    if (!value) return '';
    const date = new Date(value);
    if (isNaN(date)) return String(value);
    return date.toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  };

  const todayISO = () => {
    const d = new Date();
    const tz = d.getTimezoneOffset() * 60000;
    return new Date(d.getTime() - tz).toISOString().slice(0, 10);
  };

  const uid = (prefix) => (prefix || 'id') + '_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);

  const slugify = (text) => String(text || '')
    .toLowerCase()
    .replace(/[а-яё]/g, (ch) => ({ 'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'e', 'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm', 'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u', 'ф': 'f', 'х': 'h', 'ц': 'c', 'ч': 'ch', 'ш': 'sh', 'щ': 'sch', 'ъ': '', 'ы': 'y', 'ь': '', 'э': 'e', 'ю': 'yu', 'я': 'ya' }[ch] || ''))
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 80);

  const plural = (num, forms) => {
    const n = Math.abs(Number(num)) % 100;
    const n1 = n % 10;
    if (n > 10 && n < 20) return forms[2];
    if (n1 > 1 && n1 < 5) return forms[1];
    if (n1 === 1) return forms[0];
    return forms[2];
  };

  const phoneHref = (value) => String(value || '').replace(/[^0-9+]/g, '');

  const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(value || '').trim());

  const clone = (value) => JSON.parse(JSON.stringify(value));

  const truncate = (text, length) => {
    const src = String(text || '').replace(/\s+/g, ' ').trim();
    return src.length > length ? src.slice(0, length - 1).replace(/\s+\S*$/, '') + '…' : src;
  };

  /* =========================================================
     Данные по умолчанию
     ========================================================= */
  const DEFAULT_DATA = clone(global.VGS_CONTENT || {
    settings: {}, news: [], docs: [], divisions: [], command: [], phones: [], pages: [], gallery: [], albums: [], milestones: [], zone: [], feedback: []
  });

  if (!DEFAULT_DATA.settings) DEFAULT_DATA.settings = {};
  if (!Array.isArray(DEFAULT_DATA.pages)) DEFAULT_DATA.pages = [];
  if (!Array.isArray(DEFAULT_DATA.feedback)) DEFAULT_DATA.feedback = [];

  function normalizeData(raw) {
    const data = raw && typeof raw === 'object' ? raw : clone(DEFAULT_DATA);
    const defs = DEFAULT_DATA;

    data.settings = Object.assign({}, defs.settings, data.settings || {});
    data.settings.stats = Array.isArray(data.settings.stats) && data.settings.stats.length ? data.settings.stats : defs.settings.stats;
    data.settings.links = Object.assign({}, defs.settings.links, data.settings.links || {});

    const arrays = ['news', 'docs', 'divisions', 'command', 'phones', 'pages', 'gallery', 'albums', 'milestones', 'zone', 'feedback'];
    arrays.forEach((key) => {
      if (!Array.isArray(data[key])) data[key] = clone(defs[key] || []);
    });
    if (!Array.isArray(data.newsCategories) || !data.newsCategories.length) data.newsCategories = clone(defs.newsCategories);
    if (!Array.isArray(data.docCategories) || !data.docCategories.length) data.docCategories = clone(defs.docCategories);

    data.news.forEach((item) => {
      item.id = String(item.id || uid('n'));
      item.published = item.published === 0 || item.published === false ? 0 : 1;
      item.featured = item.featured ? 1 : 0;
      item.category = item.category || 'События';
      item.excerpt = item.excerpt || '';
      item.body = item.body || '';
      item.image = item.image || 'photo-07';
      item.tags = Array.isArray(item.tags) ? item.tags : [];
    });

    data.docs.forEach((item) => {
      item.id = String(item.id || uid('d'));
      item.published = item.published === 0 || item.published === false ? 0 : 1;
      item.category = item.category || 'Нормативные акты';
    });

    data.divisions.forEach((item) => {
      item.id = String(item.id || uid('v'));
      item.published = item.published === 0 || item.published === false ? 0 : 1;
    });

    data.command.forEach((item) => {
      item.id = String(item.id || uid('c'));
      item.published = item.published === 0 || item.published === false ? 0 : 1;
    });

    data.phones.forEach((item) => {
      item.id = String(item.id || uid('p'));
      item.published = item.published === 0 || item.published === false ? 0 : 1;
    });

    data.gallery.forEach((item) => {
      item.id = String(item.id || uid('g'));
      item.published = item.published === 0 || item.published === false ? 0 : 1;
      item.type = item.type === 'video' ? 'video' : 'photo';
      item.image = item.image || item.poster || 'photo-01';
    });

    // Юридические и информационные страницы не удаляются, их можно только снять с публикации.
    const defaultsPages = clone(defs.pages || []);
    const storedPages = data.pages.slice();
    const mergedPages = defaultsPages.map((defPage) => {
      const found = storedPages.find((p) => p.id === defPage.id || p.slug === defPage.slug);
      return Object.assign({}, defPage, found || {});
    });
    storedPages.forEach((p) => {
      if (!mergedPages.some((m) => m.id === p.id || m.slug === p.slug)) mergedPages.push(p);
    });
    data.pages = mergedPages;

    data.schema = defs.schema || 1;
    return data;
  }

  function loadData() {
    try {
      const raw = localStorage.getItem(LS_DATA);
      if (raw) {
        const parsed = JSON.parse(raw);
        if (parsed && parsed.settings) return normalizeData(parsed);
      }
    } catch (error) {
      // повреждённые данные — возвращаем исходный контент
    }
    return normalizeData(clone(DEFAULT_DATA));
  }

  function saveData(data) {
    try {
      localStorage.setItem(LS_DATA, JSON.stringify(data));
      return true;
    } catch (error) {
      return false;
    }
  }

  function resetData() {
    const fresh = normalizeData(clone(DEFAULT_DATA));
    saveData(fresh);
    return fresh;
  }

  function dataSize() {
    try {
      return (localStorage.getItem(LS_DATA) || '').length;
    } catch (error) {
      return 0;
    }
  }

  /* =========================================================
     Пользователи и пароли
     ========================================================= */
  function bufToB64(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
    return btoa(binary);
  }

  function b64ToBuf(b64) {
    const binary = atob(b64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return bytes;
  }

  function randomSalt(bytes) {
    const salt = new Uint8Array(bytes || SALT_BYTES);
    crypto.getRandomValues(salt);
    return salt;
  }

  function safeEqual(a, b) {
    if (typeof a !== 'string' || typeof b !== 'string' || a.length !== b.length) return false;
    let diff = 0;
    for (let i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
    return diff === 0;
  }

  async function pbkdf2(password, saltBytes, iterations) {
    if (!global.crypto || !global.crypto.subtle) {
      throw new Error('Web Crypto недоступен. Откройте сайт по HTTPS или через http://localhost.');
    }
    const keyMaterial = await crypto.subtle.importKey('raw', new TextEncoder().encode(password), 'PBKDF2', false, ['deriveBits']);
    const bits = await crypto.subtle.deriveBits(
      { name: 'PBKDF2', salt: saltBytes, iterations, hash: PBKDF2_HASH },
      keyMaterial,
      PBKDF2_KEYLEN * 8
    );
    return bufToB64(bits);
  }

  async function hashPassword(password, iterations) {
    if (typeof password !== 'string' || !password.length) throw new Error('Пустой пароль');
    const saltBytes = randomSalt();
    const hash = await pbkdf2(password, saltBytes, iterations || PBKDF2_ITERATIONS);
    return {
      algo: 'PBKDF2-' + PBKDF2_HASH,
      iterations: iterations || PBKDF2_ITERATIONS,
      salt: bufToB64(saltBytes),
      hash,
      updatedAt: new Date().toISOString()
    };
  }

  async function applyPassword(user, password, iterations) {
    if (!user) throw new Error('Нет пользователя');
    const h = await hashPassword(password, iterations);
    user.passwordAlgo = h.algo;
    user.passwordIterations = h.iterations;
    user.passwordSalt = h.salt;
    user.passwordHash = h.hash;
    user.passwordUpdatedAt = h.updatedAt;
    return user;
  }

  async function verifyPassword(password, user) {
    if (!user || !user.passwordSalt || !user.passwordIterations || !user.passwordHash) return { ok: false };
    try {
      const computed = await pbkdf2(password, b64ToBuf(user.passwordSalt), user.passwordIterations);
      const ok = safeEqual(computed, user.passwordHash);
      return { ok, needsRehash: ok && user.passwordIterations < PBKDF2_ITERATIONS };
    } catch (error) {
      return { ok: false };
    }
  }

  async function upgradeUserPassword(userId, password) {
    const users = loadUsers();
    const index = users.findIndex((user) => user.id === userId);
    if (index === -1) return false;
    await applyPassword(users[index], password);
    return saveUsers(users);
  }

  function loadUsers() {
    try {
      const raw = localStorage.getItem(LS_USERS);
      const list = raw ? JSON.parse(raw) : [];
      return Array.isArray(list) ? list : [];
    } catch (error) {
      return [];
    }
  }

  function saveUsers(list) {
    try {
      localStorage.setItem(LS_USERS, JSON.stringify(list));
      return true;
    } catch (error) {
      return false;
    }
  }

  function hasAnyUser() {
    const users = loadUsers();
    return users.some((user) => user && typeof user.login === 'string' && user.login.trim() && typeof user.passwordHash === 'string' && user.passwordHash.length);
  }

  function setSession(payload) {
    try { sessionStorage.setItem(SESSION_KEY, JSON.stringify(payload)); } catch (error) { /* noop */ }
  }

  function clearSession() {
    try { sessionStorage.removeItem(SESSION_KEY); } catch (error) { /* noop */ }
  }

  function getCurrentUser() {
    try {
      const raw = sessionStorage.getItem(SESSION_KEY);
      if (!raw) return null;
      const session = JSON.parse(raw);
      if (!session || !session.id) return null;
      return loadUsers().find((user) => user.id === session.id) || null;
    } catch (error) {
      return null;
    }
  }

  function can(action) {
    const user = getCurrentUser();
    if (!user) return false;
    const role = ROLES[user.role];
    return !!(role && role.can[action]);
  }

  function readThrottleMap() {
    try {
      const raw = sessionStorage.getItem(THROTTLE_KEY);
      return raw ? JSON.parse(raw) : {};
    } catch (error) {
      return {};
    }
  }

  function writeThrottleMap(map) {
    try { sessionStorage.setItem(THROTTLE_KEY, JSON.stringify(map)); } catch (error) { /* noop */ }
  }

  function getThrottleState(login) {
    const map = readThrottleMap();
    const key = String(login || '').toLowerCase();
    const state = map[key] || { fails: 0, firstAt: 0, lockedUntil: 0 };
    if (state.lockedUntil && Date.now() > state.lockedUntil) {
      state.fails = 0;
      state.firstAt = 0;
      state.lockedUntil = 0;
    }
    return state;
  }

  function isLoginLocked(login) {
    return getThrottleState(login).lockedUntil > Date.now();
  }

  function getThrottleRemainingMs(login) {
    return Math.max(0, getThrottleState(login).lockedUntil - Date.now());
  }

  function recordLoginFailure(login) {
    const map = readThrottleMap();
    const key = String(login || '').toLowerCase();
    const state = map[key] || { fails: 0, firstAt: 0, lockedUntil: 0 };
    const now = Date.now();
    if (!state.firstAt || now - state.firstAt > THROTTLE_WINDOW_MS) {
      state.fails = 0;
      state.firstAt = now;
    }
    state.fails += 1;
    if (state.fails >= THROTTLE_MAX_ATTEMPTS) state.lockedUntil = now + THROTTLE_LOCK_MS;
    map[key] = state;
    writeThrottleMap(map);
    return state;
  }

  function resetLoginThrottle(login) {
    const map = readThrottleMap();
    delete map[String(login || '').toLowerCase()];
    writeThrottleMap(map);
  }

  /* =========================================================
     Cookie-согласия
     ========================================================= */
  function getConsent() {
    try {
      const raw = localStorage.getItem(CONSENT_KEY);
      if (!raw) return null;
      const record = JSON.parse(raw);
      if (!record || !record.categories) return null;
      const timestamp = new Date(record.timestamp || 0).getTime();
      if (!timestamp || Date.now() - timestamp > 365 * 24 * 60 * 60 * 1000) return null;
      return record;
    } catch (error) {
      return null;
    }
  }

  function hasConsent() {
    return !!getConsent();
  }

  function logConsent(record) {
    try {
      const raw = localStorage.getItem(CONSENT_LOG_KEY);
      const list = raw ? JSON.parse(raw) : [];
      const safeList = Array.isArray(list) ? list : [];
      safeList.unshift(record);
      localStorage.setItem(CONSENT_LOG_KEY, JSON.stringify(safeList.slice(0, 200)));
    } catch (error) {
      // журнал не критичен для работы сайта
    }
  }

  function saveConsent(categories, method, source) {
    const record = {
      id: uid('consent'),
      version: CONSENT_VERSION,
      timestamp: new Date().toISOString(),
      categories: {
        necessary: true,
        functional: !!categories.functional,
        analytics: !!categories.analytics,
        marketing: !!categories.marketing
      },
      method: method || 'custom',
      source: source || 'banner',
      page: location.pathname + location.search
    };
    try {
      localStorage.setItem(CONSENT_KEY, JSON.stringify(record));
    } catch (error) {
      return null;
    }
    logConsent(record);
    return record;
  }

  function getConsentLog() {
    try {
      const raw = localStorage.getItem(CONSENT_LOG_KEY);
      const list = raw ? JSON.parse(raw) : [];
      return Array.isArray(list) ? list : [];
    } catch (error) {
      return [];
    }
  }

  function clearConsent() {
    try {
      localStorage.removeItem(CONSENT_KEY);
      return true;
    } catch (error) {
      return false;
    }
  }

  /* =========================================================
     Панель доступности
     ========================================================= */
  const A11Y_DEFAULTS = { font: 100, contrast: 'normal', links: false, cursor: false, motion: false, spacing: false };

  function getA11y() {
    try {
      const raw = localStorage.getItem(A11Y_KEY);
      const settings = raw ? JSON.parse(raw) : null;
      return Object.assign({}, A11Y_DEFAULTS, settings || {});
    } catch (error) {
      return Object.assign({}, A11Y_DEFAULTS);
    }
  }

  function saveA11y(settings) {
    try {
      localStorage.setItem(A11Y_KEY, JSON.stringify(Object.assign({}, A11Y_DEFAULTS, settings)));
      return true;
    } catch (error) {
      return false;
    }
  }

  /* =========================================================
     Журнал действий
     ========================================================= */
  function logAction(action, details, actor) {
    try {
      const user = actor || getCurrentUser();
      const data = loadData();
      if (!Array.isArray(data.auditLog)) data.auditLog = [];
      data.auditLog.unshift({
        id: uid('log'),
        timestamp: new Date().toISOString(),
        userId: user ? user.id : '',
        userName: user ? user.name : 'Система',
        action: action,
        details: details || ''
      });
      data.auditLog = data.auditLog.slice(0, 500);
      saveData(data);
      return true;
    } catch (error) {
      return false;
    }
  }

  function getAuditLog() {
    const data = loadData();
    return Array.isArray(data.auditLog) ? data.auditLog : [];
  }

  /* =========================================================
     Обратная связь
     ========================================================= */
  function addFeedback(entry) {
    try {
      const data = loadData();
      if (!Array.isArray(data.feedback)) data.feedback = [];
      const item = Object.assign({
        id: uid('msg'),
        createdAt: new Date().toISOString(),
        status: 'new'
      }, entry);
      data.feedback.unshift(item);
      saveData(data);
      return item;
    } catch (error) {
      return null;
    }
  }

  function getFeedback() {
    return loadData().feedback || [];
  }

  /* =========================================================
     Поиск по сайту
     ========================================================= */
  function normalizeQuery(text) {
    return String(text || '').toLowerCase().replace(/ё/g, 'е').replace(/[^\p{L}\p{N}\s]+/gu, ' ').replace(/\s+/g, ' ').trim();
  }

  const WORD_SUFFIX = /(иями|ями|ами|ов|ев|ий|ый|ая|ое|ые|ия|ии|ию|ей|ой|ам|ям|ах|ях|ом|ем|ы|а|я|и|у|ю|е|о|ь)$/u;

  function stemToken(token) {
    const word = String(token || '').toLowerCase().replace(/ё/g, 'е');
    if (word.length <= 4) return word;
    return word.replace(WORD_SUFFIX, '') || word;
  }

  function words(text) {
    return normalizeQuery(text).split(' ').filter(Boolean);
  }

  function highlight(text, query) {
    const safe = esc(text);
    const q = normalizeQuery(query);
    if (!q) return safe;
    const tokens = q.split(' ').filter((token) => token.length > 1);
    if (!tokens.length) return safe;
    const patterns = tokens.map((token) => {
      const base = token.length <= 3 ? token : stemToken(token);
      return '(?<![\\p{L}\\p{N}])' + base.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '[\\p{L}\\p{N}]*';
    });
    try {
      return safe.replace(new RegExp('(' + patterns.join('|') + ')', 'giu'), '<mark>$1</mark>');
    } catch (error) {
      return safe;
    }
  }

  function pageUrl(page) {
    const file = PAGE_FILES[page.slug] || (page.slug + '.html');
    return file;
  }

  function search(query, limit) {
    const q = normalizeQuery(query);
    if (!q) return [];
    const tokens = q.split(' ').filter((token) => token.length > 1);
    if (!tokens.length) return [];
    const data = loadData();
    const settings = data.settings;
    const items = [];

    (data.news || []).forEach((item) => {
      if (!item.published) return;
      items.push({
        type: 'news',
        typeLabel: 'Новость',
        title: item.title,
        text: [item.excerpt, stripTags(item.body), item.category, (item.tags || []).join(' ')].join(' '),
        date: item.date,
        url: 'article.html?id=' + encodeURIComponent(item.id),
        badge: item.category
      });
    });

    (data.docs || []).forEach((item) => {
      if (!item.published) return;
      items.push({
        type: 'doc',
        typeLabel: 'Документ',
        title: item.title,
        text: [item.note, item.category].join(' '),
        date: item.date,
        url: 'documents.html#' + encodeURIComponent(item.id),
        badge: item.category
      });
    });

    (data.pages || []).forEach((page) => {
      if (!page.published) return;
      items.push({
        type: 'page',
        typeLabel: 'Страница',
        title: page.title,
        text: [page.lead, stripTags(page.bodyHtml)].join(' '),
        date: page.updatedAt,
        url: pageUrl(page),
        badge: 'Раздел сайта'
      });
    });

    (data.divisions || []).forEach((item) => {
      if (!item.published) return;
      items.push({
        type: 'division',
        typeLabel: 'Подразделение',
        title: item.name,
        text: [item.city, item.address, item.description].join(' '),
        date: '',
        url: 'structure.html#' + encodeURIComponent(item.id),
        badge: item.city
      });
    });

    (data.gallery || []).forEach((item) => {
      if (!item.published) return;
      items.push({
        type: 'gallery',
        typeLabel: item.type === 'video' ? 'Видео' : 'Фотография',
        title: item.title,
        text: [item.caption, item.date].join(' '),
        date: item.date,
        url: 'gallery.html?photo=' + encodeURIComponent(item.id),
        badge: 'Фотогалерея'
      });
    });

    // контакты и общие сведения
    items.push({
      type: 'page',
      typeLabel: 'Страница',
      title: 'Контакты',
      text: [settings.address, settings.phone_duty, settings.phone_office, settings.email, settings.hours].join(' '),
      date: '',
      url: 'contacts.html',
      badge: 'Контакты'
    });

    const scored = items.map((item) => {
      const titleWords = words(item.title);
      const textWords = words(item.text);
      const titleText = titleWords.join(' ');
      const bodyText = textWords.join(' ');
      let score = 0;
      let matched = 0;
      tokens.forEach((token) => {
        const base = token.length <= 3 ? token : stemToken(token);
        const titleHit = titleWords.some((word) => word.startsWith(base));
        const textHit = textWords.some((word) => word.startsWith(base));
        let tokenScore = 0;
        if (titleHit) tokenScore += 12;
        if (textHit) tokenScore += 3;
        if (tokenScore > 0) matched += 1;
        score += tokenScore;
      });
      if (matched === 0) return null;
      if (matched < tokens.length && tokens.length > 1) score *= 0.6;
      if (titleText === q) score += 30;
      if (titleText.indexOf(q) !== -1) score += 10;
      if (bodyText.indexOf(q) !== -1) score += 2;
      return Object.assign({}, item, { score });
    }).filter(Boolean).sort((a, b) => b.score - a.score || String(b.date || '').localeCompare(String(a.date || '')));

    return typeof limit === 'number' ? scored.slice(0, limit) : scored;
  }

  /* =========================================================
     Вспомогательные функции для страниц
     ========================================================= */
  function imagePath(image, size) {
    return 'assets/img/' + image + '-' + (size || 700) + '.webp';
  }

  function pageBySlug(slug) {
    return loadData().pages.find((page) => page.slug === slug) || null;
  }

  function newsById(id) {
    return loadData().news.find((item) => item.id === String(id)) || null;
  }

  function divisionById(id) {
    return loadData().divisions.find((item) => item.id === String(id)) || null;
  }

  global.VGS = {
    LS_DATA, LS_USERS, SESSION_KEY, CONSENT_KEY, CONSENT_LOG_KEY, A11Y_KEY,
    CONSENT_VERSION, PBKDF2_ITERATIONS,
    ROLES, CATEGORY_LABELS, PAGE_FILES, A11Y_DEFAULTS,
    DEFAULT_DATA,
    $, $$, esc, stripTags, ruDate, ruDateLong, ruDateTime, todayISO,
    uid, slugify, plural, phoneHref, isValidEmail, clone, truncate,
    normalizeQuery, stemToken, highlight, words,
    normalizeData, loadData, saveData, resetData, dataSize,
    loadUsers, saveUsers, hasAnyUser,
    hashPassword, applyPassword, verifyPassword, upgradeUserPassword,
    setSession, clearSession, getCurrentUser, can,
    isLoginLocked, getThrottleRemainingMs, recordLoginFailure, resetLoginThrottle,
    getConsent, hasConsent, saveConsent, getConsentLog, clearConsent, logConsent,
    getA11y, saveA11y,
    logAction, getAuditLog,
    addFeedback, getFeedback,
    search, pageUrl, pageBySlug, newsById, divisionById, imagePath
  };
})(window);
