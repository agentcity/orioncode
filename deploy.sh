#!/bin/bash
# Остановить скрипт при ошибке
set -e

echo "🚀 Начинаем деплой..."

# 1. Подтягиваем свежий код из Git
git pull origin main

# 2. Сборка образов (с использованием кеша для скорости)
docker-compose -f docker-compose.yml -f docker-compose.prod.yml build

# 3. Перезапуск контейнеров
# --remove-orphans удалит старые контейнеры, если вы меняли названия
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d --remove-orphans

# 4. Выполнение операций внутри контейнера
echo "🧹 Очистка кеша..."
docker exec orion_backend php bin/console cache:clear --env=prod

echo "database Миграции базы данных..."
# --no-interaction важен для скриптов
docker exec orion_backend php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "⚙️ Прогрев кеша..."
docker exec orion_backend php bin/console cache:warmup --env=prod

echo "✅ Деплой успешно завершен!"