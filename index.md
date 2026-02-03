Команды для запуска:

- Сборка и запуск в фоне:
- docker-compose build
- docker-compose up -d

- Войти в контейнер PHP для запуска миграций / установки зависимостей:
- docker-compose exec orion_php sh
- composer install
- php bin/console doctrine:migrations:migrate
- php bin/console doctrine:fixtures:load (опционально)

- Логи:
- docker-compose logs -f orion_nginx
- docker-compose logs -f orion_php
- docker-compose logs -f orion_websocket
- docker-compose logs -f orion_redis
- docker-compose logs -f orion_db

Замечания и рекомендации:
- В production уберите монтирование исходников в контейнер PHP, выполните корректную сборку образа с зависимостями внутри image.
- Переменные с секретами храните безопасно (Docker secrets, Vault, CI/CD secrets).
- Обновите в коде Symfony конфигурации (DATABASE_URL, Messenger DSNs) при необходимости — они уже переданы в окружении контейнера.
- WebSocket-сервер читает REDIS_URL из окружения и подписывается на канал new_message_channel — убедитесь, что ваш Symfony-публишер использует тот же канал и хост orion_redis.
- Nginx указывает fastcgi_pass orion_php:9000 — сервис name orion_php служит DNS-именем внутри bridge-сети.


Локальная разработка (быстрый цикл: код → тест → правка)
- Используйте dev docker‑compose (override) с монтированным кодом, чтобы не пересобирать образ при каждой правке:
- docker-compose.yml (prod) + docker-compose.override.yml (dev с volumes, npm/yarn dev server, xdebug, rsync).
- Пример команд:

  # установить зависимости один раз локально (при необходимости)
  cd backend && composer install
  cd ../frontend && npm ci

  # поднять окружение разработки
  docker-compose up -d
  # или перестроить только сервис, если изменяли зависимости
  docker-compose up -d --build orion_backend orion_frontend

- Быстрая проверка изменений:
- backend: запустить unit‑/functional‑tests
  docker-compose exec orion_backend php bin/phpunit

- frontend: npm start или запуск тестов

  docker-compose exec orion_frontend npm test

- Линтеры / статический анализ:

  # PHP
  docker-compose exec orion_backend composer cs-check    # например, phpcs
  docker-compose exec orion_backend vendor/bin/phpstan analyse
  # JS
  docker-compose exec orion_frontend npm run lint

3) Выполнение миграций и фикстур локально
- Прежде чем тестировать интеграцию, примените миграции:

  docker-compose exec orion_backend php bin/console doctrine:migrations:migrate --no-interaction
  docker-compose exec orion_backend php bin/console doctrine:fixtures:load --no-interaction

- Если воркеры/мессенджер: запустите локально consumer:

  docker-compose exec orion_worker php bin/console messenger:consume async -vv

4) Локальная сборка образов (если нужно тестировать финальный образ)
- Собрать образ локально:

  docker build -t myrepo/orion_backend:local -f backend/Dockerfile backend
  docker build -t myrepo/orion_frontend:local -f frontend/Dockerfile frontend

- Использовать docker-compose build:
  ocker-compose build --no-cache
  docker-compose up -d
