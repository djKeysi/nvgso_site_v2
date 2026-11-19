# Установка сервера: MariaDB, WordPress, Nginx, HTTPS

Практическая инструкция для текущего сервера **46.149.228.111** (Nginx уже
отвечает, статический сайт выложен). Цель — привести хостинг к новой редакции
ТЗ: один сервер, локальная MariaDB 10.6+ (10.11 LTS), изображения в
`wp-content/uploads/`, один белый IP, HTTPS.

> Команды приведены для Debian/Ubuntu. Проверьте ОС:
> `cat /etc/os-release`. Если это CentOS/AlmaLinux/ISPmanager — пакеты и пути
> будут другими, но логика та же. Все команды выполняются по SSH с `sudo`.
> Перед началом сделайте резервную копию статического сайта и конфигов Nginx.

## 0. Проверка сервера и резервная копия

```bash
cat /etc/os-release
nproc
free -h
df -h
nginx -v
php -v 2>/dev/null || true
mariadb --version 2>/dev/null || true
ss -lntp | grep -E ':(80|443|3306)\b' || true
```

Требования ТЗ к серверу: не менее **4 vCPU, 8 ГБ RAM, 100 ГБ SSD/NVMe**,
квота под изображения — не менее 20 ГБ.

Резервная копия:

```bash
sudo mkdir -p /root/backup-$(date +%F)
sudo cp -a /etc/nginx /root/backup-$(date +%F)/nginx
sudo tar -czf /root/backup-$(date +%F)/site-static.tar.gz -C /var/www .
```

Узнайте текущий корень сайта:

```bash
nginx -T | grep -E 'server_name|root ' | head -20
```

Если статический сайт лежит в `/var/www/html`, **не удаляйте и не
перемещайте его сразу**: он останется рабочим, пока WordPress настраивается
в отдельном каталоге `/var/www/nvgso` на временном порту 8080. После проверки
переключите production-блок Nginx на WordPress.

Сделайте копию:

```bash
sudo cp -a /var/www/html /var/www/nvgso-static
```

Если места на диске мало, после успешного переключения статическую копию
можно удалить; до этого она является резервным вариантом.

### 0.1. Обновить текущий статический сайт

Пока WordPress не установлен, обновлённую папку `site/` можно выложить на
текущий сервер. Готовый архив — `nvgso-site-upload.zip` (без `docs/` и
`README.md`). Если его нет, создайте заново:

```powershell
$items = Get-ChildItem "C:\Users\djKeysi\Documents\для сайта nvgso\site" |
  Where-Object { $_.Name -notin @('docs','README.md') }
Compress-Archive -Path $items.FullName `
  -DestinationPath "C:\Users\djKeysi\Documents\для сайта nvgso\nvgso-site-upload.zip" -Force
scp "C:\Users\djKeysi\Documents\для сайта nvgso\nvgso-site-upload.zip" `
  user@46.149.228.111:/tmp/
```

На сервере (путь `root` уточните по `nginx -T`):

```bash
cd /var/www/html
sudo unzip -o /tmp/nvgso-site-upload.zip
sudo chown -R www-data:www-data /var/www/html
```

Не загружайте `docs/` и `README.md` в публичный корень production-сайта:
документация должна лежать вне web-root или быть закрыта от индексации.

## 1. Установка MariaDB 10.11

```bash
sudo apt update
sudo apt install -y mariadb-server mariadb-client
sudo mariadb --version
```

Убедитесь, что версия не ниже 10.6 (в Debian 12 / Ubuntu 24.04 обычно 10.11).
Если в репозитории старая версия — подключите официальный репозиторий MariaDB
10.11 LTS.

Первичная защита:

```bash
sudo mariadb-secure-installation
```

Ответы: удалить анонимных пользователей — да, запретить root удалённо — да,
удалить тестовую БД — да, перезагрузить привилегии — да.

### 1.1. Конфигурация

Файл `/etc/mysql/mariadb.conf.d/50-server.cnf` (или аналог):

```ini
[mysqld]
bind-address = 127.0.0.1
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

innodb_buffer_pool_size = 4G
innodb_log_file_size = 256M
max_connections = 100

slow_query_log = 1
slow_query_log_file = /var/log/mysql/mariadb-slow.log
long_query_time = 2

# Для внешних TCP-подключений (если разрешены):
# require_secure_transport = ON
# ssl_ca = /etc/mysql/certs/ca.pem
# ssl_cert = /etc/mysql/certs/server-cert.pem
# ssl_key = /etc/mysql/certs/server-key.pem
```

`innodb_buffer_pool_size` — 50–60 % RAM (для 8 ГБ это 4–5 ГБ).
`bind-address = 0.0.0.0` использовать нельзя.

```bash
sudo systemctl restart mariadb
sudo systemctl enable mariadb
sudo ss -lntp | grep 3306
```

