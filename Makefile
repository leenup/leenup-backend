# Variables
DOCKER_COMPOSE = docker compose
PHP_CONTAINER = php
DATABASE_CONTAINER = database
PWA_CONTAINER = pwa

# Couleurs pour les messages
GREEN = \033[0;32m
YELLOW = \033[1;33m
RED = \033[0;31m
NC = \033[0m # No Color

.PHONY: help build start stop restart logs clean doctor diagnose-local

## —— 🚀 LeenUp Backend Makefile 🚀 ——————————————————————————————————

help: ## Affiche cette aide
	@echo "$(GREEN)LeenUp Backend - Commandes disponibles:$(NC)"
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— 🐳 Docker ——————————————————————————————————————————————————————

build: ## Construit les images Docker
	@echo "$(YELLOW)🔨 Construction des images Docker...$(NC)"
	$(DOCKER_COMPOSE) build --no-cache

start: ## Démarre les conteneurs
	@echo "$(YELLOW)🚀 Démarrage des conteneurs...$(NC)"
	$(DOCKER_COMPOSE) up --wait
	@echo "$(GREEN)🌐 URLs disponibles:$(NC)"
	@echo "  • API Documentation: https://localhost/docs/"
	@echo "  • Admin Interface:   https://localhost/admin/"
	@echo "  . Github Repo:       https://github.com/leenup/leenup-backend/tree/develop"

stop: ## Arrête les conteneurs
	@echo "$(YELLOW)🛑 Arrêt des conteneurs...$(NC)"
	$(DOCKER_COMPOSE) down

restart: stop start ## Redémarre les conteneurs et reconfigure la BD de test
	@echo "$(GREEN)✅ Redémarrage terminé$(NC)"
	@echo "$(GREEN)🌐 URLs disponibles:$(NC)"
	@echo "  • API Documentation: https://localhost/docs/"
	@echo "  • Admin Interface:   https://localhost/admin/"

logs: ## Affiche les logs des conteneurs
	$(DOCKER_COMPOSE) logs -f

logs-php: ## Affiche les logs du conteneur PHP
	$(DOCKER_COMPOSE) logs -f $(PHP_CONTAINER)

status: ## Affiche le statut des conteneurs
	$(DOCKER_COMPOSE) ps

## —— 🗄️ Base de données (Développement) ————————————————————————————
db-create: ## Crée la base de données
	@echo "$(YELLOW)📊 Création de la base de données...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:database:create --if-not-exists

db-drop: ## Supprime la base de données
	@echo "$(RED)🗑️ Suppression de la base de données...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:database:drop --force --if-exists

db-reset: restart db-drop db-create migration-migrate ## Recrée la base de données à zéro
	@echo "$(GREEN)✅ Base de données recréée avec les migrations$(NC)"

reset-fixtures: db-reset## Vide la DB + migrations + toutes les fixtures
	@echo "$(RED) fixtures...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:database:drop --force --if-exists
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:database:create --if-not-exists
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:migrations:migrate --no-interaction
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:fixtures:load --no-interaction
	@echo "$(GREEN)✅ DB recréée + fixtures rejouées$(NC)"

reset-prod: db-reset## Vide la DB + migrations + seed prod-safe
	@echo "$(RED) seed prod-safe...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:database:create --if-not-exists
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:migrations:migrate --no-interaction
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console app:seed-reference-data --no-interaction
	@echo "$(GREEN)✅ DB recréée + seed prod-safe exécuté$(NC)"

migration-diff: ## Génère une nouvelle migration
	@echo "$(YELLOW)📝 Génération d'une migration...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:migrations:diff

migration-migrate: ## Applique les migrations
	@echo "$(YELLOW)🔄 Application des migrations...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:migrations:migrate --no-interaction

migration-migrate-drop: ## Vide la base et applique les migrations
	@echo "$(RED)🗑️ Vidage de la base de données...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:database:drop --force --if-exists
	@echo "$(YELLOW)📊 Recréation de la base de données...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:database:create --if-not-exists
	@echo "$(YELLOW)🔄 Application des migrations...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:migrations:migrate --no-interaction
	@echo "$(GREEN)✅ Base de données recréée avec les migrations$(NC)"

migration-status: ## Affiche le statut des migrations
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:migrations:status

schema-update: ## Met à jour le schéma de la base (DEV uniquement)
	@echo "$(YELLOW)⚠️ Mise à jour du schéma (DEV)...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:schema:update --force

schema-validate: ## Valide le mapping des entités
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:schema:validate

## —— 🧪 Base de données de TEST ————————————————————————————————————
db-test-create: ## Crée la base de données de test
	@echo "$(YELLOW)📊 Création de la base de données de test...$(NC)"
	-$(DOCKER_COMPOSE) exec $(DATABASE_CONTAINER) psql -U app -c "CREATE DATABASE app_test;" 2>/dev/null || echo "$(YELLOW)Base app_test existe déjà$(NC)"
	@echo "$(GREEN)✅ Base de données de test prête$(NC)"

db-test-drop: ## Supprime la base de données de test
	@echo "$(RED)🗑️ Suppression de la base de données de test...$(NC)"
	-$(DOCKER_COMPOSE) exec $(DATABASE_CONTAINER) psql -U app -c "DROP DATABASE IF EXISTS app_test;"
	@echo "$(GREEN)✅ Base de données de test supprimée$(NC)"

db-test-migrate: ## Applique les migrations sur la BD de test
	@echo "$(YELLOW)🔄 Application des migrations sur la BD de test...$(NC)"
	$(DOCKER_COMPOSE) exec -e APP_ENV=test -e APP_DEBUG=0 $(PHP_CONTAINER) sh -c 'DATABASE_URL="postgresql://app:!ChangeMe!@database:5432/app_test?serverVersion=16&charset=utf8" bin/console doctrine:migrations:migrate --no-interaction'
	@echo "$(GREEN)✅ Migrations appliquées sur la BD de test$(NC)"

db-test-reset: db-test-drop db-test-create db-test-migrate ## Recrée la base de test à zéro
	@echo "$(GREEN)✅ Base de données de test recréée avec les migrations$(NC)"

## —— 🏗️ Entités et Code ————————————————————————————————————————————
make-entity: ## Crée une nouvelle entité
	@echo "$(YELLOW)🏗️ Création d'une entité...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console make:entity --api-resource

make-user: ## Crée une entité User
	@echo "$(YELLOW)👤 Création de l'entité User...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console make:user

make-auth: ## Configure l'authentification
	@echo "$(YELLOW)🔐 Configuration de l'authentification...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console make:auth

make-fixtures: ## Crée des fixtures
	@echo "$(YELLOW)🎭 Création des fixtures...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console make:fixtures

fixtures-load: ## Charge les fixtures
	@echo "$(YELLOW)📥 Chargement des fixtures...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:fixtures:load --no-interaction

seed-reference-data: ## Charge les données de référence (prod-safe) sans dépendre des fixtures
	@echo "$(YELLOW)🌱 Chargement des données de référence (catégories, skills, cards)...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console app:seed-reference-data --no-interaction

fixtures-load-drop: ## Vide la base et charge les fixtures
	@echo "$(YELLOW)🗑️ Vidage de la base de données...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:database:drop --force --if-exists
	@echo "$(YELLOW)📊 Recréation de la base de données...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:database:create --if-not-exists
	@echo "$(YELLOW)🔄 Application des migrations...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:migrations:migrate --no-interaction
	@echo "$(YELLOW)📥 Chargement des fixtures...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:fixtures:load --no-interaction
	@echo "$(GREEN)✅ Base de données recréée avec les migrations et fixtures$(NC)"

## —— 🧪 Tests et Qualité ———————————————————————————————————————————

jwt-keys: ## Génère les clés JWT si absentes (profil dev)
	@echo "$(YELLOW)🔐 Vérification des clés JWT...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) sh -c "php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction"

jwt-keys-refresh: ## Régénère les clés JWT (profil dev)
	@echo "$(YELLOW)♻️ Régénération des clés JWT...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) sh -c "php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction"

jwt-keys-test: ## Génère les clés JWT avec APP_ENV=test (recommandé pour les tests)
	@echo "$(YELLOW)🔐 Vérification des clés JWT (APP_ENV=test)...$(NC)"
	$(DOCKER_COMPOSE) exec -e APP_ENV=test $(PHP_CONTAINER) sh -c "mkdir -p config/jwt/test && php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction"

jwt-keys-refresh-test: ## Régénère les clés JWT avec APP_ENV=test (corrige passphrase test)
	@echo "$(YELLOW)♻️ Régénération des clés JWT (APP_ENV=test)...$(NC)"
	$(DOCKER_COMPOSE) exec -e APP_ENV=test $(PHP_CONTAINER) sh -c "mkdir -p config/jwt/test && php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction"

test: jwt-keys-test db-test-reset ## Lance les tests (usage: make test ou make test FILE=tests/Api/Profile/CurrentUserTest.php)
	@echo "$(YELLOW)🧪 Lancement des tests...$(NC)"
ifdef FILE
	$(DOCKER_COMPOSE) exec -e APP_ENV=test -e APP_DEBUG=0 $(PHP_CONTAINER) bin/phpunit $(FILE)
else
	$(DOCKER_COMPOSE) exec -e APP_ENV=test -e APP_DEBUG=0 $(PHP_CONTAINER) bin/phpunit
endif

test-parallel: jwt-keys-test db-test-reset cache-clear ## Lance les tests en parallèle (usage: make test-parallel ou make test-parallel PROCESSES=8 ou make test-parallel FILE=tests/Api/)
	@echo "$(YELLOW)⚡ Lancement des tests en parallèle...$(NC)"
ifdef FILE
ifdef PROCESSES
	$(DOCKER_COMPOSE) exec -e APP_ENV=test -e APP_DEBUG=0 $(PHP_CONTAINER) vendor/bin/paratest -p$(PROCESSES) $(FILE)
else
	$(DOCKER_COMPOSE) exec -e APP_ENV=test -e APP_DEBUG=0 $(PHP_CONTAINER) vendor/bin/paratest $(FILE)
endif
else
ifdef PROCESSES
	$(DOCKER_COMPOSE) exec -e APP_ENV=test -e APP_DEBUG=0 $(PHP_CONTAINER) vendor/bin/paratest -p$(PROCESSES)
else
	$(DOCKER_COMPOSE) exec -e APP_ENV=test -e APP_DEBUG=0 $(PHP_CONTAINER) vendor/bin/paratest
endif
endif


test-coverage: ## Lance les tests avec couverture
	@echo "$(YELLOW)🧪 Génération de la couverture de code...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/phpunit --coverage-html public/coverage

test-coverage-parallel: ## Lance les tests avec couverture en parallèle
	@echo "$(YELLOW)⚡ Génération de la couverture de code (parallèle)...$(NC)"
ifdef PROCESSES
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) vendor/bin/paratest -p$(PROCESSES) --coverage-html public/coverage
else
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) vendor/bin/paratest --coverage-html public/coverage
endif

cs-fixer: ## Corrige le style de code
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) vendor/bin/php-cs-fixer fix src/

phpstan: ## Analyse statique du code
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) vendor/bin/phpstan analyse src/

