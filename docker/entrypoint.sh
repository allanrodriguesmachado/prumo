#!/usr/bin/env bash
set -e
cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
  echo "[prumo] Instalando dependências do Composer..."
  composer install --no-interaction --prefer-dist
fi

if [ "${PRUMO_ROLE:-app}" = "app" ]; then
  until pg_isready -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-prumo}" >/dev/null 2>&1; do
    echo "[prumo] Aguardando o PostgreSQL..."; sleep 2
  done
  php artisan migrate --force
  php artisan storage:link >/dev/null 2>&1 || true
else
  # workers aguardam o app terminar as migrations
  until php artisan migrate:status >/dev/null 2>&1; do sleep 3; done
fi

exec "$@"
