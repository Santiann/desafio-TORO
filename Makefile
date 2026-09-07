.DEFAULT_GOAL := help
.PHONY: help setup up down reset logs test race seed creds

help:
	@echo "make setup    cria o .env com segredos gerados na hora"
	@echo "make up       sobe tudo e mostra as portas"
	@echo "make down     derruba, preservando o banco"
	@echo "make reset    derruba apagando o banco e sobe de novo"
	@echo "make logs     acompanha o log da API"
	@echo "make test     roda os 24 testes de integracao"
	@echo "make race     roda a prova de concorrencia da verba"
	@echo "make seed     roda o seed de novo (idempotente)"
	@echo "make creds    mostra as credenciais de login"

# O JWT_SECRET precisa de 32 bytes ou mais, senao a API recusa a subir. Gerar aqui
# tira o unico passo manual que trava a primeira execucao de quem clonou o repo.
setup:
	@test ! -f .env || { echo ".env ja existe, nao vou sobrescrever. Apague antes se quiser recriar."; exit 1; }
	@cp .env.example .env
	@sed -i "s|^JWT_SECRET=.*|JWT_SECRET=$$(openssl rand -hex 32)|" .env
	@sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$$(openssl rand -hex 16)|" .env
	@sed -i "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=$$(openssl rand -hex 16)|" .env
	@sed -i "s|^SEED_PASSWORD=.*|SEED_PASSWORD=$$(openssl rand -hex 8)|" .env
	@echo ".env criado com segredos gerados. Agora: make up"

up:
	@test -f .env || { echo "sem .env. Rode: make setup"; exit 1; }
	docker compose up --build -d
	@echo ""
	@echo "frontend  http://localhost:$$(grep -E '^WEB_PORT=' .env | cut -d= -f2)"
	@echo "api       http://localhost:$$(grep -E '^API_PORT=' .env | cut -d= -f2)"
	@$(MAKE) --no-print-directory creds

down:
	docker compose down

reset:
	docker compose down -v
	@$(MAKE) --no-print-directory up

logs:
	docker compose logs -f api

test:
	docker compose exec api php vendor/bin/phpunit

race:
	./scripts/budget-race.sh

seed:
	docker compose exec api php seeds/seed.php

creds:
	@echo ""
	@echo "admin   admin@toro.test"
	@echo "seller  ana@toro.test, bruno@toro.test, carla@toro.test"
	@echo "senha   $$(grep -E '^SEED_PASSWORD=' .env | cut -d= -f2)"
