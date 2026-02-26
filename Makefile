# Makefile for Event Sourcing Chess Example
# Run commands in Docker containers

.PHONY: help build up down restart shell test phpstan phpcs phpmd grumphp migrate fixtures cache-clear cache-warmup logs

# Default target
help: ## Show this help message
	@echo "Available commands:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

# Docker Compose commands
build: ## Build the Docker images
	docker compose build

up: ## Start all services
	docker compose up -d

down: ## Stop all services
	docker compose down

restart: ## Restart all services
	docker compose restart

logs: ## Show logs from all services
	docker compose logs -f

logs-%: ## Show logs from a specific service (e.g., make logs-php)
	docker compose logs -f $*

# PHP container shell access
shell: ## Get a shell in the PHP container
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php bash; \
	else \
		echo "PHP container not running, using local shell"; \
		bash; \
	fi

# Testing and code quality
test: ## Run PHPUnit tests
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php ./bin/phpunit; \
	else \
		echo "PHP container not running, running locally"; \
		./bin/phpunit; \
	fi

test-coverage: ## Run PHPUnit tests with coverage
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php ./bin/phpunit --coverage-html=var/coverage; \
	else \
		echo "PHP container not running, running locally"; \
		./bin/phpunit --coverage-html=var/coverage; \
	fi

test-filter: ## Run PHPUnit tests with filter (usage: make test-filter FILTER=TestClass::testMethod)
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php ./bin/phpunit --filter=$(FILTER); \
	else \
		echo "PHP container not running, running locally"; \
		./bin/phpunit --filter=$(FILTER); \
	fi

# Code analysis
phpstan: ## Run PHPStan static analysis
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php ./bin/phpstan analyse; \
	else \
		echo "PHP container not running, running locally"; \
		./bin/phpstan analyse; \
	fi

phpcs: ## Run PHP CodeSniffer
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php ./bin/phpcs; \
	else \
		echo "PHP container not running, running locally"; \
		./bin/phpcs; \
	fi

phpcbf: ## Run PHP CodeSniffer fix
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php ./bin/phpcbf; \
	else \
		echo "PHP container not running, running locally"; \
		./bin/phpcbf; \
	fi

phpmd: ## Run PHP Mess Detector
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php ./bin/phpmd src,tests text phpmd.xml; \
	else \
		echo "PHP container not running, running locally"; \
		./bin/phpmd src,tests text phpmd.xml; \
	fi

grumphp: ## Run GrumPHP (includes multiple tools)
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php ./bin/grumphp run; \
	else \
		echo "PHP container not running, running locally"; \
		./bin/grumphp run; \
	fi

# Database commands
migrate: ## Run Doctrine migrations
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php ./bin/console doctrine:migrations:migrate --no-interaction; \
	else \
		echo "PHP container not running, running locally"; \
		./bin/console doctrine:migrations:migrate --no-interaction; \
	fi

migrate-status: ## Show Doctrine migration status
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php ./bin/console doctrine:migrations:status; \
	else \
		echo "PHP container not running, running locally"; \
		./bin/console doctrine:migrations:status; \
	fi

fixtures: ## Load Doctrine fixtures
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php ./bin/console doctrine:fixtures:load --no-interaction; \
	else \
		echo "PHP container not running, running locally"; \
		./bin/console doctrine:fixtures:load --no-interaction; \
	fi

db-create: ## Create the database
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php ./bin/console doctrine:database:create --if-not-exists; \
	else \
		echo "PHP container not running, running locally"; \
		./bin/console doctrine:database:create --if-not-exists; \
	fi

db-drop: ## Drop the database
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php ./bin/console doctrine:database:drop --force; \
	else \
		echo "PHP container not running, running locally"; \
		./bin/console doctrine:database:drop --force; \
	fi

db-reset: ## Reset database (drop and recreate)
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php ./bin/console doctrine:database:drop --force --if-exists; \
		docker compose exec php ./bin/console doctrine:database:create; \
	else \
		echo "PHP container not running, running locally"; \
		./bin/console doctrine:database:drop --force --if-exists; \
		./bin/console doctrine:database:create; \
	fi

recreate-db: ## Recreate database and run migrations (from composer scripts)
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php composer run-script recreate-db; \
	else \
		echo "PHP container not running, running locally"; \
		composer run-script recreate-db; \
	fi

# Cache commands
cache-clear: ## Clear Symfony cache
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php ./bin/console cache:clear; \
	else \
		echo "PHP container not running, running locally"; \
		./bin/console cache:clear; \
	fi

cache-warmup: ## Warm up Symfony cache
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php ./bin/console cache:warmup; \
	else \
		echo "PHP container not running, running locally"; \
		./bin/console cache:warmup; \
	fi

# Console commands
console: ## Run Symfony console (usage: make console CMD="debug:router")
	@if docker compose ps php | grep -q "Up"; then \
		docker compose exec php ./bin/console $(CMD); \
	else \
		echo "PHP container not running, running locally"; \
		./bin/console $(CMD); \
	fi

# Composer commands
composer-install: ## Install Composer dependencies
	docker compose exec php composer install

composer-update: ## Update Composer dependencies
	docker compose exec php composer update

# RabbitMQ commands
rabbitmq-status: ## Check RabbitMQ status
	docker compose exec rabbitmq rabbitmqctl status

# Redis commands
redis-cli: ## Access Redis CLI
	docker compose exec redis redis-cli

# Full development setup
setup: ## Full development setup (build, up, install deps, migrate, fixtures)
	docker compose build
	docker compose up -d
	docker compose exec php composer install
	docker compose exec php ./bin/console doctrine:database:create --if-not-exists
	docker compose exec php ./bin/console doctrine:migrations:migrate --no-interaction
	docker compose exec php ./bin/console doctrine:fixtures:load --no-interaction
	docker compose exec php ./bin/console cache:warmup

# Clean up
clean: ## Clean up Docker containers and volumes
	docker compose down -v --remove-orphans