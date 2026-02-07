# VSISPanel Makefile
# Usage: make [command]

.PHONY: help install up down restart build logs shell test fresh migrate seed cache optimize

# Default target
help:
	@echo "VSISPanel Development Commands"
	@echo ""
	@echo "Setup:"
	@echo "  make install     - First time setup (build, install deps, setup env)"
	@echo "  make build       - Build Docker containers"
	@echo ""
	@echo "Docker:"
	@echo "  make up          - Start all services"
	@echo "  make up-dev      - Start with dev services (node, mailpit)"
	@echo "  make down        - Stop all services"
	@echo "  make restart     - Restart all services"
	@echo "  make logs        - View all logs"
	@echo "  make logs-php    - View PHP logs"
	@echo ""
	@echo "Development:"
	@echo "  make shell       - Open PHP container shell"
	@echo "  make shell-mysql - Open MySQL shell"
	@echo "  make shell-redis - Open Redis CLI"
	@echo "  make npm         - Run npm commands (usage: make npm cmd='install')"
	@echo "  make artisan     - Run artisan commands (usage: make artisan cmd='migrate')"
	@echo ""
	@echo "Database:"
	@echo "  make migrate     - Run migrations"
	@echo "  make seed        - Run seeders"
	@echo "  make fresh       - Fresh migrate with seed"
	@echo "  make rollback    - Rollback last migration"
	@echo ""
	@echo "Testing:"
	@echo "  make test        - Run all tests"
	@echo "  make test-unit   - Run unit tests"
	@echo "  make test-feature- Run feature tests"
	@echo "  make coverage    - Run tests with coverage"
	@echo ""
	@echo "Cache:"
	@echo "  make cache       - Clear all caches"
	@echo "  make optimize    - Optimize for production"
	@echo ""
	@echo "Linting:"
	@echo "  make lint        - Run PHP CS Fixer"
	@echo "  make lint-fix    - Fix code style issues"
	@echo "  make analyze     - Run PHPStan analysis"

# =============================================================================
# Setup
# =============================================================================

install:
	@echo "Setting up VSISPanel..."
	@cp -n .env.example .env || true
	@make build
	@make up
	@sleep 10
	@make composer cmd="install"
	@make artisan cmd="key:generate"
	@make artisan cmd="migrate --seed"
	@make npm cmd="install"
	@make npm cmd="run build"
	@echo "Setup complete! Visit http://localhost:8000"

build:
	docker compose build

# =============================================================================
# Docker Commands
# =============================================================================

up:
	docker compose up -d

up-dev:
	docker compose --profile dev up -d

up-worker:
	docker compose --profile worker up -d

up-all:
	docker compose --profile dev --profile worker up -d

down:
	docker compose --profile dev --profile worker down

restart:
	@make down
	@make up

logs:
	docker compose logs -f

logs-php:
	docker compose logs -f php

logs-nginx:
	docker compose logs -f nginx

logs-mysql:
	docker compose logs -f mysql

# =============================================================================
# Shell Access
# =============================================================================

shell:
	docker compose exec php sh

shell-root:
	docker compose exec -u root php sh

shell-mysql:
	docker compose exec mysql mysql -u root -p

shell-redis:
	docker compose exec redis redis-cli -a secret

# =============================================================================
# Composer & NPM
# =============================================================================

composer:
	docker compose exec php composer $(cmd)

npm:
	docker compose run --rm node npm $(cmd)

artisan:
	docker compose exec php php artisan $(cmd)

# =============================================================================
# Database
# =============================================================================

migrate:
	docker compose exec php php artisan migrate

seed:
	docker compose exec php php artisan db:seed

fresh:
	docker compose exec php php artisan migrate:fresh --seed

rollback:
	docker compose exec php php artisan migrate:rollback

# =============================================================================
# Testing
# =============================================================================

test:
	docker compose exec php php artisan test

test-unit:
	docker compose exec php php artisan test --testsuite=Unit

test-feature:
	docker compose exec php php artisan test --testsuite=Feature

coverage:
	docker compose exec php php artisan test --coverage

test-parallel:
	docker compose exec php php artisan test --parallel

# =============================================================================
# Cache Management
# =============================================================================

cache:
	docker compose exec php php artisan cache:clear
	docker compose exec php php artisan config:clear
	docker compose exec php php artisan route:clear
	docker compose exec php php artisan view:clear

optimize:
	docker compose exec php php artisan config:cache
	docker compose exec php php artisan route:cache
	docker compose exec php php artisan view:cache
	docker compose exec php php artisan event:cache

# =============================================================================
# Code Quality
# =============================================================================

lint:
	docker compose exec php ./vendor/bin/pint --test

lint-fix:
	docker compose exec php ./vendor/bin/pint

analyze:
	docker compose exec php ./vendor/bin/phpstan analyse

# =============================================================================
# Production
# =============================================================================

prod-build:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml build

prod-up:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

prod-down:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml down

# =============================================================================
# Swagger/API Docs
# =============================================================================

swagger:
	docker compose exec php php artisan l5-swagger:generate

# =============================================================================
# Utilities
# =============================================================================

status:
	docker compose ps

prune:
	docker system prune -af
	docker volume prune -f

backup-db:
	docker compose exec mysql mysqldump -u root -psecret vsispanel > backup_$(shell date +%Y%m%d_%H%M%S).sql

restore-db:
	docker compose exec -T mysql mysql -u root -psecret vsispanel < $(file)
