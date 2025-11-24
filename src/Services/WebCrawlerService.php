<?php

namespace LlmsApp\Services;

use LlmsApp\Config\Database;
use LlmsApp\Models\Url;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PDO;

class WebCrawlerService
{
    private Client $client;
    private int $projectId;
    private string $baseDomain;
    private int $maxDepth;
    private int $maxUrls;
    private int $delayMs;
    private array $visitedUrls = [];
    private array $discoveredUrls = [];
    private ContentExtractorService $extractor;
    private UrlClassifier $classifier;

    public function __construct(int $projectId)
    {
        $this->projectId = $projectId;
        $this->extractor = new ContentExtractorService();
        $this->classifier = new UrlClassifier();

        // Configuration
        $this->delayMs = (int)($_ENV['CRAWL_DELAY_MS'] ?? 500);

        // HTTP client with reasonable defaults
        $this->client = new Client([
            'timeout' => 15,
            'connect_timeout' => 10,
            'allow_redirects' => [
                'max' => 5,
                'strict' => false,
                'referer' => true,
                'track_redirects' => true,
            ],
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; LLMSBot/1.0; +https://llmstxt.org)',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ],
            'verify' => false, // Skip SSL verification for development
        ]);
    }

    /**
     * Start crawling from homepage
     */
    public function crawl(string $homepageUrl, int $maxDepth = 3, int $maxUrls = 500): array
    {
        $this->maxDepth = $maxDepth;
        $this->maxUrls = $maxUrls;
        $this->baseDomain = $this->extractDomain($homepageUrl);

        // Update project status
        $this->updateProjectStatus('in_progress');
        $this->initCrawlStats();

        // Clear previous crawl data for this project
        $this->clearPreviousCrawl();

        // Add homepage to queue
        $this->addToQueue($homepageUrl, 0);

        $stats = [
            'total_discovered' => 0,
            'total_crawled' => 0,
            'total_failed' => 0,
            'total_skipped' => 0,
        ];

        try {
            // Process queue
            while ($this->hasUrlsToProcess() && $stats['total_crawled'] < $this->maxUrls) {
                // Check if crawl was stopped
                if ($this->isCrawlStopped()) {
                    $this->updateProjectStatus('stopped');
                    break;
                }

                $queueItem = $this->getNextFromQueue();
                if (!$queueItem) {
                    break;
                }

                $url = $queueItem['url'];
                $depth = $queueItem['depth'];

                // Skip if already visited
                if (isset($this->visitedUrls[$url])) {
                    $this->markQueueItem($queueItem['id'], 'skipped');
                    $stats['total_skipped']++;
                    continue;
                }

                $this->visitedUrls[$url] = true;

                // Crawl the URL
                $result = $this->crawlUrl($url, $depth);

                if ($result['success']) {
                    $stats['total_crawled']++;
                    $this->markQueueItem($queueItem['id'], 'completed');

                    // Extract and queue new links if not at max depth
                    if ($depth < $this->maxDepth) {
                        foreach ($result['links'] as $link) {
                            if ($this->addToQueue($link, $depth + 1)) {
                                $stats['total_discovered']++;
                            }
                        }
                    }
                } else {
                    $stats['total_failed']++;
                    $this->markQueueItem($queueItem['id'], 'failed', $result['error']);
                }

                // Update stats in database
                $this->updateCrawlStats($stats);

                // Rate limiting
                usleep($this->delayMs * 1000);
            }

            // Mark as completed if finished normally
            if (!$this->isCrawlStopped()) {
                $this->updateProjectStatus('completed');
            }

        } catch (\Exception $e) {
            $this->updateProjectStatus('failed', $e->getMessage());
            throw $e;
        }

        return $stats;
    }

