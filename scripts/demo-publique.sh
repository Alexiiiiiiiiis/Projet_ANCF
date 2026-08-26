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
SITE=https://transport-ancf.vercel.app

log() { echo "==> $*"; }
echec() { echo "ERREUR : $*" >&2; exit 1; }

# Lit une variable du .env, ou retourne le défaut fourni. Ces défauts reproduisent ceux de
# docker-compose.yml : le conteneur MySQL est créé avec ces valeurs-là, l'API doit s'y
# connecter avec exactement les mêmes — les coder en dur ici a déjà cassé une démo.
lire_env() {
    valeur=$(grep -E "^$1=" .env 2>/dev/null | head -1 | cut -d= -f2- | sed "s/^[\"']//; s/[\"']\$//")
    if [ -n "$valeur" ]; then printf '%s' "$valeur"; else printf '%s' "$2"; fi
}

# Encode pour une URL de connexion : sans ça, un mot de passe contenant @ : / ? # & couperait
# le DSN Doctrine en deux, avec une erreur de connexion incompréhensible au démarrage.
url_encoder() {
    printf '%s' "$1" | od -An -tx1 | tr ' ' '\n' | grep -v '^$' | while read -r octet; do
        caractere=$(printf "\\x$octet")
        case "$caractere" in
            [a-zA-Z0-9._~-]) printf '%s' "$caractere" ;;
            *)               printf '%%%s' "$octet" ;;
        esac
    done
}

# --- Vérifications préalables ------------------------------------------------------------
# Toutes ici, avant la moindre minute de build : découvrir en pleine soutenance qu'il manque
# une session Vercel après trois minutes d'attente n'est pas une option.
[ -f .env ] || echec "fichier .env absent — le copier depuis .env.example et le renseigner."
docker info >/dev/null 2>&1 || echec "Docker Desktop n'est pas démarré."

log "vérification de la session Vercel"
(cd frontend && npx --yes vercel whoami >/dev/null 2>&1) \
    || echec "pas connecté à Vercel — lancer : cd frontend && npx vercel login"

PASSPHRASE=$(lire_env JWT_PASSPHRASE '')
[ -n "$PASSPHRASE" ] || echec "JWT_PASSPHRASE est vide dans .env."

MYSQL_BASE=$(lire_env MYSQL_DATABASE ancf_transport)
MYSQL_UTILISATEUR=$(lire_env MYSQL_USER ancf_user)
MYSQL_MOT_DE_PASSE=$(lire_env MYSQL_PASSWORD ancf_pass)
IDFM=$(lire_env IDFM_API_KEY '')

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

# Le réseau est lu sur le conteneur plutôt qu'écrit en dur : Compose le préfixe par le nom du
# projet (projet_ancf_ancf_network ici), qui change si le dépôt est cloné dans un dossier
# portant un autre nom.
RESEAU=$(docker inspect ancf_mysql \
    --format '{{range $nom, $_ := .NetworkSettings.Networks}}{{$nom}}{{end}}' 2>/dev/null)
[ -n "$RESEAU" ] || echec "réseau Docker introuvable — le conteneur ancf_mysql n'a pas démarré."

log "attente de MySQL"
i=0
until docker exec ancf_mysql mysqladmin ping -h 127.0.0.1 \
        -u"$MYSQL_UTILISATEUR" -p"$MYSQL_MOT_DE_PASSE" --silent >/dev/null 2>&1; do
    i=$((i + 1))
    [ "$i" -gt 30 ] && echec "MySQL ne répond pas avec les identifiants du .env.
Une base garde les identifiants de son tout premier démarrage : si le .env a changé depuis,
soit remettre les anciennes valeurs, soit repartir d'une base vide (docker compose down -v,
ce qui efface les comptes existants)."
    sleep 2
done

