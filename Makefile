# Переменные
DC = docker-compose
WS = orion_websocket
BACK = orion_backend
FRONT = orion_frontend

# Переменные для продуктивного сервера
SSH_HOST = orion@81.200.158.70
# SSH_HOST = orion@orioncode.ru
BASE_DIR = /var/www/orioncode
RELEASE_NAME = $(shell date +%Y.%m.%d-%H.%M.%S)
RELEASE_DIR = $(BASE_DIR)/releases/$(RELEASE_NAME)
CURRENT_DIR = $(BASE_DIR)/current
RSYNC_EXCLUDE = --exclude='.git' --exclude='.idea' --exclude='node_modules' --exclude='vendor' --exclude='var/cache' --exclude='.env' --exclude='backend/public/uploads' --exclude='frontend/mobile'
DC_PROD = docker compose -p orion_prod
DC_DEV = docker-compose
DC_PROD_CMD = docker compose -p orion_prod -f docker-compose.prod.yml

.PHONY: help dev build deploy rollback prod-status prod-logs prod-ws-logs dev-redis-sub prod-redis-sub

help: ## Показать это справочное сообщение
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-25s\033[0m %s\n", $$1, $$2}'


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


restart-fr:
	$(DC) restart $(FRONT)

# --- РАЗРАБОТКА  ---
dev-up: ## Запустить локальную версию (dev)
	@echo "🛡️ Проверяем порт 8080 и останавливаем системный Apache, если он запущен..."
	-sudo apachectl stop 2>/dev/null || true
	-sudo killall httpd 2>/dev/null || true
	@echo "🚀 Запуск  Docker..."
	$(DC_DEV) up -d

dev-build: ## Пересобрать локальные контейнеры (с остановкой системного Apache)
	@echo "🛡️ Проверяем порт 8080 и останавливаем системный Apache, если он запущен..."
	-sudo apachectl stop 2>/dev/null || true
	-sudo killall httpd 2>/dev/null || true
	@echo "🚀 Запуск сборки Docker..."
	$(DC_DEV) up -d --build

dev-routes: ## Посмотреть роуты Symfony (локально)
	$(DC_DEV) exec orion_backend php bin/console debug:router

dev-docker-claean: ## Очистка от мусор для освобождения мпеста
	docker builder prune -a -f
	docker system prune -a --volumes -f



# Очистка кэша бэкенда
dev-cache-clear:
	$(DC_DEV) exec orion_backend php bin/console cache:clear

dev-redis-sub: ## Слушать Redis chat_messages (локально)
	$(DC_DEV) exec orion_redis redis-cli SUBSCRIBE chat_messages

dev-logs: ## Посмотреть логи бэкенда (локально на Mac)
	$(DC_DEV) logs -f orion_backend


dev-backend-logs-20: ## Логи бэкенда
	$(DC_DEV) exec orion_backend tail -n 20 var/log/dev.log

dev-db-sync: ## Синхронизировать структуру БД с PHP-кодом (локально на Mac)
	@echo "🔄 Обновление структуры базы данных..."
	$(DC_DEV) exec orion_backend php bin/console doctrine:schema:update --force
	@echo "✅ База данных синхронизирована!"

dev-db-migrate: ## Запустить миграции (локально на Mac)
	$(DC_DEV) exec orion_backend php bin/console make:migration
	$(DC_DEV) exec orion_backend php bin/console doctrine:migrations:migrate --no-interaction

# --- ТЕСТИРОВАНИЕ ---

dev-test: ## Подготовка БД и запуск тестов (локально)
	@echo "🧪 Сброс тестового кэша..."
	@$(DC_DEV) exec orion_backend rm -rf var/cache/test
	@echo "🧪 Настройка тестовой БД..."
	@$(DC_DEV) exec -e APP_ENV=test orion_backend php bin/console doctrine:database:create --if-not-exists
	@$(DC_DEV) exec -e APP_ENV=test orion_backend php bin/console doctrine:schema:update --force
	@echo "🚀 Запуск PHPUnit..."
	@$(DC_DEV) exec -e APP_ENV=test orion_backend bin/phpunit

dev-test-filter: ## Запустить конкретный тест (пример: make dev-test-filter name=UserTest)
	$(DC_DEV) exec -e APP_ENV=test orion_backend bin/phpunit --filter $(name)

dev-test-frontend: ## Запустить тесты фронтенда
	cd frontend && npx playwright test

dev-test-frontend-ui: ## Запустить тесты фронтенда c визуализацией
	cd frontend && npx playwright test --ui


test-all: ## Запустить ВСЕ тесты (Бэк + Фронт)
	@make dev-test
	@make dev-test-frontend

# --- ПРОДАКШЕН (JINO) ---

SERVICES ?= orion_backend orion_frontend orion_nginx orion_websocket orion_redis

deploy:
	@echo "📦 Создание релиза $(RELEASE_NAME)..."
	ssh $(SSH_HOST) "mkdir -p $(RELEASE_DIR)"

	@echo "🚀 Загрузка кода..."
	rsync -avz $(RSYNC_EXCLUDE) ./ $(SSH_HOST):$(RELEASE_DIR)

	@echo "🔗 Настройка связей (shared .env)..."
	ssh $(SSH_HOST) "ln -sfn $(BASE_DIR)/shared/.env $(RELEASE_DIR)/.env"

	@echo "🏗️ Сборка Docker на сервере..."
	ssh $(SSH_HOST) "cd $(RELEASE_DIR) && docker compose -f docker-compose.prod.yml up -d --build $(SERVICES)"

	@echo "🔄 Переключение симлинка..."
	ssh $(SSH_HOST) "ln -sfn $(RELEASE_DIR) $(CURRENT_DIR)"

	@echo "🐘 Миграции и кэш..."
	@if echo "$(SERVICES)" | grep -q "backend"; then \
		ssh $(SSH_HOST) "docker exec -t -e APP_ENV=prod orion_backend_prod php bin/console doctrine:migrations:migrate --no-interaction"; \
		ssh $(SSH_HOST) "docker exec -t -e APP_ENV=prod orion_backend_prod php bin/console cache:clear"; \
	fi
	@echo "🧹 Удаление старых релизов (оставляем последние 3)..."
	ssh $(SSH_HOST) "cd $(BASE_DIR)/releases && ls -1t | tail -n +4 | xargs -I {} docker run --rm -v $(BASE_DIR)/releases:/cleanup alpine rm -rf /cleanup/{}"
	@echo "✅ Деплой завершен: https://app.orioncode.ru"

# Полный деплой (если менял БД, Redis или Nginx)
deploy-full:
	@echo "📦 Создание релиза $(RELEASE_NAME)..."
	ssh $(SSH_HOST) "mkdir -p $(RELEASE_DIR)"
	@echo "🚀 Загрузка кода..."
	rsync -avz $(RSYNC_EXCLUDE) ./ $(SSH_HOST):$(RELEASE_DIR)
	@echo "🔗 Настройка связей (shared .env)..."
	ssh $(SSH_HOST) "ln -sfn $(BASE_DIR)/shared/.env $(RELEASE_DIR)/.env"
	@echo "🏗️ Перезапуск инфраструктуры..."
	@echo "🧹 Очистка старых образов и кэша..."
	@ssh $(SSH_HOST) "docker image prune -f"
	@echo "🏗️ Сборка Docker на сервере..."
	ssh $(SSH_HOST) "cd $(RELEASE_DIR) && docker compose -f docker-compose.prod.yml up -d --build"
	@echo "🔄 Переключение симлинка..."
	ssh $(SSH_HOST) "ln -sfn $(RELEASE_DIR) $(CURRENT_DIR)"
	@echo "🐘 Миграции и кэш..."
	ssh $(SSH_HOST) "docker exec -t -e APP_ENV=prod orion_backend_prod php bin/console doctrine:migrations:migrate --no-interaction"
	ssh $(SSH_HOST) "docker exec -t -e APP_ENV=prod orion_backend_prod php bin/console cache:clear"
	@echo "🧹 Удаление старых релизов (оставляем последние 3)..."
	ssh $(SSH_HOST) "cd $(BASE_DIR)/releases && ls -1t | tail -n +4 | xargs -I {} docker run --rm -v $(BASE_DIR)/releases:/cleanup alpine rm -rf /cleanup/{}"
	@echo "✅ Деплой завершен: https://app.orioncode.ru"



deploy-rollback:
	@echo "⏪ Откат на предыдущий релиз..."
	@ssh $(SSH_HOST) "cd $(BASE_DIR)/releases && \
		PREV_REL=\$$(ls -1t | sed -n '2p') && \
		if [ -n \"\$$PREV_REL\" ]; then \
			ln -sfn $(BASE_DIR)/releases/\$$PREV_REL $(CURRENT_DIR) && \
			cd $(CURRENT_DIR) && \
			docker compose -p orion_prod -f docker-compose.prod.yml up -d --remove-orphans && \
            docker compose -p orion_prod exec -T orion_backend php bin/console cache:clear --env=prod || true; \
			echo \"✅ Успешно откатились на релиз: \$$PREV_REL\"; \
		else \
			echo \"❌ Предыдущий релиз не найден в папке releases\"; \
		fi"

deploy-safe: ## Сначала тесты, потом деплой
	@echo "🧪 Запуск тестов..."
	@make test-all && (echo "✅ Тесты пройдены! Начинаю деплой..."; make deploy) || (echo "❌ ДЕПЛОЙ ОТМЕНЕН: Тесты упали!"; exit 1)

## Проверка технической ошибки на проде - Использовать острожно внимание
prod-check-maintenance: ## Имитация работ на проде
	@echo "🔍 Определяем реальный путь проекта на Jino..."
	$(eval REAL_PATH := $(shell ssh $(SSH_HOST) "readlink -f $(CURRENT_DIR)"))

	@echo "🛠️ Останавливаем бэкенд [orion_backend] в проекте orion_prod..."
	@ssh $(SSH_HOST) "cd $(REAL_PATH) && docker compose -p orion_prod stop orion_backend"

	@echo "🔎 Проверяем ответ API (ожидаем 502/503 и заглушку)..."
	@sleep 3
	@curl -s -I http://api.orioncode.ru | grep -E "502|503" || ( \
		echo "❌ ОШИБКА: Заглушка не отдается! Проверь nginx/prod.conf и fastcgi_intercept_errors"; \
		ssh $(SSH_HOST) "cd $(REAL_PATH) && docker compose -p orion_prod start orion_backend"; \
		exit 1 \
	)

	@echo "✅ Заглушка работает! Восстанавливаем работу..."
	@ssh $(SSH_HOST) "cd $(REAL_PATH) && docker compose -p orion_prod start orion_backend"
	@echo "🚀 OrionCode снова в строю."






