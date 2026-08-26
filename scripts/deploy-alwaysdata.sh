#!/bin/sh
# Déploiement de l'API sur un hébergement mutualisé Apache + PHP (AlwaysData).
#
# À exécuter SUR le serveur, depuis la racine du dépôt cloné :
#     ssh transport-ancf@ssh-transport-ancf.alwaysdata.net
#     cd ~/www/Projet_ANCF && sh scripts/deploy-alwaysdata.sh
#
# Le script est ré-exécutable : c'est aussi la commande de mise à jour après un git pull.
# Il ne touche ni à la base (hors migrations) ni aux clés JWT déjà en place.
set -e

cd "$(dirname "$0")/.."
PROJET_DIR=$(pwd)
cd backend

log()   { echo "==> $*"; }
echec() { echo "ERREUR : $*" >&2; exit 1; }

# --- Vérifications préalables ------------------------------------------------------------
# Sur un mutualisé, « php » peut pointer vers une version ancienne alors qu'une récente est
# installée : la version se choisit dans l'admin (Environnement → PHP), pas ici.
command -v php >/dev/null 2>&1 || echec "php introuvable dans le PATH."
VERSION_PHP=$(php -r 'echo PHP_VERSION;')
php -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' \
    || echec "PHP $VERSION_PHP est trop ancien (8.2 minimum, cf. backend/composer.json).
Régler la version dans l'\''admin AlwaysData : Environnement → PHP."
log "PHP $VERSION_PHP"

command -v composer >/dev/null 2>&1 || echec "composer introuvable dans le PATH."

# .env.local plutôt que les variables d'environnement du site : celles-ci ne sont lues que par
# Apache, pas par les commandes lancées en SSH — migrations et vidage de cache tourneraient
# alors sur une configuration différente de celle du site.
[ -f .env.local ] || echec "backend/.env.local absent (cf. README § Hébergement mutualisé)."

for variable in APP_ENV APP_SECRET DATABASE_URL JWT_PASSPHRASE CORS_ALLOW_ORIGIN FRONTEND_URL; do
    grep -qE "^$variable=.+" .env.local || echec "$variable manquante ou vide dans backend/.env.local."
done
grep -qE '^APP_ENV=prod$' .env.local || echec "APP_ENV doit valoir prod dans backend/.env.local."

PASSPHRASE=$(grep -E '^JWT_PASSPHRASE=' .env.local | head -1 | cut -d= -f2- | sed "s/^[\"']//; s/[\"']\$//")

# --- Dépendances -------------------------------------------------------------------------
# --no-dev : PHPUnit, PHPStan et le MakerBundle n'ont rien à faire en ligne, et le quota disque
# d'un mutualisé n'est pas extensible.
log "installation des dépendances PHP"
APP_ENV=prod composer install --no-dev --optimize-autoloader --no-interaction --quiet

# --- Clés JWT ----------------------------------------------------------------------------
# Générées une seule fois : les régénérer invaliderait tous les jetons déjà émis, donc
# déconnecterait tout le monde.
if [ ! -f config/jwt/private.pem ]; then
    log "génération des clés JWT (première exécution)"
    mkdir -p config/jwt
    openssl genrsa -out config/jwt/private.pem -aes256 -passout pass:"$PASSPHRASE" 4096 2>/dev/null
    openssl rsa -pubout -in config/jwt/private.pem -passin pass:"$PASSPHRASE" \
        -out config/jwt/public.pem 2>/dev/null
    # Le mutualisé sert le site sous le même compte Unix, mais les clés n'ont aucune raison
    # d'être lisibles par le reste du monde.
    chmod 600 config/jwt/private.pem
    chmod 644 config/jwt/public.pem
fi

# Une passphrase qui ne correspond plus à la clé ne se voit qu'au premier login, pas au
# déploiement : autant s'en apercevoir maintenant.
openssl rsa -in config/jwt/private.pem -passin pass:"$PASSPHRASE" -noout 2>/dev/null \
    || echec "JWT_PASSPHRASE ne correspond pas à backend/config/jwt/private.pem.
Corriger la passphrase dans .env.local, ou supprimer config/jwt/*.pem pour repartir d'une paire
neuve (tous les jetons déjà émis seront alors invalidés)."

# --- Base de données ---------------------------------------------------------------------
log "application des migrations"
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# --- Cache -------------------------------------------------------------------------------
# Vidé puis réchauffé explicitement : sans ça, la première requête d'un visiteur paie la
# compilation du conteneur, et le cache écrit par le compte SSH peut ne pas être celui que le
# processus web réutilise.
log "vidage et préchauffage du cache"
php bin/console cache:clear --no-warmup
php bin/console cache:warmup

# --- Contrôle final ----------------------------------------------------------------------
log "déploiement terminé"
echo
echo "Vérifier depuis un poste connecté à Internet :"
echo "    curl https://transport-ancf.alwaysdata.net/api/health"
echo
echo "Racine du site à déclarer dans l'admin (Web → Sites) :"
echo "    $PROJET_DIR/backend/public"
