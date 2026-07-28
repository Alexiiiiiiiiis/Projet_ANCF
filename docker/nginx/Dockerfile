# Image nginx auto-suffisante pour la prod : sert le build React et route /api vers PHP-FPM.
# Construite avec un contexte à la racine du repo (cf. .github/workflows/cd.yml), après que
# `npm run build` a généré frontend/dist dans la même étape.
FROM nginx:1.25-alpine

COPY docker/nginx/prod.conf /etc/nginx/conf.d/default.conf
COPY frontend/dist /var/www/frontend
COPY backend/public /var/www/backend/public
