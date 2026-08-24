FROM php:8.2-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Composer désactive ses plugins lorsqu'il tourne en root, et symfony/runtime en est un :
# sans cette variable, vendor/autoload_runtime.php n'est pas généré et l'application meurt
# au démarrage sur « Symfony Runtime is missing ».
ENV COMPOSER_ALLOW_SUPERUSER=1

# Create non-root user
RUN groupadd -g 1000 appuser && useradd -u 1000 -g appuser -m appuser

WORKDIR /var/www/backend

# Copy composer files first for layer caching
# Pas de `|| true` : un composer qui échoue doit casser le build. Masqué, il produit une
# image sans autoloader qui n'échouera qu'au premier appel HTTP, en production.
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-progress

COPY backend/ .
RUN composer dump-autoload --optimize

RUN chown -R appuser:appuser /var/www/backend

USER appuser

EXPOSE 9000
