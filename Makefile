DC=docker-compose
APP=$(DC) exec -u 1000 php
DB=$(DC) exec supplier-api-postgres
DBT=$(DC) exec -T supplier-api-postgres

default:
	@echo 'Please provide correct command'

up:
	$(DC) up -d --build

down:
	$(DC) down

restart: down up

migrate:
	$(APP) php artisan migrate

composer:
	$(APP) composer install
	$(APP) composer install --working-dir=./tools/php-cs-fixer

setup-test:
	$(DB) mysql -uroot -psecret -e "CREATE DATABASE IF NOT EXISTS test;"
	$(APP) php artisan migrate:fresh --env=testing

test:
	$(APP) php artisan optimize:clear --env=testing
	$(APP) php artisan test

install: up composer migrate

bash:
	$(APP) /bin/bash

ide-helper:
	$(APP) php artisan clear-compiled
	$(APP) php artisan ide-helper:generate
	$(APP) php artisan ide-helper:models -N
	$(APP) php artisan ide-helper:meta

cs-fix:
	$(APP) ./vendor/bin/php-cs-fixer fix app
