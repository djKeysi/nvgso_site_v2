# Перенос прототипа на WordPress с локальной MariaDB

Обновлённая редакция под новое ТЗ: **один сервер**, на нём одновременно
WordPress, **локальная MariaDB 10.6+ (рекомендуется 10.11 LTS)** и изображения
в `wp-content/uploads/`. Облачные БД, R2/S3 и отдельный сервер изображений
не используются. У сервера один белый (статический публичный) IP.

> Установка пакетов, MariaDB, Nginx и HTTPS — в
> [`SERVER-SETUP.md`](SERVER-SETUP.md). Этот документ — про перенос дизайна,
> структуры и контента в WordPress.

## 1. Целевая архитектура

```text
Пользователь
   │ HTTPS (TLS 1.2+)
   ▼
Nginx 1.31.x  ──►  PHP-FPM 8.2+
   │                    │
   │                    ├── WordPress (self-hosted)
   │                    │      └── MariaDB 10.11 LTS
   │                    │          bind-address = 127.0.0.1
   │                    │          DB_HOST = localhost (Unix-сокет)
   │                    │
   │                    └── wp-content/uploads/  (изображения и PDF)
   │                          отдаются Nginx напрямую, Cache-Control 30 дней
   ▼
один белый IP: 46.149.228.111 (после DNS — домен)
```

Ключевые ограничения нового ТЗ:

- MariaDB только локально, порт 3306 не доступен из интернета;
- WordPress подключается к БД через `localhost`/Unix-сокет;
- изображения — только на диске сервера, без R2/S3;
- Nginx отдаёт uploads без PHP и с кэшированием;
- резервное копирование MariaDB и uploads — на собственное внешнее хранилище.

## 2. Что значит «подключить этот сайт к WordPress»

Сейчас `site/` — **статический HTML/CSS/JS-прототип**. Он не является
WordPress-темой и не использует базу данных: контент лежит в
`localStorage`. Просто «привязать» папку к WordPress нельзя — WordPress
не выполняет произвольный HTML как тему.

Есть три пути:

| Путь | Что делается | Пригодность |
|---|---|---|
| **A. Тема WordPress (рекомендуется)** | Дизайн и разметка переносятся в дочернюю тему; контент — в записи/страницы/CPT; БД — MariaDB | Полностью соответствует ТЗ |
| B. Статический сайт + WordPress отдельно | WordPress ставится в `/wp` или поддомен, статический сайт остаётся как есть | Не соответствует п. 4.2 и 10 ТЗ |
| C. Вставить готовый HTML в страницу WordPress | Один шаблон с HTML, без динамики и админки | Только для быстрой демонстрации |

Ниже — путь A.

## 3. Соответствие файлов прототипа шаблонам темы

| Прототип | WordPress |
|---|---|
| `index.html` + `pages.js` (home) | `front-page.php` |
| `about.html` | `page-about.php` или `page.php` + ACF |
| `structure.html` | `page-structure.php` |
| `activity.html` | `page-activity.php` |
| `news.html` | `archive-news.php` + `taxonomy-news_category.php` |
| `article.html?id=…` | `single-news.php` |
| `documents.html` | `page-documents.php` + CPT `document` |
| `gallery.html` | `page-gallery.php` + CPT `gallery_item` |
| `contacts.html` | `page-contacts.php` + плагин форм |
| `veterans.html` | `page-veterans.php` |
| `privacy-policy.html` | страница с шаблоном `page-legal.php` |
| `company-policy.html` | страница с шаблоном `page-legal.php` |
| `cookie-policy.html` | страница с шаблоном `page-legal.php` |
| `search.html` | `search.php` + `searchform.php` |
| `404.html` | `404.php` |
| `admin.html` + `admin.js` | `/wp-admin` + настройки темы/ACF |
| `css/public.css` | `assets/css/public.css` темы |
| `js/site.js`, `js/pages.js` | `assets/js/main.js` без localStorage-логики |
| `js/content.js` | импорт в CPT/ACF |
| `assets/img`, `assets/docs` | медиатека WordPress (`uploads/`) |

### Что переносится как есть

- `css/public.css` — дизайн-система, адаптивность, режим доступности;
- изображения из `assets/img` — загружаются в медиатеку;
- PDF из `assets/docs` — загружаются в медиатеку и привязываются к CPT;
- SVG-иконки — можно оставить в разметке темы;
- юридические тексты — переносятся в страницы WordPress.

### Что переписывается на PHP

- шапка, подвал, меню, хлебные крошки — `header.php`, `footer.php`,
  `functions.php`;
- списки новостей/документов/галереи — `WP_Query`;
- формы — плагин форм + чекбокс согласия;
- cookie-баннер и панель доступности — плагины из ТЗ;
- поиск — `WP_Query`/Relevanssi;
- админка — штатная `/wp-admin` + ACF.

JS-прототип (`store.js`, `admin.js`, `pages.js`) **не используется** в
production: данные должны идти из WP, а не из `localStorage`.

## 4. Структура дочерней темы

