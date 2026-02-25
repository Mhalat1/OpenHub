<?php

use App\Kernel;

if (isset($_SERVER['RENDER']) || isset($_ENV['RENDER'])) {
    // Désactive la recherche du fichier .env sur Render
    $_SERVER['APP_RUNTIME_OPTIONS'] = [
        'disable_dotenv' => true,
    ];
}
// 👆 FIN DES MODIFICATIONS

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};