!!! important lancer bdd test avec wamp avant les test 

commande pour lancer les tests :
```bash
php bin/phpunit
```
exemple test specifiauement 
php bin/phpunit tests/DonationControllerTest.php
php bin/phpunit tests/LoginControllerTest.php
php bin/phpunit tests/MessagesControllerTest.php
php bin/phpunit tests/ProjectsControllerTest.php
php bin/phpunit tests/UserControllerTest.php

exemple test pour lister toutes les routes de l'application :
 php bin/phpunit tests/DebugRoutesTest.php --filter testListAllRoutes

---------------------------------------

commande pour générer un rapport de couverture de code en HTML :
Mesure quelles parties de votre code sont exécutées par vos tests.

$ php bin/phpunit --coverage-html coverage/