```text
wp-content/themes/nvgso/
├── style.css                 # заголовок темы + импорт/переменные
├── functions.php             # меню, CPT, таксономии, enqueue, ACF
├── header.php
├── footer.php
├── front-page.php
├── page-about.php
├── page-structure.php
├── page-activity.php
├── page-contacts.php
├── page-documents.php
├── page-gallery.php
├── page-veterans.php
├── page-legal.php
├── archive-news.php
├── single-news.php
├── taxonomy-news_category.php
├── search.php
├── 404.php
└── assets/
    ├── css/public.css
    ├── js/main.js
    └── img/…
```

`style.css` — обязательный заголовок:

```css
/*
Theme Name: NVGSO
Template:   twentytwentyfour
Version:    1.0.0
Text Domain: nvgso
*/
```

Если тема делается как самостоятельная, `Template` не указывается.

`functions.php` — минимум:

```php
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption']);
    register_nav_menus([
        'primary' => 'Основное меню',
        'footer'  => 'Меню в подвале',
    ]);
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('nvgso', get_stylesheet_uri(), [], '1.0.0');
    wp_enqueue_style('nvgso-main', get_theme_file_uri('assets/css/public.css'), [], '1.0.0');
    wp_enqueue_script('nvgso-main', get_theme_file_uri('assets/js/main.js'), [], '1.0.0', true);
});
```

## 5. Типы записей и поля

### Записи и таксономии

- `news` — новости. Поля: дата, категория (таксономия), краткое описание,
  текст, изображение, «главная новость», статус.
- `document` — документы. Поля: файл (ACF File), категория (таксономия),
  дата, количество страниц, примечание.
- `division` — подразделения. Поля: тип, город, адрес, телефон, состав,
  описание, порядок.
- `gallery_item` — медиаматериалы. Поля: альбом, тип (фото/видео), изображение,
  URL видео, подпись, дата.
- `staff_contact` — справочник (или ACF Repeater в настройках).

Таксономии: `news_category`, `doc_category`, `album`.

### ACF Options

- организация: краткое и полное наименование, головная организация, год основания;
- контакты: адрес, телефоны, email, режим работы, карта;
- главная: заголовок, подзаголовок, метка, показатели (repeater);
- тексты разделов;
- реквизиты и ссылки;
- ID страниц политик;
- настройки cookie/доступности.

## 6. Подключение к MariaDB

WordPress не требует отдельного «коннектора»: параметры задаются в
`wp-config.php`. Для локальной MariaDB:

```php
define('DB_NAME', 'nvgso_wp');
define('DB_USER', 'nvgso_wp');
define('DB_PASSWORD', 'ПАРОЛЬ_ИЗ_МЕНЕДЖЕРА_СЕКРЕТОВ');
define('DB_HOST', 'localhost');      // Unix-сокет, не 127.0.0.1
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_unicode_ci');

define('WP_HOME', 'https://nvgso.vgsch.ru');
define('WP_SITEURL', 'https://nvgso.vgsch.ru');
define('FS_METHOD', 'direct');
define('DISALLOW_FILE_EDIT', true);
```

Требования нового ТЗ:

- MariaDB 10.6+ (10.11 LTS), кодировка `utf8mb4_unicode_ci`, InnoDB;
- `bind-address = 127.0.0.1` (или приватный IP, если нужен внешний доступ);
- порт 3306 закрыт файрволом для интернета;
- отдельный пользователь `nvgso_wp` с правами только на свою БД;
- пароль ≥ 20 символов, `wp-config.php` с правами 600;
- TLS 1.2+ для любых сетевых подключений (локально — Unix-сокет);
- `require_secure_transport = ON`, если разрешены TCP-подключения с других хостов.

Проверка:

```bash
wp db check
wp db query "SELECT VERSION(), @@character_set_database, @@collation_database;"
wp option get siteurl
```

Подробные команды создания БД и пользователя — в `SERVER-SETUP.md`, раздел 3.

## 7. Изображения и Nginx

- Изображения загружаются в медиатеку WordPress → `wp-content/uploads/`.
- Отдаются с основного домена: `https://domain/wp-content/uploads/...`.
- Nginx отдаёт их напрямую, без PHP, с `Cache-Control: public, max-age=2592000`.
- Выполнение PHP в uploads запрещено.
- `EWWW Image Optimizer` (или ShortPixel) сжимает, конвертирует в WebP/AVIF,
  генерирует адаптивные размеры.
- WordPress сам формирует `srcset`/`sizes`; для изображений ниже первого
  экрана используется `loading="lazy"`.
- Имена файлов — латиница, без пробелов, с датой и смыслом:
  `2026-ucheniya-novokuznetsk-01.webp`.
- Дисковая квота — не менее 20 ГБ, лучше NVMe/SSD.

Пример Nginx — в `SERVER-SETUP.md`, раздел 5.

## 8. Плагины (обновлённый ТЗ)

