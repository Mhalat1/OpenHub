lancer un test particulier JEST

    npm test -- Logout.test.jsx

lancer test couverture pour JEST 
    npm test -- --coverage





Pour avoir un vrai utilisateur connecté, il faut exécuter le test de login d'abord et que ça crée un vrai token dans le vrai localStorage. = Login.test.jsx

Mais le problème c'est que chaque test est isolé. Les tests ne partagent pas le localStorage entre eux.
il faut donc se connecter vraiment sur l'app avant de lancer ces test = les autres test.jsx

Non, tu ne peux PAS lancer Symfony depuis un test Jest/React pour avoir un vrai JWT. Voici pourquoi :

Limitations techniques :
Jest = Node.js : Tourne dans Node, pas dans un serveur PHP

Symfony = PHP : Nécessite PHP + Serveur web (Apache/Nginx)

Environnements séparés : Frontend ≠ Backend



pour installer JEST 
1/ npm install --save-dev jest @vue/test-utils @vue/vue3-jest babel-jest

2/ creation fichier // jest.config.js
3/ creation fichier // src/setupTests.js

4/ ajoutee dans package.json
{
  "scripts": {
    "test": "jest",
    "test:watch": "jest --watch",
    "test:coverage": "jest --coverage",
    "test:verbose": "jest --verbose"
  }
}

5/ pour pouvoir utilsier les commandes
    "test": "jest --silent",
    "test:verbose": "jest",
    "test:watch": "jest --watch",
    "test:coverage": "jest --coverage"

    
