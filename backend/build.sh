#!/bin/bash

set -e  # Arrêter en cas d'erreur

echo "🔧 Début du déploiement Symfony..."

# Installer les dépendances
echo "📦 Installation des dépendances..."
composer install --no-dev --optimize-autoloader --no-progress --no-interaction

# Générer les clés JWT
echo "🔑 Génération des clés JWT..."
mkdir -p config/jwt
if [ ! -f config/jwt/private.pem ]; then
    openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:${JWT_PASSPHRASE}
    openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:${JWT_PASSPHRASE}
fi

# Base de données
echo "🗄️ Configuration de la base de données..."
php bin/console doctrine:database:create --if-not-exists --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction

# Cache production
echo "⚡ Configuration du cache..."
php bin/console cache:clear --env=prod --no-debug
php bin/console cache:warmup --env=prod --no-debug

# Permissions
echo "🔒 Configuration des permissions..."
chmod -R 755 var/
chmod -R 755 public/

echo "✅ Déploiement terminé avec succès!"