| Задача | Плагин |
|---|---|
| Доступность | Oksigenia Access / WP Accessibility Helper |
| Cookie и 152-ФЗ | FZ-152 RF / ConsentOwl / RuCookie & 152-ФЗ |
| Оптимизация изображений | EWWW Image Optimizer / ShortPixel |
| Ленивая загрузка и srcset | встроено в WordPress + настройка |
| SEO | Yoast SEO |
| Кэш | WP Super Cache / LiteSpeed Cache |
| Бэкап | UpdraftPlus |
| Безопасность | Wordfence Security |

Плагин cookie-согласия должен быть совместим с кэшированием и формами;
конфликты проверяются на тестовом стенде.

## 9. Перенос контента

1. Создать страницы: «Об отряде», «Структура», «Деятельность», «Документы»,
   «Фотогалерея», «Контакты», «Ветеранам и память», три политики.
2. Создать меню «Основное» и «Подвал», привязать к `primary`/`footer`.
3. Импортировать новости (12 демо-записей или реальные) в `news`,
   назначить категории, даты, изображения.
4. Импортировать документы в `document`, прикрепить PDF из `assets/docs`.
5. Загрузить фотографии в медиатеку, распределить по альбомам, создать
   `gallery_item`.
6. Заполнить справочник и подразделения.
7. Заполнить ACF Options (контакты, тексты, показатели).
8. Проверить, что все ссылки на политики ведут на страницы WordPress.

Для массового импорта удобны WP All Import (CSV/XML) или WP-CLI:

```bash
wp post create --post_type=page --post_title='Об отряде' --post_status=publish
wp media import /path/to/photo.webp --title='Учения'
```

## 10. Формы, cookie, доступность, SEO

- Contact Form 7/WPForms: обязательный чекбокс согласия со ссылкой на
  Политику ПДн; серверная валидация; антиспам.
- Cookie-плагин: opt-in, «Принять все»/«Только необходимые»/«Настроить»,
  журнал согласий, блокировка аналитики до согласия.
- Панель доступности: масштаб 200 %, контраст, отключение анимации.
- PDF: по возможности тегированные по ГОСТ Р 70176-2022 или HTML-дубли.
- Yoast: title/description, canonical, sitemap, OG.
- Постоянные ссылки: `/privacy-policy/`, `/company-policy/`, `/cookie-policy/`,
  `/news/`, `/documents/`.
- 301-редиректы со старых URL статического сайта.

## 11. Порядок работ

1. Резервная копия текущего статического сайта и конфигов Nginx.
2. Установка MariaDB 10.11, создание БД/пользователя (см. `SERVER-SETUP.md`).
3. Установка WordPress, `wp-config.php`, HTTPS.
4. Дочерняя тема, перенос CSS/ассетов, шаблоны.
5. CPT/ACF/меню, импорт контента и PDF.
6. Плагины и настройки.
7. Тестирование: 200 %, cookie, формы, поиск, мобильные, нагрузка.
8. Бэкапы MariaDB и uploads, тестовое восстановление.
9. Переключение DNS/домена, финальная проверка.

## 12. Резервное копирование

- Ежедневный `mariadb-dump --single-transaction --routines --triggers`,
  хранение ≥ 30 дней, копия вне сервера с СУБД.
- Дополнительно `mariabackup` для быстрого восстановления больших баз.
- Ежедневный инкрементный бэкап `wp-content/uploads/`, хранение ≥ 30 дней.
- UpdraftPlus — дополнительная копия БД и файлов на собственное хранилище.
- Проверка восстановления — не реже раза в месяц.
- Репликация в базовой конфигурации не требуется.

Команды — в `SERVER-SETUP.md`, раздел 8.

## 13. Чек-лист приёмки

- [ ] WordPress self-hosted, хостинг из перечня Правительства РФ.
- [ ] MariaDB 10.6+ локально, `DB_HOST=localhost`, InnoDB, utf8mb4.
- [ ] Порт 3306 закрыт из интернета, `bind-address` не `0.0.0.0`.
- [ ] Отдельный пользователь БД, права только на свою базу, пароль ≥ 20.
- [ ] Изображения в `wp-content/uploads/`, отдаются Nginx с кэшем 30 дней.
- [ ] PHP в uploads запрещён.
- [ ] EWWW/ShortPixel, WebP/AVIF, srcset, lazy.
- [ ] Cookie opt-in + 3 кнопки + журнал.
- [ ] Политики на постоянных URL, ссылки в футере и у форм.
- [ ] Доступность 200 %, PDF/альтернативы.
- [ ] Бэкапы MariaDB + uploads ≥ 30 дней, тест восстановления.
- [ ] Переданы доступы (хостинг, SSH, MariaDB, белый IP, WP, cookie-плагин)
      и руководства администратора/редактора.

## 14. Что нужно от Заказчика

1. Домен и доступ к DNS.
2. Доступ по SSH к серверу 46.149.228.111 (или панель хостинга).
3. Подтверждение ОС и параметров сервера (4 vCPU, 8 ГБ RAM, 100 ГБ SSD).
4. Утверждённые тексты Политик и Политики компании.
5. Актуальные телефоны, email, ФИО, реквизиты.
6. Оригиналы лицензий/свидетельств и PDF.
7. Фотографии и видео с подтверждением прав.
8. Список обслуживаемых предприятий и разрешённые к публикации сведения.
