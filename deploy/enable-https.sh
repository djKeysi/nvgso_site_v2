#!/usr/bin/env bash
# Включает HTTPS через Let's Encrypt после привязки домена.
# Использование: sudo bash deploy/enable-https.sh nvgso.vgsch.ru [--staging]
# Требуется: A-запись домена уже указывает на этот сервер, порт 80 открыт.
set -Eeuo pipefail

if [[ ${EUID} -ne 0 ]]; then
  echo "Запустите от root: sudo bash $0 ДОМЕН [--staging]" >&2
  exit 1
fi

DOMAIN=${1:-}
STAGING=${2:-}
if [[ -z "$DOMAIN" ]]; then
  echo "Использование: sudo bash $0 ДОМЕН [--staging]" >&2
  echo "Пример:        sudo bash $0 nvgso.vgsch.ru" >&2
  echo
  echo "Без домена Let's Encrypt сертификат не выдаёт. Для локальной проверки" >&2
  echo "можно выпустить самоподписанный сертификат вручную (см. ИНСТРУКЦИЯ.md)." >&2
  exit 1
fi

SITE_DIR=/var/www/nvgso
WP="sudo -u www-data env HOME=/var/www WP_CLI_CACHE_DIR=/var/www/.wp-cli-cache /usr/local/bin/wp --path=$SITE_DIR"

echo "== Проверка DNS =="
RESOLVED=$(getent ahostsv4 "$DOMAIN" | awk '{print $1; exit}' || true)
if [[ -z "$RESOLVED" ]]; then
  echo "Домен $DOMAIN не разрешается. Сначала настройте A-запись." >&2
  exit 1
fi
echo "$DOMAIN -> $RESOLVED"

echo "== Установка certbot =="
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq certbot python3-certbot-nginx

echo "== Выпуск сертификата =="
CERTBOT_ARGS=(--nginx -d "$DOMAIN" -d "www.$DOMAIN" --non-interactive --agree-tos --redirect -m "prognvgso@vgsch.ru")
if [[ "$STAGING" == "--staging" ]]; then
  CERTBOT_ARGS+=(--staging)
fi
certbot "${CERTBOT_ARGS[@]}"

echo "== Обновление адреса WordPress =="
$WP option update home "https://$DOMAIN"
$WP option update siteurl "https://$DOMAIN"
OLD=$($WP option get home 2>/dev/null || true)
$WP search-replace 'http://10.0.2.15' "https://$DOMAIN" --all-tables --precise || true
$WP cache flush || true

echo "== Проверка =="
curl -fsSI "https://$DOMAIN/" | head -5
curl -fsSI "https://$DOMAIN/wp-admin/" | head -5
echo
echo "Готово. Не забудьте:"
echo "  - обновить пароли/ссылки, если использовался другой адрес;"
echo "  - проверить сайт в браузере;"
echo "  - certbot renew --dry-run"
