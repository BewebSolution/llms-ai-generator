<?php

namespace LlmsApp\Controllers;

use LlmsApp\Models\Setting;
use LlmsApp\Services\ConfigService;
use LlmsApp\Config\Database;

class SettingsController
{
    public function index()
    {
        $config = ConfigService::getInstance();

        // Raggruppa le impostazioni per categoria
        $categories = Setting::getCategories();
        $settingsByCategory = [];

        foreach ($categories as $category) {
            $settingsByCategory[$category] = Setting::byCategory($category);
        }

        $this->render('settings/index', [
            'settingsByCategory' => $settingsByCategory,
            'view' => 'settings/index',
        ]);
    }

    public function update()
    {
        $settings = $_POST['settings'] ?? [];

        try {
            // Debug - log cosa stiamo ricevendo
            error_log("Settings received: " . json_encode($settings));

            // Il dropdown invia sempre 'true' o 'false' come stringa
            // Non c'è bisogno di conversioni complesse, solo assicurarsi che sia una stringa
            if (isset($settings['openai_enabled'])) {
                // Forza a stringa e assicurati che sia 'true' o 'false'
                $value = (string)$settings['openai_enabled'];
                if ($value !== 'true' && $value !== 'false') {
                    // Se per qualche motivo non è 'true' o 'false', converti
                    $settings['openai_enabled'] = 'false';
                }
            }

            Setting::bulkUpdate($settings);

            // IMPORTANTE: Pulisci la cache dopo l'update
            \LlmsApp\Models\Setting::clearCache();

            // Ricarica la configurazione
            ConfigService::getInstance()->reload();

            $_SESSION['success'] = 'Impostazioni aggiornate con successo!';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Errore durante l\'aggiornamento: ' . $e->getMessage();
        }

        header('Location: ' . $this->baseUrl() . '/settings');
        exit;
    }

    public function setup()
    {
        // Mostra la pagina di setup iniziale
        $this->render('settings/setup', [
            'view' => 'settings/setup',
            'hideNav' => true,
        ]);
    }

    public function processSetup()
    {
        $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
        $dbPort = trim($_POST['db_port'] ?? '3306');
        $dbName = trim($_POST['db_database'] ?? 'llms_app');
        $dbUser = trim($_POST['db_username'] ?? 'root');
        $dbPass = $_POST['db_password'] ?? '';

        // Testa la connessione
        $test = Database::testConnection($dbHost, $dbPort, $dbUser, $dbPass);

        if (!$test['success']) {
            $_SESSION['error'] = 'Errore di connessione al database: ' . $test['message'];
            header('Location: ' . $this->baseUrl() . '/setup');
            exit;
        }

        // Salva le credenziali nel file .env
        $this->updateEnvFile([
            'DB_HOST' => $dbHost,
            'DB_PORT' => $dbPort,
            'DB_DATABASE' => $dbName,
            'DB_USERNAME' => $dbUser,
            'DB_PASSWORD' => $dbPass,
        ]);

        // Ricarica la configurazione
        ConfigService::getInstance()->reload();

        // Inizializza il database
        $config = ConfigService::getInstance();
        if ($config->initializeDatabase()) {
            $_SESSION['success'] = 'Database creato e inizializzato con successo!';
            header('Location: ' . $this->baseUrl() . '/');
        } else {
            $_SESSION['error'] = 'Errore durante la creazione del database';
            header('Location: ' . $this->baseUrl() . '/setup');
        }
        exit;
    }

    public function testOpenAi()
    {
        header('Content-Type: application/json; charset=utf-8');

        $apiKey = $_POST['api_key'] ?? '';
        $model = $_POST['model'] ?? 'gpt-3.5-turbo';
        $temperature = (float)($_POST['temperature'] ?? 0.7);

        if (empty($apiKey)) {
            echo json_encode(['success' => false, 'message' => 'API Key è obbligatoria']);
            return;
        }

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10]);

            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Say "Hello" in Italian']
                    ],
                    'max_tokens' => 10,
                    'temperature' => $temperature,
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $body = json_decode($response->getBody(), true);
                if (isset($body['choices'][0]['message']['content'])) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Connessione OpenAI funzionante! Modello: ' . $model
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Risposta inattesa da OpenAI']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Errore HTTP: ' . $response->getStatusCode()]);
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $body = json_decode($response->getBody(), true);
            $errorMessage = $body['error']['message'] ?? 'Errore sconosciuto';

            if (strpos($errorMessage, 'Invalid authentication') !== false) {
                echo json_encode(['success' => false, 'message' => 'API Key non valida']);
            } elseif (strpos($errorMessage, 'model') !== false) {
                echo json_encode(['success' => false, 'message' => 'Modello non disponibile: ' . $model]);
            } else {
                echo json_encode(['success' => false, 'message' => $errorMessage]);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Errore: ' . $e->getMessage()]);
        }
    }

    private function updateEnvFile(array $values): void
    {
        $envPath = __DIR__ . '/../../.env';

        if (file_exists($envPath)) {
            $content = file_get_contents($envPath);
        } else {
            // Crea un nuovo file .env con valori di default
            $content = "APP_ENV=local\nAPP_DEBUG=true\nAPP_BASE_PATH=/llms-generate/public\n";
        }

        foreach ($values as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$value}";

            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content);
            } else {
                $content .= "\n{$replacement}";
            }
        }

        file_put_contents($envPath, $content);
    }

    private function render(string $view, array $data = [])
    {
        extract($data);
        $baseUrl = $this->baseUrl();

        // Avvia la sessione se non è già avviata
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        include __DIR__ . '/../Views/layout.php';
    }

    private function baseUrl(): string
    {
        $config = ConfigService::getInstance();
        return $config->getBasePath();
    }
}