<?php
use Bramus\Router\Router;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

// Carica .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

// Base path dinamico
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
$appBasePath = ($scriptPath === '/' || $scriptPath === '\\') ? '' : $scriptPath;

echo "<h1>Debug Router</h1>";
echo "<h2>Informazioni richiesta:</h2>";
echo "<pre>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'not set') . "\n";
echo "Script Path: " . $scriptPath . "\n";
echo "App Base Path: " . $appBasePath . "\n";
echo "</pre>";

// Test router
$router = new Router();
$router->setBasePath($appBasePath);

// Aggiungi route di test
$router->get('/projects/{id}/sitemaps', function($id) {
    echo "<h2>✅ Route funzionante!</h2>";
    echo "<p>Project ID: {$id}</p>";
    echo "<p>Questa è la route per gestire le sitemap del progetto {$id}</p>";
});

$router->get('/test', function() {
    echo "<h2>✅ Test route funziona!</h2>";
});

$router->set404(function() {
    echo "<h2>❌ 404 - Route non trovata</h2>";
});

echo "<h2>Test routing:</h2>";
echo "<p>Prova a visitare questi URL:</p>";
echo "<ul>";
echo "<li><a href='{$appBasePath}/test'>Test Route</a></li>";
echo "<li><a href='{$appBasePath}/projects/1/sitemaps'>Project 1 Sitemaps</a></li>";
echo "</ul>";

echo "<hr>";
echo "<h2>Esecuzione router:</h2>";

// Esegui il router
$router->run();