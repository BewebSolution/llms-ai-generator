<?php

namespace LlmsApp\Controllers;

use LlmsApp\Models\Project;
use LlmsApp\Models\Section;

class ProjectController
{
    public function index()
    {
        $projects = Project::all();
        $this->render('projects/index', ['projects' => $projects, 'view' => 'projects/index']);
    }

    public function create()
    {
        $this->render('projects/create', ['view' => 'projects/create']);
    }

    public function store()
    {
        $name = trim($_POST['name'] ?? '');
        $domain = trim($_POST['domain'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $siteSummary = trim($_POST['site_summary'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $homepageUrl = trim($_POST['homepage_url'] ?? '');
        $crawlDepth = (int)($_POST['crawl_depth'] ?? 3);
        $maxUrls = (int)($_POST['max_urls'] ?? 500);

        if ($slug === '') {
            $slug = $this->slugify($name);
        }

        // Extract domain from homepage URL if provided
        if (!empty($homepageUrl) && empty($domain)) {
            $parts = parse_url($homepageUrl);
            $domain = $parts['host'] ?? '';
        }

        $projectId = Project::create([
            'name'         => $name,
            'domain'       => $domain,
            'site_summary' => $siteSummary,
            'description'  => $description,
            'slug'         => $slug,
            'homepage_url' => $homepageUrl,
            'crawl_depth'  => $crawlDepth,
            'max_urls'     => $maxUrls,
        ]);

        Section::createDefaultsForProject($projectId);

        // Redirect to crawl page if homepage URL is provided
        if (!empty($homepageUrl)) {
            header('Location: ' . $this->baseUrl() . '/projects/' . $projectId . '/crawl');
        } else {
            header('Location: ' . $this->baseUrl() . '/projects/' . $projectId);
        }
        exit;
    }

    public function edit($id)
    {
        $project = Project::find((int)$id);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $this->render('projects/edit', ['project' => $project, 'view' => 'projects/edit']);
    }

    public function update($id)
    {
        $project = Project::find((int)$id);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $domain = trim($_POST['domain'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $siteSummary = trim($_POST['site_summary'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $homepageUrl = trim($_POST['homepage_url'] ?? '');
        $crawlDepth = (int)($_POST['crawl_depth'] ?? 3);
        $maxUrls = (int)($_POST['max_urls'] ?? 500);

        if ($slug === '') {
            $slug = $this->slugify($name);
        }

        Project::update((int)$id, [
            'name'         => $name,
            'domain'       => $domain,
            'site_summary' => $siteSummary,
            'description'  => $description,
            'slug'         => $slug,
            'homepage_url' => $homepageUrl,
            'crawl_depth'  => $crawlDepth,
            'max_urls'     => $maxUrls,
        ]);

        header('Location: ' . $this->baseUrl() . '/projects/' . $id);
        exit;
    }

    public function show($id)
    {
        $project = Project::find((int)$id);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $this->render('projects/show', ['project' => $project, 'view' => 'projects/show']);
    }

    public function destroy($id)
    {
        $projectId = (int)$id;

        if (Project::delete($projectId)) {
            $_SESSION['success'] = 'Progetto eliminato con successo!';
        } else {
            $_SESSION['error'] = 'Errore durante l\'eliminazione del progetto.';
        }

        header('Location: ' . $this->baseUrl() . '/');
        exit;
    }

    public function bulkDelete()
    {
        $ids = $_POST['project_ids'] ?? [];

        if (empty($ids)) {
            $_SESSION['error'] = 'Nessun progetto selezionato.';
            header('Location: ' . $this->baseUrl() . '/');
            exit;
        }

        $deleted = Project::deleteMultiple($ids);

        if ($deleted > 0) {
            $_SESSION['success'] = "Eliminati $deleted progetti con successo!";
        } else {
            $_SESSION['error'] = 'Errore durante l\'eliminazione dei progetti.';
        }

        header('Location: ' . $this->baseUrl() . '/');
        exit;
    }

    private function render(string $view, array $data = [])
    {
        extract($data);
        $baseUrl = $this->baseUrl();
        include __DIR__ . '/../Views/layout.php';
    }

    private function baseUrl(): string
    {
        $base = $_ENV['APP_BASE_PATH'] ?? '/';
        if ($base === '' || $base === '/') {
            return '';
        }
        return rtrim($base, '/');
    }

    private function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim($text, '-');
        return $text ?: 'project';
    }
}