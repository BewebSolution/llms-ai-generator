<?php

namespace LlmsApp\Services;

use LlmsApp\Models\Project;
use LlmsApp\Models\Section;
use LlmsApp\Models\Url;
use LlmsApp\Models\SectionUrl;

class LlmsGenerator
{
    private ?AiDescriptionService $aiService = null;

    /**
     * Ottieni il servizio AI (lazy loading)
     */
    private function getAiService(): ?AiDescriptionService
    {
        if ($this->aiService === null) {
            try {
                $this->aiService = new AiDescriptionService();
            } catch (\Exception $e) {
                error_log("AI Service non disponibile: " . $e->getMessage());
                return null;
            }
        }
        return $this->aiService;
    }

    /**
     * Genera il contenuto del file llms.txt per un progetto
     */
    public function generateForProject(int $projectId): string
    {
        return $this->generate($projectId);
    }

    /**
     * Genera il contenuto del file llms.txt per un progetto
     * Segue le specifiche ufficiali llms.txt con formato Writesonic-like
     */
    public function generate(int $projectId): string
    {
        $project = Project::find($projectId);
        if (!$project) {
            throw new \Exception('Progetto non trovato');
        }

        $lines = [];

        // 1. H1 con il nome del progetto
        $lines[] = '# ' . $project['name'];
        $lines[] = '';

        // 2. Blockquote con summary
        if (!empty($project['site_summary'])) {
            $lines[] = $project['site_summary'];
        } else {
            $lines[] = 'Website: ' . $project['domain'];
        }
        $lines[] = '';

        // Ottieni tutti gli URL selezionati organizzati per tipo
        $urlsByType = $this->getUrlsOrganizedByType($projectId);

        // Usa le etichette delle sezioni da UrlClassifier
        $sectionLabels = UrlClassifier::getSectionLabels();

        // Ordine delle sezioni basato sulla priorità
        $types = UrlClassifier::getTypes();
        usort($types, function($a, $b) {
            return UrlClassifier::getSectionPriority($a) - UrlClassifier::getSectionPriority($b);
        });

        // Genera sezioni
        foreach ($types as $type) {
            if (empty($urlsByType[$type])) {
                continue;
            }

            $sectionName = $sectionLabels[$type] ?? $type;
            $lines[] = '## ' . $sectionName;

            // Aggiungi gli URL di questa sezione
            foreach ($urlsByType[$type] as $url) {
                $lines[] = $this->formatUrl($url);
            }
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Formatta un singolo URL per il file llms.txt
     * Formato: - [Titolo](URL): Descrizione
     */
    private function formatUrl(array $url): string
    {
        $title = $url['title'] ?? $this->extractTitleFromUrl($url['loc']);
        $description = $url['short_description'] ?? '';

        // Se non c'è descrizione, prova a generarla con l'AI
        if (empty($description)) {
            $aiService = $this->getAiService();
            if ($aiService !== null) {
                try {
                    $description = $aiService->generateShortDescription($title, $url['loc']);

                    if (!empty($description) && isset($url['id'])) {
                        Url::updateDescription($url['id'], $description);
                    }
                } catch (\Exception $e) {
                    error_log("Errore generazione AI per URL {$url['loc']}: " . $e->getMessage());
                }
            }
        }

        // Formato: - [Titolo](URL): Descrizione
        $line = '- [' . $title . '](' . $url['loc'] . ')';

        if (!empty($description)) {
            $line .= ': ' . $description;
        }

        return $line;
    }

    /**
     * Ottieni tutti gli URL organizzati per tipo
     */
    private function getUrlsOrganizedByType(int $projectId): array
    {
        $urls = Url::forProject($projectId, [
            'is_selected' => 1
        ]);

        $organized = [];
        foreach ($urls as $url) {
            $type = $url['type'] ?? 'OTHER';
            if (!isset($organized[$type])) {
                $organized[$type] = [];
            }
            $organized[$type][] = $url;
        }

        // Ordina URL all'interno di ogni tipo
        foreach ($organized as $type => &$typeUrls) {
            usort($typeUrls, function($a, $b) {
                // Ordina per titolo alfabeticamente
                $titleA = $a['title'] ?? $this->extractTitleFromUrl($a['loc']);
                $titleB = $b['title'] ?? $this->extractTitleFromUrl($b['loc']);
                return strcasecmp($titleA, $titleB);
            });
        }

        return $organized;
    }

    /**
     * Estrai un titolo dall'URL
     */
    private function extractTitleFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path || $path === '/') {
            return parse_url($url, PHP_URL_HOST) ?? 'Homepage';
        }

        $segments = explode('/', trim($path, '/'));
        $lastSegment = end($segments);

        if (empty($lastSegment)) {
            return 'Homepage';
        }

        // Rimuovi estensione
        $lastSegment = preg_replace('/\.[^.]+$/', '', $lastSegment);

        // Converti slug in titolo leggibile
        $title = str_replace(['-', '_'], ' ', $lastSegment);
        return ucwords($title);
    }

    /**
     * Genera il file llms-full.txt con tutto il contenuto espanso
     */
    public function generateFull(int $projectId): string
    {
        $project = Project::find($projectId);
        if (!$project) {
            throw new \Exception('Progetto non trovato');
        }

        $lines = [];

        // 1. H1 con il nome del progetto
        $lines[] = '# ' . $project['name'];
        $lines[] = '';

        // 2. Summary
        if (!empty($project['site_summary'])) {
            $lines[] = $project['site_summary'];
        } else {
            $lines[] = 'Website: ' . $project['domain'];
        }
        $lines[] = '';

        // Ottieni tutti gli URL organizzati per tipo
        $urlsByType = $this->getUrlsOrganizedByType($projectId);
        $sectionLabels = UrlClassifier::getSectionLabels();

        // Ordine delle sezioni
        $types = UrlClassifier::getTypes();
        usort($types, function($a, $b) {
            return UrlClassifier::getSectionPriority($a) - UrlClassifier::getSectionPriority($b);
        });

        // Genera sezioni con contenuto espanso
        foreach ($types as $type) {
            if (empty($urlsByType[$type])) {
                continue;
            }

            $sectionName = $sectionLabels[$type] ?? $type;
            $lines[] = '## ' . $sectionName;
            $lines[] = '';

            foreach ($urlsByType[$type] as $url) {
                $title = $url['title'] ?? $this->extractTitleFromUrl($url['loc']);
                $description = $url['short_description'] ?? '';

                $lines[] = '### ' . $title;
                $lines[] = '**URL:** ' . $url['loc'];
                $lines[] = '**Type:** ' . $type;

                if (!empty($description)) {
                    $lines[] = '**Description:** ' . $description;
                }

                $lines[] = '';
            }
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Salva il file generato su disco
     */
    public function saveToFile(int $projectId, string $baseDir, bool $fullVersion = false): string
    {
        $project = Project::find($projectId);
        if (!$project) {
            throw new \Exception('Progetto non trovato');
        }

        if ($fullVersion) {
            $content = $this->generateFull($projectId);
            $filename = 'llms-full_' . $project['slug'] . '.txt';
        } else {
            $content = $this->generate($projectId);
            $filename = 'llms_' . $project['slug'] . '.txt';
        }

        $dir = $baseDir . '/llms';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filepath = $dir . '/' . $filename;
        file_put_contents($filepath, $content);

        return $filepath;
    }
}
