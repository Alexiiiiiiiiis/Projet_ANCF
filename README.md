# Transport ANCF — Système d'Information en Temps Réel

[![CI](https://github.com/Alexiiiiiiiiis/Projet_ANCF/actions/workflows/ci.yml/badge.svg)](https://github.com/Alexiiiiiiiiis/Projet_ANCF/actions/workflows/ci.yml)

Application web permettant de consulter en temps réel les horaires des transports en commun d'Île-de-France (RER, Métro, Tramway, Bus).

**Stack :** Symfony 7 API + React 18 + MySQL 8 + Docker

---

## Démarrage rapide

### Prérequis
- Docker Desktop ≥ 4.x
- Git

### 1. Cloner le projet
```bash
git clone https://github.com/Alexiiiiiiiiis/Projet_ANCF.git
cd Projet_ANCF/projet
```

### 2. Configuration
```bash
cp .env.example .env
# Optionnel : ajouter votre clé API IDFM dans .env
# IDFM_API_KEY=votre_clé
```

### 3. Lancer les services
```bash
docker compose up -d --build
```

### 4. Initialiser la base de données
```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

### 5. Générer les clés JWT
```bash
docker compose exec php php bin/console lexik:jwt:generate-keypair
```

### Accès
| Service     | URL                          |
|-------------|------------------------------|
| Frontend    | http://localhost:5173         |
| API Backend | http://localhost:8080/api     |
| PhpMyAdmin  | http://localhost:8081         |

---

## Comptes de test
| Rôle  | Email              | Mot de passe |
|-------|--------------------|--------------|
| Admin | admin@ancf.fr      | Admin1234!   |
| User  | user@ancf.fr       | User1234!    |

---

## Architecture

```
projet/
├── backend/          # Symfony 7 REST API
├── frontend/         # React 18 + TypeScript (Vite)
├── docker/           # Dockerfiles & configurations
│   ├── php/
│   ├── nginx/
│   └── mysql/
├── .github/
│   └── workflows/    # GitHub Actions CI/CD
├── docs/             # Documentation (Jalons 3-6)
├── docker-compose.yml        (développement)
└── docker-compose.prod.yml   (production)
```

## API Endpoints

| Méthode | Endpoint                   | Description                     | Auth |
|---------|----------------------------|---------------------------------|------|
| POST    | /api/auth/register         | Inscription                     | Non  |
| POST    | /api/auth/login_check      | Connexion → JWT                 | Non  |
| GET     | /api/auth/me               | Profil utilisateur              | Oui  |
| GET     | /api/stops/search?q=       | Recherche d'arrêts              | Non  |
| GET     | /api/stops/nearby?lat&lon  | Arrêts à proximité              | Non  |
| GET     | /api/schedules/{stopId}    | Prochains départs               | Non  |
| GET     | /api/alerts                | Alertes trafic                  | Non  |
| GET     | /api/favorites             | Mes favoris                     | Oui  |
| POST    | /api/favorites             | Ajouter un favori               | Oui  |
| GET     | /api/admin/stats           | Statistiques admin              | Admin|

## Sécurité

- **Injection SQL** : toutes les requêtes passent par Doctrine ORM (requêtes paramétrées), aucune concaténation SQL.
- **XSS** : React échappe par défaut tout contenu interpolé dans le JSX ; aucune utilisation de `dangerouslySetInnerHTML`.
- **CSRF** : non applicable par conception — l'API est *stateless* : l'authentification repose sur un JWT transmis dans le header `Authorization: Bearer`, jamais dans un cookie. Un site tiers ne peut donc pas déclencher de requête authentifiée à l'insu de l'utilisateur (le navigateur n'attache aucun credential automatiquement). C'est la protection recommandée par l'OWASP pour les API token-based.
- **Brute force** : `login_throttling` Symfony (composant RateLimiter) — 5 tentatives max, puis blocage 15 minutes par couple email/IP. Même composant utilisé pour limiter `/api/auth/register` (5/heure/IP) et `/api/auth/forgot-password` (3/15min/IP), qui n'ont pas de firewall d'authentification et n'étaient donc protégées par rien.
- **Mots de passe** : hachés via le hasher Symfony (bcrypt/argon2 auto), minimum 8 caractères, jamais stockés ni loggés en clair.
- **Clés API** : uniquement en variables d'environnement (`.env` gitignoré), jamais en dur ni côté front.
- **Contrôle d'accès** : routes `/api/admin/*` réservées à `ROLE_ADMIN` (firewall Symfony), CORS restreint via NelmioCorsBundle.
- **RGPD** : suppression de compte (et données associées), mentions légales et politique de confidentialité dans l'application.

## Écarts par rapport au cahier des charges (CDC V4)

Le CDC (§4.2) nomme des technologies précises pour quatre choix techniques. Chacun a été
volontairement remplacé par un équivalent fonctionnel, justifié ci-dessous plutôt que suivi à la
lettre — les objectifs qu'ils servent (API RESTful testée, UI réactive et cohérente, tests
automatisés, cartographie interactive) restent pleinement atteints.

| Exigé (CDC) | Implémenté | Pourquoi |
|---|---|---|
| Material-UI ou Bootstrap React | **Tailwind CSS** | Contrôle plus fin, bundle plus léger qu'un framework de composants complet ; l'appli n'a besoin d'aucun composant préfabriqué complexe (data grid, date picker...), juste d'une mise en page cohérente. |
| Jest | **Vitest** | Le frontend est bâti avec Vite : Vitest partage sa config/transformation avec le build de dev, expose une API quasi identique à Jest, et s'intègre nativement — c'est le choix recommandé par l'écosystème Vite lui-même plutôt que d'ajouter un second toolchain de test. |
| API Platform ou FOSRestBundle | Contrôleurs Symfony classiques (`AbstractController` + `JsonResponse`) | FOSRestBundle n'est plus activement maintenu. API Platform apporte une couche de génération automatique (OpenAPI, sérialisation par groupes, filtres) disproportionnée pour une douzaine d'endpoints REST simples, et rend plus difficile d'y intégrer la logique métier spécifique du projet (cache, mode dégradé, agrégations). Les contrôleurs classiques produisent une API RESTful tout aussi conforme, et plus lisible à cette échelle. |
| Google Maps ou Mapbox | **Leaflet + OpenStreetMap** | Le CDC cite lui-même OpenStreetMap (§5.2) comme mitigation au risque de quota gratuit limité de Mapbox — plutôt que d'introduire cette dépendance puis la contourner, le projet part directement sur Leaflet/OSM : ni clé API ni quota à surveiller, alors que le projet en gère déjà un (IDFM, cf. `/api/admin/stats.apiQuota`) et a appris à ses dépens ce que ça implique. |

Deux exigences fonctionnelles méritent aussi une précision sur la façon dont elles sont couvertes :

- **F3.1** (minimum 3 prochains passages) : l'application affiche jusqu'à 5 passages dès qu'ils
  existent, mais ne peut pas garantir un minimum de 3 si la source de données (réelle ou
  dégradée) n'en fournit pas assez — impossible d'inventer des passages qui n'existent pas
  (ligne peu fréquente, fin de service).
- **F6.4** (distance à pied) : approximée via un facteur de détour urbain (`GeoService::
  estimateWalkingDistance`, ×1.3 appliqué à la distance à vol d'oiseau) plutôt qu'un vrai calcul
  d'itinéraire piéton, pour éviter une dépendance à un second service externe avec son propre
  quota — leçon retenue avec l'API IDFM, dont le quota de 1000 req/jour peut être épuisé en
  quelques heures d'usage (cf. `IdfmApiService`, `/api/admin/stats.apiQuota`).

## Tests
```bash
# Backend (PHPUnit)
docker compose exec php vendor/bin/phpunit

# Backend — style de code (PSR-12 / Symfony, via PHP-CS-Fixer)
docker compose exec php composer cs-check   # vérifie sans modifier
docker compose exec php composer cs-fix     # corrige automatiquement

# Frontend (Vitest)
docker compose exec frontend npm test
```

## CI/CD (GitHub Actions)
- **Push sur `develop`** → tests unitaires + tests d'intégration (PHPUnit), PHPStan, ESLint, TypeScript, Vitest, build Docker
- **Push sur `main`** → idem + vérification docker-compose
- **Tag `v*.*.*`** → build & push des images sur Docker Hub + création d'une GitHub Release

## Déploiement en production

Les images Docker (backend + nginx/frontend) sont publiées automatiquement sur Docker Hub à
chaque tag `v*.*.*` (cf. `.github/workflows/cd.yml`) — le serveur de prod n'a donc besoin que de
`docker-compose.prod.yml` et d'un fichier `.env`, pas du code source ni d'un build local.

### 1. Récupérer les fichiers nécessaires
```bash
git clone https://github.com/Alexiiiiiiiiis/Projet_ANCF.git
cd Projet_ANCF/projet
```

### 2. Configuration
```bash
cp .env.example .env
# Renseigner au minimum : MYSQL_*, DATABASE_URL, JWT_PASSPHRASE, CORS_ALLOW_ORIGIN,
# DOCKER_IMAGE_PREFIX (votre pseudo Docker Hub) et VERSION (tag à déployer, ex. v1.0.3).
```

### 3. Générer les clés JWT (une seule fois, avant le premier démarrage)
```bash
mkdir -p config/jwt
openssl genrsa -out config/jwt/private.pem 4096
openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem
```
Ces clés sont montées en lecture seule dans le conteneur `php` (`./config/jwt`) — elles ne sont
jamais intégrées à l'image, qui est publique sur Docker Hub. À sauvegarder : les régénérer
invaliderait tous les tokens JWT déjà émis.

### 4. Récupérer et démarrer les images publiées
```bash
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
```

### 5. Initialiser la base de données (premier déploiement uniquement)
```bash
docker compose -f docker-compose.prod.yml exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### Mettre à jour vers une nouvelle version
```bash
# Modifier VERSION dans .env, puis :
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec php php bin/console doctrine:migrations:migrate --no-interaction
```

---

**Auteur :** Rodrigues Alexis — Bachelor CDA 2025-2026, IPSSI Paris
