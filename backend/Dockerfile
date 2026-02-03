# backend/Dockerfile (use PHP 8.4)
FROM php:8.4-fpm-alpine

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
    autoconf \
    curl \
    ca-certificates

RUN docker-php-ext-install pdo pdo_pgsql pcntl mbstring zip intl opcache

COPY --from=composer:2.6 /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp \
    COMPOSER_MEMORY_LIMIT=-1 \
    PATH="/usr/local/bin:${PATH}"

ARG HTTP_PROXY
ARG HTTPS_PROXY
ENV HTTP_PROXY=${HTTP_PROXY}
ENV HTTPS_PROXY=${HTTPS_PROXY}

# до этого у вас уже установлен composer и пр.
WORKDIR /srv/backend

# задаём переменные окружения для composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp \
    COMPOSER_MEMORY_LIMIT=-1

# копируем только composer-файлы
COPY composer.json composer.lock* /srv/backend/

# устанавливаем только prod-зависимости, НЕ выполняя скрипты
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# копируем приложение
COPY . /srv/backend

# выставляем prod окружение и явно очищаем/кешируем кеш
ENV APP_ENV=prod APP_DEBUG=0
RUN php bin/console cache:clear --no-warmup --no-interaction --env=prod \
 && php bin/console cache:warmup --no-interaction --env=prod || true

# права и т.д.
RUN chown -R www-data:www-data var/ vendor/ || true


EXPOSE 9000

CMD ["php-fpm"]
