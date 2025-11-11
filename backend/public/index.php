<?php

// Gestion CORS AGGRESSIVE - TOUJOURS répondre avec les bons headers
$allowedOrigins = [
    'https://openhub-front.onrender.com',
    'http://127.0.0.1:5173', 
    'http://localhost:5173'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Fallback pour les requêtes sans Origin header
    header('Access-Control-Allow-Origin: https://openhub-front.onrender.com');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 3600');

// Si c'est une requête OPTIONS, on répond DIRECTEMENT
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};