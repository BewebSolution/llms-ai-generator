<?php

namespace LlmsApp\Controllers;

use LlmsApp\Models\Project;
use LlmsApp\Services\WebCrawlerService;
use LlmsApp\Services\ConfigService;

class CrawlController
{
    /**
     * Start crawling a project
     */
    public function start(int $projectId)
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $project = Project::find($projectId);

            if (!$project) {
                echo json_encode(['success' => false, 'message' => 'Progetto non trovato']);
                return;
            }

            if (empty($project['homepage_url'])) {
                echo json_encode(['success' => false, 'message' => 'Homepage URL non configurata']);
                return;
            }

            // Check if already crawling
            if ($project['crawl_status'] === 'in_progress') {
                echo json_encode(['success' => false, 'message' => 'Crawling già in corso']);
                return;
            }

            // Get crawl settings
            $maxDepth = (int)($project['crawl_depth'] ?? 3);
            $maxUrls = (int)($project['max_urls'] ?? 500);

            // Start crawler
            $crawler = new WebCrawlerService($projectId);

            // Run crawl (this will be async in production)
            // For now, we start it and let it run
            $stats = $crawler->crawl($project['homepage_url'], $maxDepth, $maxUrls);

            echo json_encode([
                'success' => true,
                'message' => 'Crawling completato',
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Start crawling asynchronously (for background processing)
     */
    public function startAsync(int $projectId)
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $project = Project::find($projectId);

            if (!$project) {
                echo json_encode(['success' => false, 'message' => 'Progetto non trovato']);
                return;
            }

            if (empty($project['homepage_url'])) {
                echo json_encode(['success' => false, 'message' => 'Homepage URL non configurata']);
                return;
            }

            // Check if already crawling
            if ($project['crawl_status'] === 'in_progress') {
                echo json_encode(['success' => false, 'message' => 'Crawling già in corso']);
                return;
            }

            // Update status to pending (will be picked up by background worker)
            Project::updateCrawlStatus($projectId, 'pending');

            // In a real production environment, you would trigger a background job here
            // For now, we'll execute it directly but with output buffering
            ignore_user_abort(true);
            set_time_limit(0);

            // Send response immediately
            ob_start();
            echo json_encode([
                'success' => true,
                'message' => 'Crawling avviato',
            ]);
            $size = ob_get_length();
            header("Content-Length: $size");
            header("Connection: close");
            ob_end_flush();
            flush();

            // Now run the crawler in background
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            // Execute crawl
            $crawler = new WebCrawlerService($projectId);
            $maxDepth = (int)($project['crawl_depth'] ?? 3);
            $maxUrls = (int)($project['max_urls'] ?? 500);
            $crawler->crawl($project['homepage_url'], $maxDepth, $maxUrls);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Get crawl progress/status
     */
    public function status(int $projectId)
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $project = Project::find($projectId);

            if (!$project) {
                echo json_encode(['success' => false, 'message' => 'Progetto non trovato']);
                return;
            }

            $progress = WebCrawlerService::getProgress($projectId);

            echo json_encode([
                'success' => true,
                'progress' => $progress,
            ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Stop crawling
     */
    public function stop(int $projectId)
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $project = Project::find($projectId);

            if (!$project) {
                echo json_encode(['success' => false, 'message' => 'Progetto non trovato']);
                return;
            }

            WebCrawlerService::stop($projectId);

            echo json_encode([
                'success' => true,
                'message' => 'Crawling fermato',
            ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Show crawl progress page
     */
    public function progress(int $projectId)
    {
        $project = Project::find($projectId);

        if (!$project) {
            $_SESSION['error'] = 'Progetto non trovato';
            header('Location: ' . $this->baseUrl() . '/');
            exit;
        }

        $progress = WebCrawlerService::getProgress($projectId);

        $this->render('crawl/progress', [
            'project' => $project,
            'progress' => $progress,
            'view' => 'crawl/progress',
        ]);
    }

    private function render(string $view, array $data = [])
    {
        extract($data);
        $baseUrl = $this->baseUrl();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        include __DIR__ . '/../Views/layout.php';
    }

    private function baseUrl(): string
    {
        $config = ConfigService::getInstance();
        return $config->getBasePath();
    }
}