    /**
     * Crawl a single URL
     */
    private function crawlUrl(string $url, int $depth): array
    {
        try {
            $response = $this->client->get($url);
            $statusCode = $response->getStatusCode();
            $html = (string)$response->getBody();

            // Extract content
            $extracted = $this->extractor->extract($html, $url);

            // Classify URL
            $type = $this->classifier->classify($url);

            // Check if it's homepage
            if ($depth === 0) {
                $type = 'HOMEPAGE';
            }

            // Save to database
            $this->saveUrl([
                'project_id' => $this->projectId,
                'loc' => $url,
                'type' => $type,
                'title' => $extracted['title'],
                'short_description' => $extracted['description'],
                'http_status' => $statusCode,
                'crawl_depth' => $depth,
                'content_hash' => md5($html),
            ]);

            // Extract internal links
            $links = $this->extractLinks($html, $url);

            return [
                'success' => true,
                'links' => $links,
                'title' => $extracted['title'],
            ];

        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            return [
                'success' => false,
                'error' => "HTTP {$statusCode}: " . $e->getMessage(),
                'links' => [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'links' => [],
            ];
        }
    }

    /**
     * Extract internal links from HTML
     */
    private function extractLinks(string $html, string $baseUrl): array
    {
        $links = [];

        // Use DOMDocument to parse HTML
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $anchors = $dom->getElementsByTagName('a');

        foreach ($anchors as $anchor) {
            $href = $anchor->getAttribute('href');
            if (empty($href)) {
                continue;
            }

            // Resolve relative URLs
            $absoluteUrl = $this->resolveUrl($href, $baseUrl);
            if (!$absoluteUrl) {
                continue;
            }

            // Filter: only same domain, no fragments, no assets
            if ($this->isValidCrawlUrl($absoluteUrl)) {
                $links[] = $absoluteUrl;
            }
        }

        return array_unique($links);
    }

    /**
     * Resolve relative URL to absolute
     */
    private function resolveUrl(string $href, string $baseUrl): ?string
    {
        // Skip javascript:, mailto:, tel:, #
        if (preg_match('/^(javascript:|mailto:|tel:|#|data:)/i', $href)) {
            return null;
        }

        // Already absolute
        if (preg_match('/^https?:\/\//i', $href)) {
            return $this->normalizeUrl($href);
        }

        // Protocol-relative
        if (strpos($href, '//') === 0) {
            return $this->normalizeUrl('https:' . $href);
        }

        // Parse base URL
        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $basePath = $parts['path'] ?? '/';

        // Root-relative
        if (strpos($href, '/') === 0) {
            return $this->normalizeUrl("{$scheme}://{$host}{$href}");
        }

        // Relative to current path
        $baseDir = dirname($basePath);
        if ($baseDir === '\\' || $baseDir === '.') {
            $baseDir = '';
        }

        return $this->normalizeUrl("{$scheme}://{$host}{$baseDir}/{$href}");
    }

    /**
     * Normalize URL (remove fragments, standardize)
     */
    private function normalizeUrl(string $url): string
    {
        // Remove fragment
        $url = preg_replace('/#.*$/', '', $url);

        // Remove trailing slash for consistency (except root)
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }

        $normalized = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        $normalized .= $path;

        if (!empty($parts['query'])) {
            $normalized .= '?' . $parts['query'];
        }

        return $normalized;
    }

