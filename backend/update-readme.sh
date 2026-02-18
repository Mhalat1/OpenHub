#!/bin/bash
# bin/update-install-readme.sh

cd $(dirname $0)/..

# Récupération des informations dynamiques
PROJECT_NAME=$(grep '"name"' composer.json | cut -d '"' -f4 | cut -d '/' -f2)
PHP_VERSION=$(grep '"php"' composer.json | grep -o '[0-9.]\+' | head -1)
SYMFONY_VERSION=$(composer show symfony/symfony | grep -o 'v[0-9.]\+' | head -1 || echo "v6.4")
DB_TYPE=$(grep -i "DATABASE_URL" .env | grep -o "mysql\|postgresql\|sqlite" || echo "mysql")
DATE=$(date +%Y-%m-%d)

# Vérification des prérequis installés
COMPOSER_VERSION=$(composer --version 2>/dev/null | grep -o '[0-9.]\+' | head -1 || echo "non installé")
NODE_VERSION=$(node --version 2>/dev/null || echo "non installé")
YARN_VERSION=$(yarn --version 2>/dev/null || echo "non installé")
GIT_VERSION=$(git --version 2>/dev/null | grep -o '[0-9.]\+' | head -1 || echo "non installé")
DOCKER_VERSION=$(docker --version 2>/dev/null | grep -o '[0-9.]\+' | head -1 || echo "non installé")

# Compter les dépendances
DEPENDENCIES_COUNT=$(composer show --direct | wc -l)
DEV_DEPENDENCIES=$(composer show --direct --dev | wc -l)

# Mise à jour du README.md
sed -i "s/^\(Projet :\).*/\1 $PROJECT_NAME/" README.md
sed -i "s/^\(PHP nécessaire :\).*/\1 $PHP_VERSION+/" README.md
sed -i "s/^\(Symfony :\).*/\1 $SYMFONY_VERSION/" README.md
sed -i "s/^\(Base de données :\).*/\1 $DB_TYPE/" README.md
sed -i "s/^\(Dernière vérification :\).*/\1 $DATE/" README.md

# Mise à jour de la section prérequis
sed -i "/## 📋 Prérequis/,/##/ s/Composer .*/Composer : $COMPOSER_VERSION/" README.md
sed -i "/## 📋 Prérequis/,/##/ s/Node.js .*/Node.js : $NODE_VERSION/" README.md
sed -i "/## 📋 Prérequis/,/##/ s/Yarn .*/Yarn : $YARN_VERSION/" README.md
sed -i "/## 📋 Prérequis/,/##/ s/Git .*/Git : $GIT_VERSION/" README.md
sed -i "/## 📋 Prérequis/,/##/ s/Docker .*/Docker : $DOCKER_VERSION/" README.md

# Mise à jour des stats
sed -i "s/\(📦 Dépendances :\) .*/\1 $DEPENDENCIES_COUNT/" README.md
sed -i "s/\(🔧 Développement :\) .*/\1 $DEV_DEPENDENCIES/" README.md

echo "✅ README d'installation mis à jour ($PROJECT_NAME - $DATE)"