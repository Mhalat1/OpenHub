!! generation readme mise a jour protocole 
bash
bash backend/bin/generate-readme.sh
cela modifie le README.md avec les valeur a jour !!

# 🚀 Installation de backend

> **Dernière mise à jour :** 2026-02-22
> **Projet :** backend
> **Symfony :** 7.3
> **PHP nécessaire :** 8.2+
> **Base de données :** mysql

## 📋 Prérequis

| Outil | Version installée |
|-------|-------------------|
| Composer | 2.6.5 |
| Node.js | 18.18.0 |
| Yarn | 1.22.19 |
| Git | 2.52.0 |
| Docker | 24.0.6 |

## ⚡ Installation rapide

```bash
# Backend Symfony
cd backend
cp .env .env.local
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
symfony server:start