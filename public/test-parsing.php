<?php
require __DIR__ . '/../vendor/autoload.php';

use LlmsApp\Services\SitemapParser;
use LlmsApp\Models\Url;

// Test parsing
$parser = new SitemapParser();
$sitemapUrl = 'https://www.amevista.com/sitemap.xml';

echo "<h1>Test Parsing Sitemap</h1>";
echo "<p>URL: $sitemapUrl</p>";

try {
    // Simula il parsing per project ID 1
    $parser->parseSitemap(1, 1, $sitemapUrl);

    echo "<p style='color: green;'>✅ Parsing completato!</p>";

    // Conta URL salvati
    $pdo = \LlmsApp\Config\Database::getConnection();
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM urls WHERE project_id = 1');
    $stmt->execute();
    $count = $stmt->fetch()['total'];

    echo "<p>URL salvati nel database: <strong>$count</strong></p>";

    // Mostra primi 10 URL
    $stmt = $pdo->prepare('SELECT * FROM urls WHERE project_id = 1 LIMIT 10');
    $stmt->execute();
    $urls = $stmt->fetchAll();

    echo "<h2>Primi 10 URL:</h2>";
    echo "<ul>";
    foreach ($urls as $url) {
        echo "<li>{$url['loc']} - Tipo: {$url['type']}</li>";
    }
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Errore: " . $e->getMessage() . "</p>";
}