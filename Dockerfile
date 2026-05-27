FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    nginx \
    nodejs \
    npm \
    supervisor \
    curl \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-install zip mbstring pcntl

# Instalar extensión MongoDB para PHP
RUN apk add --no-cache $PHPIZE_DEPS openssl-dev \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && apk del $PHPIZE_DEPS

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY package*.json ./
RUN npm install

COPY . .

RUN npm run build \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

RUN mkdir -p /var/log/supervisor /var/run

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 8000

CMD ["/start.sh"]
