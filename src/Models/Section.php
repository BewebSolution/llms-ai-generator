<?php

namespace LlmsApp\Models;

use LlmsApp\Config\Database;
use PDO;

class Section
{
    public static function forProject(int $projectId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT * FROM sections
            WHERE project_id = :pid
            ORDER BY position ASC
        ');
        $stmt->execute(['pid' => $projectId]);
        return $stmt->fetchAll();
    }

    public static function createDefaultsForProject(int $projectId): void
    {
        $pdo = Database::getConnection();

        $defaults = [
            ['name' => 'Struttura e tassonomia prodotti', 'slug' => 'structure', 'position' => 1, 'is_optional' => 0],
            ['name' => 'Policy e informazioni legali',    'slug' => 'policies',  'position' => 2, 'is_optional' => 0],
            ['name' => 'Guide all\'acquisto e contenuti editoriali', 'slug' => 'guides', 'position' => 3, 'is_optional' => 0],
            ['name' => 'Assistenza e supporto',           'slug' => 'support',   'position' => 4, 'is_optional' => 0],
            ['name' => 'Optional',                        'slug' => 'optional',  'position' => 5, 'is_optional' => 1],
        ];

        $stmt = $pdo->prepare('
            INSERT INTO sections (project_id, name, slug, position, is_optional)
            VALUES (:pid, :name, :slug, :position, :is_optional)
        ');

        foreach ($defaults as $row) {
            $stmt->execute([
                'pid'         => $projectId,
                'name'        => $row['name'],
                'slug'        => $row['slug'],
                'position'    => $row['position'],
                'is_optional' => $row['is_optional'],
            ]);
        }
    }
}