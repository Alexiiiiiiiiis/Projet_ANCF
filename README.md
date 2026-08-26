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

## Hébergement en ligne (Vercel + PaaS)

Alternative au déploiement sur un serveur unique décrit ci-dessus : le frontend est servi par
Vercel, l'API par un hébergeur de conteneurs. Les deux parties sont indépendantes.

**Vercel ne peut héberger que le frontend.** C'est une plateforme de fichiers statiques et de
fonctions serverless : elle ne fait tourner ni PHP-FPM au long cours, ni MySQL, ni Redis. L'API
Symfony a donc besoin de son propre hébergeur.

### Frontend sur Vercel

La configuration est dans `frontend/vercel.json` (build Vite, réécriture SPA, en-têtes de
sécurité et cache des assets).

1. Sur vercel.com : **Add New > Project**, importer `Alexiiiiiiiiis/Projet_ANCF`.
2. **Root Directory : `frontend`** — sans ça, Vercel cherche un `package.json` à la racine du
   dépôt et le build échoue.
3. Variable d'environnement `VITE_API_URL` = URL publique de l'API, suffixe `/api` compris
   (cf. `frontend/.env.production.example`). Elle est lue **au moment du build** : la modifier
   impose un redéploiement, un simple redémarrage ne suffit pas.

En ligne de commande :
```bash
cd frontend
npx vercel login
npx vercel deploy --prod
```

L'URL doit être en `https://` : le site Vercel étant servi en HTTPS, un appel vers une API en
`http://` est bloqué par le navigateur (contenu mixte).

### API sur un hébergeur de conteneurs

`docker/api/Dockerfile` produit une image **mono-conteneur** (nginx + PHP-FPM supervisés) qui
écoute sur le port imposé par la variable `PORT` — le format attendu par Railway, Render,
Clever Cloud ou Fly.io, qui ne lancent qu'un conteneur par service. Elle est publiée sur Docker
Hub à chaque tag sous `<pseudo>/transport-api`.

Le couple `docker/php` + `docker/nginx` reste l'image du déploiement Docker Compose ci-dessus ;
les deux voies coexistent.

Exemple avec Railway (`railway.json` est déjà fourni) :
1. Créer un projet à partir du dépôt GitHub — le Dockerfile et la sonde `/api/health` sont
   détectés automatiquement.
2. Ajouter les services **MySQL** et **Redis** dans le même projet.
3. Renseigner les variables d'environnement ci-dessous.
4. Générer un domaine public, puis reporter cette URL dans `VITE_API_URL` côté Vercel et dans
   `CORS_ALLOW_ORIGIN` côté API.

| Variable | Valeur |
|---|---|
| `APP_ENV` | `prod` |
| `APP_SECRET` | chaîne aléatoire (`openssl rand -hex 32`) |
| `DATABASE_URL` | `mysql://user:pass@host:3306/base?serverVersion=8.0&charset=utf8mb4` |
| `REDIS_URL` | `redis://host:6379` |
| `JWT_PASSPHRASE` | passphrase des clés JWT |
| `JWT_SECRET_KEY_B64` | clé privée PEM encodée en base64 |
| `JWT_PUBLIC_KEY_B64` | clé publique PEM encodée en base64 |
| `CORS_ALLOW_ORIGIN` | `^https://.*\.vercel\.app$` (ou le domaine exact) |
| `IDFM_API_KEY` | clé PRIM |
| `MAILER_DSN` | DSN SMTP réel |
| `FRONTEND_URL` | URL Vercel, utilisée dans les liens de réinitialisation de mot de passe |

Aucun volume persistant n'existe sur ce type d'hébergeur : les clés JWT sont transmises en
variables d'environnement plutôt que montées en fichiers.
```bash
openssl genrsa -out private.pem -aes256 -passout pass:VOTRE_PASSPHRASE 4096
openssl rsa -pubout -in private.pem -passin pass:VOTRE_PASSPHRASE -out public.pem
base64 -w0 private.pem   # → JWT_SECRET_KEY_B64
base64 -w0 public.pem    # → JWT_PUBLIC_KEY_B64
```
Sans ces deux variables, le conteneur génère une paire éphémère au démarrage et **invalide tous
les jetons émis à chaque redéploiement**.

