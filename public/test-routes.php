<?php
require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use LlmsApp\Services\ConfigService;

// Carica .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

$config = ConfigService::getInstance();
$basePath = $config->getBasePath();

echo "<h1>Test Routes</h1>";
echo "<p>Base Path: <code>" . htmlspecialchars($basePath) . "</code></p>";
echo "<p>Request URI: <code>" . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') . "</code></p>";

echo "<h2>Link di test:</h2>";
echo "<ul>";
echo "<li><a href='{$basePath}/'>Home</a></li>";
echo "<li><a href='{$basePath}/settings'>Settings</a></li>";
echo "<li><a href='{$basePath}/projects/1'>Project 1</a></li>";
echo "<li><a href='{$basePath}/projects/1/sitemaps'>Sitemaps</a></li>";
echo "<li><a href='{$basePath}/projects/1/urls'>URLs</a></li>";
echo "</ul>";

echo "<h2>Environment:</h2>";
echo "<pre>";
echo "APP_BASE_PATH: " . ($_ENV['APP_BASE_PATH'] ?? 'not set') . "\n";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'not set') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'not set') . "\n";
echo "</pre>";