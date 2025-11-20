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
                // Se l'AI non è configurata, restituisci null
                error_log("AI Service non disponibile: " . $e->getMessage());
                return null;
            }
        }
        return $this->aiService;
    }
    /**
     * Genera il contenuto del file llms.txt per un progetto
     *
     * @param int $projectId
     * @return string
     */
    public function generateForProject(int $projectId): string
    {
        return $this->generate($projectId);
    }

    /**
     * Genera il contenuto del file llms.txt per un progetto
     * Segue le specifiche ufficiali llms.txt
     *
     * @param int $projectId
     * @return string
     */
    public function generate(int $projectId): string
    {
        $project = Project::find($projectId);
        if (!$project) {
            throw new \Exception('Progetto non trovato');
        }

        $lines = [];

        // 1. H1 con il nome del progetto (OBBLIGATORIO)
        $lines[] = '# ' . $project['name'];
        $lines[] = '';

        // 2. Blockquote con summary (FORTEMENTE RACCOMANDATO)
        if (!empty($project['site_summary'])) {
            $lines[] = '> ' . $project['site_summary'];
        } else {
            // Se non c'è summary, creane uno di default
            $lines[] = '> Sito web di ' . $project['name'];
        }
        $lines[] = '';

        // 3. Dettagli aggiuntivi sul progetto (OPZIONALE)
        if (!empty($project['description'])) {
            $lines[] = $project['description'];
            $lines[] = '';
        }

        // Ottieni tutti gli URL selezionati organizzati per tipo
        $urlsByType = $this->getUrlsOrganizedByType($projectId);

        // 4. Sezioni principali organizzate per tipo
        // Ordine consigliato: HOMEPAGE, poi contenuti principali, poi policy/support
        $sectionOrder = [
            'HOMEPAGE' => 'Pagina Principale',
            'CATEGORY' => 'Categorie',
            'PRODUCT' => 'Prodotti e Servizi',
            'GUIDE' => 'Guide e Documentazione',
            'BLOG' => 'Blog e Articoli',
            'SUPPORT' => 'Supporto e Contatti',
            'POLICY' => 'Policy e Termini',
            'OTHER' => 'Altri Contenuti'
        ];

        $hasOptionalSection = false;

        foreach ($sectionOrder as $type => $sectionName) {
            if (empty($urlsByType[$type])) {
                continue;
            }

            // Le sezioni POLICY e SUPPORT vanno nella sezione Optional
            $isOptional = in_array($type, ['POLICY', 'SUPPORT', 'OTHER']);

            if ($isOptional && !$hasOptionalSection) {
                $lines[] = '## Optional';
                $lines[] = '';
                $hasOptionalSection = true;
            } elseif (!$isOptional) {
                $lines[] = '## ' . $sectionName;
                $lines[] = '';
            }

            // Aggiungi gli URL di questa sezione
            foreach ($urlsByType[$type] as $url) {
                $lines[] = $this->formatUrl($url);
            }
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Genera il contenuto di una sezione
     *
     * @param array $section
     * @param int $projectId
     * @return string
     */
    private function generateSection(array $section, int $projectId): string
    {
        // Ottieni gli URL per questa sezione
        if (isset($section['id'])) {
            $urls = SectionUrl::getUrlsForSection($section['id']);
        } else {
            // Sezione predefinita basata sul tipo
            $urls = $this->getUrlsByType($projectId, $section['type'] ?? null);
        }

        if (empty($urls)) {
            return '';
        }

        $lines = [];
        $lines[] = '## ' . $section['name'];
        $lines[] = '';

        foreach ($urls as $url) {
            $lines[] = $this->formatUrl($url);
        }

        return implode("\n", $lines);
    }

    /**
     * Formatta un singolo URL per il file llms.txt
     * Formato: - [Titolo](URL): Descrizione
     *
     * @param array $url
     * @return string
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

                    // Salva la descrizione generata nel database per usi futuri
                    if (!empty($description) && isset($url['id'])) {
                        Url::updateDescription($url['id'], $description);
                        error_log("Descrizione AI generata per URL ID {$url['id']}: " . substr($description, 0, 50) . "...");
                    }
                } catch (\Exception $e) {
                    // Se l'AI fallisce, continua senza descrizione
                    error_log("Errore generazione AI per URL {$url['loc']}: " . $e->getMessage());
                }
            }
        }

        // Formato corretto: - [Titolo](URL): Descrizione
        $line = '- [' . $title . '](' . $url['loc'] . ')';

        if (!empty($description)) {
            $line .= ': ' . $description;
        }

        return $line;
    }

    /**
     * Ottieni tutti gli URL organizzati per tipo
     *
     * @param int $projectId
     * @return array Array con chiavi = tipo, valori = array di URL
     */
    private function getUrlsOrganizedByType(int $projectId): array
    {
        // Ottieni tutti gli URL selezionati per il progetto
        $urls = Url::forProject($projectId, [
            'is_selected' => 1
        ]);

        // Organizza per tipo
        $organized = [];
        foreach ($urls as $url) {
            $type = $url['type'] ?? 'OTHER';
            if (!isset($organized[$type])) {
                $organized[$type] = [];
            }
            $organized[$type][] = $url;
        }

        // Ordina gli URL all'interno di ogni tipo per priorità/posizione
        foreach ($organized as $type => &$typeUrls) {
            // Homepage sempre per prima
            if ($type === 'HOMEPAGE') {
                usort($typeUrls, function($a, $b) {
                    // URL principale (/) viene sempre prima
                    $aPath = parse_url($a['loc'], PHP_URL_PATH) ?? '';
                    $bPath = parse_url($b['loc'], PHP_URL_PATH) ?? '';
                    if ($aPath === '/' || $aPath === '') return -1;
                    if ($bPath === '/' || $bPath === '') return 1;
                    return 0;
                });
            }
        }

        return $organized;
    }

    /**
     * Ottieni le sezioni predefinite se non configurate
     *
     * @param int $projectId
     * @return array
     */
    private function getDefaultSections(int $projectId): array
    {
        return [
            ['name' => 'Policy e Termini', 'type' => 'POLICY'],
            ['name' => 'Categorie', 'type' => 'CATEGORY'],
            ['name' => 'Guide e Tutorial', 'type' => 'GUIDE'],
            ['name' => 'Supporto', 'type' => 'SUPPORT'],
        ];
    }

    /**
     * Ottieni URL per tipo
     *
     * @param int $projectId
     * @param string|null $type
     * @return array
     */
    private function getUrlsByType(int $projectId, ?string $type): array
    {
        if (!$type) {
            return [];
        }

        return Url::forProject($projectId, [
            'type' => $type,
            'is_selected' => 1, // Solo selezionati
        ]);
    }

    /**
     * Ottieni URL non categorizzati (tipo OTHER) e selezionati
     *
     * @param int $projectId
     * @return array
     */
    private function getUncategorizedUrls(int $projectId): array
    {
        return Url::forProject($projectId, [
            'type' => 'OTHER',
            'is_selected' => 1,
        ]);
    }

    /**
     * Estrai un titolo dall'URL
     *
     * @param string $url
     * @return string
     */
    private function extractTitleFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path || $path === '/') {
            return 'Homepage';
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
     *
     * @param int $projectId
     * @return string
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

        // 2. Blockquote con summary
        if (!empty($project['site_summary'])) {
            $lines[] = '> ' . $project['site_summary'];
        } else {
            $lines[] = '> Sito web di ' . $project['name'];
        }
        $lines[] = '';

        // 3. Indice dei contenuti
        $lines[] = '## Indice dei Contenuti';
        $lines[] = '';

        // Ottieni tutti gli URL selezionati organizzati per tipo
        $urlsByType = $this->getUrlsOrganizedByType($projectId);

        // Crea l'indice
        $sectionOrder = [
            'HOMEPAGE' => 'Pagina Principale',
            'CATEGORY' => 'Categorie',
            'PRODUCT' => 'Prodotti e Servizi',
            'GUIDE' => 'Guide e Documentazione',
            'BLOG' => 'Blog e Articoli',
            'SUPPORT' => 'Supporto e Contatti',
            'POLICY' => 'Policy e Termini',
            'OTHER' => 'Altri Contenuti'
        ];

        foreach ($sectionOrder as $type => $sectionName) {
            if (!empty($urlsByType[$type])) {
                $lines[] = '### ' . $sectionName;
                foreach ($urlsByType[$type] as $url) {
                    $title = $url['title'] ?? $this->extractTitleFromUrl($url['loc']);
                    $lines[] = '- [' . $title . '](' . $url['loc'] . ')';
                }
                $lines[] = '';
            }
        }

        $lines[] = '---';
        $lines[] = '';

        // 4. Contenuto completo di ogni pagina
        $lines[] = '## Contenuto Completo';
        $lines[] = '';

        foreach ($sectionOrder as $type => $sectionName) {
            if (empty($urlsByType[$type])) {
                continue;
            }

            $lines[] = '### ' . $sectionName;
            $lines[] = '';

            foreach ($urlsByType[$type] as $url) {
                $title = $url['title'] ?? $this->extractTitleFromUrl($url['loc']);
                $description = $url['short_description'] ?? '';

                // Sezione per ogni URL
                $lines[] = '#### ' . $title;
                $lines[] = '**URL:** ' . $url['loc'];
                $lines[] = '**Tipo:** ' . $type;

                if (!empty($description)) {
                    $lines[] = '**Descrizione:** ' . $description;
                }

                // Qui potresti aggiungere il contenuto completo della pagina se disponibile
                if (!empty($url['content'])) {
                    $lines[] = '';
                    $lines[] = '**Contenuto:**';
                    $lines[] = $url['content'];
                }

                $lines[] = '';
                $lines[] = '---';
                $lines[] = '';
            }
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Salva il file generato su disco
     *
     * @param int $projectId
     * @param string $baseDir
     * @param bool $fullVersion Se true, genera llms-full.txt
     * @return string Il percorso del file salvato
     */
    public function saveToFile(int $projectId, string $baseDir, bool $fullVersion = false): string
    {
        $project = Project::find($projectId);
        if (!$project) {
            throw new \Exception('Progetto non trovato');
        }

        // Genera il contenuto appropriato
        if ($fullVersion) {
            $content = $this->generateFull($projectId);
            $filename = 'llms-full_' . $project['slug'] . '.txt';
        } else {
            $content = $this->generate($projectId);
            $filename = 'llms_' . $project['slug'] . '.txt';
        }

        // Crea la directory se non esiste
        $dir = $baseDir . '/llms';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Salva il file
        $filepath = $dir . '/' . $filename;
        file_put_contents($filepath, $content);

        return $filepath;
    }
}