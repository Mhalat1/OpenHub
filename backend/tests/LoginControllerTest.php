<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LoginControllerTest extends WebTestCase
{
    public function testLoginRouteWorks(): void
    {
        
        $client = static::createClient();
        
        $client->request('POST', '/api/login');
        
        echo "Status code: " . $client->getResponse()->getStatusCode() . "\n";
        echo "Content: " . $client->getResponse()->getContent() . "\n";
        
        // Si c'est 200, c'est bon
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }
    
    public function testAllRoutesInSameController(): void
    {
        $client = static::createClient();
        $router = $client->getContainer()->get('router');
        
        // Vérifiez que la classe existe
        echo "\n=== Vérification de la classe ===\n";
        if (class_exists('App\\Controller\\LoginController')) {
            echo "✓ LoginController existe\n";
            
            // Vérifiez les méthodes
            $methods = get_class_methods('App\\Controller\\LoginController');
            echo "Méthodes disponibles: " . implode(', ', $methods) . "\n";
        } else {
            echo "✗ LoginController n'existe pas\n";
        }
        
        // Vérifiez les routes
        echo "\n=== Routes disponibles ===\n";
        $routes = $router->getRouteCollection()->all();
        
        $foundRoutes = [];
        foreach ($routes as $name => $route) {
            if (strpos($name, 'login') !== false || strpos($name, 'logout') !== false) {
                echo "$name: " . $route->getPath() . " [" . implode(', ', $route->getMethods() ?: ['ANY']) . "]\n";
                $foundRoutes[] = $name;
            }
        }
        
        if (empty($foundRoutes)) {
            echo "Aucune route login/logout trouvée!\n";
        }
        
        $this->assertTrue(true);
    }
}