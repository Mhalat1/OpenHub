#!/bin/bash

# Installer les dépendances
composer install --no-dev --optimize-autoloader

# Générer les clés JWT
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:${JWT_PASSPHRASE}
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:${JWT_PASSPHRASE}

# Vérifier que la base de données est accessible
php bin/console doctrine:database:create --if-not-exists

# Exécuter les migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Vider le cache POUR LA PRODUCTION
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Créer .env.local pour la production
cat > .env.local << EOF
APP_ENV=prod
APP_SECRET=${APP_SECRET}
DATABASE_URL=${DATABASE_URL}
JWT_PASSPHRASE=${JWT_PASSPHRASE}
CORS_ALLOW_ORIGIN=https://react-frontend.onrender.com
EOF

# Vérifier les permissions
chmod -R 755 var/
chmod -R 755 public/