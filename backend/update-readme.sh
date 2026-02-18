#!/bin/bash
# Auto-README - Met à jour le README avec les infos actuelles

VERSION=$(node -p "require('./package.json').version")
DATE=$(date +%Y-%m-%d)
PROJECT=$(node -p "require('./package.json').name")

# CORRECTION: Remplacer dans l'URL du badge Version
sed -i "s|badge/Version: [^-]*|badge/Version: $VERSION|" README.md

# CORRECTION: Remplacer dans l'URL du badge Date
sed -i "s|Dernière%20mise%20à%20jour-[^\-]*-[^\-]*-[^-]*|Dernière%20mise%20à%20jour-$DATE|" README.md

# CORRECTION: Remplacer la commande d'installation
sed -i "s|npx create-.*@latest|npx create-$PROJECT@latest|" README.md

echo "✅ README mis à jour (v$VERSION - $DATE)"