# --- МОНИТОРИНГ И ДЕБАГ (ПРОД) ---

prod-status: ## Проверить статус контейнеров и ресурсы на Jino
	@ssh $(SSH_HOST) "docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' && echo '' && docker stats --no-stream"

# ТОТАЛЬНЫЙ МОНИТОРИНГ ПРОДА
prod-status-total: ## Показать полный статус систем (Docker, RAM, Redis, WS)
	@echo "🚀 --- ORIONCODE SYSTEMS STATUS --- 🚀"
	@echo "📅 Время: $$(date)"
	@echo ""
	@echo "📦 [DOCKER CONTAINERS]"
	@ssh $(SSH_HOST) "docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'"
	@echo ""
	@echo "💾 [RESOURCES / MEMORY]"
	@ssh $(SSH_HOST) "docker stats --no-stream --format 'table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}'"
	@echo ""
	@echo "🔑 [REDIS STATS]"
	@ssh $(SSH_HOST) "docker exec orion_redis_prod redis-cli dbsize | sed 's/^/Ключей в базе: /'"
	@echo ""
	@echo "📡 [WEBSOCKET / API PORTS]"
	@ssh $(SSH_HOST) "echo 'API (80/443): ' && curl -s -I http://api.orioncode.ru | grep HTTP"
	@ssh $(SSH_HOST) "echo 'WS (3000-internal): ' && docker exec orion_websocket_prod netstat -tulpn | grep :3000 || echo 'OFFLINE'"
	@echo ""
	@echo "💻 [FRONTEND CHECK]"
	@echo -n "Status: app.orioncode.ru" && curl -s -o /dev/null -w "%{http_code}" app.orioncode.ru || echo "❌ CONNECTION_FAILED"
	@echo -n "\nJS Engine: " && ssh $(SSH_HOST) "docker exec orion_frontend_prod sh -c 'ls build/static/js/main.*.js >/dev/null 2>&1 && echo ✅_READY || echo ❌_EMPTY_BUILD'"
	@echo -n "Build Size: " && ssh $(SSH_HOST) "docker exec orion_frontend_prod du -sh build | awk '{print \$$1}'"
	@echo "\n⚙️ [BACKEND: SYMFONY ENGINE]"
	@echo -n "API Status: api.orioncode.ru" && curl -s -o /dev/null -w "%{http_code}" api.orioncode.ru || echo "❌ CONNECTION_FAILED"
	@echo -n "\nPHP-FPM Health: " && ssh $(SSH_HOST) "docker exec orion_backend_prod php-fpm -t 2>&1 | grep 'test is successful' >/dev/null && echo '✅ OK' || echo '❌ FAILED'"
	@echo -n "\nDatabase: " && ssh $(SSH_HOST) "docker exec orion_backend_prod php bin/console dbal:run-sql 'SELECT 1' --env=prod >/dev/null 2>&1 && echo ✅_CONNECTED || echo ❌_DB_ERROR"
	@echo "📜 [LAST BACKEND ERRORS]"
	@ssh $(SSH_HOST) "docker logs --tail 5 orion_backend_prod"
	@echo "---------------------------------------"