Должно быть `127.0.0.1:3306`, а не `0.0.0.0:3306`.

### 1.2. База и пользователь WordPress

Сгенерируйте пароль (≥ 20 символов):

```bash
openssl rand -base64 32
```

Создайте БД и пользователя (пароль подставьте свой):

```bash
sudo mariadb
```

```sql
CREATE DATABASE nvgso_wp
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'nvgso_wp'@'localhost'
  IDENTIFIED BY 'PASTE_STRONG_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER
  ON nvgso_wp.* TO 'nvgso_wp'@'localhost';

FLUSH PRIVILEGES;
SHOW GRANTS FOR 'nvgso_wp'@'localhost';
```

Пользователь `root` для сайта не используется. Хост `%` не используется.
Если плагин при обновлении запросит дополнительные права — выдавайте
конкретное право, а не `ALL`.

### 1.3. Файрвол

Если используется `ufw`:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw deny 3306/tcp
sudo ufw enable
sudo ufw status verbose
```

Если файрвол настраивается в панели хостинга/security groups — закройте 3306
там. Проверка с внешней машины:

```bash
# С вашего компьютера, не с сервера:
nc -vz 46.149.228.111 3306
# Ожидаемо: connection refused / timeout
```

### 1.4. Доступ к БД извне (если нужен)

Рекомендуемый вариант — **SSH-туннель**, без открытия 3306:

```bash
ssh -L 3307:127.0.0.1:3306 user@46.149.228.111
```

В HeidiSQL/DBeaver подключайтесь к `127.0.0.1:3307`, пользователь `nvgso_wp`,
база `nvgso_wp`. Порт 3307 выбран, чтобы не конфликтовать с локальной СУБД.

Прямое сетевое подключение допускается только по приватному IP, с
`require_secure_transport = ON`, TLS 1.2+ и белым списком IP. Публичный
интерфейс для 3306 не открывается.

## 2. Установка PHP 8.2+

```bash
sudo apt update
sudo apt install -y \
  php8.3-fpm php8.3-mysql php8.3-curl php8.3-gd php8.3-mbstring \
  php8.3-xml php8.3-zip php8.3-intl php8.3-imagick unzip
```

Если пакетов `php8.3-*` нет (Debian 12) — используйте `php8.2-*`.
Проверка:

```bash
php -v
php -m | grep -E 'mysqli|pdo_mysql|gd|mbstring|xml|zip'
systemctl status php8.3-fpm --no-pager
ls /run/php/
```

Запомните сокет PHP-FPM, например `/run/php/php8.3-fpm.sock`.

## 3. Установка WordPress

Создайте каталог и скачайте WordPress:

```bash
sudo mkdir -p /var/www/nvgso
sudo chown -R www-data:www-data /var/www/nvgso

# WP-CLI (удобно для установки и бэкапов)
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp

cd /var/www/nvgso
sudo -u www-data wp core download --locale=ru_RU
```

`wp-config.php` (пароль БД подставьте):

```bash
sudo -u www-data wp config create \
  --dbname=nvgso_wp \
  --dbuser=nvgso_wp \
  --dbpass='PASTE_STRONG_PASSWORD' \
  --dbhost=localhost \
  --dbcharset=utf8mb4 \
  --dbcollate=utf8mb4_unicode_ci \
  --extra-php <<'PHP'
// WP_HOME/WP_SITEURL на этапе стенда не задавайте константами:
// адрес устанавливается wp core install и меняется через wp option update.
define('FS_METHOD', 'direct');
define('DISALLOW_FILE_EDIT', true);
define('WP_DEBUG', false);
PHP
```

`DB_HOST = localhost` использует Unix-сокет MariaDB. Это важно: при
`require_secure_transport = ON` TCP-подключение по `127.0.0.1` потребовало бы
TLS, а сокет — нет.

Проверка БД:

```bash
sudo -u www-data wp db check
sudo -u www-data wp db query "SELECT VERSION(), @@character_set_database, @@collation_database;"
```

Установка сайта:

```bash
sudo -u www-data wp core install \
  --url='http://46.149.228.111:8080' \
  --title='Новокузнецкий ВГСО' \
  --admin_user='admin' \
  --admin_password='PASTE_WP_ADMIN_PASSWORD' \
  --admin_email='admin@example.ru' \
  --skip-email

