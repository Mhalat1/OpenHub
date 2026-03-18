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

# --- Phase 6 : Configuration Apache ---
ENV APACHE_DOCUMENT_ROOT=/opt/render/project/src/backend/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN echo '<Directory /opt/render/project/src/backend/public>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

EXPOSE 80
CMD ["apache2-foreground"]