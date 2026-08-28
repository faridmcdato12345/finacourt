FROM php:8.3-fpm-bookworm

ARG APP_UID=1000
ARG APP_GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libicu-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        pcntl \
        pdo_mysql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN groupadd --gid "${APP_GID}" app \
    && useradd --uid "${APP_UID}" --gid app --create-home --shell /bin/bash app \
    && sed -i 's/^user = www-data/user = app/; s/^group = www-data/group = app/' /usr/local/etc/php-fpm.d/www.conf \
    && printf 'expose_php=Off\n' > /usr/local/etc/php/conf.d/99-security.ini

WORKDIR /var/www/html

COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/98-uploads.ini
RUN chmod +x /usr/local/bin/app-entrypoint

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
