<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// 🔑 AJOUT POUR PARATEST - Database per worker
if (isset($_SERVER['TEST_TOKEN'])) {
    $testToken = $_SERVER['TEST_TOKEN'];
    
    // Récupérer l'URL de la base de données
    $databaseUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? null;
    
    if ($databaseUrl) {
        // Ajouter le suffix du worker à la base de données
        // Exemple: postgresql://app:pass@db:5432/app_test → app_test_1, app_test_2, etc.
        $databaseUrl = preg_replace(
            '/\/([^\/\?]+)(\?|$)/',
            '/${1}_' . $testToken . '${2}',
            $databaseUrl
        );
        
        // Mettre à jour les variables d'environnement
        $_ENV['DATABASE_URL'] = $databaseUrl;
        $_SERVER['DATABASE_URL'] = $databaseUrl;
        putenv('DATABASE_URL=' . $databaseUrl);
    }
}
