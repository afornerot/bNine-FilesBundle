# syntax=docker/dockerfile:1.6

# =====================================================
# Stage 0 : base PHP + libs système (build rare)
# =====================================================
FROM php:8.2-apache AS base

RUN apt-get update && apt-get install -y \
    git unzip curl \
    libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install intl zip gd opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html

# =====================================================
# Stage 1 : vendor PHP (build lors d'un changement de deps)
# =====================================================
FROM base AS vendor

WORKDIR /var/www/html

# On utilise le composer.json du sample (contexte additionnel 'app')
# pour avoir une seule source de verite des dependances.
COPY --from=app sample/composer.json composer.json

RUN composer install \
        --no-interaction \
        --prefer-dist \
        --no-progress \
        --no-scripts \
        --ignore-platform-reqs \
        --no-security-blocking \
    && rm -rf vendor/bnine/filesbundle \
    && composer dump-autoload --no-scripts --optimize

# =====================================================
# Stage final : assemble
# =====================================================
FROM base

COPY apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
COPY seed.sh /usr/local/bin/seed.sh

RUN a2enmod rewrite headers \
    && chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/seed.sh \
    && mkdir -p public

# Vendor depuis stage 1
COPY --from=vendor /var/www/html/vendor /var/www/html/vendor

# Les libs tierces (jQuery, Bootstrap, FontAwesome, Dropzone, Cropper, GLightbox)
# sont embarquées dans vendor/bnine/filesbundle/Resources/public/lib/ et seront
# publiées dans public/bundles/bninefiles/lib/ via assets:install au démarrage.

# Skeleton code app (sera écrasé par bind-mount)
COPY --from=app sample/composer.json ./composer.json
COPY --from=app sample/bin ./bin
COPY --from=app sample/src ./src
COPY --from=app sample/config ./config
COPY --from=app sample/templates ./templates
COPY --from=app sample/.env ./.env
COPY --from=app sample/public/index.php ./public/index.php

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
