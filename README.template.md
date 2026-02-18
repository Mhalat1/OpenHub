# 🚀 Installation de __PROJECT_NAME__

> **Dernière mise à jour :** __DATE__
> **Projet :** __PROJECT_NAME__
> **Symfony :** __SYMFONY_VERSION__
> **PHP nécessaire :** __PHP_VERSION__+
> **Base de données :** __DB_TYPE__

## 📋 Prérequis

| Outil | Version installée |
|-------|-------------------|
| Composer | __COMPOSER_VERSION__ |
| Node.js | __NODE_VERSION__ |
| Yarn | __YARN_VERSION__ |
| Git | __GIT_VERSION__ |
| Docker | __DOCKER_VERSION__ |

## ⚡ Installation rapide

```bash
# Backend Symfony
cd backend
cp .env .env.local
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
symfony server:start