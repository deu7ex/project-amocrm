FROM php:8.2-fpm

# Установка зависимостей
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql sockets

# Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Установка Laravel
WORKDIR /var/www

COPY . .

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

RUN mkdir -p /var/www/storage /var/www/bootstrap/cache \
 && chown -R www-data:www-data /var/www \
 && chmod -R 775 /var/www/storage /var/www/bootstrap/cache



# Права
RUN chown -R www-data:www-data /var/www

# Laravel Artisan bootstrap
RUN php artisan key:generate || true

CMD ["php-fpm"]