sudo -u www-data wp language core install ru_RU --activate
sudo -u www-data wp option update timezone_string 'Asia/Novokuznetsk'
sudo -u www-data wp rewrite structure '/%postname%/'
sudo -u www-data wp rewrite flush
```

Права:

```bash
sudo chown -R www-data:www-data /var/www/nvgso
sudo find /var/www/nvgso -type d -exec chmod 755 {} \;
sudo find /var/www/nvgso -type f -exec chmod 644 {} \;
sudo chmod 600 /var/www/nvgso/wp-config.php
```

## 4. Nginx: временный стенд, затем production

Сначала поднимите WordPress на временном порту **8080**, не трогая рабочий
статический сайт на 80/443. Создайте `/etc/nginx/sites-available/nvgso`:

```nginx
server {
    # Временный стенд. После проверки замените на listen 80; + 443 через certbot.
    listen 8080;
    server_name 46.149.228.111 DOMAIN.RU www.DOMAIN.RU;

    root /var/www/nvgso;
    index index.php index.html;
    client_max_body_size 64m;

    access_log /var/log/nginx/nvgso.access.log;
    error_log  /var/log/nginx/nvgso.error.log;

    location = /favicon.ico { log_not_found off; access_log off; }

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    # Запрет выполнения PHP в uploads — обязательно по ТЗ
    location ~* ^/wp-content/uploads/.*\.php$ {
        deny all;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    # Кэширование статики/изображений на 30 дней
    location ~* \.(webp|avif|jpg|jpeg|png|gif|svg|pdf|css|js|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        access_log off;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/nvgso /etc/nginx/sites-enabled/nvgso
sudo nginx -t
sudo systemctl reload nginx
sudo ufw allow 8080/tcp        # временно, только для проверки
```

Проверка стенда:

```bash
curl -I http://46.149.228.111:8080/
curl -I http://46.149.228.111:8080/wp-admin/install.php
```

Когда стенд проверен:

1. В `/etc/nginx/sites-available/nvgso` замените `listen 8080;` на
   `listen 80;` (443 добавит certbot).
2. Отключите старый статический `server`-блок на 80/443 или переключите его
   `root` на `/var/www/nvgso`.
3. `sudo nginx -t && sudo systemctl reload nginx`.
4. Обновите адрес WordPress (иначе он будет редиректить на `:8080`):
   `sudo -u www-data wp option update home 'http://46.149.228.111'` и
   `sudo -u www-data wp option update siteurl 'http://46.149.228.111'`.
5. Закройте временный порт: `sudo ufw delete allow 8080/tcp`.
6. Настройте HTTPS (раздел 5).

Если старый статический сайт остался в другом `server`-блоке на тот же
`server_name`, отключите его, иначе Nginx будет отдавать старую папку.

## 5. HTTPS после привязки домена

Когда DNS `A`-запись домена указывает на `46.149.228.111`:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d DOMAIN.RU -d www.DOMAIN.RU
```

Certbot сам добавит 443 и редирект с 80. Затем:

```bash
sudo -u www-data wp option update home 'https://DOMAIN.RU'
sudo -u www-data wp option update siteurl 'https://DOMAIN.RU'
sudo -u www-data wp search-replace 'http://46.149.228.111:8080' 'https://DOMAIN.RU' --all-tables --precise
```

Последняя команда заменяет временный адрес в загруженных изображениях и
контенте. Перед `search-replace` сделайте дамп БД.

До появления домена можно работать по IP, но для production HTTPS обязателен.

## 6. Тема и перенос дизайна

Статическая папка `site/` **не является темой WordPress**. Установите
дочернюю тему, перенесите в неё CSS и ассеты. Кратко:

С вашего компьютера (PowerShell):

```powershell
scp -r "C:\Users\djKeysi\Documents\для сайта nvgso\site" user@46.149.228.111:/tmp/nvgso-site
```

На сервере:

```bash
sudo mkdir -p /var/www/nvgso/wp-content/themes/nvgso/assets
sudo cp -r /tmp/nvgso-site/css /var/www/nvgso/wp-content/themes/nvgso/assets/
sudo cp -r /tmp/nvgso-site/assets/img /var/www/nvgso/wp-content/themes/nvgso/assets/
sudo cp -r /tmp/nvgso-site/assets/docs /var/www/nvgso/wp-content/themes/nvgso/assets/
```

Файлы темы и шаблоны — в
[`WORDPRESS-MIGRATION.md`](WORDPRESS-MIGRATION.md), разделы 3–4.

Быстрый перенос контента:

```bash
# Пример: загрузка всех изображений в медиатеку
cd /var/www/nvgso
sudo -u www-data wp media import /tmp/nvgso-site/assets/img/*.webp \
  --title='Фото Новокузнецкого ВГСО'

# Создание страниц
sudo -u www-data wp post create --post_type=page --post_title='Об отряде' \
  --post_status=publish --post_name='about'
```

Не вставляйте весь статический `index.html` в одну страницу WordPress:
шапка/подвал должны быть в `header.php`/`footer.php`, а контент — в полях
ACF и записях.

## 7. Плагины (новое ТЗ)

```bash
sudo -u www-data wp plugin install \
  oksigenia-access fz-152-rf ewww-image-optimizer \
  wordpress-seo wp-super-cache updraftplus wordfence contact-form-7 \
  --activate
```

Названия плагинов в каталоге WordPress могут отличаться; установите
аналоги из списка ТЗ через админку. После установки:

- доступность: включить масштабирование/контраст/подавление анимации;
- cookie: opt-in, три кнопки, журнал согласий;
- EWWW: WebP/AVIF, сжатие, адаптивные размеры;
- Yoast: title/description, sitemap, canonical;
- кэш: исключить страницы форм и cookie-баннера;
- UpdraftPlus: расписание и собственное внешнее хранилище;
- Wordfence: 2FA, файрвол, ограничение попыток входа.

## 8. Резервное копирование MariaDB и изображений

Скрипт `/usr/local/bin/nvgso-backup.sh`:

```bash
#!/bin/bash
set -euo pipefail

BACKUP=/var/backups/nvgso
STAMP=$(date +%F_%H%M)
mkdir -p "$BACKUP/db" "$BACKUP/uploads"

# Дамп БД (root читает /root/.my.cnf, права 600)
mariadb-dump --defaults-extra-file=/root/.my.cnf \
  --single-transaction --quick --routines --triggers nvgso_wp \
  | gzip > "$BACKUP/db/nvgso_wp_$STAMP.sql.gz"

# Изображения и медиафайлы
rsync -a --delete /var/www/nvgso/wp-content/uploads/ "$BACKUP/uploads/"

# Хранение 30 дней
find "$BACKUP/db" -type f -name '*.sql.gz' -mtime +30 -delete
```

`/root/.my.cnf`:

```ini
[client]
user=root
password=PASTE_ROOT_PASSWORD
```

```bash
sudo chmod 600 /root/.my.cnf
sudo chmod +x /usr/local/bin/nvgso-backup.sh
```

Cron (ежедневно в 02:30):

```cron
30 2 * * * root /usr/local/bin/nvgso-backup.sh >> /var/log/nvgso-backup.log 2>&1
```

Выгрузка вне сервера (собственный NAS/резервный сервер, не облачное
объектное хранилище):

```bash
rsync -az --delete /var/backups/nvgso/ backup@BACKUP_HOST:/backups/nvgso/
```

Дополнительно:

```bash
# физическая копия (опционально)
mariabackup --backup --target-dir=/var/backups/nvgso/mariabackup
```

Проверка восстановления — раз в месяц на отдельной БД:

```bash
gunzip -c /var/backups/nvgso/db/nvgso_wp_YYYY-MM-DD_HHMM.sql.gz \
  | mariadb nvgso_wp_restore
```

UpdraftPlus настраивается как вторая независимая копия БД и файлов на
собственное внешнее хранилище.

## 9. Проверка после установки

```bash
# Сайт и админка
curl -I https://DOMAIN.RU/
curl -I https://DOMAIN.RU/wp-admin/

# БД
sudo -u www-data wp db check
sudo -u www-data wp db query "SELECT VERSION();"
sudo ss -lntp | grep 3306        # только 127.0.0.1

# Порт 3306 снаружи (с вашего ПК)
nc -vz 46.149.228.111 3306       # должен быть закрыт

# Изображения и запрет PHP
curl -I https://DOMAIN.RU/wp-content/uploads/test.webp
curl -I https://DOMAIN.RU/wp-content/uploads/test.php   # 403/404

# Заголовки кэша
curl -I https://DOMAIN.RU/wp-content/uploads/test.webp | grep -i cache-control
```

Проверьте в браузере: главную, новости, документы, галерею, контакты,
политики, cookie-баннер, панель доступности 200 %, мобильную версию.
Lighthouse mobile — не менее 80, главная ≤ 3 с на 3G.

## 10. Безопасность

- SSH только по ключам, парольная аутентификация отключена;
- `PermitRootLogin prohibit-password`, нестандартный порт SSH (по желанию);
- файрвол: 80, 443, SSH; 3306 закрыт;
- `/wp-admin` ограничить по IP или basic-auth на уровне Nginx;
- 2FA для администраторов и редакторов;
- регулярные обновления WordPress, плагинов, PHP, MariaDB, Nginx, ОС;
- запрет выполнения PHP в uploads (уже в конфиге);
- `DISALLOW_FILE_EDIT` в `wp-config.php`;
- резервные копии — вне сервера, с проверкой восстановления.

## 11. Откат

Если что-то пошло не так:

```bash
sudo cp -a /root/backup-ДАТА/nginx/* /etc/nginx/
sudo nginx -t
sudo systemctl reload nginx
```

Старый статический сайт остаётся в `/var/www/html` (или в каталоге, который
указан в восстановленном конфиге Nginx). WordPress можно оставить в
`/var/www/nvgso` и продолжить настройку позже — он не мешает статическому
сайту, пока Nginx на него не переключён.
