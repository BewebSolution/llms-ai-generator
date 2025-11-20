<?php

namespace LlmsApp\Models;

use LlmsApp\Config\Database;
use PDO;

class Sitemap
{
    public static function forProject(int $projectId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM sitemaps WHERE project_id = :pid ORDER BY created_at DESC');
        $stmt->execute(['pid' => $projectId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM sitemaps WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(int $projectId, string $url): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO sitemaps (project_id, url)
            VALUES (:pid, :url)
        ');
        $stmt->execute([
            'pid' => $projectId,
            'url' => $url,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function updateLastParsed(int $id): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE sitemaps
            SET last_parsed_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute(['id' => $id]);
    }

    public static function findByProject(int $projectId): array
    {
        return self::forProject($projectId);
    }

    public static function updateParsedAt(int $id): void
    {
        self::updateLastParsed($id);
    }
}