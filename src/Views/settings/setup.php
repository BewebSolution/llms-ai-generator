<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Setup - llms.txt Generator</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .setup-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }

        h1 {
            color: #333;
            margin: 0 0 10px 0;
            font-size: 28px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #555;
            margin-bottom: 5px;
            font-weight: 500;
        }

        input[type="text"],
        input[type="password"],
        input[type="number"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }

        input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .help-text {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }

        button {
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
        }

        button:hover {
            background: #5a67d8;
        }

        .error {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #fcc;
        }

        .success {
            background: #efe;
            color: #363;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #cfc;
        }

        .test-connection {
            background: #f0f0f0;
            color: #333;
            font-size: 14px;
            padding: 8px 15px;
            margin-top: 10px;
        }

        .test-connection:hover {
            background: #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <h1>🚀 Setup Iniziale</h1>
        <p class="subtitle">Configurazione database per llms.txt Generator</p>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error">
                <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="success">
                <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/setup/process">
            <div class="form-group">
                <label for="db_host">Host Database</label>
                <input type="text" id="db_host" name="db_host" value="127.0.0.1" required>
                <div class="help-text">Solitamente localhost o 127.0.0.1</div>
            </div>

            <div class="form-group">
                <label for="db_port">Porta Database</label>
                <input type="number" id="db_port" name="db_port" value="3306" required>
                <div class="help-text">Porta standard MySQL: 3306</div>
            </div>

            <div class="form-group">
                <label for="db_database">Nome Database</label>
                <input type="text" id="db_database" name="db_database" value="llms_app" required>
                <div class="help-text">Verrà creato automaticamente se non esiste</div>
            </div>

            <div class="form-group">
                <label for="db_username">Username Database</label>
                <input type="text" id="db_username" name="db_username" value="root" required>
                <div class="help-text">Username per accedere a MySQL</div>
            </div>

            <div class="form-group">
                <label for="db_password">Password Database</label>
                <input type="password" id="db_password" name="db_password">
                <div class="help-text">Lascia vuoto se non hai una password</div>
            </div>

            <button type="button" onclick="testConnection()" class="test-connection">
                Test Connessione
            </button>
            <div id="test-result"></div>

            <button type="submit">
                Installa Database e Continua
            </button>
        </form>
    </div>

    <script>
    function testConnection() {
        const resultDiv = document.getElementById('test-result');
        resultDiv.innerHTML = '<p style="color: #666;">Testing connessione...</p>';

        const form = document.querySelector('form');
        const formData = new FormData(form);

        fetch('<?= htmlspecialchars($baseUrl) ?>/setup/test-connection', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = '<p style="color: #4CAF50;">✓ ' + data.message + '</p>';
            } else {
                resultDiv.innerHTML = '<p style="color: #f44336;">✗ ' + data.message + '</p>';
            }
        })
        .catch(error => {
            resultDiv.innerHTML = '<p style="color: #f44336;">✗ Errore: ' + error.message + '</p>';
        });
    }
    </script>
</body>
</html>