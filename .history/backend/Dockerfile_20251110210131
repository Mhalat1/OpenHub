#!/bin/sh
set -e

# IMPORTANT: Configurer le port dynamique de Render
export PORT=${PORT:-10000}
echo "🔧 Configuration du port: $PORT"

# Configurer Apache pour écouter sur le port de Render
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf

# 1. Création utilisateur www-data si nécessaire
if ! id -u www-data >/dev/null 2>&1; then
    echo "👤 Création de l'utilisateur www-data..."
    useradd -ms /bin/bash www-data
fi

# 2. Réglage des permissions Symfony
echo "📁 Configuration des permissions..."
mkdir -p /var/www/html/var
chown -R www-data:www-data /var/www/html/var /var/www/html/vendor || true
chmod -R 755 /var/www/html/var || true

# 3. Vérification de la DB MySQL Aiven
if [ -n "$DATABASE_URL" ]; then
    echo "⏳ Vérification de la base de données..."
    
    # Extraction des paramètres de connexion
    export DB_HOST=$(echo $DATABASE_URL | sed -E 's|mysql://[^:]+:[^@]+@([^:/]+):[0-9]+/.*|\1|')
    export DB_PORT=$(echo $DATABASE_URL | sed -E 's|mysql://[^:]+:[^@]+@[^:/]+:([0-9]+)/.*|\1|')
    export DB_NAME=$(echo $DATABASE_URL | sed -E 's|mysql://[^:]+:[^@]+@[^:/]+:[0-9]+/([^?]+).*|\1|')
    export DB_USER=$(echo $DATABASE_URL | sed -E 's|mysql://([^:]+):[^@]+@.*|\1|')
    export DB_PASS=$(echo $DATABASE_URL | sed -E 's|mysql://[^:]+:([^@]+)@.*|\1|')

    # Tentatives de connexion avec timeout
    MAX_RETRIES=30
    RETRY_COUNT=0
    
    until php -r "
        \$options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
        ];
        try {
            new PDO(
                'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4',
                getenv('DB_USER'),
                getenv('DB_PASS'),
                \$options
            );
            exit(0);
        } catch (Exception \$e) {
            echo 'Erreur: ' . \$e->getMessage() . PHP_EOL;
            exit(1);
        }
    "; do
        RETRY_COUNT=$((RETRY_COUNT + 1))
        if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
            echo "❌ Impossible de se connecter à la base de données après $MAX_RETRIES tentatives"
            exit 1
        fi
        sleep 3
        echo "⏳ Nouvelle tentative ($RETRY_COUNT/$MAX_RETRIES)..."
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
echo "🚀 Démarrage d'Apache sur le port $PORT..."
exec apache2-foreground