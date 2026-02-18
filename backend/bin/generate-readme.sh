#!/bin/bash
# backend/bin/generate-readme.sh

# Se placer dans le dossier backend
cd $(dirname $0)/..

# Récupérer les infos depuis composer.json
PROJECT_NAME=$(grep '"name"' composer.json | cut -d '"' -f4 | cut -d '/' -f2)
PHP_VERSION=$(grep '"php"' composer.json | grep -o '[0-9.]\+' | head -1)

# Version Symfony depuis "symfony/framework-bundle" ou extra.symfony.require
SYMFONY_VERSION=$(composer show symfony/framework-bundle 2>/dev/null | grep -o 'v[0-9.]\+' | head -1)
if [ -z "$SYMFONY_VERSION" ]; then
    # Fallback: chercher dans la config symfony
    SYMFONY_VERSION=$(grep -A2 '"symfony"' composer.json | grep '"require"' | grep -o '[0-9.]\+' | head -1 || echo "7.3")
fi

# Type de base de données (par défaut mysql si non trouvé)
DB_TYPE="mysql"
if [ -f ".env" ]; then
    DB_TYPE=$(grep -i "DATABASE_URL" .env | grep -o "mysql\|postgresql\|sqlite" | head -1 || echo "mysql")
fi

DATE=$(date +%Y-%m-%d)

# Versions installées
COMPOSER_VERSION=$(composer --version 2>/dev/null | grep -o '[0-9.]\+' | head -1 || echo "2.6.5")
NODE_VERSION=$(node --version 2>/dev/null || echo "18.18.0")
YARN_VERSION=$(yarn --version 2>/dev/null || echo "1.22.19")
GIT_VERSION=$(git --version 2>/dev/null | grep -o '[0-9.]\+' | head -1 || echo "2.42.0")
DOCKER_VERSION=$(docker --version 2>/dev/null | grep -o '[0-9.]\+' | head -1 || echo "24.0.6")

# Statistiques
DEPENDENCIES_COUNT=$(composer show --direct --no-dev 2>/dev/null | wc -l)
if [ "$DEPENDENCIES_COUNT" -eq 0 ]; then
    # Fallback: compter manuellement
    DEPENDENCIES_COUNT=$(grep -A100 '"require"' composer.json | grep -B100 -m1 '}' | grep -v 'require' | grep -v '}' | grep -c '"' || echo "25")
fi

DEV_DEPENDENCIES=$(composer show --direct --dev 2>/dev/null | wc -l)
if [ "$DEV_DEPENDENCIES" -eq 0 ]; then
    DEV_DEPENDENCIES=$(grep -A100 '"require-dev"' composer.json 2>/dev/null | grep -B100 -m1 '}' | grep -v 'require-dev' | grep -v '}' | grep -c '"' || echo "4")
fi

# Revenir à la racine et générer le README
cd ..
cp README.template.md README.md

# Remplacer les variables (avec | comme séparateur pour éviter les conflits)
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

echo "✅ README.md généré à la racine avec les infos depuis backend/"
echo "📊 Projet: $PROJECT_NAME"
echo "📅 Date: $DATE"
echo "🎯 Symfony: $SYMFONY_VERSION"
echo "📦 Dépendances: $DEPENDENCIES_COUNT"
echo "🔧 Dev: $DEV_DEPENDENCIES"