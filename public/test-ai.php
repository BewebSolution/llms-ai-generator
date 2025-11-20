<?php
require __DIR__ . '/../vendor/autoload.php';

use LlmsApp\Services\ConfigService;
use LlmsApp\Services\AiDescriptionService;

// Inizializza la sessione
session_start();

// Carica configurazione
$config = ConfigService::getInstance();
$basePath = $config->getBasePath();

// Controlla configurazione OpenAI - LEGGI DIRETTAMENTE DAL DATABASE
$pdo = \LlmsApp\Config\Database::getConnection();
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'openai_enabled'");
$stmt->execute();
$dbValue = $stmt->fetch()['setting_value'] ?? 'false';

// Interpreta il valore
$openaiEnabled = ($dbValue === 'true' || $dbValue === '1');

// Leggi anche l'API key direttamente dal database
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'openai_api_key'");
$stmt->execute();
$openaiKey = $stmt->fetch()['setting_value'] ?? '';

// Altri parametri
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'openai_model'");
$stmt->execute();
$openaiModel = $stmt->fetch()['setting_value'] ?? 'gpt-3.5-turbo';

$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'openai_temperature'");
$stmt->execute();
$openaiTemperature = $stmt->fetch()['setting_value'] ?? '0.7';

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Integrazione AI - LLMS Generator</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        h1 {
            color: #667eea;
            margin-bottom: 30px;
        }
        .status-box {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .status-ok {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .config-item {
            padding: 10px;
            margin: 10px 0;
            background: #f8f9fa;
            border-left: 4px solid #667eea;
        }
        .test-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        input, button {
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 16px;
        }
        input {
            width: 100%;
            box-sizing: border-box;
        }
        button {
            background: #667eea;
            color: white;
            border: none;
            cursor: pointer;
            padding: 12px 24px;
            font-weight: bold;
        }
        button:hover {
            background: #5a67d8;
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        #result {
            margin-top: 20px;
            padding: 20px;
            background: #e9ecef;
            border-radius: 8px;
            display: none;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0,0,0,.1);
            border-radius: 50%;
            border-top-color: #667eea;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 Test Integrazione AI</h1>

        <!-- Debug info - RIMUOVI IN PRODUZIONE -->
        <div style="background: #f0f0f0; padding: 10px; border-radius: 5px; margin-bottom: 20px; font-family: monospace; font-size: 12px;">
            <strong>DEBUG:</strong><br>
            DB Value: <?= htmlspecialchars($dbValue) ?><br>
            OpenAI Enabled: <?= $openaiEnabled ? 'true' : 'false' ?><br>
            API Key presente: <?= !empty($openaiKey) ? 'Sì' : 'No' ?><br>
            API Key (prime lettere): <?= !empty($openaiKey) ? substr($openaiKey, 0, 10) . '...' : 'vuota' ?>
        </div>

        <h2>Stato Configurazione</h2>

        <?php if ($openaiEnabled && !empty($openaiKey)): ?>
            <div class="status-box status-ok">
                <strong>✅ OpenAI Configurato e Attivo</strong>
                <div class="config-item">
                    <strong>Modello:</strong> <?= htmlspecialchars($openaiModel) ?><br>
                    <strong>Temperature:</strong> <?= htmlspecialchars($openaiTemperature) ?><br>
                    <strong>API Key:</strong> <?= substr($openaiKey, 0, 7) ?>...<?= substr($openaiKey, -4) ?>
                </div>
            </div>
        <?php elseif (!$openaiEnabled): ?>
            <div class="status-box status-error">
                <strong>❌ OpenAI Non Abilitato</strong>
                <p>Vai nelle <a href="<?= htmlspecialchars($basePath) ?>/settings">impostazioni</a> per abilitare OpenAI.</p>
            </div>
        <?php else: ?>
            <div class="status-box status-warning">
                <strong>⚠️ API Key Mancante</strong>
                <p>OpenAI è abilitato ma manca l'API key. Vai nelle <a href="<?= htmlspecialchars($basePath) ?>/settings">impostazioni</a>.</p>
            </div>
        <?php endif; ?>

        <?php if ($openaiEnabled && !empty($openaiKey)): ?>
        <div class="test-form">
            <h2>Test Generazione Descrizione</h2>
            <form id="testForm">
                <label>
                    <strong>Titolo della Pagina:</strong>
                    <input type="text" id="title" placeholder="Es: Guida all'installazione del software" value="Guida all'installazione del software">
                </label>

                <label>
                    <strong>URL della Pagina:</strong>
                    <input type="url" id="url" placeholder="Es: https://example.com/docs/installation" value="https://example.com/docs/installation">
                </label>

                <button type="submit" id="generateBtn">
                    ✨ Genera Descrizione con AI
                </button>
            </form>

            <div id="result"></div>
        </div>

        <script>
        document.getElementById('testForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const title = document.getElementById('title').value;
            const url = document.getElementById('url').value;
            const resultDiv = document.getElementById('result');
            const button = document.getElementById('generateBtn');

            if (!title || !url) {
                alert('Inserisci sia il titolo che l\'URL');
                return;
            }

            button.disabled = true;
            button.innerHTML = '<span class="loading"></span> Generazione in corso...';
            resultDiv.style.display = 'none';

            try {
                const response = await fetch('<?= htmlspecialchars($basePath) ?>/api/ai/description', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ title, url })
                });

                const data = await response.json();

                if (data.description) {
                    resultDiv.innerHTML = `
                        <h3 class="success">✅ Descrizione Generata con Successo!</h3>
                        <p><strong>Descrizione:</strong></p>
                        <div style="padding: 15px; background: white; border-left: 4px solid #28a745; margin: 10px 0;">
                            ${data.description}
                        </div>
                        <p><small>Lunghezza: ${data.description.length} caratteri</small></p>
                    `;
                    resultDiv.className = 'success';
                } else {
                    resultDiv.innerHTML = `
                        <h3 class="error">❌ Errore nella Generazione</h3>
                        <p>${data.error || 'Errore sconosciuto'}</p>
                    `;
                    resultDiv.className = 'error';
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <h3 class="error">❌ Errore di Connessione</h3>
                    <p>${error.message}</p>
                `;
                resultDiv.className = 'error';
            } finally {
                resultDiv.style.display = 'block';
                button.disabled = false;
                button.innerHTML = '✨ Genera Descrizione con AI';
            }
        });
        </script>
        <?php endif; ?>

        <hr style="margin: 30px 0;">

        <div style="text-align: center;">
            <a href="<?= htmlspecialchars($basePath) ?>/" style="margin: 0 10px;">🏠 Home</a>
            <a href="<?= htmlspecialchars($basePath) ?>/settings" style="margin: 0 10px;">⚙️ Impostazioni</a>
            <?php
            // Trova un progetto esistente per test
            $pdo = \LlmsApp\Config\Database::getConnection();
            $stmt = $pdo->query('SELECT id, name FROM projects LIMIT 1');
            $project = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($project):
            ?>
            <a href="<?= htmlspecialchars($basePath) ?>/projects/<?= $project['id'] ?>/urls" style="margin: 0 10px;">📝 Gestione URL</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>