<?php
require __DIR__ . '/../vendor/autoload.php';

use Bramus\Router\Router;
use Dotenv\Dotenv;

// Carica .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
$appBasePath = ($scriptPath === '/' || $scriptPath === '\\') ? '' : $scriptPath;

$router = new Router();
$router->setBasePath($appBasePath);

// Test specifico per le route problematiche
$router->get('/projects/{id}/sitemaps', function($id) {
    echo "<h1 style='color:green'>✅ SITEMAP ROUTE FUNZIONA!</h1>";
    echo "<p>Project ID: $id</p>";
    echo "<p>Questa route dovrebbe chiamare SitemapController@index</p>";

    // Prova a chiamare il controller realmente
    try {
        $controller = new \LlmsApp\Controllers\SitemapController();
        echo "<hr><h2>Chiamata al controller reale:</h2>";
        $controller->index($id);
    } catch (Exception $e) {
        echo "<p style='color:red'>Errore nel controller: " . $e->getMessage() . "</p>";
    }
});

$router->get('/projects/{id}/urls', function($id) {
    echo "<h1 style='color:green'>✅ URL ROUTE FUNZIONA!</h1>";
    echo "<p>Project ID: $id</p>";

    try {
        $controller = new \LlmsApp\Controllers\UrlController();
        echo "<hr><h2>Chiamata al controller reale:</h2>";
        $controller->index($id);
    } catch (Exception $e) {
        echo "<p style='color:red'>Errore nel controller: " . $e->getMessage() . "</p>";
    }
});

$router->get('/projects/{id}', function($id) {
    echo "<h1 style='color:orange'>⚠️ PROJECT SHOW ROUTE</h1>";
    echo "<p>Project ID: $id</p>";
    echo "<p>Se vedi questo invece di SITEMAP o URL, c'è un problema nell'ordine delle route!</p>";
});

// Default
$router->set404(function() {
    echo "<h1>404 - Route non trovata nel debug</h1>";
});

echo "<h1>Debug Routes - Test diretto</h1>";
echo "<p>Stai testando: " . htmlspecialchars($_SERVER['REQUEST_URI']) . "</p>";
echo "<hr>";

$router->run();
?>