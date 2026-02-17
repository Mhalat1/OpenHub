Installation rapide local
bash
# 1. Cloner
git clone https://github.com/Mhalat1/OpenHub.git
cd OpenHub

# 2. Installer dépendances
composer install

# 3. Configurer (.env.local)
cp .env .env.local
# Éditez DATABASE_URL dans .env.local

# 4. Créer BDD
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 5. Lancer
symfony serve:start


🔧 Fichiers clés

Fichier	Rôle
.htaccess	CORS + routage Apache

ApiTokenAuthenticator.php	Auth JWT pour API
docker-compose.yml	PostgreSQL + Mercure

##########################################################################

🚀 Déploiement Render
Push sur GitHub

Sur Render : New Web Service → Connecter dépôt
back et front sur differents service web 

Variables env PRIVATEs a definir sur Render:
Racine de depot aussi /backend /frontend

#############################################################################

Template .env frontend

CORS_ALLOW_ORIGIN=..........
DATABASE_URL=..........
JWT_PASSPHRASE=..........
JWT_PUBLIC_KEY=..........
JWT_PRIVATE_KEY=..........
STRIPE_PUBLIC_KEY=..........
STRIPE_PRIVATE_KEY=..........


Template .enc backend

VITE_API_URL= url backend
VITE_API_JWT_STORAGE_KEY=auth_token

#############################################################################

🔧 Maintenance
Mise à jour des dépendances
bash
# Voir les mises à jour disponibles
composer outdated

# Mettre à jour tout
composer update

# Mettre à jour Symfony seulement
composer update symfony/*
Backup base de données
PostgreSQL (local)

bash
# Backup
pg_dump -U app -h localhost openhub > backup_$(date +%Y%m%d).sql

# Restauration
psql -U app -h localhost openhub < backup_20250217.sql
Via Docker

bash
# Backup
docker exec -t openhub-db-1 pg_dump -U app openhub > backup.sql

# Restauration
cat backup.sql | docker exec -i openhub-db-1 psql -U app openhub
Monitoring rapide
Logs Symfony

bash
tail -f var/log/prod.log      # Logs en temps réel
grep ERROR var/log/prod.log    # Voir les erreurs
Health check

bash
# Tester que l'API répond
curl https://votre-site.com/api/health
Render Dashboard

URL: https://dashboard.render.com

Voir: Logs, Métriques (CPU/RAM), Statut du service

Commandes utiles
bash
# Vider le cache
php bin/console cache:clear

# Migrations
php bin/console doctrine:migrations:migrate

# Statut des migrations
php bin/console doctrine:migrations:status