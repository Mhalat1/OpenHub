#!/bin/sh
set -e

# ----------------------------
# 1. Création utilisateur www-data si nécessaire
# ----------------------------
if ! id -u www-data >/dev/null 2>&1; then
    echo "Création de l'utilisateur www-data..."
    useradd -ms /bin/bash www-data
fi

# ----------------------------
# 2. Fix des permissions pour Symfony
# ----------------------------
echo "Réglage des permissions sur var/ et vendor/"
chown -R www-data:www-data /var/www/html/var /var/www/html/vendor
chmod -R 755 /var/www/html/var /var/www/html/vendor

# ----------------------------
# 3. Clear cache Symfony
# ----------------------------
echo "Nettoyage du cache Symfony..."
su -s /bin/sh www-data -c "php /var/www/html/bin/console cache:clear"

# ----------------------------
# 4. Lancement Apache en foreground
# ----------------------------
echo "Démarrage d'Apache..."
exec apache2-foreground
