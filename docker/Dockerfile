FROM php:8.4-cli-bookworm

COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get install -y --no-install-recommends git unzip curl postgresql-client \
    && install-php-extensions pdo_pgsql pgsql bcmath intl zip gd pcntl opcache redis \
    && rm -rf /var/lib/apt/lists/*

ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} prumo 2>/dev/null || true \
    && useradd -m -u ${UID} -g ${GID} -s /bin/bash prumo 2>/dev/null || true

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-prumo.ini
COPY docker/entrypoint.sh /usr/local/bin/prumo-entrypoint
RUN chmod +x /usr/local/bin/prumo-entrypoint

WORKDIR /var/www/html
ENV PHP_CLI_SERVER_WORKERS=6
EXPOSE 8000
ENTRYPOINT ["prumo-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
