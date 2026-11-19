#!/usr/bin/env bash
# =============================================================================
#  setup-server.sh — настройка сервера NVGSO по docs/SERVER-SETUP.md
#
#  Что делает:
#    1. резервная копия nginx и статического сайта;
#    2. MariaDB: конфигурация (bind 127.0.0.1, utf8mb4, slow log) + БД/пользователь;
#    3. PHP-FPM: проверка/запуск;
#    4. WordPress: wp-config.php, установка, ru_RU, ЧПУ, часовой пояс;
#    5. Nginx: стенд на :8080 -> проверка -> переключение на :80;
#    6. базовые страницы и меню;
#    7. плагины из ТЗ (что доступно в каталоге WordPress);
#    8. ежедневный бэкап БД и uploads + cron;
#    9. отчёт и файл с доступами.
#
#  Запуск:  sudo bash deploy/setup-server.sh
#  Повторный запуск безопасен (идемпотентно).
#
#  Переопределяемые переменные:
#     SITE_URL=http://10.0.2.15  sudo -E bash deploy/setup-server.sh
# =============================================================================
set -Eeuo pipefail

if [[ ${EUID} -ne 0 ]]; then
  echo "Запустите от root:  sudo bash $0" >&2
  exit 1
fi

SITE_DIR=/var/www/nvgso
STATIC_DIR=/var/www/nvgso-static
DB_NAME=nvgso_wp
DB_USER=nvgso_wp
SECRETS_DIR=/root/nvgso
SECRETS_FILE=$SECRETS_DIR/secrets.env
LOG_FILE=/var/log/nvgso-setup.log
REPORT_FILE=$SECRETS_DIR/report.txt
NGX_AVAIL=/etc/nginx/sites-available/nvgso
NGX_ENABLED=/etc/nginx/sites-enabled/nvgso
WP=/usr/local/bin/wp
WP_CACHE_DIR=/var/www/.wp-cli-cache

exec > >(tee -a "$LOG_FILE") 2>&1
trap 'echo "[FAIL] строка $LINENO, код $?"' ERR

step() { echo; echo "===== $* ====="; }
ok()   { echo "[OK] $*"; }
warn() { echo "[WARN] $*"; }

