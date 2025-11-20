<?php

namespace LlmsApp\Controllers;

use LlmsApp\Models\Project;
use LlmsApp\Services\LlmsGenerator;

class LlmsController
{
    public function preview($projectId)
    {
        $projectId = (int)$projectId;
        $project = Project::find($projectId);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $generator = new LlmsGenerator();
        $content = $generator->generateForProject($projectId);

        $this->render('llms/preview', [
            'project' => $project,
            'content' => $content,
            'view'    => 'llms/preview',
        ]);
    }

    public function generate($projectId)
    {
        $projectId = (int)$projectId;
        $project = Project::find($projectId);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $storageBase = $_ENV['STORAGE_PATH'] ?? 'storage';
        $baseDir = realpath(__DIR__ . '/../../' . $storageBase) ?: (__DIR__ . '/../../storage');

        $generator = new LlmsGenerator();
        $generator->saveToFile($projectId, $baseDir);

        header('Location: ' . $this->baseUrl() . '/projects/' . $projectId . '/llms/preview');
        exit;
    }

    public function publicTxt($slug)
    {
        $project = Project::findBySlug($slug);
        if (!$project) {
            http_response_code(404);
            echo 'llms.txt not found';
            return;
        }

        $storageBase = $_ENV['STORAGE_PATH'] ?? 'storage';
        $baseDir = realpath(__DIR__ . '/../../' . $storageBase) ?: (__DIR__ . '/../../storage');
        $filePath = $baseDir . '/llms/llms_' . $project['slug'] . '.txt';

        if (!is_file($filePath)) {
            $generator = new LlmsGenerator();
            $generator->saveToFile((int)$project['id'], $baseDir);
        }

        if (!is_file($filePath)) {
            http_response_code(404);
            echo 'llms.txt not found';
            return;
        }

        header('Content-Type: text/plain; charset=utf-8');
        readfile($filePath);
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
}