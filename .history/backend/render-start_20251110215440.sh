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

# 3. Cache Symfony (avant démarrage Apache)
if [ -f "/var/www/html/bin/console" ]; then
    echo "🧹 Cache Symfony..."
    su -s /bin/sh www-data -c "php /var/www/html/bin/console cache:clear --env=prod" 2>/dev/null || true
fi

# 4. Test DB en arrière-plan (non bloquant)
if [ -n "$DATABASE_URL" ]; then
    (
        echo "🔍 Test de connexion DB en arrière-plan..."
        sleep 5  # Attendre qu'Apache soit lancé
        
        for i in 1 2 3 4 5; do
            if php -r "
                try {
                    \$url = getenv('DATABASE_URL');
                    if (preg_match('/mysql:\/\/([^:]+):([^@]+)@([^:\/]+):(\d+)\/([^\?]+)/', \$url, \$m)) {
                        new PDO(
                            'mysql:host='.\$m[3].';port='.\$m[4].';dbname='.\$m[5],
                            \$m[1], \$m[2],
                            [PDO::ATTR_TIMEOUT => 5, PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false]
                        );
                        echo 'OK';
                        exit(0);
                    }
                } catch (Exception \$e) { exit(1); }
            " 2>/dev/null; then
                echo "✅ DB connectée (tentative $i)"
                break
            fi
            echo "⏳ DB tentative $i/5..."
            sleep 3
        done
    ) &
fi

# 5. Démarrage Apache IMMÉDIAT
echo "🚀 Démarrage d'Apache sur le port $PORT..."
exec apache2-foreground