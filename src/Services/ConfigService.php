<?php

namespace LlmsApp\Services;

use LlmsApp\Models\Setting;

class ConfigService
{
    private static ?ConfigService $instance = null;
    private array $config = [];
    private bool $isLoading = false;

    private function __construct()
    {
        $this->loadConfiguration();
    }

    public static function getInstance(): ConfigService
    {
        if (self::$instance === null) {
            self::$instance = new ConfigService();
        }
        return self::$instance;
    }

    private function loadConfiguration(): void
    {
        // Previeni loop infiniti
        if ($this->isLoading) {
            return;
        }

        $this->isLoading = true;

        // Prima carica da .env se esiste
        $this->loadFromEnv();

        // Poi sovrascrive con i valori dal database (se disponibile)
        $this->loadFromDatabase();

        $this->isLoading = false;
    }

    private function loadFromEnv(): void
    {
        // Carica le variabili di ambiente
        foreach ($_ENV as $key => $value) {
            $this->config[strtolower($key)] = $value;
        }
    }

    private function loadFromDatabase(): void
    {
        // Solo se non siamo già in fase di loading (previene loop)
        if ($this->isLoading) {
            return;
        }

        try {
            // Non usare Database::getConnection() qui perché potrebbe creare loop
            // Usa una connessione diretta temporanea
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $db = $_ENV['DB_DATABASE'] ?? 'llms_app';
            $user = $_ENV['DB_USERNAME'] ?? 'root';
            $pass = $_ENV['DB_PASSWORD'] ?? '';

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
            $pdo = new \PDO($dsn, $user, $pass);

            // Verifica se la tabella settings esiste
            $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
            if (!$stmt->fetch()) {
                return;
            }

            // Carica le impostazioni
            $stmt = $pdo->query('SELECT * FROM settings');
            $settings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($settings as $setting) {
                $value = Setting::castValue($setting['setting_value'], $setting['setting_type']);
                $this->config[$setting['setting_key']] = $value;
            }
        } catch (\Exception $e) {
            // Database might not be initialized yet - that's ok
        }
    }

    public function get(string $key, $default = null)
    {
        // NON convertire in lowercase - usa la chiave così com'è
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        // Prova con underscore se ha punti
        if (strpos($key, '.') !== false) {
            $key_underscore = str_replace('.', '_', $key);
            if (isset($this->config[$key_underscore])) {
                return $this->config[$key_underscore];
            }
        }

        // Se non trovato, ricarica dal database e riprova
        $this->reload();
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        return $default;
    }

    public function set(string $key, $value): void
    {
        $this->config[$key] = $value;
        // Non salva automaticamente nel database, usa Setting::set() per quello
    }

    public function getBasePath(): string
    {
        // Usa lo stesso metodo dell'index.php per determinare il base path
        $scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        return ($scriptPath === '/' || $scriptPath === '\\') ? '' : $scriptPath;
    }

    public function getStoragePath(): string
    {
        $storage = $this->get('storage_path', 'storage');
        return realpath(__DIR__ . '/../../' . $storage) ?: (__DIR__ . '/../../storage');
    }

    public function isAiEnabled(): bool
    {
        return (bool)$this->get('ai_enabled', false);
    }

    public function getAiConfig(): array
    {
        return [
            'enabled' => $this->isAiEnabled(),
            'base_url' => $this->get('ai_api_base_url', ''),
            'api_key' => $this->get('ai_api_key', ''),
            'model_name' => $this->get('ai_model_name', 'short-desc-it'),
        ];
    }

    public function isDatabaseInitialized(): bool
    {
        try {
            // Prova a connettersi al database
            $host = $this->get('db_host', '127.0.0.1');
            $port = $this->get('db_port', '3306');
            $db = $this->get('db_database', 'llms_app');
            $user = $this->get('db_username', 'root');
            $pass = $this->get('db_password', '');

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
            $pdo = new \PDO($dsn, $user, $pass);

            // Verifica se la tabella settings esiste
            $stmt = $pdo->query("SHOW TABLES LIKE 'settings'");
            if ($stmt->fetch()) {
                // Verifica se è già inizializzata
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'db_initialized'");
                $stmt->execute();
                $row = $stmt->fetch();
                return $row && $row['setting_value'] === 'true';
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function initializeDatabase(): bool
    {
        try {
            $host = $this->get('db_host', '127.0.0.1');
            $port = $this->get('db_port', '3306');
            $user = $this->get('db_username', 'root');
            $pass = $this->get('db_password', '');

            // Prima connessione senza database
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new \PDO($dsn, $user, $pass);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Leggi e esegui lo schema
            $schemaPath = __DIR__ . '/../../sql/schema.sql';
            if (!file_exists($schemaPath)) {
                throw new \Exception('Schema file not found: ' . $schemaPath);
            }

            $schema = file_get_contents($schemaPath);

            // Splitta i comandi SQL
            $commands = array_filter(
                array_map('trim', explode(';', $schema)),
                function($cmd) {
                    return !empty($cmd) && !preg_match('/^--/', $cmd);
                }
            );

            foreach ($commands as $command) {
                if (!empty($command)) {
                    $pdo->exec($command);
                }
            }

            // Inizializza le impostazioni di default
            Setting::initializeDefaults();

            return true;
        } catch (\Exception $e) {
            error_log('Database initialization error: ' . $e->getMessage());
            return false;
        }
    }

    public function reload(): void
    {
        $this->config = [];
        $this->loadConfiguration();
        Setting::clearCache();
    }
}