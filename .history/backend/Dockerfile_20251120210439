FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libzip-dev libpng-dev zip libonig-dev curl && \
    docker-php-ext-install intl pdo pdo_mysql gd zip opcache mbstring && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

WORKDIR /var/www/html
COPY . .

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Créer le dossier JWT
RUN mkdir -p config/jwt

ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && sed -i '/<Directory \/var\/www\/html\/public>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

EXPOSE 80

# Script de démarrage corrigé
CMD sh -c ' \
    echo "=== Starting Application ==="; \
    mkdir -p config/jwt; \
    if [ -f "/etc/secrets/private.pem" ]; then \
        echo "✅ Copying JWT keys..."; \
        cp /etc/secrets/private.pem config/jwt/private.pem; \
        cp /etc/secrets/public.pem config/jwt/public.pem; \
        chmod 644 config/jwt/*.pem; \
    else \
        echo "❌ ERROR: JWT secrets not found!"; \
        echo "Please configure Secret Files in Render dashboard"; \
        exit 1; \
    fi; \
    echo "=== Clearing cache ==="; \
    php bin/console cache:clear --no-warmup; \
    echo "=== Starting Apache ==="; \
    apache2-foreground \
'