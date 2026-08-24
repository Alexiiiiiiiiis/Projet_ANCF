#!/bin/sh
# Remet l'API en ligne pour une démonstration publique et rebranche le frontend Vercel dessus.
#
# Contexte : le frontend est hébergé en permanence sur Vercel, mais l'API Symfony tourne sur
# cette machine et est exposée par un tunnel Cloudflare. Un tunnel « quick » ne demande aucun
# compte, mais son URL change à chaque démarrage — d'où ce script, qui la récupère et la
# réinjecte dans la configuration Vercel avant de redéployer.
#
# À lancer depuis la racine du dépôt :  sh scripts/demo-publique.sh
#
# Prérequis : Docker Desktop démarré, et `npx vercel login` déjà fait une fois.
set -eu

PROJET_DIR=$(cd "$(dirname "$0")/.." && pwd)
cd "$PROJET_DIR"

PORT_API=8090
CONTENEUR=ancf_api_public
IMAGE=transport-api:local
CLOUDFLARED="$PROJET_DIR/.tools/cloudflared.exe"

log() { echo "==> $*"; }

# --- Dépendances -------------------------------------------------------------------------
if [ ! -f "$CLOUDFLARED" ]; then
    log "téléchargement de cloudflared"
    mkdir -p "$PROJET_DIR/.tools"
    curl -sL -o "$CLOUDFLARED" \
        https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe
fi

# --- Base de données et cache ------------------------------------------------------------
# L'API a besoin de MySQL et Redis, fournis par la stack de développement.
log "démarrage de MySQL et Redis"
docker compose up -d mysql redis >/dev/null

# --- Clés JWT ----------------------------------------------------------------------------
# Générées une seule fois : les régénérer invaliderait tous les comptes déjà connectés.
if [ ! -f config/jwt/private.pem ]; then
    log "génération des clés JWT (première exécution)"
    PASSPHRASE=$(grep '^JWT_PASSPHRASE=' .env | cut -d= -f2-)
    mkdir -p config/jwt
    openssl genrsa -out config/jwt/private.pem -aes256 -passout pass:"$PASSPHRASE" 4096 2>/dev/null
    openssl rsa -pubout -in config/jwt/private.pem -passin pass:"$PASSPHRASE" \
        -out config/jwt/public.pem 2>/dev/null
fi

# --- Image de l'API ----------------------------------------------------------------------
if ! docker image inspect "$IMAGE" >/dev/null 2>&1; then
    log "construction de l'image API"
    docker build -f docker/api/Dockerfile -t "$IMAGE" .
fi

# --- Conteneur API -----------------------------------------------------------------------
log "démarrage de l'API sur le port $PORT_API"
PASSPHRASE=$(grep '^JWT_PASSPHRASE=' .env | cut -d= -f2-)
IDFM=$(grep '^IDFM_API_KEY=' .env | cut -d= -f2-)
docker rm -f "$CONTENEUR" >/dev/null 2>&1 || true

# MSYS_NO_PATHCONV : sans lui, Git Bash traduit les chemins absolus du conteneur en chemins
# Windows (/var/www/... devient C:/Program Files/Git/var/www/...).
MSYS_NO_PATHCONV=1 docker run -d --name "$CONTENEUR" --restart unless-stopped \
    --network projet_ancf_network -p "$PORT_API:8080" \
    -v "$PROJET_DIR/config/jwt:/var/www/backend/config/jwt:ro" \
    -e APP_ENV=prod -e APP_DEBUG=0 \
    -e APP_SECRET="$(openssl rand -hex 32)" \
    -e DATABASE_URL='mysql://ancf_user:ancf_pass@mysql:3306/ancf_transport?serverVersion=8.0&charset=utf8mb4' \
    -e REDIS_URL='redis://redis:6379' \
    -e JWT_PASSPHRASE="$PASSPHRASE" \
    -e IDFM_API_KEY="$IDFM" \
    -e CORS_ALLOW_ORIGIN='^https://transport-ancf(-[a-z0-9]+)?(-alexis12)?\.vercel\.app$' \
    -e MAILER_DSN='null://null' \
    -e FRONTEND_URL='https://transport-ancf.vercel.app' \
    "$IMAGE" >/dev/null

log "attente du démarrage (cache, migrations)"
i=0
until curl -sf "http://localhost:$PORT_API/api/health" >/dev/null 2>&1; do
    i=$((i + 1))
    [ "$i" -gt 60 ] && { echo "L'API n'a pas démarré. Journaux : docker logs $CONTENEUR"; exit 1; }
    sleep 2
done
log "API prête"

# --- Tunnel ------------------------------------------------------------------------------
log "ouverture du tunnel Cloudflare"
JOURNAL="$PROJET_DIR/.tools/tunnel.log"
: > "$JOURNAL"
"$CLOUDFLARED" tunnel --url "http://localhost:$PORT_API" --no-autoupdate >"$JOURNAL" 2>&1 &
TUNNEL_PID=$!

URL=""
i=0
while [ -z "$URL" ]; do
    i=$((i + 1))
    [ "$i" -gt 30 ] && { echo "URL du tunnel introuvable. Journal : $JOURNAL"; kill $TUNNEL_PID; exit 1; }
    sleep 2
    URL=$(grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' "$JOURNAL" | head -1)
done
log "tunnel ouvert : $URL"

# --- Frontend Vercel ---------------------------------------------------------------------
# VITE_API_URL est figée dans le bundle au moment du build : changer la variable impose un
# redéploiement, un simple redémarrage ne suffirait pas.
log "mise à jour de VITE_API_URL et redéploiement"
cd frontend
npx vercel env rm VITE_API_URL production --yes >/dev/null 2>&1 || true
printf '%s/api' "$URL" | npx vercel env add VITE_API_URL production --visibility config --no-sensitive >/dev/null
npx vercel deploy --prod --yes --force >/dev/null
cd "$PROJET_DIR"

echo
echo "  Frontend : https://transport-ancf.vercel.app"
echo "  API      : $URL/api"
echo
echo "  Le tunnel tourne dans ce terminal (PID $TUNNEL_PID)."
echo "  Le fermer coupe l'accès public à l'API ; le frontend, lui, reste en ligne."
echo

wait $TUNNEL_PID
