# Переменные
DC = docker-compose
WS = orion_websocket
BACK = orion_backend
FRONT = orion_frontend

# Переменные для продуктивного сервера
SSH_HOST = orion@orioncode.ru
BASE_DIR = /var/www/orioncode
RELEASE_NAME = $(shell date +%Y.%m.%d-%H.%M.%S)
RELEASE_DIR = $(BASE_DIR)/releases/$(RELEASE_NAME)
CURRENT_DIR = $(BASE_DIR)/current
RSYNC_EXCLUDE = --exclude='.git' --exclude='node_modules' --exclude='vendor' --exclude='var/cache' --exclude='.env'
DC_PROD = docker compose -p orion_prod
DC_DEV = docker-compose

# По умолчанию показываем помощь
help:
	@echo "Доступные команды:"
	@echo "  make reset-ws   - Полный перезапуск и пересборка вебсокетов"
	@echo "  make logs-ws     - Логи вебсокетов в реальном времени"
	@echo "  make restart     - Перезапуск всех контейнеров"
	@echo "  make build       - Полная пересборка проекта"
	@echo "  make cache       - Очистка кэша Symfony"

# Тот самый Hard Reset для вебсокетов
reset-ws:
	$(DC) stop $(WS)
	$(DC) rm -f $(WS)
	$(DC) up -d --build $(WS)
	@echo "🚀 Вебсокеты пересобраны и запущены!"

reset-bk:
	$(DC) stop $(BACK)
	$(DC) rm -f $(BACK)
	$(DC) up -d --build $(BACK)
	@echo "🚀 Бэкенд пересобран и запущен!"

reset-fr:
	$(DC) stop $(FRONT)
	$(DC) rm -f $(FRONT)
	$(DC) up -d --build $(FRONT)
	@echo "🚀 Фронтенд пересобран и запущен!"

# Логи сокетов
logs-ws:
	$(DC) logs -f $(WS)

# Перезапуск всего
restart:
	$(DC) restart

# Полная пересборка без кэша
build:
	$(DC) up -d --build

# Очистка кэша бэкенда
cache:
	$(DC) exec $(BACK) php bin/console cache:clear

deploy:
	@echo "📦 Создание релиза $(RELEASE_NAME)..."
	ssh $(SSH_HOST) "mkdir -p $(RELEASE_DIR)"

	@echo "🚀 Загрузка кода..."
	rsync -avz $(RSYNC_EXCLUDE) ./ $(SSH_HOST):$(RELEASE_DIR)

	@echo "🔗 Настройка связей (shared .env)..."
	ssh $(SSH_HOST) "ln -sfn $(BASE_DIR)/shared/.env $(RELEASE_DIR)/.env"

	@echo "🏗️ Сборка Docker на сервере..."
	ssh $(SSH_HOST) "cd $(RELEASE_DIR) && docker compose -f docker-compose.prod.yml up -d --build"

	@echo "🔄 Переключение симлинка..."
	ssh $(SSH_HOST) "ln -sfn $(RELEASE_DIR) $(CURRENT_DIR)"

	@echo "🐘 Миграции и кэш..."
	ssh $(SSH_HOST) "docker exec -t -e APP_ENV=prod orion_backend_prod php bin/console doctrine:migrations:migrate --no-interaction"
	ssh $(SSH_HOST) "docker exec -t -e APP_ENV=prod orion_backend_prod php bin/console cache:clear"

	@echo "🧹 Удаление старых релизов (оставляем последние 3)..."
	ssh $(SSH_HOST) "cd $(BASE_DIR)/releases && ls -1t | tail -n +4 | xargs rm -rf"
	@echo "✅ Деплой завершен: https://app.orioncode.ru"