pwa-test: ## Lance les tests e2e Playwright
	@echo "$(YELLOW)🎭 Lancement des tests e2e Playwright...$(NC)"
	docker run --network host -w /app -v ./e2e:/app --rm --ipc=host mcr.microsoft.com/playwright:v1.50.0-noble /bin/sh -c 'npm i; npx playwright test;'

## —— 📦 Composer ———————————————————————————————————————————————————
composer-install: ## Installe les dépendances Composer
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) composer install

composer-update: ## Met à jour les dépendances
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) composer update

composer-require: ## Installe une nouvelle dépendance
	@read -p "Nom du package: " package; \
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) composer require $$package

## —— 🔧 Utilitaires ————————————————————————————————————————————————
shell: ## Ouvre un shell dans le conteneur PHP
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bash

shell-db: ## Ouvre un shell dans la base de données
	$(DOCKER_COMPOSE) exec $(DATABASE_CONTAINER) psql -U app -d app

shell-db-test: ## Ouvre un shell dans la base de données de test
	$(DOCKER_COMPOSE) exec $(DATABASE_CONTAINER) psql -U app -d app_test

cache-clear: ## Vide le cache Symfony
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console cache:clear

cache-warmup: ## Préchauffe le cache
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console cache:warmup

## —— 📋 Documentation et APIs ——————————————————————————————————————
docs-generate: ## Génère la documentation OpenAPI
	@echo "$(YELLOW)📚 Génération de la documentation...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console api:openapi:export > api/public/docs/openapi.json

