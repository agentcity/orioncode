# php/Dockerfile
FROM php:8.2-fpm-alpine

# system deps
RUN apk add --no-cache \
    git \
    unzip \
    bash \
    icu-libs \
    libzip-dev \
    oniguruma-dev \
    zlib-dev \
    libxml2-dev \
    openssl \
    postgresql-dev \
    g++ \
    make \
    autoconf

# php extensions
RUN docker-php-ext-install pdo pdo_pgsql pcntl mbstring zip intl opcache

# install composer
COPY --from=composer:2.6 /usr/bin/composer /usr/local/bin/composer

WORKDIR /srv/app

# Copy only composer files first for caching
COPY composer.json composer.lock /srv/app/

# install php deps (best-effort, in dev mounts composer may be re-run later)
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader || true

# Copy project files
COPY . /srv/app

# Ensure vendor is present (for local dev composer will be run by dev)
RUN composer install --no-interaction || true

# Permissions (if needed)
RUN chown -R www-data:www-data /srv/app/var /srv/app/vendor || true

EXPOSE 9000

CMD ["php-fpm"]