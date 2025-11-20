<?php

namespace LlmsApp\Controllers;

use LlmsApp\Models\Project;
use LlmsApp\Models\Sitemap;
use LlmsApp\Models\Url;
use LlmsApp\Services\SitemapParser;
use LlmsApp\Services\ConfigService;

class SitemapController
{
    public function index($projectId)
    {
        $projectId = (int)$projectId;
        $project = Project::find($projectId);
        if (!$project) {
            $_SESSION['error'] = 'Progetto non trovato';
            header('Location: ' . $this->baseUrl() . '/');
            exit;
        }

        $sitemaps = Sitemap::forProject($projectId);

        $this->render('sitemaps/index', [
            'project'  => $project,
            'sitemaps' => $sitemaps,
            'view'     => 'sitemaps/index',
        ]);
    }

    public function store($projectId)
    {
        $projectId = (int)$projectId;
        $project = Project::find($projectId);
        if (!$project) {
            $_SESSION['error'] = 'Progetto non trovato';
            header('Location: ' . $this->baseUrl() . '/');
            exit;
        }

        $url = trim($_POST['sitemap_url'] ?? $_POST['url'] ?? '');

        if (empty($url)) {
            $_SESSION['error'] = 'Inserisci l\'URL della sitemap';
            header('Location: ' . $this->baseUrl() . '/projects/' . $projectId . '/sitemaps');
            exit;
        }

        // Valida URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $_SESSION['error'] = 'URL non valido';
            header('Location: ' . $this->baseUrl() . '/projects/' . $projectId . '/sitemaps');
            exit;
        }

        try {
            $sitemapId = Sitemap::create($projectId, $url);
            $_SESSION['success'] = 'Sitemap aggiunta con successo! Ora clicca su "Avvia Parsing" per estrarre gli URL.';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Errore durante l\'aggiunta: ' . $e->getMessage();
        }

        header('Location: ' . $this->baseUrl() . '/projects/' . $projectId . '/sitemaps');
        exit;
    }

    public function parse($sitemapId)
    {
        $sitemapId = (int)$sitemapId;
        $sitemap = Sitemap::find($sitemapId);

        header('Content-Type: application/json');

        if (!$sitemap) {
            echo json_encode(['success' => false, 'message' => 'Sitemap non trovata']);
            return;
        }

        $projectId = (int)$sitemap['project_id'];

        try {
            $parser = new SitemapParser();

            // Effettua il parsing
            $parser->parseSitemap($sitemapId, $projectId, $sitemap['url']);

            // Conta gli URL salvati
            $pdo = \LlmsApp\Config\Database::getConnection();
            $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM urls WHERE project_id = :pid');
            $stmt->execute(['pid' => $projectId]);
            $urlCount = $stmt->fetch()['total'];

            // Aggiorna il timestamp di parsing
            Sitemap::updateLastParsed($sitemapId);

            echo json_encode([
                'success' => true,
                'message' => "Parsing completato! Trovati e salvati {$urlCount} URL.",
                'url_count' => $urlCount
            ]);

        } catch (\Exception $e) {
            error_log('Errore parsing sitemap: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Errore durante il parsing: ' . $e->getMessage()
            ]);
        }
    }

    public function delete($sitemapId)
    {
        $sitemapId = (int)$sitemapId;
        $sitemap = Sitemap::find($sitemapId);

        if (!$sitemap) {
            $_SESSION['error'] = 'Sitemap non trovata';
            header('Location: ' . $this->baseUrl() . '/');
            exit;
        }

        $projectId = $sitemap['project_id'];

        try {
            $pdo = \LlmsApp\Config\Database::getConnection();
            $stmt = $pdo->prepare('DELETE FROM sitemaps WHERE id = :id');
            $stmt->execute(['id' => $sitemapId]);

            $_SESSION['success'] = 'Sitemap eliminata con successo';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Errore durante l\'eliminazione: ' . $e->getMessage();
        }

        header('Location: ' . $this->baseUrl() . '/projects/' . $projectId . '/sitemaps');
        exit;
    }

    private function render(string $view, array $data = [])
    {
        extract($data);
        $baseUrl = $this->baseUrl();

        // Avvia sessione se non attiva
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