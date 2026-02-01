# 🚀 LeenUp Backend !

API REST moderne construite avec **Symfony 7**, **API Platform 3**, et **PostgreSQL**.

## 📋 Table des matières

- [Prérequis](#-prérequis)
- [Installation](#-installation)
- [Commandes principales](#-commandes-principales)
- [Développement](#-développement)
- [Tests](#-tests)
- [Base de données](#️-base-de-données)
- [Dépannage](#-dépannage)

---

## 🔧 Prérequis

- **Docker** et **Docker Compose**
- **Make** (généralement préinstallé sur Linux/Mac)
- **Git**

---

## 🚀 Installation

### Installation complète du projet

```bash
# Cloner le projet
git clone <url-du-repo>
cd leenup-backend

# Installation complète (build + start + base de données)
make install
```

Cette commande va :
- ✅ Construire les images Docker
- ✅ Démarrer les conteneurs
- ✅ Créer la base de données
- ✅ Appliquer les migrations
- ✅ Configurer la base de données de test

**🌐 Accès :**
- API Documentation : https://localhost/docs
- Admin Interface : https://localhost/admin
- GraphQL : https://localhost/graphql

---

## 📦 Commandes principales

### Docker

```bash
make start              # Démarrer les conteneurs
make stop               # Arrêter les conteneurs
make restart            # Redémarrer les conteneurs + reconfigurer la BD de test
make logs               # Afficher tous les logs
make logs-php           # Afficher les logs PHP uniquement
make status             # Voir le statut des conteneurs
make shell              # Ouvrir un shell dans le conteneur PHP
```

### Aide

```bash
make help               # Afficher toutes les commandes disponibles
make doctor             # Diagnostic complet du système
```

---

## 💻 Développement

### Créer une nouvelle entité avec CRUD complet

```bash
# 1. Créer l'entité
make make-entity

# 2. Créer la migration
make migration-diff

# 3. Appliquer la migration
make migration-migrate

# 4. Créer la Factory pour les tests
docker compose exec php bin/console make:factory

# 5. Créer les tests (voir section Tests)
```

### Créer une entité User

```bash
make make-user          # Créer une entité User
make make-auth          # Configurer l'authentification
```

### Gestion du code

```bash
make composer-install   # Installer les dépendances
make composer-update    # Mettre à jour les dépendances
make cache-clear        # Vider le cache Symfony
```

---

## 🧪 Tests

Le projet utilise **PHPUnit** avec **DAMA DoctrineTestBundle** (transactions automatiques) et **ParaTest** (exécution parallèle).

### Lancer les tests

```bash
# Tests classiques (séquentiel) - ~1m30s
make test

# Tests en parallèle (recommandé) - ~40s ⚡
make test-parallel

# Tester un fichier spécifique
make test FILE=api/tests/Api/Profile/ChangePasswordTest.php

# Tester en parallèle avec un fichier spécifique
make test-parallel FILE=api/tests/Api/Entity/

# Spécifier le nombre de processus parallèles
make test-parallel PROCESSES=8

# Générer la couverture de code
make test-coverage
```

### Créer des tests pour une nouvelle entité

**Template de base :**

```php
<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\ProductFactory;  // Votre Factory
use Zenstruck\Foundry\Test\Factories;

class ProductsTest extends ApiTestCase
{
    use Factories;  // Foundry + DAMA gèrent tout automatiquement

    public function testGetProducts(): void
    {
        ProductFactory::createMany(3);

        static::createClient()->request('GET', '/api/products');

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['hydra:totalItems' => 3]);
    }

    public function testCreateProduct(): void
    {
        static::createClient()->request('POST', '/api/products', [
            'json' => [
                'name' => 'New Product',
                'price' => 99.99,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains(['name' => 'New Product']);
    }
}
```

**Points clés :**
- ✅ Utiliser `use Factories;` (pas de `ResetDatabase` nécessaire)
- ✅ DAMA gère les transactions automatiquement
- ✅ Créer une Factory pour chaque entité testée
- ✅ Utiliser `make test-parallel` pour gagner du temps

---

## 🗄️ Base de données

### Base de données de développement

```bash
make db-create          # Créer la base de données
make db-drop            # Supprimer la base de données
make db-reset           # Recréer la base à zéro avec les migrations

make migration-diff     # Générer une nouvelle migration
make migration-migrate  # Appliquer les migrations
make migration-status   # Voir le statut des migrations

make schema-validate    # Valider le mapping des entités
make shell-db           # Ouvrir un shell PostgreSQL
```

### Base de données de test

```bash
make db-test-create     # Créer la base de test
make db-test-drop       # Supprimer la base de test
make db-test-reset      # Recréer la base de test avec les migrations
make shell-db-test      # Ouvrir un shell PostgreSQL (base test)
```

**⚠️ Important :** La base de test est automatiquement gérée par DAMA lors des tests. Vous n'avez besoin de `make db-test-reset` qu'après un `make restart` ou si vous modifiez le schéma.

### Fixtures

```bash
make make-fixtures      # Créer des fixtures
make fixtures-load      # Charger les fixtures dans la base
```

---

## 🎨 Frontend PWA

```bash
make pwa-install        # Installer les dépendances
make pwa-dev            # Lancer le serveur de développement
make pwa-build          # Build pour production
make pwa-test           # Lancer les tests e2e Playwright
make pwa-generate       # Générer le client API
```

---

## 🚀 Déploiement continu (GitHub Actions + VPS)

Le déploiement en production est automatisé via le workflow GitHub Actions `Deploy` qui :

1. build les images Docker (PHP + PWA) et les pousse sur GHCR,
2. se connecte au VPS par SSH,
3. met à jour le repo et relance `docker compose` avec `compose.prod.yaml`.

### Pré-requis côté VPS

- Le repo est cloné sur le VPS (ex : `/srv/apps/leenup-backend`).
- Le réseau Docker externe `web` existe déjà (utilisé par le reverse proxy).
- L’utilisateur de déploiement a accès à Docker (groupe `docker`).

### Secrets GitHub requis

Renseigner les secrets suivants dans le dépôt GitHub :

- `APP_SECRET` : secret Symfony.
- `POSTGRES_PASSWORD` : mot de passe Postgres.
- `CADDY_MERCURE_JWT_SECRET` : secret Mercure.
- `DEPLOY_HOST` : IP/host du VPS.
- `DEPLOY_USER` : utilisateur SSH.
- `DEPLOY_SSH_KEY` : clé SSH privée (format PEM).
- `DEPLOY_PATH` : chemin du repo sur le VPS.
- `GHCR_USERNAME` : utilisateur GHCR (souvent le même que le compte GitHub).
- `GHCR_TOKEN` : token GHCR (scope `read:packages`).

### Variables d’images (optionnel)

Les images utilisées en production peuvent être personnalisées via ces variables :

- `REGISTRY_IMAGE_PHP` (défaut : `ghcr.io/<owner>/<repo>-php`)
- `REGISTRY_IMAGE_PWA` (défaut : `ghcr.io/<owner>/<repo>-pwa`)

---

## 📚 Documentation

```bash
make docs-generate      # Générer la documentation OpenAPI
make postman-collection # Générer une collection Postman
```

---

## 🏥 Dépannage

### Diagnostic complet

```bash
make doctor             # Vérifie l'état de tous les services
```

Ce diagnostic affiche :
- ✅ Statut des conteneurs
- ✅ État de la base de données
- ✅ Validation du schéma Doctrine
- ✅ URLs disponibles
- ✅ Espace disque Docker

### Problèmes courants

#### Les conteneurs ne démarrent pas

```bash
make stop
make clean-docker       # Nettoyer les ressources Docker
make build              # Reconstruire les images
make start
```

#### Erreur de base de données après un redémarrage

```bash
make db-test-reset      # Reconfigurer la base de test
```

#### Les tests échouent

```bash
# Vérifier que la base de test existe
make db-test-reset

# Relancer les tests
make test
```

#### Cache Symfony pose problème

```bash
make cache-clear        # Vider le cache
```

#### Reset complet du projet

```bash
make full-reset         # ⚠️ ATTENTION : Supprime tout et recommence à zéro
```

Cette commande va :
- Arrêter tous les conteneurs
- Supprimer les volumes Docker
- Reconstruire les images
- Recréer les bases de données
- Charger les fixtures

---

## 🔐 Authentification JWT

### Générer les clés JWT

Les clés JWT sont générées automatiquement lors de l'installation. Si besoin :

```bash
docker compose exec php bin/console lexik:jwt:generate-keypair --skip-if-exists
```

### Tester l'authentification

```bash
# S'inscrire
curl -X POST https://localhost/api/register \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'

# Se connecter
curl -X POST https://localhost/auth \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'
```

---

## 📊 Architecture des tests

Le projet utilise une stack moderne pour les tests :

```
┌─────────────────────────────────┐
│   ApiTestCase (Symfony)         │  Base pour les tests API
├─────────────────────────────────┤
│   DAMA (transactions)           │  Gestion automatique du rollback
├─────────────────────────────────┤
│   Foundry (factories)           │  Création de données de test
├─────────────────────────────────┤
│   ParaTest (parallélisation)    │  Exécution parallèle
└─────────────────────────────────┘
```

**Avantages :**
- ⚡ Tests 2x plus rapides avec ParaTest
- 🔄 Isolation automatique avec DAMA (transactions)
- 🏭 Données de test faciles avec Foundry
- ✅ Aucun nettoyage manuel nécessaire

---

## 🔄 Workflow de développement

### Workflow typique pour une nouvelle feature

```bash
# 1. Créer une branche
git checkout -b feature/new-entity

# 2. Créer l'entité
make make-entity

# 3. Créer et appliquer la migration
make migration-diff
make migration-migrate

# 4. Créer la Factory
docker compose exec php bin/console make:factory

# 5. Créer les tests
# Éditer api/tests/Api/Entity/NewEntityTest.php

# 6. Lancer les tests en parallèle
make test-parallel

# 7. Vérifier le schéma
make schema-validate

# 8. Commit et push
git add .
git commit -m "feat: add NewEntity with CRUD"
git push origin feature/new-entity
```

---

## 🌐 URLs utiles

| Service | URL | Description |
|---------|-----|-------------|
| **API Docs** | https://localhost/docs | Documentation Swagger |
| **Admin** | https://localhost/admin | Interface d'administration |
| **GraphQL** | https://localhost/graphql | Playground GraphQL |
| **Mercure** | https://localhost/.well-known/mercure | Hub Mercure (temps réel) |
| **Couverture** | https://localhost/coverage | Couverture de code (après `make test-coverage`) |

---

## 🤝 Contribution

1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/amazing-feature`)
3. Commit les changements (`git commit -m 'feat: add amazing feature'`)
4. Push vers la branche (`git push origin feature/amazing-feature`)
5. Ouvrir une Pull Request

**⚠️ Important :** Tous les tests doivent passer avant de merge :

```bash
make test-parallel      # Vérifier que tous les tests passent
make schema-validate    # Vérifier le schéma Doctrine
```

---

## 📝 Notes importantes

### Base de données de test

- La base `app_test` est utilisée automatiquement pour les tests
- DAMA gère les transactions : chaque test est isolé automatiquement
- Pas besoin de nettoyer manuellement entre les tests
- Recréer la base après un `make restart` : `make db-test-reset`

### Performance des tests

- Utiliser `make test-parallel` plutôt que `make test` (2x plus rapide)
- La CI utilise aussi ParaTest automatiquement
- 4 processus par défaut, ajustable avec `PROCESSES=8`

### Makefile

Toutes les commandes disponibles sont documentées :

```bash
make help
```

---

## 📞 Support

En cas de problème :

1. Lancer `make doctor` pour un diagnostic
2. Consulter les logs : `make logs`
3. Vérifier la documentation API Platform : https://api-platform.com/docs/

---

## 📄 Licence

[Votre licence ici]

---

## 👥 Auteurs

[Vos informations ici]