postman-collection: ## Génère une collection Postman
	@echo "$(YELLOW)📮 Génération de la collection Postman...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console api:openapi:export --format=json > postman/leenup-api.json

## —— 🏥 Diagnostic ————————————————————————————————————————————————
doctor: ## Diagnostic complet du système
	@echo "$(GREEN)🏥 Diagnostic du système LeenUp Backend$(NC)"
	@echo "$(YELLOW)======================================$(NC)"
	@echo ""
	@echo "$(GREEN)📊 Statut des conteneurs:$(NC)"
	$(DOCKER_COMPOSE) ps
	@echo ""
	@echo "$(GREEN)🗄️ Statut de la base de données (dev):$(NC)"
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:migrations:status 2>/dev/null || echo "❌ Problème avec la base"
	@echo ""
	@echo "$(GREEN)🗄️ Statut de la base de données (test):$(NC)"
	@$(DOCKER_COMPOSE) exec $(DATABASE_CONTAINER) psql -U app -c "SELECT COUNT(*) as users_in_test FROM \"user\";" app_test 2>/dev/null || echo "❌ Base de test non configurée"
	@echo ""
	@echo "$(GREEN)🔧 Validation du schéma:$(NC)"
	@$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console doctrine:schema:validate 2>/dev/null || echo "❌ Schéma invalide"
	@echo ""
	@echo "$(GREEN)🌐 URLs disponibles:$(NC)"
	@echo "  • API Documentation: https://localhost/docs/"
	@echo "  • Admin Interface:   https://localhost/admin/"
	@echo ""
	@echo "$(GREEN)💾 Espace disque Docker:$(NC)"
	@docker system df


