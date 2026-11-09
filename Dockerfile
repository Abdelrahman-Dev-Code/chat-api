# ---------------------------------------------------------------------------
# Stage 1: compile the Vite bundles (CSS + Livewire/Echo JavaScript)
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2: runtime image
# ---------------------------------------------------------------------------
FROM php:8.3-cli-alpine

RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo_pgsql pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install dependencies first to take advantage of the layer cache.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 8000 8080

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
