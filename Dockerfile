FROM php:8.4-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    bash \
    curl \
    git \
    nodejs \
    npm \
    sqlite \
    sqlite-dev \
    zip \
    unzip

# PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader \
    && php -r "file_exists('.env') || copy('.env.example', '.env');" \
    && php artisan key:generate --no-interaction \
    && touch database/database.sqlite \
    && php artisan migrate --force --no-interaction \
    && npm ci --ignore-scripts \
    && npm run build

EXPOSE 9000

CMD ["php-fpm"]
