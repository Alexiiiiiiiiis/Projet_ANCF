#!/bin/sh
# Préparation du conteneur API avant de passer la main à supervisord.
set -eu

: "${PORT:=8080}"
: "${APP_ENV:=prod}"
export PORT APP_ENV

log() { echo "[entrypoint] $*"; }

# --- nginx : port imposé par l'hébergeur ------------------------------------------------
# Liste explicite de variables : sinon envsubst viderait $uri, $is_args et consorts.
envsubst '${PORT}' \
    < /etc/nginx/templates/default.conf.template \
    > /etc/nginx/conf.d/default.conf
log "nginx écoutera sur le port ${PORT}"

# --- Clés JWT ---------------------------------------------------------------------------
# Aucun volume persistant sur un PaaS : les clés arrivent en variables d'environnement,
# encodées en base64 pour survivre au passage par un formulaire web.
JWT_DIR=/var/www/backend/config/jwt
mkdir -p "$JWT_DIR"

if [ -n "${JWT_SECRET_KEY_B64:-}" ] && [ -n "${JWT_PUBLIC_KEY_B64:-}" ]; then
    printf '%s' "$JWT_SECRET_KEY_B64" | base64 -d > "$JWT_DIR/private.pem"
    printf '%s' "$JWT_PUBLIC_KEY_B64" | base64 -d > "$JWT_DIR/public.pem"
    chmod 640 "$JWT_DIR/private.pem" "$JWT_DIR/public.pem"
    chown www-data:www-data "$JWT_DIR/private.pem" "$JWT_DIR/public.pem"
    log "clés JWT restaurées depuis l'environnement"
elif [ ! -f "$JWT_DIR/private.pem" ]; then
    log "ATTENTION : ni JWT_SECRET_KEY_B64 ni clé existante — génération d'une paire éphémère."
    log "ATTENTION : tous les jetons émis seront invalidés au prochain redémarrage."
    php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
    chown -R www-data:www-data "$JWT_DIR"
fi

# --- Cache Symfony ----------------------------------------------------------------------
# Le build de l'image utilise --no-scripts : le cache n'y a jamais été chauffé, et il
# dépend de variables (DATABASE_URL, REDIS_URL) qui n'existent qu'ici.
php bin/console cache:clear --no-warmup
php bin/console cache:warmup
chown -R www-data:www-data var

# --- Migrations -------------------------------------------------------------------------
# Mettre RUN_MIGRATIONS=0 si le schéma est appliqué par un job séparé (déploiement à
# plusieurs instances : sinon chacune tenterait de migrer la même base en parallèle).
if [ "${RUN_MIGRATIONS:-1}" != "0" ]; then
    log "application des migrations Doctrine"
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

log "démarrage des services"
exec "$@"
