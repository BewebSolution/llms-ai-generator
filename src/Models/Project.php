<?php

namespace LlmsApp\Models;

use LlmsApp\Config\Database;
use PDO;

class Project
{
    public static function all(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT * FROM projects ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM projects WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO projects (name, domain, site_summary, description, slug)
            VALUES (:name, :domain, :site_summary, :description, :slug)
        ');
        $stmt->execute([
            'name'         => $data['name'],
            'domain'       => $data['domain'],
            'site_summary' => $data['site_summary'] ?? null,
            'description'  => $data['description'] ?? null,
            'slug'         => $data['slug'],
        ]);

        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE projects
            SET name = :name,
                domain = :domain,
                site_summary = :site_summary,
                description = :description,
                slug = :slug
            WHERE id = :id
        ');
        $stmt->execute([
            'name'         => $data['name'],
            'domain'       => $data['domain'],
            'site_summary' => $data['site_summary'] ?? null,
            'description'  => $data['description'] ?? null,
            'slug'         => $data['slug'],
            'id'           => $id,
        ]);
    }

    public static function delete(int $id): bool
    {
        try {
            $pdo = Database::getConnection();

            // L'eliminazione a cascata è gestita dal database (ON DELETE CASCADE)
            $stmt = $pdo->prepare('DELETE FROM projects WHERE id = :id');
            $stmt->execute(['id' => $id]);

            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            error_log('Error deleting project: ' . $e->getMessage());
            return false;
        }
    }

    public static function deleteMultiple(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        try {
            $pdo = Database::getConnection();

            // Sanitizza gli ID
            $ids = array_map('intval', $ids);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';

            $stmt = $pdo->prepare("DELETE FROM projects WHERE id IN ($placeholders)");
            $stmt->execute($ids);

            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log('Error deleting multiple projects: ' . $e->getMessage());
            return 0;
        }
    }

    public static function count(): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT COUNT(*) as total FROM projects');
        return (int)$stmt->fetch()['total'];
    }
}