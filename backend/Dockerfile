FROM php:8.3-apache

# --- Installation dépendances ---
RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libzip-dev libpng-dev zip libonig-dev curl && \
    docker-php-ext-install intl pdo pdo_mysql gd zip opcache mbstring && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

# 👉 CHANGEMENT CRITIQUE : Ajouter /backend au chemin
WORKDIR /opt/render/project/src/backend
COPY . .

# --- Composer ---
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader --no-scripts

# --- Préparation des répertoires cache/log ---
RUN mkdir -p var/cache var/log && \
    chown -R www-data:www-data var && \
    chmod -R 775 var

# --- 🔧 CORRECTION DES PERMISSIONS ---
RUN chown -R www-data:www-data /opt/render/project/src/backend && \
    chmod -R 755 /opt/render/project/src/backend && \
    chmod -R 775 /opt/render/project/src/backend/var

# --- Configuration Apache avec le bon chemin ---
ENV APACHE_DOCUMENT_ROOT /opt/render/project/src/backend/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && sed -i '/<Directory \/opt\/render\/project\/src\/backend\/public>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# --- 🔧 AJOUTER LA DIRECTIVE "Require all granted" ---
RUN echo '<Directory /opt/render/project/src/backend/public>\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>' > /etc/apache2/conf-available/render.conf \
 && a2enconf render

EXPOSE 80

USER www-data
CMD ["apache2-foreground"]