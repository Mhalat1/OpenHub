FROM php:8.3-apache

# Installer les dépendances PHP nécessaires et modules
RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libzip-dev libpng-dev zip \
    libonig-dev curl && \
    docker-php-ext-install intl pdo pdo_mysql gd zip opcache mbstring && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Activer les modules Apache nécessaires
RUN a2enmod rewrite headers

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier les fichiers du projet
COPY . .

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Installer les dépendances PHP
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Configurer Apache pour servir /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -i '/<Directory \/var\/www\/html\/public>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Créer le dossier JWT (vide au build)
RUN mkdir -p config/jwt

EXPOSE 80

# Commande finale : copier les secrets JWT au démarrage et lancer Apache
CMD cp /etc/secrets/private.pem config/jwt/private.pem && \
    cp /etc/secrets/public.pem config/jwt/public.pem && \
    chmod 600 config/jwt/*.pem && \
    php bin/console cache:clear --no-warmup && \
    apache2-foreground
