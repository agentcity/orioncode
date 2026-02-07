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

RUN pecl install redis \
    && docker-php-ext-enable redis

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

# Создаем пустой файл .env, чтобы Symfony не выдавал PathException
RUN touch .env

# устанавливаем только prod-зависимости, НЕ выполняя скрипты
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# копируем приложение
COPY . /srv/backend

# выставляем prod окружение и явно очищаем/кешируем кеш

# 1. Указываем АРГУМЕНТЫ (они видны только при сборке)
ARG DATABASE_URL="postgresql://db_user:db_password@127.0.0.1:5432/db_name?serverVersion=13&charset=utf8"
ARG REDIS_URL="redis://localhost:6379"
ARG APP_SECRET="temporary_secret_for_build"

# 2. Пробрасываем их в переменные окружения ДЛЯ СБОРКИ КЭША
ENV DATABASE_URL=${DATABASE_URL}
ENV REDIS_URL=${REDIS_URL}
ENV APP_SECRET=${APP_SECRET}
ENV APP_ENV=prod APP_DEBUG=0

RUN php bin/console cache:clear --no-warmup --no-interaction --env=prod \
 && php bin/console cache:warmup --no-interaction --env=prod || true

# права и т.д.
RUN chown -R www-data:www-data var/ vendor/ || true


EXPOSE 9000

CMD ["php-fpm"]
