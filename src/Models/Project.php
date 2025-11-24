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
            INSERT INTO projects (name, domain, site_summary, description, slug, homepage_url, crawl_depth, max_urls)
            VALUES (:name, :domain, :site_summary, :description, :slug, :homepage_url, :crawl_depth, :max_urls)
        ');
        $stmt->execute([
            'name'         => $data['name'],
            'domain'       => $data['domain'],
            'site_summary' => $data['site_summary'] ?? null,
            'description'  => $data['description'] ?? null,
            'slug'         => $data['slug'],
            'homepage_url' => $data['homepage_url'] ?? null,
            'crawl_depth'  => $data['crawl_depth'] ?? 3,
            'max_urls'     => $data['max_urls'] ?? 500,
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
                slug = :slug,
                homepage_url = :homepage_url,
                crawl_depth = :crawl_depth,
                max_urls = :max_urls
            WHERE id = :id
        ');
        $stmt->execute([
            'name'         => $data['name'],
            'domain'       => $data['domain'],
            'site_summary' => $data['site_summary'] ?? null,
            'description'  => $data['description'] ?? null,
            'slug'         => $data['slug'],
            'homepage_url' => $data['homepage_url'] ?? null,
            'crawl_depth'  => $data['crawl_depth'] ?? 3,
            'max_urls'     => $data['max_urls'] ?? 500,
            'id'           => $id,
        ]);
    }

    public static function updateCrawlStatus(int $id, string $status, ?string $error = null): void
    {
        $pdo = Database::getConnection();

        $sql = 'UPDATE projects SET crawl_status = :status';
        $params = ['status' => $status, 'id' => $id];

        if ($error !== null) {
            $sql .= ', crawl_error = :error';
            $params['error'] = $error;
        }

        if (in_array($status, ['completed', 'failed', 'stopped'])) {
            $sql .= ', last_crawl_at = NOW()';
        }

        $sql .= ' WHERE id = :id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    public static function updateHomepageUrl(int $id, string $url): void
    {
        $pdo = Database::getConnection();

        // Extract domain from URL
        $parts = parse_url($url);
        $domain = $parts['host'] ?? '';

        $stmt = $pdo->prepare('
            UPDATE projects
            SET homepage_url = :url, domain = :domain
            WHERE id = :id
        ');

        $stmt->execute([
            'url' => $url,
            'domain' => $domain,
            'id' => $id,
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