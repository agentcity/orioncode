# Переменные
DC = docker-compose
WS = orion_websocket
BACK = orion_backend
FRONT = orion_frontend

# Переменные для продуктивного сервера
SSH_HOST = orion@orioncode.ru
BASE_DIR = /var/www/orioncode
RELEASE_NAME = $(shell date +%Y%m%d%H%M%S)
RELEASE_DIR = $(BASE_DIR)/releases/$(RELEASE_NAME)
CURRENT_DIR = $(BASE_DIR)/current
RSYNC_EXCLUDE = --exclude='.git' --exclude='node_modules' --exclude='vendor' --exclude='var/cache' --exclude='.env'


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
	ssh $(SSH_HOST) "cd $(CURRENT_DIR) && docker compose -f docker-compose.prod.yml -p orion_prod exec -T orion_backend_prod php bin/console doctrine:migrations:migrate --no-interaction"
	ssh $(SSH_HOST) "cd $(CURRENT_DIR) && docker compose -f docker-compose.prod.yml -p orion_prod exec -T orion_backend_prod php bin/console cache:clear"

	@echo "🧹 Удаление старых релизов (оставляем последние 3)..."
	ssh $(SSH_HOST) "cd $(BASE_DIR)/releases && ls -1t | tail -n +4 | xargs rm -rf"
	@echo "✅ Деплой завершен: https://app.orioncode.ru"

deploy-rollback:
	@echo "⏪ Откат на предыдущий релиз..."
	ssh $(SSH_HOST) "cd $(BASE_DIR)/releases && \
		PREV_REL=\$$(ls -1t | sed -n '2p') && \
		if [ -n \"\$$PREV_REL\" ]; then \
			ln -sfn $(BASE_DIR)/releases/\$$PREV_REL $(CURRENT_DIR) && \
			cd $(CURRENT_DIR) && \
			docker-compose -f docker-compose.prod.yml up -d && \
			echo \"✅ Откатились на \$$PREV_REL\"; \
		else \
			echo \"❌ Предыдущий релиз не найден\"; \
		fi"