diagnose-local: ## Diagnostic ciblé des erreurs localhost (ERR_CONNECTION_CLOSED)
	@./scripts/diagnose-local.sh

diagnose-test-500: ## Diagnostic des 500 en test (auth, env effectif, logs)
	@echo "$(YELLOW)🧪 Diagnostic ciblé des erreurs 500 en test...$(NC)"
	@echo "$(GREEN)1) Vérification des clés JWT dans le conteneur$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) sh -c 'ls -l config/jwt || true; test -s config/jwt/test/private.pem && echo "test/private.pem: OK" || echo "test/private.pem: MISSING"; test -s config/jwt/test/public.pem && echo "test/public.pem: OK" || echo "test/public.pem: MISSING"'
	@echo "$(GREEN)2) Vérification de la lecture de la clé privée avec la passphrase courante$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) sh -c 'openssl pkey -in config/jwt/test/private.pem -passin pass:"$${JWT_PASSPHRASE:-}" -noout >/dev/null 2>&1 && echo "private key load: OK" || echo "private key load: FAIL"'
	@echo "$(GREEN)3) Variables résolues en APP_ENV=test$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) sh -c 'bin/console debug:container --env-vars --env=test | grep -E "APP_SECRET|JWT_SECRET_KEY|JWT_PUBLIC_KEY|JWT_PASSPHRASE|DATABASE_URL" || true'
	@echo "$(GREEN)4) Exécution du 1er test d'auth + logs test$(NC)"
	-$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/phpunit tests/Api/Auth/AuthenticationTest.php --filter testLogin
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) sh -c 'tail -n 200 var/log/test.log || true'

## —— 🧹 Nettoyage ——————————————————————————————————————————————————
clean: ## Nettoie le cache et les fichiers temporaires
	@echo "$(YELLOW)🧹 Nettoyage...$(NC)"
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) bin/console cache:clear
	$(DOCKER_COMPOSE) exec $(PHP_CONTAINER) rm -rf var/log/*.log

clean-docker: ## Nettoie les ressources Docker inutiles
	@echo "$(YELLOW)🧹 Nettoyage Docker...$(NC)"
	docker system prune -f
	docker volume prune -f

clean-all: clean clean-docker ## Nettoyage complet

## —— 🚀 Installation complète ——————————————————————————————————————
install: build start jwt-keys db-create migration-migrate db-test-reset ## Installation complète du projet
	@echo "$(GREEN)✅ Installation terminée !$(NC)"
	@echo "$(YELLOW)🌐 Accédez à votre API: https://localhost/docs/$(NC)"

setup-after-restart: db-test-reset ## Configure la BD de test après un restart
	@echo "$(GREEN)✅ Configuration post-restart terminée !$(NC)"

## —— 📱 Frontend PWA ———————————————————————————————————————————————
pwa-install: ## Installe les dépendances PWA
	$(DOCKER_COMPOSE) exec $(PWA_CONTAINER) pnpm install

pwa-dev: ## Lance le serveur de développement PWA
	$(DOCKER_COMPOSE) exec $(PWA_CONTAINER) pnpm dev

pwa-build: ## Build la PWA pour production
	$(DOCKER_COMPOSE) exec $(PWA_CONTAINER) pnpm build

pwa-generate: ## Génère le client API
	$(DOCKER_COMPOSE) exec $(PWA_CONTAINER) pnpm create @api-platform/client

## —— 🎯 Commandes rapides ——————————————————————————————————————————
dev: start ## Alias pour start (environnement de dev)

full-reset: stop clean-docker build start db-reset db-test-reset fixtures-load ## Reset complet du projet
	@echo "$(GREEN)🔄 Reset complet terminé !$(NC)"

url: ## Affiche les URLs disponibles
	@echo "$(GREEN)🌐 URLs disponibles:$(NC)"
	@echo "  • API Documentation: https://localhost/docs/"
	@echo "  • Admin Interface:   https://localhost/admin/"
	@echo "  • Github Repo:       https://github.com/leenup/leenup-backend/tree/develop"
