<?php
/**
 * Router alternativo che funziona anche senza mod_rewrite
 * Usa questo file visitando: http://localhost/llms-generate/public/router.php
 */

use Bramus\Router\Router;
use Dotenv\Dotenv;
use LlmsApp\Config\Database;
use LlmsApp\Services\ConfigService;

require __DIR__ . '/../vendor/autoload.php';

// Avvia la sessione
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carica .env se esiste
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

// Inizializza ConfigService
$config = ConfigService::getInstance();

// Per questo router alternativo, usiamo parametri GET
$route = $_GET['route'] ?? '/';

// Simula il routing
$needsSetup = false;
try {
    Database::getConnection();
    if (!$config->isDatabaseInitialized()) {
        $needsSetup = true;
    }
} catch (Exception $e) {
    $needsSetup = true;
}

// Base URL per i link
$baseUrl = '/llms-generate/public/router.php';

// Aggiungi questo all'inizio per debug
if (isset($_GET['debug'])) {
    echo "<h3>Debug Info:</h3>";
    echo "<pre>";
    echo "Route richiesta: " . htmlspecialchars($_GET['route'] ?? '/') . "\n";
    echo "Regex match sitemaps: " . (preg_match('#^/projects/(\d+)/sitemaps$#', $_GET['route'] ?? '') ? 'SI' : 'NO') . "\n";
    echo "</pre>";
    echo "<hr>";
}

// Processa le route manualmente
switch(true) {
    case $route === '/' || $route === '':
        $controller = new \LlmsApp\Controllers\ProjectController();
        $controller->index();
        break;

    case $route === '/settings':
        $controller = new \LlmsApp\Controllers\SettingsController();
        $controller->index();
        break;

    case preg_match('#^/projects/(\d+)$#', $route, $matches):
        $controller = new \LlmsApp\Controllers\ProjectController();
        $controller->show($matches[1]);
        break;

    case preg_match('#^/projects/(\d+)/sitemaps$#', $route, $matches):
        $controller = new \LlmsApp\Controllers\SitemapController();
        $controller->index($matches[1]);
        break;

    case preg_match('#^/projects/(\d+)/urls$#', $route, $matches):
        $controller = new \LlmsApp\Controllers\UrlController();
        $controller->index($matches[1]);
        break;

    case preg_match('#^/projects/(\d+)/llms/preview$#', $route, $matches):
        $controller = new \LlmsApp\Controllers\LlmsController();
        $controller->preview($matches[1]);
        break;

    default:
        echo "<h1>404 - Pagina non trovata</h1>";
        echo "<p>Route richiesta: " . htmlspecialchars($route) . "</p>";
        echo "<p><a href='$baseUrl?route=/'>Torna alla home</a></p>";
        break;
}