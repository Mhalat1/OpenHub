FROM php:8.3-apache

# Installer les dépendances PHP nécessaires
RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libzip-dev libpng-dev zip \
    libonig-dev curl && \
    docker-php-ext-install intl pdo pdo_mysql gd zip opcache mbstring && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Activer les modules Apache nécessaires
RUN a2enmod rewrite headers

WORKDIR /var/www/html

# Copier les fichiers du projet
COPY . .

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Installer les dépendances PHP
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Configurer Apache pour servir /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN sed -i '/<Directory \/var\/www\/html\/public>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# # Créer les clés JWT à partir des variables d'environnement
# RUN mkdir -p config/jwt \
#     && echo "$JWT_SECRET_KEY" > config/jwt/private.pem \
#     && echo "$JWT_PUBLIC_KEY" > config/jwt/public.pem \
#     && chmod 600 config/jwt/*.pem \
#     && chown -R www-data:www-data config/jwt

EXPOSE 80

# Commande finale : nettoyage cache + lancement Apache
CMD php bin/console cache:clear --no-warmup && apache2-foreground
