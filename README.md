# Transport ANCF — Système d'Information en Temps Réel

[![CI](https://github.com/Alexiiiiiiiiis/Projet_ANCF/actions/workflows/ci.yml/badge.svg)](https://github.com/Alexiiiiiiiiis/Projet_ANCF/actions/workflows/ci.yml)

Application web permettant de consulter en temps réel les horaires des transports en commun d'Île-de-France (RER, Métro, Tramway, Bus).

**Stack :** Symfony 7 API + React 18 + MySQL 8 + Redis 7 + Docker

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
│   ├── php/          # image PHP-FPM (Docker Compose)
│   ├── nginx/        # reverse proxy, embarque le frontend buildé en production
│   ├── mysql/        # script d'initialisation de la base
│   └── api/          # image mono-conteneur nginx + PHP-FPM (hébergeurs PaaS)
├── config/jwt/       # clés JWT de la prod Docker et de la démo (non versionnées)
├── scripts/          # déploiement AlwaysData (deploy-alwaysdata.sh)
├── .github/
│   └── workflows/    # GitHub Actions : ci.yml et cd.yml
├── docker-compose.yml        (développement)
└── docker-compose.prod.yml   (déploiement sur serveur unique)
```

## API Endpoints

**Auth** : *Non* = public, *Oui* = JWT requis, *Facultative* = réponse enrichie si connecté,
*Admin* = `ROLE_ADMIN`.

| Méthode | Endpoint                   | Description                     | Auth |
|---------|----------------------------|---------------------------------|------|
| GET     | /api/health                | Sonde de santé (503 si la base est injoignable) | Non  |
| GET     | /api/config                | Paramètres système destinés au client (cadence de rafraîchissement, rayon par défaut, plafond de favoris, mode maintenance) | Non  |
| POST    | /api/auth/register         | Inscription                     | Non  |
| POST    | /api/auth/login_check      | Connexion → JWT                 | Non  |
| POST    | /api/auth/forgot-password  | Envoi d'un lien de réinitialisation du mot de passe | Non  |
| POST    | /api/auth/reset-password   | Nouveau mot de passe (`token`, `newPassword`) | Non  |
| GET     | /api/auth/me               | Profil utilisateur              | Oui  |
| PUT     | /api/auth/me               | Modifier prénom, nom ou email   | Oui  |
| PUT     | /api/auth/change-password  | Changer de mot de passe (`currentPassword`, `newPassword`) | Oui  |
| DELETE  | /api/auth/account          | Supprimer son compte et ses données — mot de passe requis | Oui  |
| GET     | /api/stops/search?q=       | Recherche d'arrêts (`?type=`, `?limit=` jusqu'à 20) | Non  |
| GET     | /api/stops/nearby?lat&lon  | Arrêts à proximité (`?radius=` jusqu'à 2000 m, `?type=`) | Non  |
| GET     | /api/stops/history         | 8 dernières recherches de l'utilisateur (liste vide sans connexion) | Facultative |
| GET     | /api/stops/{stopId}        | Prochains départs d'un arrêt    | Non  |
| GET     | /api/schedules/{stopId}    | Prochains départs — 5 par défaut (`?type=` mode, `?line=` ligne, `?limit=` jusqu'à 40) | Non  |
| GET     | /api/lines?type=           | Lignes d'un mode (METRO, RER, TRAM, BUS) | Non  |
| GET     | /api/lines/{lineId}/stops  | Arrêts desservis par une ligne  | Non  |
| GET     | /api/lines/status?ids=     | État de trafic de plusieurs lignes | Non  |
| GET     | /api/alerts                | Alertes trafic (`?lineId=`, `?severity=`, `?type=`, `?category=`, `?page=`, `?limit=` jusqu'à 100) | Non  |
| GET     | /api/alerts/{lineId}       | Alertes d'une ligne             | Non  |
| GET     | /api/journeys?from&to      | Calcul d'itinéraire             | Oui  |
| GET     | /api/favorites             | Mes favoris                     | Oui  |
| POST    | /api/favorites             | Ajouter un favori — arrêt, ou ligne avec `kind: LINE` | Oui  |
| DELETE  | /api/favorites/{id}        | Retirer un favori               | Oui  |
| PUT     | /api/favorites/{id}/reorder | Déplacer un favori (`sortOrder`) | Oui  |
| GET     | /api/admin/stats           | Statistiques, dont le quota IDFM (`apiQuota`) | Admin|
| GET     | /api/admin/users           | Utilisateurs, paginés (`?page=`, `?limit=` jusqu'à 100) | Admin|
| PUT     | /api/admin/users/{id}/toggle | Bloquer ou réactiver un compte | Admin|
| GET     | /api/admin/api-logs        | Derniers appels à l'API IDFM et temps de réponse moyen (`?limit=` jusqu'à 200) | Admin|
| GET     | /api/admin/parameters      | Paramètres système              | Admin|
| PUT     | /api/admin/parameters/{id} | Modifier un paramètre (`value`) | Admin|

Les paramètres système sont appliqués au runtime : les TTL de cache pilotent `IdfmApiService`,
le rayon par défaut `/api/stops/nearby`, le plafond de favoris `/api/favorites`, et le mode
maintenance renvoie 503 sur toutes les routes publiques (`MaintenanceListener`). Restent
ouvertes en maintenance : `/api/health` pour la sonde de l'hébergeur, `/api/config` pour que le
frontend affiche sa page d'attente, `/api/auth/login_check` et `/api/admin` pour qu'un
administrateur puisse rouvrir le service. Les valeurs sont bornées côté serveur
(`SystemParameters`) : un TTL à 0 saisi dans l'administration épuiserait le quota IDFM.

## Sécurité

- **Injection SQL** : toutes les requêtes passent par Doctrine ORM (requêtes paramétrées), aucune concaténation SQL.
- **XSS** : React échappe par défaut tout contenu interpolé dans le JSX ; aucune utilisation de `dangerouslySetInnerHTML`.
- **CSRF** : non applicable par conception — l'API est *stateless* : l'authentification repose sur un JWT transmis dans le header `Authorization: Bearer`, jamais dans un cookie. Un site tiers ne peut donc pas déclencher de requête authentifiée à l'insu de l'utilisateur (le navigateur n'attache aucun credential automatiquement). C'est la protection recommandée par l'OWASP pour les API token-based.
- **Brute force** : `login_throttling` Symfony (composant RateLimiter) — 5 tentatives max, puis blocage 15 minutes par couple email/IP. Même composant utilisé pour limiter `/api/auth/register` (5/heure/IP) et `/api/auth/forgot-password` (3/15min/IP), qui n'ont pas de firewall d'authentification et n'étaient donc protégées par rien.
- **Mots de passe** : hachés via le hasher Symfony (bcrypt/argon2 auto), minimum 8 caractères, jamais stockés ni loggés en clair.
- **Clés API** : uniquement en variables d'environnement (`.env` gitignoré), jamais en dur ni côté front.
- **Contrôle d'accès** : routes `/api/admin/*` réservées à `ROLE_ADMIN` et calcul d'itinéraire (`/api/journeys`) aux utilisateurs connectés (firewall Symfony), CORS restreint via NelmioCorsBundle.
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
| API Platform ou FOSRestBundle | Contrôleurs Symfony classiques (`AbstractController` + `JsonResponse`) | FOSRestBundle n'est plus activement maintenu. API Platform apporte une couche de génération automatique (OpenAPI, sérialisation par groupes, filtres) disproportionnée pour une trentaine d'endpoints REST simples, et rend plus difficile d'y intégrer la logique métier spécifique du projet (cache, mode dégradé, agrégations). Les contrôleurs classiques produisent une API RESTful tout aussi conforme, et plus lisible à cette échelle. |
| Google Maps ou Mapbox | **Leaflet + OpenStreetMap** | Le CDC cite lui-même OpenStreetMap (§5.2) comme mitigation au risque de quota gratuit limité de Mapbox — plutôt que d'introduire cette dépendance puis la contourner, le projet part directement sur Leaflet/OSM : ni clé API ni quota à surveiller, alors que le projet en gère déjà un (IDFM, cf. `/api/admin/stats.apiQuota`) et a appris à ses dépens ce que ça implique. |

Deux exigences fonctionnelles méritent aussi une précision sur la façon dont elles sont couvertes :

- **F3.1** (minimum 3 prochains passages) : l'application affiche 5 passages par défaut (accueil,
  carte, favoris) et jusqu'à 40 sur la fiche d'un arrêt, mais ne peut pas garantir un minimum de 3
  si la source de données (réelle ou dégradée) n'en fournit pas assez — impossible d'inventer des
  passages qui n'existent pas (ligne peu fréquente, fin de service).
- **F6.4** (distance à pied) : approximée via un facteur de détour urbain (`GeoService::
  estimateWalkingDistance`, ×1.3 appliqué à la distance à vol d'oiseau) plutôt qu'un vrai calcul
  d'itinéraire piéton, pour éviter une dépendance à un second service externe avec son propre
  quota — leçon retenue avec l'API IDFM, dont le quota de 1000 req/jour peut être épuisé en
  quelques heures d'usage (cf. `IdfmApiService`, `/api/admin/stats.apiQuota`).

## Tests
```bash
# Backend (PHPUnit) — toutes les suites, ou une seule avec --testsuite=Unit / Functional
docker compose exec php vendor/bin/phpunit

# Backend — analyse statique (PHPStan, même niveau que la CI)
docker compose exec php vendor/bin/phpstan analyse src --level=5

# Backend — style de code (PSR-12 / Symfony, via PHP-CS-Fixer)
docker compose exec php composer cs-check   # vérifie sans modifier
docker compose exec php composer cs-fix     # corrige automatiquement

# Frontend (Vitest) — sans --run, Vitest reste en mode watch
docker compose exec frontend npm test -- --run

# Frontend — typage et lint
docker compose exec frontend npx tsc --noEmit
docker compose exec frontend npm run lint
```

## CI/CD (GitHub Actions)
- **Push ou pull request sur `develop` / `main`** (`ci.yml`) :
  - backend → PHPStan (niveau 5), PHP-CS-Fixer (non bloquant), tests unitaires puis tests d'intégration PHPUnit avec couverture
  - frontend → TypeScript, ESLint, Vitest, build de production
- **Push sur `develop` / `main`** (pas sur les pull requests) → en plus, build de l'image PHP et validation de `docker-compose.yml`
- **Tag `v*.*.*`** (`cd.yml`) → build & push sur Docker Hub des images backend, API mono-conteneur et nginx/frontend, puis création d'une GitHub Release

## Déploiement

La production repose sur **deux hébergements distincts et indépendants** :

| Partie | Hébergeur | URL |
|---|---|---|
| API Symfony | AlwaysData (mutualisé PHP + MySQL) | `https://transport-ancf.alwaysdata.net` |
| Frontend React | Vercel | `https://transport-ancf.vercel.app` |

**Vercel ne peut héberger que le frontend.** C'est une plateforme de fichiers statiques et de
fonctions serverless : elle ne fait tourner ni PHP-FPM au long cours, ni MySQL, ni Redis. L'API
Symfony a donc son propre hébergeur.

### API sur AlwaysData

Un mutualisé PHP + MySQL héberge l'API en permanence, sans conteneur et sans machine allumée à
la maison. Deux conséquences sur la configuration : Apache remplace nginx — d'où
`backend/public/.htaccess`, sans lequel toutes les routes autres que `/` renvoient 404 — et il
n'y a pas de Redis, le cache applicatif retombant sur le disque. Le choix de l'adaptateur est
fait dans `backend/config/packages/cache.php` d'après la présence de `REDIS_URL` : le même code
tourne sur Redis en Docker et sur disque ici, sans modification.

**1. Dans l'admin AlwaysData**, avant tout déploiement :

| Écran | Réglage |
|---|---|
| Environnement → PHP | version 8.2 ou plus (`composer.json` l'exige) |
| Bases de données → MySQL | créer la base et son utilisateur, noter hôte / base / identifiants |
| Accès distant → SSH | activer, et y déposer sa clé publique pour éviter la saisie du mot de passe |
| Web → Sites | site **PHP**, adresse `transport-ancf.alwaysdata.net`, racine `/www/Projet_ANCF/backend/public` |

La racine du site pointe sur `public/`, jamais sur la racine du dépôt : sinon `.env.local`, les
clés JWT et le code source deviennent téléchargeables depuis le navigateur.

**2. Récupérer le code et le configurer**, en SSH (première installation seulement) :
```bash
ssh transport-ancf@ssh-transport-ancf.alwaysdata.net
git clone https://github.com/Alexiiiiiiiiis/Projet_ANCF.git ~/www/Projet_ANCF
cd ~/www/Projet_ANCF/backend
cp .env.dist .env.local   # puis renseigner les valeurs ci-dessous
```

`.env.local` (ignoré par git, lu aussi bien par Apache que par les commandes SSH) :

| Variable | Valeur |
|---|---|
| `APP_ENV` | `prod` |
| `APP_SECRET` | chaîne aléatoire (`openssl rand -hex 32`) |
| `DATABASE_URL` | `mysql://utilisateur:motdepasse@mysql-transport-ancf.alwaysdata.net:3306/base?serverVersion=8.0&charset=utf8mb4` |
| `JWT_PASSPHRASE` | passphrase des clés JWT (générées au premier déploiement) |
| `REDIS_URL` | laisser **vide** — AlwaysData n'en fournit pas, le cache bascule sur le disque |
| `CORS_ALLOW_ORIGIN` | `^https://transport-ancf(-[a-z0-9-]+)?\.vercel\.app$` |
| `FRONTEND_URL` | `https://transport-ancf.vercel.app` |
| `IDFM_API_KEY` | clé PRIM (facultatif — données simulées si vide) |
| `MAILER_DSN` | `null://null`, ou un DSN SMTP réel |

Un mot de passe MySQL contenant `@ : / ? # &` doit être encodé (`%40`, `%3A`…) : ces caractères
coupent le DSN en deux et produisent une erreur de connexion peu lisible.

**3. Déployer** — c'est la même commande à chaque mise à jour :
```bash
ssh transport-ancf@ssh-transport-ancf.alwaysdata.net
cd ~/www/Projet_ANCF
git pull origin main
sh scripts/deploy-alwaysdata.sh
```
Le script installe les dépendances sans les paquets de développement, génère les clés JWT à la
première exécution, applique les migrations, puis vide et réchauffe le cache. Ce vidage n'est pas
cosmétique : le conteneur de services Symfony est compilé une fois et mis en cache, donc un
changement de configuration — l'adaptateur de cache, par exemple — reste sans effet tant que le
cache n'a pas été reconstruit.

### Frontend sur Vercel

La configuration de build est dans `frontend/vercel.json` (build Vite, réécriture SPA, en-têtes
de sécurité et cache des assets). Le déploiement se déclenche automatiquement à chaque push sur
`main`, via l'intégration GitHub.

Deux réglages se font **dans le dashboard Vercel**, pas dans le dépôt :

1. **Settings → Build and Deployment → Root Directory : `frontend`.** Sans ça, Vercel lance
   l'installation à la racine du dépôt, où il n'y a ni `package.json` ni `package-lock.json`, et
   le build échoue sur `npm ci` (`EUSAGE`). À noter : un `npx vercel deploy` lancé depuis
   `frontend/` ignore ce réglage et passe malgré tout — le défaut ne se voit donc que sur les
   déploiements déclenchés par un push.
2. **Variable d'environnement `VITE_API_URL`** = `https://transport-ancf.alwaysdata.net/api`.
   Elle est lue **au moment du build** et figée dans le bundle : la modifier impose un
   redéploiement, un simple redémarrage ne suffit pas.

L'URL doit être en `https://` : le site Vercel étant servi en HTTPS, un appel vers une API en
`http://` est bloqué par le navigateur (contenu mixte).

### Vérifier un déploiement

```bash
# API
curl https://transport-ancf.alwaysdata.net/api/health
# {"status":"ok","database":"up","time":"..."}

# Frontend
curl -s -o /dev/null -w '%{http_code}\n' https://transport-ancf.vercel.app
```
`/api/health` renvoie 503 tant que la base n'est pas jointe : c'est la sonde utilisée par
l'hébergeur pour ne pas router de trafic vers une instance pas encore prête.

> **Ne pas lancer `scripts/demo-publique.sh`.** Ce script date d'avant l'hébergement sur
> AlwaysData : il démarre l'API sur le poste de développement derrière un tunnel Cloudflare
> éphémère, **puis réécrit `VITE_API_URL` en production sur Vercel et redéploie**. Le site en
> ligne se retrouve branché sur une machine locale, et redevient hors service dès que le tunnel
> se ferme.

---

## Autres voies de déploiement

Ces deux voies sont fonctionnelles et leurs images sont publiées à chaque tag `v*.*.*`
(cf. `.github/workflows/cd.yml`), mais ce n'est pas ainsi que le projet est hébergé aujourd'hui.

### Serveur unique avec Docker Compose

`docker-compose.prod.yml` fait tourner la pile complète (API, nginx + frontend buildé, MySQL,
Redis) sur une seule machine, à partir des images publiées sur Docker Hub — ni code source ni
build local nécessaires.

```bash
cp .env.example .env    # MYSQL_*, DATABASE_URL, JWT_PASSPHRASE, CORS_ALLOW_ORIGIN,
                        # DOCKER_IMAGE_PREFIX (pseudo Docker Hub), VERSION (tag à déployer)
mkdir -p config/jwt
openssl genrsa -out config/jwt/private.pem 4096
openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec php php bin/console doctrine:migrations:migrate --no-interaction
```

Les clés JWT sont montées en lecture seule depuis l'hôte, jamais intégrées à l'image, qui est
publique. À sauvegarder : les régénérer invaliderait tous les jetons déjà émis. C'est la seule
configuration de déploiement où Redis sert réellement de cache applicatif, `REDIS_URL` y étant
renseignée.

### Hébergeur de conteneurs (PaaS)

`docker/api/Dockerfile` produit une image **mono-conteneur** (nginx + PHP-FPM supervisés) qui
écoute sur le port imposé par la variable `PORT` — le format attendu par Render, Clever Cloud ou
Fly.io, qui ne lancent qu'un conteneur par service. Elle est publiée sous
`<pseudo>/transport-api`.

Aucun volume persistant n'existe sur ce type d'hébergeur : les clés JWT se transmettent en
variables d'environnement (`JWT_SECRET_KEY_B64`, `JWT_PUBLIC_KEY_B64`, encodées avec
`base64 -w0`) plutôt que montées en fichiers. Sans elles, le conteneur génère une paire éphémère
au démarrage et **invalide tous les jetons émis à chaque redéploiement**. Les migrations sont
appliquées automatiquement au démarrage ; mettre `RUN_MIGRATIONS=0` si plusieurs instances
tournent en parallèle.

---

**Auteur :** Rodrigues Alexis — Bachelor CDA 2025-2026, IPSSI Paris
