composer require --dev symfony/browser-kit
composer require --dev symfony/http-client
C'est des dépendance requise pour les tests fonctionnels.

LANCER TEST SPECIFIQUE
php vendor/bin/phpunit tests/LoginControllerTest.php

Jai cree une bdd de test en local avec phpmyadmin pour realiser les test unitaires de repositorys
DATABASE_URL="mysql://root:@localhost:3306/openhup-bddphpunit?serverVersion=8.0.32&charset=utf8mb4"
