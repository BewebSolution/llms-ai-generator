<?php

namespace LlmsApp\Controllers;

use LlmsApp\Models\AiUsage;
use LlmsApp\Models\Project;

class CostController
{
    public function index()
    {
        $this->render('costs/index', [
            'view' => 'costs/index',
        ]);
    }

    public function projectCosts($projectId)
    {
        $projectId = (int)$projectId;
        $project = Project::find($projectId);

        if (!$project) {
            http_response_code(404);
            echo 'Progetto non trovato';
            return;
        }

        $stats = AiUsage::getProjectStats($projectId);

        $this->render('costs/project', [
            'project' => $project,
            'stats' => $stats,
            'view' => 'costs/project',
        ]);
    }

    public function estimate()
    {
        $urlCount = (int)($_POST['url_count'] ?? 100);
        $operations = $_POST['operations'] ?? ['title', 'description', 'classification'];

        $estimate = AiUsage::estimateBatchCost($urlCount, $operations);

        header('Content-Type: application/json');
        echo json_encode($estimate);
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
        return ($base === '' || $base === '/') ? '' : rtrim($base, '/');
    }
}