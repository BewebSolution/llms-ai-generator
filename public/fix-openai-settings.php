<?php
require __DIR__ . '/../vendor/autoload.php';

use LlmsApp\Services\ConfigService;
use LlmsApp\Models\Setting;
use LlmsApp\Config\Database;

session_start();

// Azioni di correzione
if (isset($_POST['action'])) {
    $pdo = Database::getConnection();

    switch ($_POST['action']) {
        case 'enable':
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = 'true' WHERE setting_key = 'openai_enabled'");
            $stmt->execute();
            $_SESSION['success'] = 'OpenAI abilitato correttamente';
            break;

        case 'disable':
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = 'false' WHERE setting_key = 'openai_enabled'");
            $stmt->execute();
            $_SESSION['success'] = 'OpenAI disabilitato correttamente';
            break;

        case 'fix_all':
            // Correggi tutti i valori booleani
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = 'true' WHERE setting_key = 'openai_enabled' AND setting_value IN ('Abilitato', '1', 'true')");
            $stmt->execute();

            $stmt = $pdo->prepare("UPDATE settings SET setting_value = 'false' WHERE setting_key = 'openai_enabled' AND setting_value IN ('Disabilitato', '0', 'false', '')");
            $stmt->execute();

            // Assicurati che il tipo sia corretto
            $stmt = $pdo->prepare("UPDATE settings SET setting_type = 'boolean' WHERE setting_key = 'openai_enabled'");
            $stmt->execute();

            $_SESSION['success'] = 'Impostazioni corrette!';
            break;

        case 'save_key':
            $apiKey = trim($_POST['api_key'] ?? '');
            if (!empty($apiKey)) {
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = :key WHERE setting_key = 'openai_api_key'");
                $stmt->execute(['key' => $apiKey]);
                $_SESSION['success'] = 'API Key salvata!';
            } else {
                $_SESSION['error'] = 'API Key vuota!';
            }
            break;
    }

    // Pulisci cache
    Setting::clearCache();
    ConfigService::getInstance()->reload();

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Leggi stato attuale
$pdo = Database::getConnection();
$stmt = $pdo->query("SELECT * FROM settings WHERE setting_key IN ('openai_enabled', 'openai_api_key', 'openai_model', 'openai_temperature') ORDER BY setting_key");
$settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$config = ConfigService::getInstance();
$basePath = $config->getBasePath();

// Crea array associativo per accesso facile
$settingsMap = [];
foreach ($settings as $setting) {
    $settingsMap[$setting['setting_key']] = $setting;
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix OpenAI Settings - LLMS Generator</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        h1 {
            color: #667eea;
            text-align: center;
            margin-bottom: 30px;
        }
        .status-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .status-ok {
            background: #d4edda;
            color: #155724;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        button {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
            font-size: 16px;
        }
        button:hover {
            background: #5a67d8;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix OpenAI Settings</h1>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                ❌ <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="status-card">
            <h2>📊 Stato Attuale Database</h2>
            <table>
                <thead>
                    <tr>
                        <th>Chiave</th>
                        <th>Valore nel DB</th>
                        <th>Tipo</th>
                        <th>Interpretazione</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($settingsMap as $key => $setting): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($key) ?></strong></td>
                            <td>
                                <code><?= htmlspecialchars($setting['setting_value']) ?></code>
                                <?php if ($key === 'openai_api_key' && !empty($setting['setting_value'])): ?>
                                    (<?= substr($setting['setting_value'], 0, 7) ?>...<?= substr($setting['setting_value'], -4) ?>)
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($setting['setting_type']) ?></td>
                            <td>
                                <?php
                                if ($key === 'openai_enabled') {
                                    $value = $setting['setting_value'];
                                    if ($value === 'true' || $value === '1') {
                                        echo '<span style="color: green;">✅ Abilitato</span>';
                                    } elseif ($value === 'false' || $value === '0' || empty($value)) {
                                        echo '<span style="color: red;">❌ Disabilitato</span>';
                                    } else {
                                        echo '<span style="color: orange;">⚠️ Valore non standard: ' . htmlspecialchars($value) . '</span>';
                                    }
                                } elseif ($key === 'openai_api_key') {
                                    echo empty($setting['setting_value']) ? '⚠️ Non configurata' : '✅ Configurata';
                                } else {
                                    echo htmlspecialchars($setting['setting_value']);
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="status-card">
            <h2>🎯 Azioni Rapide</h2>

            <h3>1. Abilita/Disabilita OpenAI</h3>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="enable">
                <button type="submit" class="btn-success">✅ Abilita OpenAI</button>
            </form>

            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="disable">
                <button type="submit" class="btn-danger">❌ Disabilita OpenAI</button>
            </form>

            <h3>2. Correggi Formato Automaticamente</h3>
            <form method="post">
                <input type="hidden" name="action" value="fix_all">
                <button type="submit" class="btn-warning">🔧 Correggi Tutti i Valori</button>
                <p style="color: #666; font-size: 14px;">
                    Questo convertirà tutti i valori non standard nel formato corretto (true/false)
                </p>
            </form>

            <h3>3. Aggiorna API Key</h3>
            <form method="post">
                <input type="hidden" name="action" value="save_key">
                <input type="text" name="api_key" placeholder="sk-..." value="<?= htmlspecialchars($settingsMap['openai_api_key']['setting_value'] ?? '') ?>">
                <button type="submit" style="margin-top: 10px;">💾 Salva API Key</button>
            </form>
        </div>

        <div class="status-card">
            <h2>✅ Test Configurazione</h2>
            <?php
            // Test vari metodi di lettura
            $dbValue = $settingsMap['openai_enabled']['setting_value'] ?? 'false';
            $configValue = $config->get('openai_enabled');
            $settingValue = Setting::get('openai_enabled');
            $apiKey = $config->get('openai_api_key', '');

            $isEnabled = ($dbValue === 'true' || $dbValue === '1');
            ?>

            <table>
                <tr>
                    <td><strong>Valore DB:</strong></td>
                    <td><code><?= htmlspecialchars($dbValue) ?></code></td>
                </tr>
                <tr>
                    <td><strong>ConfigService::get():</strong></td>
                    <td><code><?= var_export($configValue, true) ?></code></td>
                </tr>
                <tr>
                    <td><strong>Setting::get():</strong></td>
                    <td><code><?= var_export($settingValue, true) ?></code></td>
                </tr>
                <tr>
                    <td><strong>API Key presente:</strong></td>
                    <td><?= !empty($apiKey) ? '✅ Sì' : '❌ No' ?></td>
                </tr>
                <tr>
                    <td><strong>Risultato finale:</strong></td>
                    <td>
                        <?php if ($isEnabled && !empty($apiKey)): ?>
                            <span style="color: green; font-weight: bold;">✅ OpenAI ATTIVO E CONFIGURATO</span>
                        <?php elseif ($isEnabled && empty($apiKey)): ?>
                            <span style="color: orange; font-weight: bold;">⚠️ OpenAI abilitato ma MANCA API KEY</span>
                        <?php else: ?>
                            <span style="color: red; font-weight: bold;">❌ OpenAI NON ATTIVO</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <a href="<?= htmlspecialchars($basePath) ?>/" style="margin: 0 10px;">🏠 Home</a>
            <a href="<?= htmlspecialchars($basePath) ?>/settings" style="margin: 0 10px;">⚙️ Impostazioni</a>
            <a href="<?= htmlspecialchars($basePath) ?>/test-ai.php" style="margin: 0 10px;">🤖 Test AI</a>
            <a href="<?= htmlspecialchars($basePath) ?>/debug-settings.php" style="margin: 0 10px;">🔍 Debug</a>
        </div>
    </div>
</body>
</html>