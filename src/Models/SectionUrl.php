<?php

namespace LlmsApp\Models;

use LlmsApp\Config\Database;
use PDO;

class SectionUrl
{
    public static function assign(int $sectionId, int $urlId, int $position = 1): void
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            SELECT id FROM section_url
            WHERE section_id = :sid AND url_id = :uid
        ');
        $stmt->execute([
            'sid' => $sectionId,
            'uid' => $urlId,
        ]);
        $exists = $stmt->fetch();

        if ($exists) {
            $stmt = $pdo->prepare('
                UPDATE section_url
                SET position = :position
                WHERE id = :id
            ');
            $stmt->execute([
                'position' => $position,
                'id'       => $exists['id'],
            ]);
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO section_url (section_id, url_id, position)
                VALUES (:sid, :uid, :position)
            ');
            $stmt->execute([
                'sid'      => $sectionId,
                'uid'      => $urlId,
                'position' => $position,
            ]);
        }
    }

    public static function urlsForSection(int $sectionId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT su.*, u.*
            FROM section_url su
            INNER JOIN urls u ON u.id = su.url_id
            WHERE su.section_id = :sid AND u.is_selected = 1
            ORDER BY su.position ASC
        ');
        $stmt->execute(['sid' => $sectionId]);
        return $stmt->fetchAll();
    }

    public static function getUrlsForSection(int $sectionId): array
    {
        return self::urlsForSection($sectionId);
    }
}