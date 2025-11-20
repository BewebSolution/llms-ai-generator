<?php
require __DIR__ . '/../vendor/autoload.php';

use LlmsApp\Config\Database;
use LlmsApp\Models\Setting;
use LlmsApp\Services\ConfigService;

echo "<!DOCTYPE html>";
echo "<html><head><title>Force Enable AI</title></head><body style='font-family: monospace; padding: 20px;'>";
echo "<h1>FORCE ENABLE OPENAI</h1>";

try {
    $pdo = Database::getConnection();

    // 1. FORZA l'abilitazione
    echo "<p>1. Forzando openai_enabled = 'true'...</p>";
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = 'true', setting_type = 'boolean' WHERE setting_key = 'openai_enabled'");
    $stmt->execute();
    echo "<p style='color: green;'>✓ Fatto!</p>";

    // 2. Verifica API key
    echo "<p>2. Verificando API key...</p>";
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'openai_api_key'");
    $stmt->execute();
    $apiKey = $stmt->fetch()['setting_value'] ?? '';

    if (empty($apiKey)) {
        echo "<p style='color: orange;'>⚠️ API Key mancante! Aggiungila nelle impostazioni.</p>";
    } else {
        echo "<p style='color: green;'>✓ API Key presente: " . substr($apiKey, 0, 10) . "...</p>";
    }

    // 3. Pulisci cache
    echo "<p>3. Pulizia cache...</p>";
    Setting::clearCache();
    ConfigService::getInstance()->reload();
    echo "<p style='color: green;'>✓ Cache pulita!</p>";

    // 4. Verifica finale
    echo "<hr><h2>VERIFICA FINALE:</h2>";

    $stmt = $pdo->prepare("SELECT setting_key, setting_value, setting_type FROM settings WHERE setting_key LIKE 'openai_%' ORDER BY setting_key");
    $stmt->execute();
    $settings = $stmt->fetchAll();

    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Chiave</th><th>Valore</th><th>Tipo</th></tr>";
    foreach ($settings as $setting) {
        $value = $setting['setting_value'];
        if ($setting['setting_key'] === 'openai_api_key' && !empty($value)) {
            $value = substr($value, 0, 10) . '...' . substr($value, -4);
        }
        echo "<tr>";
        echo "<td>" . htmlspecialchars($setting['setting_key']) . "</td>";
        echo "<td>" . htmlspecialchars($value) . "</td>";
        echo "<td>" . htmlspecialchars($setting['setting_type']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<hr>";
    echo "<h2 style='color: green;'>✅ OPENAI ORA DOVREBBE ESSERE ABILITATO!</h2>";
    echo "<p>Link utili:</p>";
    echo "<ul>";
    echo "<li><a href='test-ai.php'>Test AI</a></li>";
    echo "<li><a href='/llms-generate/public/settings'>Impostazioni</a></li>";
    echo "<li><a href='/llms-generate/public/'>Home</a></li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color: red;'>ERRORE: " . $e->getMessage() . "</p>";
}

echo "</body></html>";