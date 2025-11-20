FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libzip-dev libpng-dev zip libonig-dev curl && \
    docker-php-ext-install intl pdo pdo_mysql gd zip opcache mbstring && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

WORKDIR /var/www/html

# ✅ COPIER SEULEMENT LES FICHIERS NÉCESSAIRES
COPY composer.json composer.lock symfony.lock ./
COPY bin/ bin/
COPY config/ config/
COPY public/ public/
COPY src/ src/
COPY templates/ templates/

# ✅ COPIER .env.production S'IL EXISTE
COPY .env.production .env.production 2>/dev/null || true

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader --no-scripts

# ✅ CRÉER LA STRUCTURE DE DOSSIERS AVEC BONNES PERMISSIONS
RUN mkdir -p var/cache var/log config/jwt && \
    chown -R www-data:www-data var/ && \
    chmod -R 775 var/

# ✅ CRÉER .env POUR LA PRODUCTION
RUN if [ -f ".env.production" ]; then \
        cp .env.production .env && \
        echo "✅ .env.production copied to .env"; \
    else \
        echo "APP_ENV=prod" > .env; \
        echo "APP_SECRET=temp_$(date +%s)" >> .env; \
        echo "DATABASE_URL=mysql://temp" >> .env; \
        echo "✅ Minimal .env created"; \
    fi

ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && sed -i '/<Directory \/var\/www\/html\/public>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# ✅ CHANGER L'UTILISATEUR POUR www-data
USER www-data

EXPOSE 80

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
        exit 1; \
    fi; \
    echo "=== Warming up cache ==="; \
    php bin/console cache:warmup || echo "⚠️  Cache warmup failed, but continuing..."; \
    echo "=== Starting Apache ==="; \
    apache2-foreground \
'