    /**
     * Check if URL is valid for crawling
     */
    private function isValidCrawlUrl(string $url): bool
    {
        // Must be same domain
        if ($this->extractDomain($url) !== $this->baseDomain) {
            return false;
        }

        // Skip common non-content URLs
        $skipPatterns = [
            '/\.(jpg|jpeg|png|gif|svg|webp|ico|pdf|doc|docx|xls|xlsx|zip|rar|mp3|mp4|avi|mov)$/i',
            '/\/(cart|checkout|login|logout|register|account|my-account|wp-admin|wp-login|admin|feed|rss)\b/i',
            '/\/page\/\d+/',
            '/[?&](add-to-cart|action=|logout|login)/i',
            '/\/(tag|author|attachment|trackback)\//i',
        ];

        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return false;
            }
        }

        // Already discovered
        if (isset($this->discoveredUrls[$url])) {
            return false;
        }

        return true;
    }

    /**
     * Extract domain from URL
     */
    private function extractDomain(string $url): string
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';

        // Remove www prefix for comparison
        return preg_replace('/^www\./i', '', $host);
    }

    /**
     * Add URL to crawl queue
     */
    private function addToQueue(string $url, int $depth): bool
    {
        if (isset($this->discoveredUrls[$url])) {
            return false;
        }

        $this->discoveredUrls[$url] = true;

        $pdo = Database::getConnection();

        try {
            $stmt = $pdo->prepare('
                INSERT INTO crawl_queue (project_id, url, depth, status)
                VALUES (:project_id, :url, :depth, "pending")
                ON DUPLICATE KEY UPDATE depth = LEAST(depth, VALUES(depth))
            ');

            $stmt->execute([
                'project_id' => $this->projectId,
                'url' => $url,
                'depth' => $depth,
            ]);

            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get next URL from queue
     */
    private function getNextFromQueue(): ?array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            SELECT id, url, depth
            FROM crawl_queue
            WHERE project_id = :project_id AND status = "pending"
            ORDER BY depth ASC, id ASC
            LIMIT 1
        ');
        $stmt->execute(['project_id' => $this->projectId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Mark as processing
            $update = $pdo->prepare('UPDATE crawl_queue SET status = "processing" WHERE id = :id');
            $update->execute(['id' => $row['id']]);
        }

        return $row ?: null;
    }

    /**
     * Check if there are URLs to process
     */
    private function hasUrlsToProcess(): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            SELECT COUNT(*) as count
            FROM crawl_queue
            WHERE project_id = :project_id AND status = "pending"
        ');
        $stmt->execute(['project_id' => $this->projectId]);

        return (int)$stmt->fetch()['count'] > 0;
    }

    /**
     * Mark queue item status
     */
    private function markQueueItem(int $id, string $status, ?string $error = null): void
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            UPDATE crawl_queue
            SET status = :status, error_message = :error, processed_at = NOW()
            WHERE id = :id
        ');

        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'error' => $error,
        ]);
    }

    /**
     * Save URL to database
     */
    private function saveUrl(array $data): void
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            INSERT INTO urls (project_id, loc, type, title, short_description, http_status, crawl_depth, content_hash)
            VALUES (:project_id, :loc, :type, :title, :short_description, :http_status, :crawl_depth, :content_hash)
            ON DUPLICATE KEY UPDATE
                type = VALUES(type),
                title = COALESCE(VALUES(title), title),
                short_description = COALESCE(VALUES(short_description), short_description),
                http_status = VALUES(http_status),
                crawl_depth = VALUES(crawl_depth),
                content_hash = VALUES(content_hash),
                updated_at = NOW()
        ');

        $stmt->execute($data);
    }

    /**
     * Update project crawl status
     */
    private function updateProjectStatus(string $status, ?string $error = null): void
    {
        $pdo = Database::getConnection();

        $sql = 'UPDATE projects SET crawl_status = :status';
        $params = ['status' => $status, 'id' => $this->projectId];

        if ($error) {
            $sql .= ', crawl_error = :error';
            $params['error'] = $error;
        }

        if ($status === 'completed' || $status === 'failed' || $status === 'stopped') {
            $sql .= ', last_crawl_at = NOW()';
        }

        $sql .= ' WHERE id = :id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Check if crawl was stopped by user
     */
    private function isCrawlStopped(): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT crawl_status FROM projects WHERE id = :id');
        $stmt->execute(['id' => $this->projectId]);

        return $stmt->fetch()['crawl_status'] === 'stopped';
    }

    /**
     * Clear previous crawl data
     */
    private function clearPreviousCrawl(): void
    {
        $pdo = Database::getConnection();

        // Clear queue
        $stmt = $pdo->prepare('DELETE FROM crawl_queue WHERE project_id = :id');
        $stmt->execute(['id' => $this->projectId]);

        // Clear URLs (optional - you might want to keep them)
        // $stmt = $pdo->prepare('DELETE FROM urls WHERE project_id = :id');
        // $stmt->execute(['id' => $this->projectId]);
    }

    /**
     * Initialize crawl stats
     */
    private function initCrawlStats(): void
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            INSERT INTO crawl_stats (project_id, started_at)
            VALUES (:project_id, NOW())
            ON DUPLICATE KEY UPDATE
                total_discovered = 0,
                total_crawled = 0,
                total_failed = 0,
                total_skipped = 0,
                started_at = NOW(),
                completed_at = NULL
        ');

        $stmt->execute(['project_id' => $this->projectId]);
    }

    /**
     * Update crawl stats
     */
    private function updateCrawlStats(array $stats): void
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('
            UPDATE crawl_stats
            SET total_discovered = :discovered,
                total_crawled = :crawled,
                total_failed = :failed,
                total_skipped = :skipped
            WHERE project_id = :project_id
        ');

        $stmt->execute([
            'project_id' => $this->projectId,
            'discovered' => $stats['total_discovered'],
            'crawled' => $stats['total_crawled'],
            'failed' => $stats['total_failed'],
            'skipped' => $stats['total_skipped'],
        ]);
    }

    /**
     * Get current crawl progress
     */
    public static function getProgress(int $projectId): array
    {
        $pdo = Database::getConnection();

        // Get stats
        $stmt = $pdo->prepare('SELECT * FROM crawl_stats WHERE project_id = :id');
        $stmt->execute(['id' => $projectId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Get queue counts
        $stmt = $pdo->prepare('
            SELECT status, COUNT(*) as count
            FROM crawl_queue
            WHERE project_id = :id
            GROUP BY status
        ');
        $stmt->execute(['id' => $projectId]);
        $queueStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Get project status
        $stmt = $pdo->prepare('SELECT crawl_status, crawl_error FROM projects WHERE id = :id');
        $stmt->execute(['id' => $projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'status' => $project['crawl_status'] ?? 'pending',
            'error' => $project['crawl_error'] ?? null,
            'total_discovered' => (int)($stats['total_discovered'] ?? 0),
            'total_crawled' => (int)($stats['total_crawled'] ?? 0),
            'total_failed' => (int)($stats['total_failed'] ?? 0),
            'total_skipped' => (int)($stats['total_skipped'] ?? 0),
            'pending_in_queue' => (int)($queueStats['pending'] ?? 0),
            'started_at' => $stats['started_at'] ?? null,
            'completed_at' => $stats['completed_at'] ?? null,
        ];
    }

    /**
     * Stop crawl for a project
     */
    public static function stop(int $projectId): void
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('UPDATE projects SET crawl_status = "stopped" WHERE id = :id');
        $stmt->execute(['id' => $projectId]);
    }
}
