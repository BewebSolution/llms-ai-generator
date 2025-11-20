<?php
require __DIR__ . '/../vendor/autoload.php';

use LlmsApp\Services\ConfigService;
use LlmsApp\Models\Setting;
use LlmsApp\Config\Database;

// Inizializza la sessione
session_start();

// Carica configurazione
$config = ConfigService::getInstance();
$basePath = $config->getBasePath();

// Funzione per correggere le impostazioni
if (isset($_POST['fix_settings'])) {
    try {
        $pdo = Database::getConnection();

        // Correggi openai_enabled se necessario
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = 'true' WHERE setting_key = 'openai_enabled' AND setting_value IN ('Abilitato', '1', 'true')");
        $stmt->execute();

        $stmt = $pdo->prepare("UPDATE settings SET setting_value = 'false' WHERE setting_key = 'openai_enabled' AND setting_value IN ('Disabilitato', '0', 'false', '')");
        $stmt->execute();

        // Ricarica la cache
        Setting::clearCache();
        ConfigService::getInstance()->reload();

        $_SESSION['success'] = 'Impostazioni corrette!';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = 'Errore: ' . $e->getMessage();
    }
}

// Leggi tutte le impostazioni OpenAI
$openaiSettings = Setting::byCategory('openai');

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Impostazioni - LLMS Generator</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .status-ok {
            background: #d4edda;
            color: #155724;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-warning {
            background: #fff3cd;
            color: #856404;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
        }
        button {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }
        button:hover {
            background: #5a67d8;
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
        .debug-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Debug Impostazioni OpenAI</h1>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="debug-section">
            <h2>📊 Stato Attuale Database</h2>
            <table>
                <thead>
                    <tr>
                        <th>Chiave</th>
                        <th>Valore Raw (DB)</th>
                        <th>Tipo</th>
                        <th>Valore Interpretato</th>
                        <th>Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($openaiSettings as $setting): ?>
                        <?php
                        $rawValue = $setting['setting_value'];
                        $interpretedValue = Setting::castValue($rawValue, $setting['setting_type']);

                        // Determina lo stato
                        $status = 'ok';
                        $statusClass = 'status-ok';
                        $statusText = 'OK';

                        if ($setting['setting_key'] === 'openai_enabled') {
                            if ($rawValue !== 'true' && $rawValue !== 'false') {
                                $status = 'warning';
                                $statusClass = 'status-warning';
                                $statusText = 'Formato non standard';
                            }
                            if ($interpretedValue === true) {
                                $statusText = '✅ Abilitato';
                            } elseif ($interpretedValue === false) {
                                $statusText = '❌ Disabilitato';
                            }
                        }

                        if ($setting['setting_key'] === 'openai_api_key') {
                            if (empty($rawValue)) {
                                $status = 'warning';
                                $statusClass = 'status-warning';
                                $statusText = '⚠️ Non configurata';
                            } else {
                                $statusText = '🔑 Configurata';
                                // Nascondi la chiave per sicurezza
                                $rawValue = substr($rawValue, 0, 7) . '...' . substr($rawValue, -4);
                            }
                        }
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($setting['setting_key']) ?></strong></td>
                            <td><code><?= htmlspecialchars($rawValue) ?></code></td>
                            <td><?= htmlspecialchars($setting['setting_type']) ?></td>
                            <td>
                                <?php if ($setting['setting_type'] === 'boolean'): ?>
                                    <?= $interpretedValue ? '<span class="status-ok">TRUE</span>' : '<span class="status-error">FALSE</span>' ?>
                                <?php else: ?>
                                    <code><?= htmlspecialchars(var_export($interpretedValue, true)) ?></code>
                                <?php endif; ?>
                            </td>
                            <td><span class="<?= $statusClass ?>"><?= $statusText ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="debug-section">
            <h2>🔍 Test di Lettura</h2>
            <?php
            // Test vari metodi di lettura
            $tests = [
                'ConfigService::get()' => $config->get('openai_enabled'),
                'Setting::get()' => Setting::get('openai_enabled'),
                'Setting::get() con cast' => Setting::castValue(Setting::get('openai_enabled'), 'boolean'),
            ];
            ?>
            <table>
                <thead>
                    <tr>
                        <th>Metodo</th>
                        <th>Valore Restituito</th>
                        <th>Tipo</th>
                        <th>Interpretazione Booleana</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tests as $method => $value): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($method) ?></code></td>
                            <td><code><?= htmlspecialchars(var_export($value, true)) ?></code></td>
                            <td><?= gettype($value) ?></td>
                            <td>
                                <?php
                                $boolInterpretation = ($value === 'true' || $value === true || $value === '1' || $value === 1);
                                ?>
                                <?= $boolInterpretation ? '<span class="status-ok">TRUE</span>' : '<span class="status-error">FALSE</span>' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="debug-section">
            <h2>🛠️ Azioni di Correzione</h2>
            <form method="post">
                <button type="submit" name="fix_settings">
                    🔧 Correggi Formato Impostazioni
                </button>
                <p style="margin-top: 10px; color: #666;">
                    Questo pulsante correggerà automaticamente i valori nel database per garantire il formato standard.
                </p>
            </form>
        </div>

        <div class="debug-section">
            <h2>📝 Raccomandazioni</h2>
            <ul>
                <li>Il valore di <code>openai_enabled</code> dovrebbe essere sempre <code>'true'</code> o <code>'false'</code> (stringhe)</li>
                <li>Il tipo nel database dovrebbe essere <code>boolean</code></li>
                <li>La chiave API deve essere una stringa non vuota per funzionare</li>
                <li>Dopo ogni modifica, la cache deve essere svuotata con <code>Setting::clearCache()</code></li>
            </ul>
        </div>

        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <a href="<?= htmlspecialchars($basePath) ?>/" style="margin: 0 10px;">🏠 Home</a>
            <a href="<?= htmlspecialchars($basePath) ?>/settings" style="margin: 0 10px;">⚙️ Impostazioni</a>
            <a href="<?= htmlspecialchars($basePath) ?>/test-ai.php" style="margin: 0 10px;">🤖 Test AI</a>
        </div>
    </div>
</body>
</html>