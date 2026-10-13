# Atalhos — tudo roda em Docker, nada é instalado na sua máquina.
.PHONY: setup up down logs test assets demo shell migrate fresh ai

setup: ## Primeira instalação completa
	@test -f .env || cp .env.example .env
	docker compose build
	docker compose run --rm --no-deps --entrypoint "" app composer install --no-interaction
	docker compose run --rm --no-deps --entrypoint "" app php artisan key:generate --force
	docker compose run --rm node sh -c "npm install && npm run build"
	docker compose up -d
	@sleep 8
	docker compose exec db psql -U prumo -d prumo -tc "SELECT 1 FROM pg_database WHERE datname='prumo_test'" | grep -q 1 || docker compose exec db psql -U prumo -d prumo -c "CREATE DATABASE prumo_test"
	@echo "\n✅ Prumo no ar: http://localhost:8080  ·  e-mails: http://localhost:8025\n"

up: ## Sobe os serviços
	docker compose up -d

down: ## Para os serviços
	docker compose down

logs: ## Logs da aplicação
	docker compose logs -f app scheduler queue

test: ## Roda a suíte de testes (banco prumo_test)
	docker compose exec app php artisan test

assets: ## Recompila CSS/JS
	docker compose run --rm node sh -c "npm install && npm run build"

demo: ## Cria a conta de demonstração (demo@prumo.local / demo12345)
	docker compose exec app php artisan db:seed --class=DemoSeeder

migrate: ## Aplica migrations
	docker compose exec app php artisan migrate

shell: ## Terminal dentro do container
	docker compose exec app bash

ai: ## IA 100% local e gratuita (Ollama + modelo leve)
	docker compose --profile ai up -d ollama
	docker compose exec ollama ollama pull qwen2.5:3b
