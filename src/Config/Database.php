<?php

namespace LlmsApp\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;
    private static bool $setupAttempted = false;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            // Usa direttamente $_ENV per evitare loop con ConfigService
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $db   = $_ENV['DB_DATABASE'] ?? 'llms_app';
            $user = $_ENV['DB_USERNAME'] ?? 'root';
            $pass = $_ENV['DB_PASSWORD'] ?? '';

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

            try {
                self::$connection = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);

                // Verifica se le tabelle esistono
                self::verifyDatabase();

            } catch (PDOException $e) {
                // Se il database non esiste, prova a crearlo
                if ($e->getCode() == 1049 && !self::$setupAttempted) {
                    self::$setupAttempted = true;
                    if (self::createDatabase($host, $port, $db, $user, $pass)) {
                        // Reset connection
                        self::$connection = null;
                        // Riprova la connessione
                        return self::getConnection();
                    }
                }

                // Se siamo in una pagina di setup, non mostrare l'errore
                if (strpos($_SERVER['REQUEST_URI'] ?? '', '/setup') !== false) {
                    throw $e; // Lancia l'eccezione per gestirla nel controller
                }

                die('Database connection error: ' . $e->getMessage());
            }
        }

        return self::$connection;
    }

    private static function createDatabase(string $host, string $port, string $db, string $user, string $pass): bool
    {
        try {
            // Connessione senza database specifico
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // Crea il database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Usa il database
            $pdo->exec("USE `{$db}`");

            // Esegui lo schema
            $schemaPath = __DIR__ . '/../../sql/schema.sql';
            if (file_exists($schemaPath)) {
                $schema = file_get_contents($schemaPath);

                // Rimuovi il comando CREATE DATABASE e USE dal file schema
                $schema = preg_replace('/CREATE DATABASE[^;]+;/i', '', $schema);
                $schema = preg_replace('/USE[^;]+;/i', '', $schema);

                // Splitta i comandi SQL in modo più robusto
                $commands = [];
                $delimiter = ';';
                $delimiterLength = 1;
                $inString = false;
                $stringChar = '';
                $currentCommand = '';

                for ($i = 0; $i < strlen($schema); $i++) {
                    $char = $schema[$i];

                    // Gestione stringhe SQL
                    if (($char === '"' || $char === "'") && ($i === 0 || $schema[$i-1] !== '\\')) {
                        if (!$inString) {
                            $inString = true;
                            $stringChar = $char;
                        } elseif ($char === $stringChar) {
                            $inString = false;
                            $stringChar = '';
                        }
                    }

                    $currentCommand .= $char;

                    // Verifica delimiter solo se non siamo in una stringa
                    if (!$inString && substr($schema, $i, $delimiterLength) === $delimiter) {
                        $cmd = trim($currentCommand);
                        if (!empty($cmd) && !preg_match('/^--/', $cmd)) {
                            $commands[] = substr($cmd, 0, -$delimiterLength);
                        }
                        $currentCommand = '';
                    }
                }

                // Aggiungi l'ultimo comando se presente
                if (!empty(trim($currentCommand))) {
                    $commands[] = trim($currentCommand);
                }

                foreach ($commands as $command) {
                    if (!empty(trim($command))) {
                        $pdo->exec($command);
                    }
                }
            }

            return true;
        } catch (PDOException $e) {
            error_log('Failed to create database: ' . $e->getMessage());
            return false;
        }
    }

    private static function verifyDatabase(): void
    {
        try {
            // Verifica se la tabella settings esiste
            $stmt = self::$connection->query("SHOW TABLES LIKE 'settings'");
            if (!$stmt->fetch()) {
                // Se non esiste, esegui lo schema
                self::runSchema();
            }
        } catch (PDOException $e) {
            // Ignora errori di verifica
        }
    }

    private static function runSchema(): void
    {
        $schemaPath = __DIR__ . '/../../sql/schema.sql';
        if (!file_exists($schemaPath)) {
            return;
        }

        $schema = file_get_contents($schemaPath);

        // Rimuovi CREATE DATABASE e USE
        $schema = preg_replace('/CREATE DATABASE[^;]+;/i', '', $schema);
        $schema = preg_replace('/USE[^;]+;/i', '', $schema);

        // Esegui comando per comando
        $lines = explode("\n", $schema);
        $tempCommand = '';

        foreach ($lines as $line) {
            // Rimuovi commenti e spazi
            $line = trim($line);
            if (empty($line) || strpos($line, '--') === 0) {
                continue;
            }

            $tempCommand .= $line . "\n";

            // Se la linea finisce con ; esegui il comando
            if (substr($line, -1) === ';') {
                try {
                    self::$connection->exec($tempCommand);
                } catch (PDOException $e) {
                    // Ignora errori (es. tabella già esistente)
                }
                $tempCommand = '';
            }
        }
    }

    public static function testConnection(string $host, string $port, string $user, string $pass): array
    {
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            return ['success' => true, 'message' => 'Connessione riuscita'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}