deploy-rollback:
	@echo "⏪ Откат на предыдущий релиз..."
	@ssh $(SSH_HOST) "cd $(BASE_DIR)/releases && \
		PREV_REL=\$$(ls -1t | sed -n '2p') && \
		if [ -n \"\$$PREV_REL\" ]; then \
			ln -sfn $(BASE_DIR)/releases/\$$PREV_REL $(CURRENT_DIR) && \
			cd $(CURRENT_DIR) && \
			docker compose -p orion_prod -f docker-compose.prod.yml up -d --remove-orphans && \
			echo \"✅ Успешно откатились на релиз: \$$PREV_REL\"; \
		else \
			echo \"❌ Предыдущий релиз не найден в папке releases\"; \
		fi"


# --- КОМАНДЫ ДЛЯ ПРОДАКШЕНА (JINO) ---

# Посмотреть роуты на ПРОДЕ
prod-routes:
	ssh $(SSH_HOST) "docker exec -t -e APP_ENV=prod orion_backend_prod php bin/console debug:router"

# Очистить кэш на ПРОДЕ
prod-cache-cl:
	ssh $(SSH_HOST) "docker exec -t -e APP_ENV=prod orion_backend_prod php bin/console cache:clear"

# Посмотреть логи бэкенда на ПРОДЕ
prod-logs:
	ssh $(SSH_HOST) "docker logs -f orion_backend_prod"

# Проверить статус БД на проде
prod-db-status:
	ssh $(SSH_HOST) "docker exec -t orion_backend_prod php bin/console doctrine:migrations:status"

# Удалить старые релизы (оставить последние 3)
prod-clean-releases:
	ssh $(SSH_HOST) "cd $(BASE_DIR)/releases && ls -1t | tail -n +4 | xargs rm -rf"


# --- КОМАНДЫ ДЛЯ ВЕБСОКЕТОВ (Node.js) ---

# Логи сокетов локально (Mac)
dev-ws-logs:
	$(DC_DEV) logs -f orion_websocket

# Перезапуск сокетов локально (быстрый сброс соединений)
dev-ws-restart:
	$(DC_DEV) restart orion_websocket

# Логи сокетов на ПРОДЕ (Jino)
# Поможет увидеть, прилетают ли Typing и NewMessage из Redis
prod-ws-logs:
	ssh $(SSH_HOST) "docker logs -f orion_websocket_prod"

# Перезапуск сокетов на ПРОДЕ
prod-ws-restart:
	ssh $(SSH_HOST) "docker restart orion_websocket_prod"

# --- МОНИТОРИНГ REDIS (КАНАЛ CHAT_MESSAGES) ---

# Слушать сообщения в Redis локально (Mac)
dev-redis-sub:
	$(DC_DEV) exec orion_redis redis-cli SUBSCRIBE chat_messages

# Слушать сообщения в Redis на ПРОДЕ (Jino)
# Нажми Ctrl+C, чтобы остановить прослушивание
prod-redis-sub:
	ssh -t $(SSH_HOST) "docker exec orion_redis_prod redis-cli SUBSCRIBE chat_messages"

# Мониторинг всех команд Redis на проде
prod-redis-monitor:
	ssh -t $(SSH_HOST) "docker exec orion_redis_prod redis-cli monitor"

# Если хочешь именно зайти внутрь (интерактивно), используй -t у SSH:
prod-db-shell:
	ssh -t $(SSH_HOST) "docker exec -it orion_db_prod psql -U $(DB_USER) -d $(DB_NAME)"


# --- МОБИЛЬНОЕ ПРИЛОЖЕНИЕ (Capacitor / Android) ---

# Полная сборка мобильной версии через твой скрипт
mobile-build:
	@echo "📱 Запуск сборки мобильной версии из папки frontend..."
	chmod +x frontend/build-mobile.sh
	cd frontend && ./build-mobile.sh

# Открыть проект в Android Studio (удобно для финальной сборки APK)
mobile-open:
	cd frontend && npx cap open android

# Быстрая синхронизация изменений фронтенда без пересборки натива
mobile-copy:
	cd frontend && npm run build && npx cap copy

# Проверка состояния Capacitor (плагины, платформы)
mobile-status:
	cd frontend && npx cap doctor
