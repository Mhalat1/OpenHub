# 🚀 Installation de backend

> **Dernière mise à jour :** 2026-02-18
> **Projet :** backend
> **Symfony :** v7.3.10
> **PHP nécessaire :** 8.2+
> **Base de données :** 

## 📋 Prérequis

| Outil | Version installée |
|-------|-------------------|
| Composer | 32 |
| Node.js | v20.15.0 |
| Yarn | 1.22.19 |
| Git | 2.49.0. |
| Docker | 27.0.3 |

## ⚡ Installation rapide

```bash
# Backend Symfony
cd backend
cp .env .env.local
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
symfony server:start