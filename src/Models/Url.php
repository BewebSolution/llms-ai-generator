<?php

namespace LlmsApp\Models;

use LlmsApp\Config\Database;
use PDO;

class Url
{
    public static function upsert(
        int $projectId,
        string $loc,
        ?string $lastmod,
        string $type
    ): void {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            SELECT id FROM urls
            WHERE project_id = :pid AND loc = :loc
        ');
        $stmt->execute(['pid' => $projectId, 'loc' => $loc]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare('
                UPDATE urls
                SET lastmod = :lastmod, type = :type
                WHERE id = :id
            ');
            $stmt->execute([
                'lastmod' => $lastmod,
                'type'    => $type,
                'id'      => $existing['id'],
            ]);
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO urls (project_id, loc, lastmod, type)
                VALUES (:pid, :loc, :lastmod, :type)
            ');
            $stmt->execute([
                'pid'     => $projectId,
                'loc'     => $loc,
                'lastmod' => $lastmod,
                'type'    => $type,
            ]);
        }
    }

    public static function forProject(int $projectId, array $filters = []): array
    {
        $pdo = Database::getConnection();

        $sql = 'SELECT * FROM urls WHERE project_id = :pid';
        $params = ['pid' => $projectId];

        if (!empty($filters['type'])) {
            $sql .= ' AND type = :type';
            $params['type'] = $filters['type'];
        }

        if (isset($filters['is_selected'])) {
            $sql .= ' AND is_selected = :sel';
            $params['sel'] = (int)$filters['is_selected'];
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND (loc LIKE :search OR title LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function bulkUpdateSelection(
        int $projectId,
        array $selections
    ): void {
        $pdo = Database::getConnection();

        $pdo->beginTransaction();

        foreach ($selections as $urlId => $data) {
            $stmt = $pdo->prepare('
                UPDATE urls
                SET is_selected = :sel,
                    title = :title,
                    short_description = :desc,
                    type = :type
                WHERE id = :id AND project_id = :pid
            ');
            $stmt->execute([
                'sel'   => (int)($data['is_selected'] ?? 0),
                'title' => $data['title'] ?? null,
                'desc'  => $data['short_description'] ?? null,
                'type'  => $data['type'] ?? 'OTHER',
                'id'    => $urlId,
                'pid'   => $projectId,
            ]);
        }

        $pdo->commit();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM urls WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            INSERT INTO urls (project_id, loc, lastmod, type, title, short_description)
            VALUES (:project_id, :loc, :lastmod, :type, :title, :short_description)
            ON DUPLICATE KEY UPDATE
                lastmod = VALUES(lastmod),
                type = VALUES(type)
        ');

        $stmt->execute([
            'project_id' => $data['project_id'],
            'loc' => $data['loc'],
            'lastmod' => $data['lastmod'] ?? null,
            'type' => $data['type'] ?? 'OTHER',
            'title' => $data['title'] ?? null,
            'short_description' => $data['short_description'] ?? null,
        ]);

        return (int)$pdo->lastInsertId();
    }

    public static function findByProject(int $projectId, ?string $typeFilter = null, bool $selectedOnly = false, int $page = 1, int $perPage = 50): array
    {
        $pdo = Database::getConnection();

        $sql = 'SELECT * FROM urls WHERE project_id = :pid';
        $params = ['pid' => $projectId];

        if ($typeFilter) {
            $sql .= ' AND type = :type';
            $params['type'] = $typeFilter;
        }

        if ($selectedOnly) {
            $sql .= ' AND is_selected = 1';
        }

        $sql .= ' ORDER BY created_at DESC';

        $offset = ($page - 1) * $perPage;
        $sql .= ' LIMIT :limit OFFSET :offset';

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function countByProject(int $projectId, ?string $typeFilter = null, bool $selectedOnly = false): int
    {
        $pdo = Database::getConnection();

        $sql = 'SELECT COUNT(*) as total FROM urls WHERE project_id = :pid';
        $params = ['pid' => $projectId];

        if ($typeFilter) {
            $sql .= ' AND type = :type';
            $params['type'] = $typeFilter;
        }

        if ($selectedOnly) {
            $sql .= ' AND is_selected = 1';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetch()['total'];
    }

    /**
     * Aggiorna un URL per ID
     */
    public static function updateById(int $id, array $data): bool
    {
        $pdo = Database::getConnection();

        $fields = [];
        $params = ['id' => $id];

        // Costruisci dinamicamente la query UPDATE
        foreach ($data as $key => $value) {
            // Solo campi permessi
            if (in_array($key, ['title', 'short_description', 'type', 'is_selected'])) {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE urls SET " . implode(', ', $fields) . " WHERE id = :id";

        try {
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            error_log("Error updating URL ID $id: " . $e->getMessage());
            return false;
        }
    }

    public static function getProjectStats(int $projectId): array
    {
        $pdo = Database::getConnection();

        // Totali per tipo
        $stmt = $pdo->prepare('
            SELECT type, COUNT(*) as count
            FROM urls
            WHERE project_id = :pid
            GROUP BY type
        ');
        $stmt->execute(['pid' => $projectId]);
        $byType = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Totale selezionati
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as total
            FROM urls
            WHERE project_id = :pid AND is_selected = 1
        ');
        $stmt->execute(['pid' => $projectId]);
        $selected = $stmt->fetch()['total'];

        // Totale con descrizione
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as total
            FROM urls
            WHERE project_id = :pid AND short_description IS NOT NULL
        ');
        $stmt->execute(['pid' => $projectId]);
        $withDescription = $stmt->fetch()['total'];

        return [
            'byType' => $byType,
            'selected' => $selected,
            'withDescription' => $withDescription,
            'total' => array_sum($byType),
        ];
    }

    public static function updateSelection(array $ids, bool $selected): void
    {
        if (empty($ids)) return;

        $pdo = Database::getConnection();
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';

        $stmt = $pdo->prepare("
            UPDATE urls
            SET is_selected = ?
            WHERE id IN ($placeholders)
        ");

        $params = array_merge([$selected ? 1 : 0], $ids);
        $stmt->execute($params);
    }

    public static function updateType(array $ids, string $type): void
    {
        if (empty($ids)) return;

        $pdo = Database::getConnection();
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';

        $stmt = $pdo->prepare("
            UPDATE urls
            SET type = ?
            WHERE id IN ($placeholders)
        ");

        $params = array_merge([$type], $ids);
        $stmt->execute($params);
    }

    public static function updateDescription(int $id, string $description): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            UPDATE urls
            SET short_description = :desc
            WHERE id = :id
        ');
        $stmt->execute([
            'desc' => $description,
            'id' => $id,
        ]);
    }
}