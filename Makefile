DC  = docker compose
RUN = $(DC) run --rm --no-deps app

.PHONY: setup up down logs test analyse shell migrate demo

## First run: build images, install dependencies, create .env, migrate
setup:
	test -f .env || cp .env.example .env
	test -f sources.json || cp sources.example.json sources.json
	$(DC) build
	$(RUN) composer install
	$(RUN) php artisan key:generate
	$(DC) up -d postgres redis rabbitmq
	$(DC) run --rm app php artisan migrate --force
	$(DC) up -d

up:
	$(DC) up -d

down:
	$(DC) down

logs:
	$(DC) logs -f app consumer scheduler

test:
	$(DC) up -d postgres redis rabbitmq
	$(DC) run --rm app php artisan test

analyse:
	$(RUN) vendor/bin/phpstan analyse --memory-limit=1G

migrate:
	$(DC) run --rm app php artisan migrate

shell:
	$(DC) exec app bash

## Send a few sample webhooks to the local instance
demo:
	$(DC) exec app php artisan hookrelay:demo
