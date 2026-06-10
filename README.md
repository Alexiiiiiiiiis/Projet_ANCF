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
- **Brute force** : `login_throttling` Symfony (composant RateLimiter) — 5 tentatives max, puis blocage 15 minutes par couple email/IP.
- **Mots de passe** : hachés via le hasher Symfony (bcrypt/argon2 auto), minimum 8 caractères, jamais stockés ni loggés en clair.
- **Clés API** : uniquement en variables d'environnement (`.env` gitignoré), jamais en dur ni côté front.
- **Contrôle d'accès** : routes `/api/admin/*` réservées à `ROLE_ADMIN` (firewall Symfony), CORS restreint via NelmioCorsBundle.
- **RGPD** : suppression de compte (et données associées), mentions légales et politique de confidentialité dans l'application.

## Tests
```bash
# Backend (PHPUnit)
docker compose exec php vendor/bin/phpunit

# Frontend (Vitest)
docker compose exec frontend npm test
```

## CI/CD (GitHub Actions)
- **Push sur `develop`** → tests PHPUnit + TypeScript + build Docker
- **Push sur `main`** → idem + vérification docker-compose
- **Tag `v*.*.*`** → build & push images Docker Hub + GitHub Release

---

**Auteur :** Rodrigues Alexis — Bachelor CDA 2025-2026, IPSSI Paris
