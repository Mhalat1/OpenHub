#!/bin/bash
# backend/bin/generate-readme.sh

# Se placer dans le dossier backend
cd $(dirname $0)/..

# Récupérer les infos depuis composer.json
PROJECT_NAME=$(grep '"name"' composer.json | cut -d '"' -f4 | cut -d '/' -f2)
PHP_VERSION=$(grep '"php"' composer.json | grep -o '[0-9.]\+' | head -1)

# Version Symfony (avec fallback si composer pas dispo)
if command -v composer &> /dev/null; then
    SYMFONY_VERSION=$(composer show symfony/framework-bundle 2>/dev/null | grep -o 'v[0-9.]\+' | head -1)
fi
if [ -z "$SYMFONY_VERSION" ]; then
    # Fallback: lire depuis composer.json directement
    SYMFONY_VERSION=$(grep -A5 '"symfony/framework-bundle"' composer.json | grep -o '[0-9]\.[0-9]' | head -1 || echo "7.3")
fi

# Type de base de données
DB_TYPE="mysql"
if [ -f ".env" ]; then
    DB_TYPE=$(grep -i "DATABASE_URL" .env | grep -o "mysql\|postgresql\|sqlite" | head -1 || echo "mysql")
fi

DATE=$(date +%Y-%m-%d)

# Valeurs par défaut pour GitHub Actions (où les commandes peuvent ne pas exister)
COMPOSER_VERSION="2.6.5"
NODE_VERSION="18.18.0" 
YARN_VERSION="1.22.19"
GIT_VERSION=$(git --version 2>/dev/null | grep -o '[0-9.]\+' | head -1 || echo "2.42.0")
DOCKER_VERSION="24.0.6"

# Statistiques
DEPENDENCIES_COUNT=$(grep '"require"' composer.json | grep -o '"' | wc -l || echo "25")
DEV_DEPENDENCIES=$(grep '"require-dev"' composer.json | grep -o '"' | wc -l || echo "4")

# Revenir à la racine
cd ..

# Générer le README depuis le template
cp README.template.md README.md

# Remplacer TOUTES les variables
sed -i "s|__PROJECT_NAME__|$PROJECT_NAME|g" README.md
sed -i "s|__DATE__|$DATE|g" README.md
sed -i "s|__PHP_VERSION__|$PHP_VERSION|g" README.md
sed -i "s|__SYMFONY_VERSION__|$SYMFONY_VERSION|g" README.md
sed -i "s|__DB_TYPE__|$DB_TYPE|g" README.md
sed -i "s|__COMPOSER_VERSION__|$COMPOSER_VERSION|g" README.md
sed -i "s|__NODE_VERSION__|$NODE_VERSION|g" README.md
sed -i "s|__YARN_VERSION__|$YARN_VERSION|g" README.md
sed -i "s|__GIT_VERSION__|$GIT_VERSION|g" README.md
sed -i "s|__DOCKER_VERSION__|$DOCKER_VERSION|g" README.md
sed -i "s|__DEPENDENCIES_COUNT__|$DEPENDENCIES_COUNT|g" README.md
sed -i "s|__DEV_DEPENDENCIES__|$DEV_DEPENDENCIES|g" README.md

echo "✅ README.md généré avec les valeurs :"
echo "PROJECT_NAME: $PROJECT_NAME"
echo "DATE: $DATE"
echo "SYMFONY: $SYMFONY_VERSION"