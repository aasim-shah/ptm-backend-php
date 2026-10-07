# Production image: PHP 8.2 + Apache serving public/.
# Build from the repository root:  docker compose -f docker-compose.prod.yml build

# ---- Front-end assets -------------------------------------------------------
FROM node:18-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json webpack.mix.js ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY public ./public
RUN npm run prod

# ---- PHP dependencies -------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# ---- Runtime ----------------------------------------------------------------
FROM php:8.2-apache

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
# grpc is required by google/cloud-firestore (chat). It compiles from source, so the first build is slow.
# One compile job keeps a CPU free for other services on a shared host.
# Build with --build-arg WITH_GRPC=0 for a fast image without chat support.
ARG IPE_PROCESSOR_COUNT=1
ARG WITH_GRPC=1
RUN IPE_PROCESSOR_COUNT=${IPE_PROCESSOR_COUNT} install-php-extensions pdo_mysql mysqli gd zip intl bcmath exif opcache pcntl \
    && if [ "$WITH_GRPC" = "1" ]; then IPE_PROCESSOR_COUNT=${IPE_PROCESSOR_COUNT} install-php-extensions grpc; fi \
    && a2enmod rewrite headers \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n</Directory>\nServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-enabled/app.conf \
    && echo 'ServerName localhost' > /etc/apache2/conf-enabled/servername.conf

COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/js /var/www/html/public/js
COPY --from=assets --chown=www-data:www-data /app/public/css /var/www/html/public/css
COPY --from=assets --chown=www-data:www-data /app/public/mix-manifest.json /var/www/html/public/mix-manifest.json

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