prod-logs: ## Логи бэкенда на Jino
	ssh $(SSH_HOST) "docker logs -f orion_backend_prod"

# Потоковое чтение логов Symfony прямо с прода
prod-logs-tail: ## Показать последние логи бэкенда на Jino
	@echo "📡 Подключаюсь к логам Symfony на проде..."
	@ssh $(SSH_HOST) "docker logs --tail 20 orion_backend_prod"

# Команда для поиска конкретных PHP ошибок
prod-find-errors: ## Найти последние критические ошибки в логах
	@echo "🔍 Ищу критические ошибки (CRITICAL/ERROR)..."
	@ssh $(SSH_HOST) "docker logs orion_backend_prod 2>&1 | tail -n 20"

prod-logs-messenger: ## Логи бэкенда на Jino
	ssh $(SSH_HOST) "docker exec orion_backend_prod php bin/console messenger:consume async -vv"

prod-nginx-logs-50: ## Логи бэкенда на Jino
	ssh $(SSH_HOST) "docker logs orion_nginx_prod --tail 50"


# Посмотреть роуты на ПРОДЕ
prod-routes:
	ssh $(SSH_HOST) "docker exec -t -e APP_ENV=prod orion_backend_prod php bin/console debug:router"

# Очистить кэш на ПРОДЕ
prod-cache-clear:
	ssh $(SSH_HOST) "docker exec -t -e APP_ENV=prod orion_backend_prod php bin/console cache:clear"