Les migrations Doctrine sont appliquées automatiquement au démarrage du conteneur. Mettre
`RUN_MIGRATIONS=0` si plusieurs instances tournent en parallèle, pour éviter qu'elles migrent la
même base simultanément.

### API sur un hébergement mutualisé (AlwaysData)

Un mutualisé PHP + MySQL héberge l'API en permanence, sans conteneur et sans machine allumée à
la maison. Deux différences avec un hébergeur de conteneurs : Apache remplace nginx — d'où
`backend/public/.htaccess`, sans lequel toutes les routes autres que `/` renvoient 404 — et il
n'y a pas de Redis, le cache applicatif retombant alors sur le disque (`config/packages/prod/cache.php`).

**1. Dans l'admin AlwaysData**, avant tout déploiement :

| Écran | Réglage |
|---|---|
| Environnement → PHP | version 8.2 ou plus (`composer.json` l'exige) |
| Bases de données → MySQL | créer la base et son utilisateur, noter hôte / base / identifiants |
| Accès distant → SSH | activer, et y déposer sa clé publique pour éviter la saisie du mot de passe |
| Web → Sites | site **PHP**, adresse `transport-ancf.alwaysdata.net`, racine `/www/Projet_ANCF/backend/public` |

La racine du site pointe sur `public/`, jamais sur la racine du dépôt : sinon `.env.local`, les
clés JWT et le code source deviennent téléchargeables depuis le navigateur.

**2. Récupérer le code et le configurer**, en SSH :
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
| `REDIS_URL` | laisser **vide** — le cache bascule sur le disque |
| `CORS_ALLOW_ORIGIN` | `^https://transport-ancf(-[a-z0-9-]+)?\.vercel\.app$` |
| `FRONTEND_URL` | `https://transport-ancf.vercel.app` |
| `IDFM_API_KEY` | clé PRIM (facultatif — données simulées si vide) |
| `MAILER_DSN` | `null://null`, ou un DSN SMTP réel |

Un mot de passe MySQL contenant `@ : / ? # &` doit être encodé (`%40`, `%3A`…) : ces caractères
coupent le DSN en deux et produisent une erreur de connexion peu lisible.

**3. Déployer** — c'est aussi la commande de mise à jour, après un `git pull` :
```bash
sh scripts/deploy-alwaysdata.sh
```
Le script installe les dépendances sans les paquets de développement, génère les clés JWT à la
première exécution, applique les migrations et réchauffe le cache.

**4. Brancher le frontend** sur cette URL, une fois pour toutes :
```bash
cd frontend
npx vercel env rm VITE_API_URL production --yes
printf 'https://transport-ancf.alwaysdata.net/api' | npx vercel env add VITE_API_URL production
npx vercel deploy --prod
```
Contrairement au tunnel de démonstration, l'URL ne change plus : ce redéploiement n'est à faire
qu'une seule fois.

### Mode démonstration : API exposée depuis le poste de développement

Pour une soutenance, l'API peut tourner sur la machine de développement et être exposée par un
tunnel Cloudflare, sans hébergeur ni compte. Le frontend reste hébergé en permanence sur Vercel.

```bash
sh scripts/demo-publique.sh
```

Le script démarre MySQL et Redis, lance l'API en `APP_ENV=prod` sur le port 8090, ouvre le
tunnel, puis réinjecte l'URL obtenue dans `VITE_API_URL` côté Vercel et redéploie.

Deux limites à connaître : l'API n'est accessible que tant que le script tourne et que la
machine est allumée, et un tunnel « quick » reçoit une **URL différente à chaque démarrage** —
c'est pourquoi le script redéploie le frontend à chaque fois, `VITE_API_URL` étant figée dans le
bundle au moment du build.

Les clés JWT sont générées une seule fois dans `config/jwt/` (ignoré par git) et montées en
lecture seule : les comptes restent valides d'une session à l'autre.

### Vérifier un déploiement
```bash
curl https://votre-api.example.com/api/health
# {"status":"ok","database":"up","time":"..."}
```
La route renvoie 503 tant que la base n'est pas jointe : c'est la sonde utilisée par
l'hébergeur pour ne pas router de trafic vers une instance pas encore prête.

---

**Auteur :** Rodrigues Alexis — Bachelor CDA 2025-2026, IPSSI Paris
