# Théâtre Argane — developer commands
# Backend commands run inside the `backend` container when it is up,
# otherwise on the host (requires PHP 8.3+ and Composer locally).

.DEFAULT_GOAL := help
SHELL := /bin/sh

COMPOSE := docker compose
BACKEND_UP := $(shell $(COMPOSE) ps --status running -q backend 2>/dev/null)

ifeq ($(BACKEND_UP),)
  PHP_RUN := cd backend &&
else
  PHP_RUN := $(COMPOSE) exec -T backend
endif
NPM_RUN := cd frontend && npm run

.PHONY: help up down restart logs sh install test test-back test-front lint lint-back lint-front fix fixtures db-reset

help: ## List available targets
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[33m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Start the local stack (mysql, backend, frontend, mailpit)
	$(COMPOSE) up -d --build

down: ## Stop the local stack
	$(COMPOSE) down

restart: down up ## Restart the local stack

logs: ## Follow logs of all services
	$(COMPOSE) logs -f --tail=100

sh: ## Open a shell in the backend container
	$(COMPOSE) exec backend sh

install: ## Install backend and frontend dependencies on the host
	cd backend && composer install
	cd frontend && npm install

test: test-back test-front ## Run all tests

test-back: ## Run PHPUnit
	$(PHP_RUN) php bin/phpunit

test-front: ## Run Vitest
	$(NPM_RUN) test

lint: lint-back lint-front ## Run all linters and static analysis

lint-back: ## PHP-CS-Fixer (dry run) and PHPStan
	$(PHP_RUN) vendor/bin/php-cs-fixer fix --dry-run --diff
	$(PHP_RUN) vendor/bin/phpstan analyse --memory-limit=1G

lint-front: ## ESLint, Prettier check and type check
	$(NPM_RUN) lint
	$(NPM_RUN) format:check
	$(NPM_RUN) typecheck

fix: ## Auto-fix code style on both sides
	$(PHP_RUN) vendor/bin/php-cs-fixer fix
	$(NPM_RUN) format

db-reset: ## Drop, create and migrate the database
	$(PHP_RUN) php bin/console doctrine:database:drop --force --if-exists
	$(PHP_RUN) php bin/console doctrine:database:create
	$(PHP_RUN) php bin/console doctrine:migrations:migrate --no-interaction

fixtures: db-reset ## Reset the database and load demo data (deterministic, ~30 s)
	$(PHP_RUN) php bin/console cache:clear --no-debug -q
	$(PHP_RUN) php -d memory_limit=1G bin/console doctrine:fixtures:load --no-interaction --no-debug
