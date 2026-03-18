FROM php:8.3-apache

# --- Phase 1 : Dépendances système ---
RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libzip-dev libpng-dev zip libonig-dev curl && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# --- Phase 2 : Extensions PHP ---
RUN docker-php-ext-install intl pdo pdo_mysql gd zip opcache mbstring && \
    a2enmod rewrite headers

# --- Composer depuis l'image officielle ---
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# --- Phase 3 : WORKDIR imposé par Render ---
WORKDIR /opt/render/project/src/backend

# --- Code ---
COPY . .

# --- Phase 4 : Composer production ---
RUN composer install --no-dev --optimize-autoloader --no-scripts

# --- Phase 5 : Permissions ---
RUN mkdir -p var/cache var/log && \
    chown -R www-data:www-data /opt/render/project/src/backend && \
    chmod -R 755 /opt/render/project/src/backend && \
    chmod -R 775 /opt/render/project/src/backend/var

# --- Phase 6 : Configuration Apache explicite ---
RUN printf '<VirtualHost *:80>\n\
    DocumentRoot /opt/render/project/src/backend/public\n\
    <Directory /opt/render/project/src/backend/public>\n\
        AllowOverride All\n\
        Require all granted\n\
        Options -Indexes +FollowSymLinks\n\
    </Directory>\n\
</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

EXPOSE 80
CMD ["apache2-foreground"]