step "0. Параметры окружения"
IP=$(ip -4 route get 1.1.1.1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="src"){print $(i+1); exit}}' || true)
IP=${IP:-10.0.2.15}
SITE_URL=${SITE_URL:-http://$IP}
TMP_URL="http://$IP:8080"
echo "IP:        $IP"
echo "SITE_URL:  $SITE_URL"
echo "SITE_DIR:  $SITE_DIR"

step "1. Резервная копия конфигов и статического сайта"
STAMP=$(date +%F_%H%M%S)
BK=/root/backup-$STAMP
mkdir -p "$BK"
cp -a /etc/nginx "$BK/nginx"
if [[ -d /var/www/html ]]; then tar -czf "$BK/html.tar.gz" -C /var/www/html . || true; fi
if [[ -d $STATIC_DIR ]]; then tar -czf "$BK/nvgso-static.tar.gz" -C /var/www/nvgso-static . || true; fi
if [[ ! -f $STATIC_DIR/index.html && -d /var/www/html ]]; then
  [[ $STATIC_DIR == /var/www/nvgso-static ]] || { echo "Неожиданный путь $STATIC_DIR" >&2; exit 1; }
  rm -rf "$STATIC_DIR"
  cp -a /var/www/html "$STATIC_DIR"
  ok "Создана копия статического сайта: $STATIC_DIR"
fi
ok "Резервная копия: $BK"

step "2. Пакеты (MariaDB, PHP-FPM, rsync, unzip)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq || warn "apt-get update завершился с ошибкой — проверьте источники APT"
MISSING=()
for p in mariadb-client rsync unzip curl; do
  dpkg -s "$p" >/dev/null 2>&1 || MISSING+=("$p")
done
if ((${#MISSING[@]})); then apt-get install -y -qq "${MISSING[@]}" >/dev/null; fi
if ! command -v mariadbd >/dev/null 2>&1; then
  apt-get install -y -qq mariadb-server >/dev/null
fi
PHP_VER=$(ls /etc/php 2>/dev/null | sort -V | tail -1 || true)
if [[ -z ${PHP_VER:-} || ! -f /etc/php/$PHP_VER/fpm/pool.d/www.conf ]]; then
  if apt-cache show php8.3-fpm >/dev/null 2>&1; then NEWPHP=8.3; else NEWPHP=8.2; fi
  warn "PHP-FPM не установлен — устанавливаю php$NEWPHP-fpm и модули"
  apt-get install -y -qq php$NEWPHP-fpm php$NEWPHP-mysql php$NEWPHP-curl php$NEWPHP-gd \
    php$NEWPHP-mbstring php$NEWPHP-xml php$NEWPHP-zip php$NEWPHP-intl php$NEWPHP-imagick >/dev/null
  PHP_VER=$NEWPHP
fi
PHP_SOCK=$(awk '/^listen[[:space:]]*=/{print $3}' /etc/php/$PHP_VER/fpm/pool.d/www.conf)
PHP_SOCK=${PHP_SOCK:-/run/php/php$PHP_VER-fpm.sock}
echo "PHP $PHP_VER, сокет: $PHP_SOCK"

step "3. MariaDB: конфигурация"
CNF=/etc/mysql/mariadb.conf.d/99-nvgso.cnf
cat > "$CNF" <<'EOF'
[mysqld]
bind-address = 127.0.0.1
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# 8 ГБ RAM, но на машине работает графическая оболочка —
# 2 ГБ достаточно; на «чистом» сервере можно поднять до 4 ГБ.
innodb_buffer_pool_size = 2G
max_connections = 100

slow_query_log = 1
slow_query_log_file = /var/log/mysql/mariadb-slow.log
long_query_time = 2
EOF
mkdir -p /var/log/mysql
chown mysql:adm /var/log/mysql 2>/dev/null || true
systemctl enable mariadb >/dev/null 2>&1 || true
if ! systemctl restart mariadb; then
  warn "MariaDB не стартует с $CNF — откатываю конфиг"
  rm -f "$CNF"
  systemctl restart mariadb
fi
if systemctl is-active --quiet mariadb; then ok "MariaDB запущена"; else echo "MariaDB не запущена" >&2; exit 1; fi
mariadb -e 'SELECT VERSION();' >/dev/null
BIND=$(ss -lntp 2>/dev/null | awk '/:3306 /{print $4}' | head -1)
echo "3306 слушает: $BIND"
[[ $BIND == 127.0.0.1:3306 || $BIND == "[::1]:3306" ]] || warn "Ожидалось 127.0.0.1:3306"

step "4. БД и пользователь WordPress"
mkdir -p "$SECRETS_DIR"; chmod 700 "$SECRETS_DIR"
if [[ -f $SECRETS_FILE ]]; then
  # shellcheck disable=SC1090
  source "$SECRETS_FILE"
else
  DB_PASS=$(openssl rand -hex 24)
  WP_ADMIN_PASS=$(openssl rand -hex 16)
  cat > "$SECRETS_FILE" <<EOF
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASS=$DB_PASS
WP_ADMIN_USER=admin
WP_ADMIN_PASS=$WP_ADMIN_PASS
WP_ADMIN_EMAIL=admin@nvgso.local
EOF
  chmod 600 "$SECRETS_FILE"
fi
WP_ADMIN_USER=${WP_ADMIN_USER:-admin}
WP_ADMIN_EMAIL=${WP_ADMIN_EMAIL:-admin@nvgso.local}

mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER,
      REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES
  ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
mariadb -u "$DB_USER" -p"$DB_PASS" -h localhost "$DB_NAME" -e 'SELECT 1;' >/dev/null
ok "БД $DB_NAME и пользователь $DB_USER готовы"

step "5. PHP-FPM"
# Лимиты загрузки: Nginx разрешает 64 МБ, PHP по умолчанию — только 2 МБ
cat > "/etc/php/$PHP_VER/fpm/conf.d/99-nvgso.ini" <<'PHPINI'
upload_max_filesize = 64M
post_max_size = 64M
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
date.timezone = Asia/Novokuznetsk
PHPINI
cat > "/etc/php/$PHP_VER/cli/conf.d/99-nvgso.ini" <<'PHPINI'
upload_max_filesize = 64M
post_max_size = 64M
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
date.timezone = Asia/Novokuznetsk
PHPINI
systemctl enable "php$PHP_VER-fpm" >/dev/null 2>&1 || true
systemctl restart "php$PHP_VER-fpm"
for _ in $(seq 1 20); do [[ -S $PHP_SOCK ]] && break; sleep 0.5; done
[[ -S $PHP_SOCK ]] || { echo "Нет сокета $PHP_SOCK" >&2; exit 1; }
ok "PHP-FPM слушает $PHP_SOCK"

step "6. WP-CLI и файлы WordPress"
if [[ ! -x $WP ]]; then
  curl -fsSL -o /tmp/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
  install -m 755 /tmp/wp-cli.phar "$WP"
fi
mkdir -p "$SITE_DIR" "$WP_CACHE_DIR" "$SITE_DIR/wp-content/uploads" "$SITE_DIR/wp-content/upgrade"
chown -R www-data:www-data "$SITE_DIR" "$WP_CACHE_DIR"

wp_cli() {
  sudo -u www-data env HOME=/var/www WP_CLI_CACHE_DIR="$WP_CACHE_DIR" \
    "$WP" --path="$SITE_DIR" "$@"
}

if ! wp_cli core version >/dev/null 2>&1; then
  wp_cli core download --locale=ru_RU --force
fi
ok "WordPress: $(wp_cli core version 2>/dev/null || echo '?')"

if [[ ! -f $SITE_DIR/wp-config.php ]]; then
  wp_cli config create \
    --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" \
    --dbhost=localhost --dbcharset=utf8mb4 --dbcollate=utf8mb4_unicode_ci \
    --extra-php <<'PHP'
define('FS_METHOD', 'direct');
define('DISALLOW_FILE_EDIT', true);
define('WP_DEBUG', false);
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');
define('WP_POST_REVISIONS', 20);
PHP
  ok "Создан wp-config.php"
else
  ok "wp-config.php уже существует — не перезаписываю"
fi
chown www-data:www-data "$SITE_DIR/wp-config.php"
chmod 600 "$SITE_DIR/wp-config.php"

wp_cli db check
FRESH=0
if ! wp_cli core is-installed >/dev/null 2>&1; then
  FRESH=1
  wp_cli core install \
    --url="$TMP_URL" \
    --title='Новокузнецкий ВГСО' \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASS" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
  ok "WordPress установлен"
  wp_cli language core install ru_RU --activate || warn "ru_RU уже установлен"
else
  ok "WordPress уже установлен"
  wp_cli option update home "$TMP_URL" >/dev/null
  wp_cli option update siteurl "$TMP_URL" >/dev/null
fi
wp_cli option update timezone_string 'Asia/Novokuznetsk' >/dev/null
wp_cli option update blogdescription 'Официальный сайт Новокузнецкого военизированного горноспасательного отряда' >/dev/null
wp_cli rewrite structure '/%postname%/' >/dev/null
wp_cli rewrite flush >/dev/null
wp_cli theme activate twentytwentyfive >/dev/null 2>&1 || true
wp_cli option update uploads_use_yearmonth_folders 1 >/dev/null 2>&1 || true

step "7. Nginx: временный стенд на :8080"
cat > "$NGX_AVAIL" <<EOF
# NVGSO / WordPress. Сгенерировано deploy/setup-server.sh
server {
    listen 8080;
    server_name _;

    root $SITE_DIR;
    index index.php index.html;
    client_max_body_size 64m;

    access_log /var/log/nginx/nvgso.access.log;
    error_log  /var/log/nginx/nvgso.error.log;

    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options SAMEORIGIN always;

    location = /favicon.ico { log_not_found off; access_log off; }
    location = /robots.txt  { log_not_found off; access_log off; allow all; }

    # Запрет выполнения PHP в uploads (ТЗ)
    location ~* ^/wp-content/uploads/.*\.php\$ { deny all; }
    location = /wp-config.php { deny all; }

    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_SOCK;
        fastcgi_read_timeout 300;
    }

    # Кэш статики и изображений на 30 дней
    location ~* \.(webp|avif|jpg|jpeg|png|gif|svg|pdf|css|js|woff2?)\$ {
        expires 30d;
        add_header Cache-Control "public, max-age=2592000";
        access_log off;
    }

    location ~ /\.(?!well-known) { deny all; }
}
EOF
ln -sfn "$NGX_AVAIL" "$NGX_ENABLED"
nginx -t
systemctl reload nginx
sleep 1
CODE=$(curl -s -o /tmp/nvgso-8080.html -w '%{http_code}' "http://127.0.0.1:8080/" -H "Host: $IP" || true)
echo "HTTP :8080 -> $CODE"
if [[ $CODE != 200 && $CODE != 301 && $CODE != 302 ]]; then
  echo "----- nginx error log -----"; tail -20 /var/log/nginx/nvgso.error.log || true
  echo "----- php-fpm -----"; systemctl status "php$PHP_VER-fpm" --no-pager -n 10 || true
  echo "Стенд на :8080 не работает. Статический сайт на :80 не тронут." >&2
  exit 2
fi
ok "Стенд на :8080 отвечает"

step "8. Переключение production-блока на :80"
if [[ -L /etc/nginx/sites-enabled/default || -f /etc/nginx/sites-enabled/default ]]; then
  mv /etc/nginx/sites-enabled/default "$BK/default.sites-enabled"
  ok "Старый статический server-блок отключён (копия в $BK)"
fi
sed -i 's/^    listen 8080;$/    listen 80 default_server;\n    listen [::]:80 default_server;/' "$NGX_AVAIL"
grep -q 'listen 80 default_server' "$NGX_AVAIL" || sed -i 's/listen 8080;/listen 80 default_server;/' "$NGX_AVAIL"
nginx -t
systemctl reload nginx
sleep 1
CODE=$(curl -s -o /tmp/nvgso-80.html -w '%{http_code}' "http://127.0.0.1/" -H "Host: $IP" || true)
echo "HTTP :80 -> $CODE"
[[ $CODE == 200 || $CODE == 301 || $CODE == 302 ]] || { echo "Сайт на :80 не отвечает" >&2; exit 3; }

wp_cli option update home "$SITE_URL" >/dev/null
wp_cli option update siteurl "$SITE_URL" >/dev/null
if [[ $TMP_URL != "$SITE_URL" ]]; then
  wp_cli search-replace "$TMP_URL" "$SITE_URL" --all-tables --precise >/dev/null 2>&1 || true
fi
CODE=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1/" -H "Host: $IP" || true)
echo "Проверка после смены URL: $CODE"
[[ $CODE == 200 || $CODE == 301 || $CODE == 302 ]] || warn "Проверьте home/siteurl"

step "9. Базовые страницы и меню"
make_page() { # $1 slug, $2 title, $3 content
  local id
  id=$(wp_cli post list --post_type=page --name="$1" --field=ID --format=ids 2>/dev/null || true)
  if [[ -z ${id:-} ]]; then
    id=$(wp_cli post create --post_type=page --post_status=publish \
      --post_title="$2" --post_name="$1" --post_content="$3" --porcelain)
  fi
  echo "$id"
}

HOME_ID=$(make_page home 'Главная' '<h2>Новокузнецкий военизированный горноспасательный отряд</h2><p>Официальный сайт отряда. Разделы сайта доступны в меню.</p><p>Материалы разделов публикуются через панель управления WordPress.</p>')
ABOUT_ID=$(make_page about 'Об отряде' '<p>Новокузнецкий военизированный горноспасательный отряд выполняет аварийно-спасательные и горноспасательные работы, обеспечивает готовность сил и средств к ликвидации аварий.</p>')
STRUCT_ID=$(make_page structure 'Структура' '<p>Структура отряда: подразделения, дежурные смены и вспомогательные службы.</p>')
ACT_ID=$(make_page activity 'Деятельность' '<p>Основные направления деятельности отряда: профилактика аварий, горноспасательные работы, обучение и взаимодействие с обслуживаемыми организациями.</p>')
NEWS_ID=$(make_page news 'Новости' '')
DOC_ID=$(make_page documents 'Документы' '<p>Нормативные документы, лицензии и свидетельства публикуются в этом разделе.</p>')
GAL_ID=$(make_page gallery 'Фотогалерея' '<p>Фотоматериалы учений, соревнований и повседневной деятельности отряда.</p>')
CONT_ID=$(make_page contacts 'Контакты' '<p>Адрес: 654028, г. Новокузнецк, ул. Горноспасательная, д. 5.</p><p>Телефон дежурной службы: +7 (3843) 00-00-00.</p>')
VET_ID=$(make_page veterans 'Ветеранам и память' '<p>Раздел о ветеранах отряда и сохранении памяти.</p>')
PRIV_ID=$(make_page privacy-policy 'Политика конфиденциальности' '<p>Текст политики конфиденциальности утверждается и публикуется в этом разделе.</p>')
COMP_ID=$(make_page company-policy 'Политика компании' '<p>Текст политики компании утверждается и публикуется в этом разделе.</p>')
COOK_ID=$(make_page cookie-policy 'Политика cookie' '<p>Текст политики использования cookie утверждается и публикуется в этом разделе.</p>')

# WordPress при установке создаёт черновик «Политика конфиденциальности» —
# публикуем его, иначе страница отдаёт 404.
wp_cli post update "$PRIV_ID" --post_status=publish >/dev/null 2>&1 || true
wp_cli option update wp_page_for_privacy_policy "$PRIV_ID" >/dev/null 2>&1 || true

wp_cli option update show_on_front page >/dev/null
wp_cli option update page_on_front "$HOME_ID" >/dev/null
wp_cli option update page_for_posts "$NEWS_ID" >/dev/null

if ! wp_cli menu list --fields=name --format=csv 2>/dev/null | grep -q 'Основное'; then
  wp_cli menu create 'Основное' >/dev/null
fi
if [[ -z $(wp_cli menu item list 'Основное' --format=ids 2>/dev/null) ]]; then
  for id in "$ABOUT_ID" "$STRUCT_ID" "$ACT_ID" "$NEWS_ID" "$DOC_ID" "$GAL_ID" "$CONT_ID" "$VET_ID"; do
    wp_cli menu item add-post 'Основное' "$id" >/dev/null
  done
  wp_cli menu location assign 'Основное' primary >/dev/null 2>&1 || warn "Тема не поддерживает позицию primary"
fi
ok "Страницы и меню готовы"

step "10. Плагины (best-effort)"
if curl -fsSI https://wordpress.org/ >/dev/null 2>&1; then
  for p in wordpress-seo wp-super-cache updraftplus wordfence contact-form-7 ewww-image-optimizer wp-accessibility; do
    if wp_cli plugin is-installed "$p" >/dev/null 2>&1; then
      wp_cli plugin activate "$p" >/dev/null 2>&1 && echo "[OK] $p" || warn "$p не активирован"
    else
      if wp_cli plugin install "$p" --activate >/dev/null 2>&1; then
        echo "[OK] $p установлен"
      else
        warn "не удалось установить $p"
      fi
    fi
  done
  warn "Плагины Oksigenia Access и FZ-152 RF отсутствуют в каталоге WordPress — подберите аналоги вручную"
else
  warn "Нет доступа к wordpress.org — плагины не установлены"
fi

# Простой режим кэша WP Super Cache (не требует правил Nginx)
if [[ -f $SITE_DIR/wp-content/wp-cache-config.php ]]; then
  sed -i 's/^\$cache_enabled = false;/$cache_enabled = true;/; s/^\$super_cache_enabled = true;/$super_cache_enabled = false;/' \
    "$SITE_DIR/wp-content/wp-cache-config.php"
  ok "WP Super Cache: включён простой режим"
fi

step "11. Права на файлы"
chown -R www-data:www-data "$SITE_DIR"
find "$SITE_DIR" -type d -exec chmod 755 {} +
find "$SITE_DIR" -type f -exec chmod 644 {} +
chmod 600 "$SITE_DIR/wp-config.php"
chmod 755 "$SITE_DIR"
ok "Права выставлены"

step "12. Файрвол"
if LANG=C ufw status 2>/dev/null | grep -q 'Status: active'; then
  ufw allow 22/tcp  >/dev/null 2>&1 || true
  ufw allow 80/tcp  >/dev/null 2>&1 || true
  ufw allow 443/tcp >/dev/null 2>&1 || true
  ufw deny 3306/tcp >/dev/null 2>&1 || true
  ok "ufw активен — правила 22/80/443 добавлены, 3306 закрыт"
else
  warn "ufw неактивен — не включаю автоматически, чтобы не потерять доступ к Web GUI на :3080"
fi

step "13. Резервное копирование"
cat > /usr/local/bin/nvgso-backup.sh <<'EOS'
#!/bin/bash
set -euo pipefail
BACKUP=/var/backups/nvgso
STAMP=$(date +%F_%H%M)
mkdir -p "$BACKUP/db" "$BACKUP/uploads"
mariadb-dump --single-transaction --quick --routines --triggers nvgso_wp \
  | gzip > "$BACKUP/db/nvgso_wp_$STAMP.sql.gz"
rsync -a --delete /var/www/nvgso/wp-content/uploads/ "$BACKUP/uploads/"
find "$BACKUP/db" -type f -name '*.sql.gz' -mtime +30 -delete
EOS
chmod 700 /usr/local/bin/nvgso-backup.sh
echo '30 2 * * * root /usr/local/bin/nvgso-backup.sh >> /var/log/nvgso-backup.log 2>&1' > /etc/cron.d/nvgso-backup
chmod 644 /etc/cron.d/nvgso-backup
/usr/local/bin/nvgso-backup.sh
gzip -t /var/backups/nvgso/db/*.sql.gz
ok "Бэкап выполнен: $(ls -1 /var/backups/nvgso/db | tail -1)"

step "14. Отчёт и доступы"
mkdir -p /var/backups/nvgso
{
  echo "Дата:            $(date '+%F %T %Z')"
  echo "Сайт:            $SITE_URL"
  echo "Админка:         $SITE_URL/wp-admin/"
  echo "IP:              $IP"
  echo "WordPress:       $(wp_cli core version 2>/dev/null || echo '?')"
  echo "PHP:             $PHP_VER ($PHP_SOCK)"
  echo "MariaDB:         $(mariadb -N -e 'SELECT VERSION();')"
  echo "БД:              $DB_NAME, пользователь $DB_USER (localhost)"
  echo "Каталог сайта:   $SITE_DIR"
  echo "Статический сайт: $STATIC_DIR (резерв)"
  echo "Логи:            /var/log/nginx/nvgso.*.log, $LOG_FILE"
  echo "Бэкапы:          /var/backups/nvgso (cron 02:30)"
  echo "Доступы:         $SECRETS_FILE"
} > "$REPORT_FILE"
chmod 600 "$REPORT_FILE"

cat > /home/nvgso/nvgso-access.txt <<EOF
Доступы к сайту Новокузнецкий ВГСО (создано $(date '+%F %H:%M'))
=================================================================
Сайт:        $SITE_URL
Админка:     $SITE_URL/wp-admin/
Логин:       $WP_ADMIN_USER
Пароль:      $WP_ADMIN_PASS

База данных MariaDB (только локально):
  БД:        $DB_NAME
  Пользователь: $DB_USER
  Пароль:    $DB_PASS
  Хост:      localhost (Unix-сокет), порт 3306 закрыт снаружи

SSH-туннель к БД с другого компьютера:
  ssh -L 3307:127.0.0.1:3306 nvgso@$IP
  затем подключаться к 127.0.0.1:3307

Полный отчёт: /root/nvgso/report.txt (sudo)
EOF
chown nvgso:nvgso /home/nvgso/nvgso-access.txt 2>/dev/null || true
chmod 600 /home/nvgso/nvgso-access.txt
ok "Доступы: /home/nvgso/nvgso-access.txt"

step "ГОТОВО"
cat "$REPORT_FILE"
cat <<EOF

Сайт:     $SITE_URL
Админка:  $SITE_URL/wp-admin/  ($WP_ADMIN_USER)
Пароли:   /home/nvgso/nvgso-access.txt

Проверка:
  curl -I $SITE_URL/
  curl -I $SITE_URL/wp-admin/
  curl -I $SITE_URL/wp-content/uploads/test.php   # 403/404
EOF
