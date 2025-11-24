<?php

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

// Base path dinamico - corretto per gestire /llms-generate/public
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
$appBasePath = ($scriptPath === '/' || $scriptPath === '\\') ? '' : $scriptPath;

$router = new Router();
$router->setBasePath($appBasePath);

// Route di setup (priorità alta)
$router->get('/setup', '\LlmsApp\Controllers\SettingsController@setup');
$router->post('/setup/process', '\LlmsApp\Controllers\SettingsController@processSetup');
$router->post('/setup/test-connection', function() {
    header('Content-Type: application/json');
    $host = $_POST['db_host'] ?? '127.0.0.1';
    $port = $_POST['db_port'] ?? '3306';
    $user = $_POST['db_username'] ?? 'root';
    $pass = $_POST['db_password'] ?? '';

    $result = \LlmsApp\Config\Database::testConnection($host, $port, $user, $pass);
    echo json_encode($result);
});

// Verifica se il database è inizializzato
$needsSetup = false;
try {
    Database::getConnection();
    if (!$config->isDatabaseInitialized()) {
        $needsSetup = true;
    }
} catch (Exception $e) {
    $needsSetup = true;
}

// Se il database non è inizializzato, redirect al setup
if ($needsSetup && !str_contains($_SERVER['REQUEST_URI'], '/setup')) {
    header('Location: ' . $appBasePath . '/setup');
    exit;
}

// Route normali (solo se il database è inizializzato)
if (!$needsSetup) {
    // Homepage
    $router->get('/', '\LlmsApp\Controllers\ProjectController@index');

    // Settings
    $router->get('/settings', '\LlmsApp\Controllers\SettingsController@index');
    $router->post('/settings/update', '\LlmsApp\Controllers\SettingsController@update');
    $router->post('/settings/test-ai', '\LlmsApp\Controllers\SettingsController@testAi');
    $router->post('/settings/test-openai', '\LlmsApp\Controllers\SettingsController@testOpenAi');

    // Projects - IMPORTANTE: route più specifiche PRIMA di quelle generiche!
    $router->get('/projects/create', '\LlmsApp\Controllers\ProjectController@create');
    $router->post('/projects/store', '\LlmsApp\Controllers\ProjectController@store');
    $router->post('/projects/bulk-delete', '\LlmsApp\Controllers\ProjectController@bulkDelete');

    // Sitemaps - DEVE essere PRIMA di /projects/{id}
    $router->get('/projects/{id}/sitemaps', '\LlmsApp\Controllers\SitemapController@index');
    $router->post('/projects/{id}/sitemaps/add', '\LlmsApp\Controllers\SitemapController@store');
    $router->post('/sitemaps/{id}/delete', '\LlmsApp\Controllers\SitemapController@delete');

    // URLs - DEVE essere PRIMA di /projects/{id}
    $router->get('/projects/{id}/urls', '\LlmsApp\Controllers\UrlController@index');
    $router->post('/projects/{id}/urls/bulk-update', '\LlmsApp\Controllers\UrlController@bulkUpdate');

    // LLMS - DEVE essere PRIMA di /projects/{id}
    $router->get('/projects/{id}/llms/preview', '\LlmsApp\Controllers\LlmsController@preview');
    $router->post('/projects/{id}/llms/generate', '\LlmsApp\Controllers\LlmsController@generate');

    // Crawl - DEVE essere PRIMA di /projects/{id}
    $router->get('/projects/{id}/crawl', '\LlmsApp\Controllers\CrawlController@progress');
    $router->post('/projects/{id}/crawl/start', '\LlmsApp\Controllers\CrawlController@startAsync');
    $router->post('/projects/{id}/crawl/stop', '\LlmsApp\Controllers\CrawlController@stop');
    $router->get('/api/projects/{id}/crawl/status', '\LlmsApp\Controllers\CrawlController@status');

    // Project edit/update/delete - DEVE essere PRIMA di /projects/{id}
    $router->get('/projects/{id}/edit', '\LlmsApp\Controllers\ProjectController@edit');
    $router->post('/projects/{id}/update', '\LlmsApp\Controllers\ProjectController@update');
    $router->post('/projects/{id}/delete', '\LlmsApp\Controllers\ProjectController@destroy');

    // Project show - DEVE essere ULTIMA perché matcha tutto!
    $router->get('/projects/{id}', '\LlmsApp\Controllers\ProjectController@show');

    // Altri route sitemaps
    $router->post('/sitemaps/{id}/parse', '\LlmsApp\Controllers\SitemapController@parse');
    $router->get('/llms/{slug}.txt', '\LlmsApp\Controllers\LlmsController@publicTxt');

    // API AI
    $router->post('/api/ai/description', '\LlmsApp\Controllers\AiController@generateDescription');

    // API URLs
    $router->post('/api/urls/{id}/delete', '\LlmsApp\Controllers\UrlController@deleteApi');
    $router->post('/api/urls/extract-meta', '\LlmsApp\Controllers\AiController@extractMetaTags');

    // Costi AI
    $router->get('/costs', '\LlmsApp\Controllers\CostController@index');
    $router->get('/projects/{id}/costs', '\LlmsApp\Controllers\CostController@projectCosts');
    $router->post('/api/costs/estimate', '\LlmsApp\Controllers\CostController@estimate');
}

// Gestione 404
$router->set404(function() use ($appBasePath) {
    header('HTTP/1.1 404 Not Found');
    echo "<h1>404 - Route non trovata</h1>";
    echo "<p>URI richiesto: " . htmlspecialchars($_SERVER['REQUEST_URI']) . "</p>";
    echo "<p>Base Path: " . htmlspecialchars($appBasePath) . "</p>";
    echo "<p><a href='{$appBasePath}/'>Torna alla home</a></p>";
    echo "<hr>";
    echo "<h2>Link funzionanti:</h2>";
    echo "<ul>";
    echo "<li><a href='{$appBasePath}/'>Home</a></li>";
    echo "<li><a href='{$appBasePath}/settings'>Impostazioni</a></li>";
    echo "<li><a href='/llms-generate/public/router.php?route=/'>Usa Router Alternativo</a></li>";
    echo "</ul>";
});

$router->run();