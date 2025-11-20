<?php

namespace LlmsApp\Models;

use LlmsApp\Config\Database;
use PDO;

class Setting
{
    private static array $cache = [];

    public static function get(string $key, $default = null)
    {
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('SELECT setting_value, setting_type FROM settings WHERE setting_key = :key');
            $stmt->execute(['key' => $key]);
            $row = $stmt->fetch();

            if (!$row) {
                return $default;
            }

            $value = self::castValue($row['setting_value'], $row['setting_type']);
            self::$cache[$key] = $value;
            return $value;
        } catch (\Exception $e) {
            return $default;
        }
    }

    public static function set(string $key, $value, string $type = 'string', ?string $description = null, string $category = 'general'): void
    {
        try {
            $pdo = Database::getConnection();

            $stringValue = self::stringifyValue($value, $type);

            $stmt = $pdo->prepare('
                INSERT INTO settings (setting_key, setting_value, setting_type, description, category)
                VALUES (:key, :value, :type, :description, :category)
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    setting_type = VALUES(setting_type),
                    description = COALESCE(VALUES(description), description),
                    category = VALUES(category)
            ');

            $stmt->execute([
                'key' => $key,
                'value' => $stringValue,
                'type' => $type,
                'description' => $description,
                'category' => $category,
            ]);

            // Update cache
            self::$cache[$key] = $value;
        } catch (\Exception $e) {
            // Ignora errori durante l'inizializzazione
        }
    }

    public static function all(): array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query('SELECT * FROM settings ORDER BY category, setting_key');
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function byCategory(string $category): array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('SELECT * FROM settings WHERE category = :category ORDER BY setting_key');
            $stmt->execute(['category' => $category]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function getCategories(): array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query('SELECT DISTINCT category FROM settings ORDER BY category');
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function bulkUpdate(array $settings): void
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            foreach ($settings as $key => $value) {
                // Get current type
                $stmt = $pdo->prepare('SELECT setting_type FROM settings WHERE setting_key = :key');
                $stmt->execute(['key' => $key]);
                $row = $stmt->fetch();

                if ($row) {
                    $stringValue = self::stringifyValue($value, $row['setting_type']);

                    $stmt = $pdo->prepare('
                        UPDATE settings
                        SET setting_value = :value
                        WHERE setting_key = :key
                    ');
                    $stmt->execute([
                        'key' => $key,
                        'value' => $stringValue,
                    ]);

                    // Update cache
                    self::$cache[$key] = self::castValue($stringValue, $row['setting_type']);
                }
            }

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    public static function castValue($value, string $type)
    {
        if ($value === null || $value === '') {
            return null;
        }

        switch ($type) {
            case 'boolean':
                // Gestisci vari formati booleani
                if ($value === 'true' || $value === true || $value === '1' || $value === 1) {
                    return true;
                }
                if ($value === 'false' || $value === false || $value === '0' || $value === 0) {
                    return false;
                }
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    private static function stringifyValue($value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'boolean':
                // IMPORTANTE: gestire correttamente le stringhe 'true' e 'false'
                if ($value === 'false' || $value === false || $value === '0' || $value === 0) {
                    return 'false';
                }
                if ($value === 'true' || $value === true || $value === '1' || $value === 1) {
                    return 'true';
                }
                // Se è una stringa non vuota diversa da 'false', considera true
                return $value ? 'true' : 'false';
            case 'integer':
                return (string)(int)$value;
            case 'float':
                return (string)(float)$value;
            case 'json':
                return json_encode($value);
            default:
                return (string)$value;
        }
    }

    public static function initializeDefaults(): void
    {
        $defaults = [
            // General settings
            ['key' => 'app_name', 'value' => 'llms.txt Generator', 'type' => 'string', 'desc' => 'Nome dell\'applicazione', 'cat' => 'general'],
            ['key' => 'app_debug', 'value' => true, 'type' => 'boolean', 'desc' => 'Modalità debug', 'cat' => 'general'],
            ['key' => 'db_initialized', 'value' => true, 'type' => 'boolean', 'desc' => 'Database inizializzato', 'cat' => 'system'],

            // OpenAI settings (URL non serve, è sempre api.openai.com)
            ['key' => 'openai_enabled', 'value' => false, 'type' => 'boolean', 'desc' => 'Abilita servizio OpenAI', 'cat' => 'openai'],
            ['key' => 'openai_api_key', 'value' => '', 'type' => 'string', 'desc' => 'Chiave API OpenAI', 'cat' => 'openai'],
            ['key' => 'openai_model', 'value' => 'gpt-3.5-turbo', 'type' => 'string', 'desc' => 'Modello OpenAI', 'cat' => 'openai'],
            ['key' => 'openai_temperature', 'value' => 0.7, 'type' => 'float', 'desc' => 'Temperature (0-2)', 'cat' => 'openai'],

            // Storage
            ['key' => 'storage_path', 'value' => 'storage', 'type' => 'string', 'desc' => 'Percorso di storage', 'cat' => 'storage'],
        ];

        foreach ($defaults as $setting) {
            self::set($setting['key'], $setting['value'], $setting['type'], $setting['desc'], $setting['cat']);
        }
    }
}