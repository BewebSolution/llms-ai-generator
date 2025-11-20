<?php
require __DIR__ . '/../vendor/autoload.php';

use LlmsApp\Config\Database;

echo "<h1>Correzione date nel database</h1>";

try {
    $pdo = Database::getConnection();

    // Prima conta quanti record hanno date problematiche
    $stmt = $pdo->query("
        SELECT COUNT(*) as total
        FROM urls
        WHERE lastmod IS NOT NULL
        AND (lastmod LIKE '%T%' OR lastmod = '0000-00-00 00:00:00' OR lastmod < '1000-01-01')
    ");
    $problematic = $stmt->fetch()['total'];

    echo "<p>Record con date problematiche trovati: <strong>$problematic</strong></p>";

    if ($problematic > 0) {
        // Correggi impostando a NULL le date non valide
        $stmt = $pdo->exec("
            UPDATE urls
            SET lastmod = NULL
            WHERE lastmod IS NOT NULL
            AND (lastmod LIKE '%T%' OR lastmod = '0000-00-00 00:00:00' OR lastmod < '1000-01-01')
        ");

        echo "<p style='color: green;'>✅ Corretti $stmt record impostando lastmod a NULL</p>";
    } else {
        echo "<p style='color: green;'>✅ Nessuna correzione necessaria!</p>";
    }

    // Mostra statistiche finali
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total,
            COUNT(lastmod) as with_lastmod,
            MIN(lastmod) as min_date,
            MAX(lastmod) as max_date
        FROM urls
    ");
    $stats = $stmt->fetch();

    echo "<hr>";
    echo "<h2>Statistiche finali:</h2>";
    echo "<ul>";
    echo "<li>Totale URL: {$stats['total']}</li>";
    echo "<li>URL con lastmod: {$stats['with_lastmod']}</li>";
    echo "<li>Data minima: " . ($stats['min_date'] ?? 'N/A') . "</li>";
    echo "<li>Data massima: " . ($stats['max_date'] ?? 'N/A') . "</li>";
    echo "</ul>";

    echo "<hr>";
    echo "<p><a href='" . dirname($_SERVER['SCRIPT_NAME']) . "/'>← Torna alla home</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Errore: " . $e->getMessage() . "</p>";
}