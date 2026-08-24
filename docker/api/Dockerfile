# Image API auto-suffisante : nginx et PHP-FPM dans un même conteneur, à l'écoute du port
# imposé par la variable PORT.
#
# Elle existe parce que les hébergeurs PaaS (Railway, Render, Clever Cloud, Fly.io) ne
# lancent qu'un seul conteneur par service et choisissent eux-mêmes le port : le couple
# docker/php + docker/nginx, qui suppose deux conteneurs sur un réseau Docker partagé et un
# port 80 fixe, n'y est pas déployable tel quel. Le déploiement Docker Compose (dev comme
# docker-compose.prod.yml) continue d'utiliser docker/php et docker/nginx, pas cette image.
#
# Contexte de build : la racine du dépôt.
#   docker build -f docker/api/Dockerfile -t transport-api .
FROM php:8.2-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        nginx \
        supervisor \
        gettext-base \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        libicu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Composer désactive ses plugins lorsqu'il tourne en root, et symfony/runtime en est un :
# sans cette variable, vendor/autoload_runtime.php n'est pas généré et bin/console comme
# public/index.php meurent au démarrage sur « Symfony Runtime is missing ».
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY docker/api/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/api/supervisord.conf /etc/supervisor/conf.d/app.conf
COPY docker/api/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY docker/api/entrypoint.sh /usr/local/bin/entrypoint

# Debian livre un vhost par défaut sur le port 80 qui entrerait en conflit avec le nôtre.
RUN rm -f /etc/nginx/sites-enabled/default \
    && chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/backend

# composer.lock d'abord : la couche de dépendances n'est réinstallée que s'il change.
# Pas de `|| true` ici — un composer install qui échoue doit casser le build, pas produire
# une image incomplète qui ne se révélera qu'au démarrage.
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY backend/ .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && mkdir -p var/cache var/log config/jwt \
    && chown -R www-data:www-data var config/jwt

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    PORT=8080

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf", "-n"]
