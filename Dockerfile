FROM php:8.3-cli-alpine

RUN apk add --no-cache \
    curl \
    libzip-dev \
    oniguruma-dev \
    nodejs \
    npm \
    && docker-php-ext-install zip mbstring pcntl

RUN apk add --no-cache $PHPIZE_DEPS openssl-dev \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts

COPY package*.json ./
RUN npm install

COPY . .

RUN composer dump-autoload --optimize \
    && npm run build \
    && chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8000

CMD ["sh", "-c", "php artisan config:clear && php artisan config:cache && php artisan serve --host=0.0.0.0 --port=8000"]