# --- Clés JWT ----------------------------------------------------------------------------
# Générées une seule fois : les régénérer invaliderait tous les comptes déjà connectés.
if [ ! -f config/jwt/private.pem ]; then
    log "génération des clés JWT (première exécution)"
    mkdir -p config/jwt
    openssl genrsa -out config/jwt/private.pem -aes256 -passout pass:"$PASSPHRASE" 4096 2>/dev/null
    openssl rsa -pubout -in config/jwt/private.pem -passin pass:"$PASSPHRASE" \
        -out config/jwt/public.pem 2>/dev/null
fi

# Une passphrase qui ne correspond plus à la clé ne se voit qu'au premier login, pas au
# démarrage : autant s'en apercevoir maintenant plutôt que devant le jury.
openssl rsa -in config/jwt/private.pem -passin pass:"$PASSPHRASE" -noout 2>/dev/null \
    || echec "JWT_PASSPHRASE ne correspond pas à config/jwt/private.pem.
Corriger la passphrase dans .env, ou supprimer config/jwt/*.pem pour repartir d'une paire
neuve (tous les jetons déjà émis seront alors invalidés)."

# --- Image de l'API ----------------------------------------------------------------------
if ! docker image inspect "$IMAGE" >/dev/null 2>&1; then
    log "construction de l'image API"
    docker build -f docker/api/Dockerfile -t "$IMAGE" .
fi

# --- Conteneur API -----------------------------------------------------------------------
log "démarrage de l'API sur le port $PORT_API"
docker rm -f "$CONTENEUR" >/dev/null 2>&1 || true

DSN_UTILISATEUR=$(url_encoder "$MYSQL_UTILISATEUR")
DSN_MOT_DE_PASSE=$(url_encoder "$MYSQL_MOT_DE_PASSE")
DSN="mysql://$DSN_UTILISATEUR:$DSN_MOT_DE_PASSE@mysql:3306/$MYSQL_BASE?serverVersion=8.0&charset=utf8mb4"

# MSYS_NO_PATHCONV : sans lui, Git Bash traduit les chemins absolus du conteneur en chemins
# Windows (/var/www/... devient C:/Program Files/Git/var/www/...).
MSYS_NO_PATHCONV=1 docker run -d --name "$CONTENEUR" --restart unless-stopped \
    --network "$RESEAU" -p "$PORT_API:8080" \
    -v "$PROJET_DIR/config/jwt:/var/www/backend/config/jwt:ro" \
    -e APP_ENV=prod -e APP_DEBUG=0 \
    -e APP_SECRET="$(openssl rand -hex 32)" \
    -e DATABASE_URL="$DSN" \
    -e REDIS_URL='redis://redis:6379' \
    -e JWT_PASSPHRASE="$PASSPHRASE" \
    -e IDFM_API_KEY="$IDFM" \
    -e CORS_ALLOW_ORIGIN='^https://transport-ancf(-[a-z0-9-]+)?\.vercel\.app$' \
    -e MAILER_DSN='null://null' \
    -e FRONTEND_URL="$SITE" \
    "$IMAGE" >/dev/null

log "attente du démarrage (cache, migrations)"
i=0
until curl -sf "http://localhost:$PORT_API/api/health" >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "$i" -gt 60 ]; then
        echo "--- 30 dernières lignes du conteneur ---" >&2
        docker logs --tail 30 "$CONTENEUR" >&2 2>&1 || true
        echec "l'API n'a pas démarré (journaux ci-dessus, ou : docker logs $CONTENEUR)."
    fi
    sleep 2
done
log "API prête"

# --- Tunnel ------------------------------------------------------------------------------
# Les exécutions précédentes laissent leur tunnel ouvert si le terminal a été fermé sans
# Ctrl+C. Ils continuent d'annoncer une URL alors que l'API derrière eux n'existe plus — de
# quoi croire à une panne de l'application, ou déboguer la mauvaise URL.
if tasklist //FI "IMAGENAME eq cloudflared.exe" 2>/dev/null | grep -q cloudflared.exe; then
    log "fermeture des tunnels laissés ouverts par une exécution précédente"
    taskkill //F //IM cloudflared.exe >/dev/null 2>&1 || true
fi

log "ouverture du tunnel Cloudflare"
JOURNAL="$PROJET_DIR/.tools/tunnel.log"
: > "$JOURNAL"
"$CLOUDFLARED" tunnel --url "http://localhost:$PORT_API" --no-autoupdate >"$JOURNAL" 2>&1 &
TUNNEL_PID=$!
trap 'kill $TUNNEL_PID 2>/dev/null || true' EXIT INT TERM

URL=""
i=0
while [ -z "$URL" ]; do
    i=$((i + 1))
    [ "$i" -gt 30 ] && echec "URL du tunnel introuvable. Journal : $JOURNAL"
    sleep 2
    URL=$(grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' "$JOURNAL" | head -1)
done
log "tunnel ouvert : $URL"

# Cloudflare annonce l'URL avant qu'elle ne soit routée : sans cette attente, le premier appel
# du jury peut tomber sur un 502. Un délai de grâce puis des sondes espacées — marteler une
# URL pas encore prête la fait répondre en erreur bien plus longtemps qu'en la laissant venir.
log "attente du routage du tunnel"
sleep 15
i=0
until curl -sf --max-time 10 "$URL/api/health" >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "$i" -gt 18 ]; then
        # Non bloquant : le déploiement Vercel qui suit prend encore une minute, ce qui laisse
        # au tunnel le temps de finir de se propager. Le contrôle final tranchera.
        echo "AVERTISSEMENT : le tunnel n'a pas encore répondu après 90 s." >&2
        echo "                Vérifier ensuite : curl $URL/api/health" >&2
        break
    fi
    sleep 5
done
[ "$i" -gt 18 ] || log "tunnel opérationnel"

# --- Frontend Vercel ---------------------------------------------------------------------
# VITE_API_URL est figée dans le bundle au moment du build : changer la variable impose un
# redéploiement, un simple redémarrage ne suffirait pas.
log "mise à jour de VITE_API_URL et redéploiement"
cd frontend
npx --yes vercel env rm VITE_API_URL production --yes >/dev/null 2>&1 || true
printf '%s/api' "$URL" | npx --yes vercel env add VITE_API_URL production \
    --visibility config --no-sensitive >/dev/null
npx --yes vercel deploy --prod --yes --force >/dev/null
cd "$PROJET_DIR"

# Contrôle de bout en bout : le site répond, et le bundle qu'il sert contient bien l'URL du
# tunnel du jour — c'est ce dernier point qui distingue « déployé » de « déployé à jour ».
log "vérification du frontend"
if curl -sf "$SITE" >/dev/null 2>&1; then
    DOMAINE=$(printf '%s' "$URL" | sed 's|https://||')
    if curl -s "$SITE" | grep -oE '/assets/[A-Za-z0-9_.-]+\.js' | head -1 \
        | { read -r actif; curl -s "$SITE$actif"; } | grep -q "$DOMAINE"; then
        log "le bundle en ligne pointe bien sur le tunnel du jour"
    else
        echo "AVERTISSEMENT : le bundle servi ne référence pas $DOMAINE — le déploiement" >&2
        echo "                Vercel est peut-être encore en cours. Réessayer dans une minute." >&2
    fi
else
    echo "AVERTISSEMENT : $SITE ne répond pas encore." >&2
fi

echo
echo "  Frontend : $SITE"
echo "  API      : $URL/api"
echo
echo "  Comptes de test : admin@ancf.fr / Admin1234!   —   user@ancf.fr / User1234!"
echo
echo "  Le tunnel tourne dans ce terminal (PID $TUNNEL_PID)."
echo "  Le fermer coupe l'accès public à l'API ; le frontend, lui, reste en ligne."
echo

wait $TUNNEL_PID
