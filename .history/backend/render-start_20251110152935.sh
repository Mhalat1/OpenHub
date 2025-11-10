#!/bin/sh
set -e

# 1. Création utilisateur www-data si nécessaire
if ! id -u www-data >/dev/null 2>&1; then
    echo "👤 Création de l'utilisateur www-data..."
    useradd -ms /bin/bash www-data
fi

# 2. Réglage des permissions Symfony
mkdir -p /var/www/html/var
chown -R www-data:www-data /var/www/html/var /var/www/html/vendor || true
chmod -R 755 /var/www/html/var || true

# 3. Vérification de la DB MySQL Aiven
if [ -n "$DATABASE_URL" ]; then
    echo "⏳ Vérification de la base de données..."
    export PDO_DSN=$(echo $DATABASE_URL | sed -E 's|mysql://([^:]+):([^@]+)@([^:/]+):([0-9]+)/([^?]+).*|mysql:host=\3;port=\4;dbname=\5;charset=utf8mb4|')
    export PDO_USER=$(echo $DATABASE_URL | sed -E 's|mysql://([^:]+):([^@]+)@.*|\1|')
    export PDO_PASS=$(echo $DATABASE_URL | sed -E 's|mysql://([^:]+):([^@]+)@.*|\2|')


until php -r "try { new PDO(getenv('PDO_DSN').';sslmode=REQUIRED', getenv('PDO_USER'), getenv('PDO_PASS')); exit(0); } catch (Exception \$e) { exit(1); }"; do
    sleep 3
    echo "⏳ Nouvelle tentative..."
done



    echo "✅ Base de données prête !"
fi

# 4. Clear & warmup Symfony cache
if [ -f "/var/www/html/bin/console" ]; then
    echo "🧹 Nettoyage du cache Symfony..."
    su -s /bin/sh www-data -c "php /var/www/html/bin/console cache:clear --no-warmup --env=prod" || true
    su -s /bin/sh www-data -c "php /var/www/html/bin/console cache:warmup --env=prod" || true
fi

# 5. Démarrage Apache
echo "🚀 Démarrage d'Apache..."
exec apache2-foreground
