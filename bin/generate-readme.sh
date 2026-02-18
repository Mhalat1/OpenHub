#!/bin/bash
# bin/update-readme.sh

cd $(dirname $0)/..

# Aller dans backend pour les infos Symfony
cd backend

# Récupération des informations
PROJECT_NAME=$(grep '"name"' composer.json | cut -d '"' -f4 | cut -d '/' -f2)
PHP_VERSION=$(grep '"php"' composer.json | grep -o '[0-9.]\+' | head -1)
SYMFONY_VERSION=$(composer show symfony/symfony | grep -o 'v[0-9.]\+' | head -1 || echo "v6.4")
DB_TYPE=$(grep -i "DATABASE_URL" .env | grep -o "mysql\|postgresql\|sqlite" || echo "mysql")
DATE=$(date +%Y-%m-%d)

# Versions installées
COMPOSER_VERSION=$(composer --version 2>/dev/null | grep -o '[0-9.]\+' | head -1 || echo "non installé")
NODE_VERSION=$(node --version 2>/dev/null || echo "non installé")
YARN_VERSION=$(yarn --version 2>/dev/null || echo "non installé")
GIT_VERSION=$(git --version 2>/dev/null | grep -o '[0-9.]\+' | head -1 || echo "non installé")
DOCKER_VERSION=$(docker --version 2>/dev/null | grep -o '[0-9.]\+' | head -1 || echo "non installé")

# Statistiques
DEPENDENCIES_COUNT=$(composer show --direct | wc -l)
DEV_DEPENDENCIES=$(composer show --direct --dev | wc -l)

# Revenir à la racine
cd ..

# Générer le README depuis le template
cp README.template.md README.md

# Remplacer TOUTES les variables (version SÉCURISÉE avec | comme séparateur)
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

echo "✅ README.md généré avec succès !"
echo "📊 Projet: $PROJECT_NAME"
echo "📅 Date: $DATE"
echo "🎯 Symfony: $SYMFONY_VERSION"