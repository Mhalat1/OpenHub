##  génération readme mise à jour protocole ##
bash 
backend/bin/generate-readme.sh
## les valeurs dans README.md sont mises à jour automatiquement à chaque déploiment grace à ce script ## 

# Installation de backend

> **Dernière mise à jour :** 2026-04-04
> **Projet :** backend
> **Symfony :** 7.3
> **PHP nécessaire :** 8.2+
> **Base de données :** 

## Prérequis

| Outil | Version installée |
|-------|-------------------|
| Composer | 2.6.5 |
| Node.js | 18.18.0 |
| Yarn | 1.22.19 |
| Git | 2.53.0 |
| Docker | 24.0.6 |

## Installation rapide

```bash
# Backend Symfony
cd backend
cp .env .env.local
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
symfony server:start
