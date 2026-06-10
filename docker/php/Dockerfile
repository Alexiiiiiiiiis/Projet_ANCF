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

# Create non-root user
RUN groupadd -g 1000 appuser && useradd -u 1000 -g appuser -m appuser

WORKDIR /var/www/backend

# Copy composer files first for layer caching
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-progress 2>/dev/null || true

COPY backend/ .
RUN composer dump-autoload --optimize 2>/dev/null || true

RUN chown -R appuser:appuser /var/www/backend

USER appuser

EXPOSE 9000
