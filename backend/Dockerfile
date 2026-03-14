FROM php:8.3-apache

# --- Dépendances + extensions en un seul layer ---
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev zip libonig-dev curl && \
    docker-php-ext-install pdo pdo_mysql zip opcache mbstring && \
    a2enmod rewrite headers && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# --- Composer depuis l'image officielle (plus propre que curl) ---
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# --- Code ---
WORKDIR /var/www/html
COPY . .

# --- Installation + permissions en un seul layer ---
RUN composer install --no-dev --optimize-autoloader --no-scripts && \
    mkdir -p var/cache var/log && \
    chown -R www-data:www-data . && \
    chmod -R 755 . && \
    chmod -R 775 var

EXPOSE 80
CMD ["apache2-foreground"]