# Проверить статус БД на проде
prod-db-status:
	ssh $(SSH_HOST) "docker exec -t orion_backend_prod php bin/console doctrine:migrations:status"

# Удалить старые релизы (оставить последние 3)
prod-clean-releases:
	ssh $(SSH_HOST) "cd $(BASE_DIR)/releases && ls -1t | tail -n +4 | xargs rm -rf"

prod-create-user: ## Создать админа на Jino
	ssh -t $(SSH_HOST) "docker exec -it -e APP_ENV=prod orion_backend_prod php bin/console app:create-user"


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

# Слушать сообщения в Redis на ПРОДЕ (Jino)
# Нажми Ctrl+C, чтобы остановить прослушивание
prod-redis-sub:
	ssh -t $(SSH_HOST) "docker exec orion_redis_prod redis-cli SUBSCRIBE chat_messages"

# Мониторинг в реальном времени (видишь каждый приходящий запрос)
prod-redis-monitor: ## Следить за всеми операциями в Redis (LIVE)
	@echo "👀 Режим мониторинга (Ctrl+C для выхода)..."
	@ssh $(SSH_HOST) "docker exec orion_redis_prod redis-cli monitor"

# Если хочешь именно зайти внутрь (интерактивно), используй -t у SSH:
prod-db-shell:
	ssh -t $(SSH_HOST) "docker exec -it orion_db_prod psql -U $(DB_USER) -d $(DB_NAME)"

# Проверка: жив ли Redis и сколько в нем ключей
prod-redis-info: ## Показать общую статистику Redis на проде
	@echo "📊 Статистика Redis на Jino..."
	@ssh $(SSH_HOST) "docker exec orion_redis_prod redis-cli info memory | grep used_memory_human"
	@ssh $(SSH_HOST) "docker exec orion_redis_prod redis-cli dbsize | sed 's/^/Ключей в базе: /'"


# Список всех ключей (полезно, если чат "завис")
prod-redis-keys: ## Список всех ключей в базе Redis
	@echo "🔑 Список ключей в Redis:"
	@ssh $(SSH_HOST) "docker exec orion_redis_prod redis-cli keys '*'"

