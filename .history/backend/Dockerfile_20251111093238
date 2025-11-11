FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    git unzip libicu-dev libzip-dev libpng-dev zip \
    libonig-dev curl && \
    docker-php-ext-install intl pdo pdo_mysql gd zip opcache mbstring

RUN a2enmod rewrite headers

WORKDIR /var/www/html

COPY . .

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Configure Apache for Render
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Add startup script for Render
COPY build.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/build.sh

# Permissions
RUN chown -R www-data:www-data /var/www/html/var
RUN chmod -R 755 var/

EXPOSE 80

# Use the startup script
CMD ["/usr/local/bin/build.sh"]