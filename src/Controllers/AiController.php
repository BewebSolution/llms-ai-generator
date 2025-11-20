<?php

namespace LlmsApp\Controllers;

use LlmsApp\Services\AiDescriptionService;
use LlmsApp\Models\Url;
use RuntimeException;

class AiController
{
    public function generateDescription()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $title = trim($input['title'] ?? '');
        $url   = trim($input['url'] ?? '');
        $urlId = $input['urlId'] ?? null; // ID del record URL nel database
        $generateTitle = $input['generateTitle'] ?? false;
        $classifyType = $input['classifyType'] ?? false;

        if ($url === '') {
            http_response_code(400);
            echo json_encode([
                'error' => 'Missing url'
            ]);
            return;
        }

        try {
            $service = new AiDescriptionService();
            $result = [];

            // Genera la descrizione se il titolo è presente
            if (!empty($title)) {
                $result['description'] = $service->generateShortDescription($title, $url);
            }

            // Genera il titolo se richiesto
            if ($generateTitle) {
                $result['title'] = $service->generateTitle($url, $title);
            }

            // Classifica il tipo se richiesto
            if ($classifyType) {
                try {
                    $result['type'] = $service->classifyUrlType($url, $title);
                } catch (\Exception $e) {
                    // Se la classificazione fallisce, usa OTHER
                    error_log("Classificazione fallita: " . $e->getMessage());
                    $result['type'] = 'OTHER';
                }
            }

            // Se non è stato generato nulla, genera almeno la descrizione
            if (empty($result)) {
                $result['description'] = $service->generateShortDescription('Pagina', $url);
            }

            // SALVA AUTOMATICAMENTE NEL DATABASE
            if ($urlId && (!empty($result['title']) || !empty($result['description']) || !empty($result['type']))) {
                try {
                    $updateData = [];

                    if (!empty($result['title'])) {
                        $updateData['title'] = $result['title'];
                    }

                    if (!empty($result['description'])) {
                        $updateData['short_description'] = $result['description'];
                    }

                    if (!empty($result['type'])) {
                        $updateData['type'] = $result['type'];
                    }

                    // Aggiorna il record nel database
                    Url::updateById($urlId, $updateData);
                    $result['saved'] = true;

                    error_log("AI results saved to DB for URL ID: $urlId");
                } catch (\Exception $e) {
                    error_log("Errore salvataggio risultati AI: " . $e->getMessage());
                    $result['saved'] = false;
                }
            }

            echo json_encode($result);
        } catch (RuntimeException $e) {
            http_response_code(503);
            echo json_encode([
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Unexpected error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Estrae meta tags dalla pagina senza generazione AI
     * L'AI viene usata solo per classificare il tipo
     */
    public function extractMetaTags()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $url = trim($input['url'] ?? '');
        $urlId = $input['urlId'] ?? null;
        $classifyType = $input['classifyType'] ?? true; // Di default classifica il tipo

        if ($url === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing url']);
            return;
        }

        try {
            $result = [];

            // Usa WebScraperService per estrarre meta tags
            $scraper = new \LlmsApp\Services\WebScraperService();
            $metaData = $scraper->scrapeMetaOnly($url); // Metodo leggero

            if ($metaData) {
                $result['title'] = $metaData['title'] ?? '';
                $result['description'] = $metaData['meta_description'] ?? '';
                $result['extracted'] = true;
            }

            // Se richiesto, classifica SOLO il tipo con AI
            if ($classifyType && !empty($result['title'])) {
                try {
                    $aiService = new \LlmsApp\Services\AiDescriptionService();
                    $result['type'] = $aiService->classifyUrlType($url, $result['title']);
                } catch (\Exception $e) {
                    error_log("Classificazione tipo fallita: " . $e->getMessage());
                    $result['type'] = 'OTHER';
                }
            }

            // SALVA AUTOMATICAMENTE NEL DATABASE
            if ($urlId && (!empty($result['title']) || !empty($result['description']) || !empty($result['type']))) {
                try {
                    $updateData = [];
                    if (!empty($result['title'])) {
                        $updateData['title'] = $result['title'];
                    }
                    if (!empty($result['description'])) {
                        $updateData['short_description'] = $result['description'];
                    }
                    if (!empty($result['type'])) {
                        $updateData['type'] = $result['type'];
                    }

                    Url::updateById($urlId, $updateData);
                    $result['saved'] = true;

                    error_log("Meta tags extracted and saved for URL ID: $urlId");
                } catch (\Exception $e) {
                    error_log("Errore salvataggio meta tags: " . $e->getMessage());
                    $result['saved'] = false;
                }
            }

            echo json_encode($result);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}