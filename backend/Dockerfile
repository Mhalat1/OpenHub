FROM php:8.3-apache

# --- Dépendances + extensions en un seul layer ---
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev zip libonig-dev curl && \
    docker-php-ext-install pdo pdo_mysql zip opcache mbstring && \
    a2enmod rewrite headers && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# --- Composer depuis l'image officielle ---
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# --- Code ---
WORKDIR /var/www/html

# SI VOTRE CODE EST DANS backend/ (d'après votre structure)
COPY ./backend /var/www/html/

# --- 🔥 CORRECTION CRUCIALE : Configurer Apache pour Symfony 🔥 ---
# Change le DocumentRoot pour pointer vers public/
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# --- Installation + permissions ---
RUN composer install --no-dev --optimize-autoloader --no-scripts && \
    mkdir -p var/cache var/log && \
    chown -R www-data:www-data . && \
    chmod -R 755 . && \
    chmod -R 775 var

# --- Debug optionnel (à commenter après) ---
RUN echo "Vérification du dossier public:" && ls -la /var/www/html/public/

EXPOSE 80
CMD ["apache2-foreground"]