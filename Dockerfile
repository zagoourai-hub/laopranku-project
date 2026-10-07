FROM php:8.4-cli

COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN install-php-extensions pdo_pgsql pcntl zip gd intl bcmath && apt-get update \
    && apt-get install -y --no-install-recommends git unzip && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