# Очистка Redis (использовать осторожно!)
prod-redis-flush: ## ПОЛНАЯ ОЧИСТКА Redis на проде
	@echo "⚠️  ВНИМАНИЕ: Очистка всех данных в Redis..."
	@ssh $(SSH_HOST) "docker exec orion_redis_prod redis-cli flushall"


# --- РАБОТА С БАЗОЙ ДАННЫХ (СЖАТИЕ GZIP) ---

prod-db-dump: ## Дамп базы с прода (сжатый gzip) в папку backups/
	@mkdir -p backups
	@echo "📡 Сжимаем и скачиваем дамп с Jino..."
	@ssh $(SSH_HOST) "docker exec orion_db_prod sh -c 'pg_dump -U \$$POSTGRES_USER \$$POSTGRES_DB | gzip -c'" > backups/backup_prod_$(shell date +%Y.%m.%d-%H.%M.%S).sql.gz
	@echo "✅ Сжатый дамп сохранен: backups/backup_prod_$(shell date +%Y.%m.%d-%H.%M.%S).sql.gz"
	@du -h backups/backup_prod_*.gz | tail -n 1

dev-db-restore: ## Распаковать и накатить последний дамп на ЛОКАЛЬНУЮ БД (Mac)
	@echo "🔄 Распаковка и восстановление локальной БД..."
	@ls -t backups/*.sql.gz | head -n 1 | xargs -I {} sh -c 'gunzip -c {} | $(DC_DEV) exec -T orion_db psql -U app_user -d app_db'
	@echo "✅ Локальная база синхронизирована с продом!"

DB_CMD=docker exec orion_db_prod psql -U orion_admin -d orion_db -c

## --- FULL SYSTEM STATUS ---
prod-db-status:
	@echo "--- ТАБЛИЦЫ ---"
	ssh orion@81.200.158.70 "docker exec orion_db_prod psql -U orion_admin -d orion_db -c '\dt+'"
	@echo "\n--- СООБЩЕНИЯ (ReplyTo) ---"
	ssh orion@81.200.158.70 "docker exec orion_db_prod psql -U orion_admin -d orion_db -c 'SELECT id, left(text, 40), reply_to_id, sent_at FROM messages ORDER BY sent_at DESC LIMIT 5;'"
	@echo "\n--- АККАУНТЫ (Telegram Token) ---"
	ssh orion@81.200.158.70 "docker exec orion_db_prod psql -U orion_admin -d orion_db -c \"SELECT id, name, credentials->>'telegram_token' as token, status FROM accounts;\""
	@echo "\n--- AI ЮЗЕР (Орион Кот) ---"
	ssh orion@81.200.158.70 "docker exec orion_db_prod psql -U orion_admin -d orion_db -c \"SELECT id, first_name, last_name, roles FROM users WHERE id = '00000000-0000-0000-0000-000000000000';\""

prod-db-inspect:
	@echo "--- [1] АККАУНТЫ (Клиенты и Токены) ---"
	@ssh $(SSH_HOST) "docker exec orion_db_prod psql -U orion_admin -d orion_db -c \"SELECT id, name, type, credentials, status FROM accounts;\""
	@echo "\n--- [2] ПОЛЬЗОВАТЕЛИ (Команда и Орион Кот) ---"
	@ssh $(SSH_HOST) "docker exec orion_db_prod psql -U orion_admin -d orion_db -c \"SELECT id, email, first_name, last_name, roles FROM users ORDER BY created_at DESC LIMIT 10;\""

	@echo "\n--- [3] КОНТАКТЫ (Клиенты из мессенджеров) ---"
	@ssh $(SSH_HOST) "docker exec orion_db_prod psql -U orion_admin -d orion_db -c \"SELECT id, main_name, source, external_id, account_id FROM contacts LIMIT 10;\""

	@echo "\n--- [4] БЕСЕДЫ (Активность чатов) ---"
	@ssh $(SSH_HOST) "docker exec orion_db_prod psql -U orion_admin -d orion_db -c \"SELECT id, type, status, unread_count, left(last_message_at::text, 19) as last_msg FROM conversations ORDER BY last_message_at DESC LIMIT 5;\""

	@echo "\n--- [5] СООБЩЕНИЯ (ReplyTo и Направление) ---"
	@ssh $(SSH_HOST) "docker exec orion_db_prod psql -U orion_admin -d orion_db -c \"SELECT id, left(text, 30) as text, direction, reply_to_id, sender_type FROM messages ORDER BY sent_at DESC LIMIT 10;\""

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
