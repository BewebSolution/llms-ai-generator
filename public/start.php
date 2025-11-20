<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LLMS.txt Generator - Start</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .status {
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .instructions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 LLMS.txt Generator</h1>

        <?php
        require __DIR__ . '/../vendor/autoload.php';

        // Controlla mod_rewrite
        $hasModRewrite = false;
        if (function_exists('apache_get_modules')) {
            $hasModRewrite = in_array('mod_rewrite', apache_get_modules());
        }

        // Controlla connessione database
        $dbConnected = false;
        try {
            \LlmsApp\Config\Database::getConnection();
            $dbConnected = true;
        } catch (Exception $e) {
            $dbError = $e->getMessage();
        }
        ?>

        <h2>📊 Stato del Sistema</h2>

        <div class="status <?php echo $hasModRewrite ? 'success' : 'warning'; ?>">
            <strong>mod_rewrite:</strong>
            <?php if ($hasModRewrite): ?>
                ✅ Abilitato - Puoi usare URL puliti
            <?php else: ?>
                ⚠️ Non disponibile - Useremo il router alternativo
            <?php endif; ?>
        </div>

        <div class="status <?php echo $dbConnected ? 'success' : 'error'; ?>">
            <strong>Database:</strong>
            <?php if ($dbConnected): ?>
                ✅ Connesso correttamente
            <?php else: ?>
                ❌ Non connesso - <?php echo htmlspecialchars($dbError ?? 'Errore sconosciuto'); ?>
            <?php endif; ?>
        </div>

        <h2>🎯 Avvia l'Applicazione</h2>

        <?php if ($hasModRewrite): ?>
            <p>✨ <strong>mod_rewrite è attivo!</strong> Puoi usare l'applicazione con URL puliti:</p>
            <a href="/llms-generate/public/" class="btn">Avvia Applicazione (URL Puliti)</a>
        <?php else: ?>
            <div class="instructions">
                <h3>⚠️ mod_rewrite non è attivo</h3>
                <p>Non preoccuparti! Puoi comunque usare l'applicazione con il router alternativo.</p>

                <h4>Per abilitare mod_rewrite in Laragon:</h4>
                <ol>
                    <li>Apri Laragon</li>
                    <li>Menu → Apache → httpd.conf</li>
                    <li>Cerca: <code>#LoadModule rewrite_module modules/mod_rewrite.so</code></li>
                    <li>Rimuovi il # all'inizio della riga</li>
                    <li>Salva e riavvia Apache</li>
                    <li>Ricarica questa pagina</li>
                </ol>
            </div>

            <a href="/llms-generate/public/router.php?route=/" class="btn btn-secondary">
                Usa Router Alternativo (Senza mod_rewrite)
            </a>
        <?php endif; ?>

        <?php if (!$dbConnected): ?>
            <div class="instructions">
                <h3>⚙️ Configurazione Database Richiesta</h3>
                <p>Il database non è ancora configurato. L'applicazione ti guiderà nella configurazione al primo avvio.</p>
            </div>
        <?php endif; ?>

        <h2>📚 Link Utili</h2>
        <ul>
            <li><a href="info.php">PHP Info</a> - Informazioni dettagliate su PHP e moduli</li>
            <li><a href="test-routes.php">Test Routes</a> - Verifica routing</li>
            <li><strong>Documentazione:</strong> <code>C:\laragon\www\llms-generate\enable-mod-rewrite.md</code></li>
        </ul>
    </div>
</body>
</html>