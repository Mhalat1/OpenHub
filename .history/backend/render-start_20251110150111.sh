#!/bin/sh
set -e

# ----------------------------
# 1. Création de l'utilisateur www-data si nécessaire
# ----------------------------
if ! id -u www-data >/dev/null 2>&1; then
    echo "👤 Création de l'utilisateur www-data..."
    useradd -ms /bin/bash www-data
fi

# ----------------------------
# 2. Attente de la base de données (optionnel mais utile sur Render)
# ----------------------------
if [ -n "$DATABASE_URL" ]; then
    echo "⏳ Attente de la base de données..."
    until php -r "try { new PDO(getenv('DATABASE_URL')); } catch (Exception \$e) { exit(1); }"; do
        sleep 3
        echo "⏳ Nouvelle tentative..."
    done
    echo "✅ Base de données prête !"
fi

# ----------------------------
# 3. Fix des permissions pour Symfony
# ----------------------------
echo "🔧 Réglage des permissions sur var/ et vendor/"
mkdir -p /var/www/html/var
chown -R www-data:www-data /var/www/html/var /var/www/html/vendor || true
chmod -R 755 /var/www/html/var || true

# ----------------------------
# 4. Nettoyage et warmup du cache Symfony
# ----------------------------
if [ -f "/var/www/html/bin/console" ]; then
    echo "🧹 Nettoyage du cache Symfony..."
    su -s /bin/sh www-data -c "php /var/www/html/bin/console cache:clear --no-warmup --env=prod" || true
    su -s /bin/sh www-data -c "php /var/www/html/bin/console cache:warmup --env=prod" || true
fi

# ----------------------------
# 5. Lancement d'Apache
# ----------------------------
echo "🚀 Démarrage d'Apache..."
exec apache2-foreground
