#!/bin/bash
# Auto-README - Met à jour le README avec les infos actuelles

VERSION=$(node -p "require('./package.json').version")
DATE=$(date +%Y-%m-%d)
PROJECT=$(node -p "require('./package.json').name")

# Mise à jour directe du README.md
sed -i "s/Version: .*/Version: $VERSION/" README.md
sed -i "s/Dernière mise à jour: .*/Dernière mise à jour: $DATE/" README.md
sed -i "s/npx create-.*/npx create-$PROJECT@latest mon-projet/" README.md

echo "✅ README mis à jour (v$VERSION - $DATE)"