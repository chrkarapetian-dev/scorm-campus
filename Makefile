.PHONY: up down rebuild-cache clear migrate exec-db exec-app prod-up prod-down prod-rebuild-cache prod-clear prod-migrate prod-exec-db prod-exec-app

LOCAL = docker compose --env-file .env -f compose.yaml
PROD = docker compose --env-file .env.prod -f compose.prod.yaml

up:
	@test -f .env || cp .env.example .env
	$(LOCAL) up -d --build --wait
	$(LOCAL) exec -T app php artisan migrate --force

down:
	$(LOCAL) down

rebuild-cache:
	$(LOCAL) exec -T app php artisan courses:rebuild-cache
clear:
	$(LOCAL) exec -T app php artisan optimize:clear
migrate:
	$(LOCAL) exec -T app php artisan migrate --force
exec-db:
	$(LOCAL) exec db sh -c 'exec mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'
exec-app:
	$(LOCAL) exec app sh

prod-up:
	@docker/production/deploy.sh
prod-down:
	$(PROD) down
prod-rebuild-cache:
	$(PROD) exec -T app php artisan courses:rebuild-cache
prod-clear:
	$(PROD) exec -T app php artisan optimize:clear
prod-migrate:
	@docker/production/deploy.sh migrate
prod-exec-db:
	$(PROD) exec db sh -c 'exec mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'
prod-exec-app:
	$(PROD) exec app sh
