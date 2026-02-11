composer require --dev symfony/browser-kit
composer require --dev symfony/http-client
C'est des dépendance requise pour les tests fonctionnels.

LANCER TEST SPECIFIQUE
php vendor/bin/phpunit tests/LoginControllerTest.php