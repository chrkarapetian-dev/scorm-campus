.PHONY: up down

up:
	@test -f .env || cp .env.example .env
	docker compose up -d --build
	docker compose exec -T app php artisan migrate --force

down:
	docker compose down
