#!/bin/bash

# Script pour créer les bases de données de test pour ParaTest
# Usage: ./setup-test-databases.sh [nombre_de_workers]

set -e

WORKERS=${1:-4}  # Par défaut 4 workers

echo "🗄️  Configuration des bases de données de test pour ParaTest"
echo "   Nombre de workers: ${WORKERS}"
echo ""

# Base de données principale de test
echo "📊 Création de la base de données principale (app_test)..."
docker compose exec -T php bin/console doctrine:database:drop --force --if-exists --env=test || true
docker compose exec -T php bin/console doctrine:database:create --if-not-exists --env=test
docker compose exec -T php bin/console doctrine:migrations:migrate --no-interaction --env=test

echo "✅ Base de données principale créée"
echo ""

# Bases de données pour chaque worker ParaTest
echo "📊 Création des bases de données workers..."
for i in $(seq 1 $WORKERS); do
    echo "   Worker ${i}..."
    docker compose exec -T database psql -U app -c "DROP DATABASE IF EXISTS app_test_${i};" 2>/dev/null || true
    docker compose exec -T database psql -U app -c "CREATE DATABASE app_test_${i} TEMPLATE app_test;" 2>/dev/null || true
done

echo ""
echo "✅ ${WORKERS} bases de données workers créées"
echo ""
echo "🎉 Configuration terminée !"
echo ""
echo "📝 Vous pouvez maintenant lancer les tests avec:"
echo "   make test-paratest"
echo "   ou"
echo "   docker compose exec php vendor/bin/paratest --processes=${WORKERS}"
