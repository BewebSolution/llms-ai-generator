<?php

namespace LlmsApp\Controllers;

use LlmsApp\Models\Project;
use LlmsApp\Models\Url;
use LlmsApp\Models\Section;
use LlmsApp\Models\SectionUrl;

class UrlController
{
    public function index($projectId)
    {
        $projectId = (int)$projectId;
        $project = Project::find($projectId);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $filters = [
            'type'        => $_GET['type'] ?? null,
            'is_selected' => isset($_GET['is_selected']) && $_GET['is_selected'] !== '' ? (int)$_GET['is_selected'] : null,
            'search'      => $_GET['search'] ?? null,
        ];

        $urls = Url::forProject($projectId, $filters);
        $sections = Section::forProject($projectId);

        $this->render('urls/index', [
            'project'  => $project,
            'urls'     => $urls,
            'sections' => $sections,
            'filters'  => $filters,
            'view'     => 'urls/index',
        ]);
    }

    public function bulkUpdate($projectId)
    {
        $projectId = (int)$projectId;
        $project = Project::find($projectId);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $selections = $_POST['urls'] ?? [];
        Url::bulkUpdateSelection($projectId, $selections);

        $sectionAssignments = $_POST['section'] ?? [];

        foreach ($sectionAssignments as $urlId => $sectionId) {
            if ($sectionId) {
                SectionUrl::assign((int)$sectionId, (int)$urlId, 1);
            }
        }

        header('Location: ' . $this->baseUrl() . '/projects/' . $projectId . '/urls');
        exit;
    }

    private function render(string $view, array $data = [])
    {
        extract($data);
        $baseUrl = $this->baseUrl();
        include __DIR__ . '/../Views/layout.php';
    }

    public function deleteApi($urlId)
    {
        header('Content-Type: application/json');

        $urlId = (int)$urlId;
        $url = Url::find($urlId);

        if (!$url) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'URL non trovato']);
            return;
        }

        try {
            $pdo = \LlmsApp\Config\Database::getConnection();
            $stmt = $pdo->prepare('DELETE FROM urls WHERE id = :id');
            $stmt->execute(['id' => $urlId]);

            echo json_encode(['success' => true, 'message' => 'URL eliminato']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Errore durante l\'eliminazione']);
        }
    }

    private function baseUrl(): string
    {
        $base = $_ENV['APP_BASE_PATH'] ?? '/';
        if ($base === '' || $base === '/') {
            return '';
        }
        return rtrim($base, '/');
    }
}