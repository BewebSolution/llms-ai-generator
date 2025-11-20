<?php

namespace LlmsApp\Services;

use GuzzleHttp\Client;
use RuntimeException;
use LlmsApp\Models\AiUsage;

class AiDescriptionService
{
    private Client $client;
    private bool $enabled;
    private string $apiKey;
    private string $model;
    private float $temperature;

    public function __construct()
    {
        // Leggi DIRETTAMENTE dal database per evitare problemi di cache
        $pdo = \LlmsApp\Config\Database::getConnection();

        // OpenAI enabled
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'openai_enabled'");
        $stmt->execute();
        $enabledValue = $stmt->fetch()['setting_value'] ?? 'false';
        $this->enabled = ($enabledValue === 'true' || $enabledValue === '1');

        // API Key
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'openai_api_key'");
        $stmt->execute();
        $this->apiKey = $stmt->fetch()['setting_value'] ?? '';

        // Model
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'openai_model'");
        $stmt->execute();
        $this->model = $stmt->fetch()['setting_value'] ?? 'gpt-3.5-turbo';

        // Temperature
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'openai_temperature'");
        $stmt->execute();
        $tempValue = $stmt->fetch()['setting_value'] ?? '0.7';
        $this->temperature = (float)$tempValue;

        $this->client = new Client([
            'timeout' => 20,
        ]);
    }

    public function generateShortDescription(string $title, string $url): string
    {
        // Debug
        error_log("AiDescriptionService - Enabled: " . ($this->enabled ? 'true' : 'false'));
        error_log("AiDescriptionService - API Key: " . (!empty($this->apiKey) ? substr($this->apiKey, 0, 10) . '...' : 'empty'));

        if (!$this->enabled) {
            throw new RuntimeException('OpenAI service is disabled (enabled=' . ($this->enabled ? 'true' : 'false') . ')');
        }

        if (empty($this->apiKey)) {
            throw new RuntimeException('OpenAI API key not configured');
        }

        $prompt = $this->buildPrompt($title, $url);

        try {
            $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'  => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Sei un assistente che genera descrizioni brevi e concise per pagine web in italiano.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => 60,
                    'temperature' => $this->temperature,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException('OpenAI API error: HTTP ' . $response->getStatusCode());
            }

            $body = json_decode((string)$response->getBody(), true);

            // Traccia l'uso per i costi
            if (isset($body['usage'])) {
                AiUsage::log([
                    'operation_type' => 'description',
                    'model' => $this->model,
                    'input_tokens' => $body['usage']['prompt_tokens'] ?? 0,
                    'output_tokens' => $body['usage']['completion_tokens'] ?? 0,
                    'success' => 1,
                ]);
            }

            // Estrai il testo dalla risposta di OpenAI
            $text = $body['choices'][0]['message']['content'] ?? '';

            return trim($this->sanitizeOutput($text));
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $body = json_decode($response->getBody(), true);
            $errorMessage = $body['error']['message'] ?? 'Unknown error';
            throw new RuntimeException('OpenAI API error: ' . $errorMessage);
        } catch (\Exception $e) {
            throw new RuntimeException('AI service error: ' . $e->getMessage());
        }
    }

    private function buildPrompt(string $title, string $url): string
    {
        return sprintf(
            "Genera una singola frase breve (massimo 150 caratteri) in italiano che descrive questa pagina web per un file llms.txt.\n".
            "La frase deve essere descrittiva e professionale, senza tono promozionale.\n\n".
            "Titolo pagina: %s\nURL: %s\n\n".
            "Rispondi solo con la descrizione, senza prefissi o spiegazioni.",
            $title,
            $url
        );
    }

    private function sanitizeOutput(string $text): string
    {
        // Rimuove eventuali ritorni a capo e doppi spazi
        $text = preg_replace('/\s+/', ' ', $text);
        // Rimuove virgolette di troppo
        $text = trim($text, "\"' \t\n\r\0\x0B");
        // Limita a 150 caratteri
        if (strlen($text) > 150) {
            $text = substr($text, 0, 147) . '...';
        }
        return $text;
    }

    /**
     * Genera un titolo ottimizzato per la pagina
     */
    public function generateTitle(string $url, string $currentTitle = ''): string
    {
        // Debug
        error_log("AiDescriptionService::generateTitle - Enabled: " . ($this->enabled ? 'true' : 'false'));

        if (!$this->enabled) {
            throw new RuntimeException('OpenAI service is disabled');
        }

        if (empty($this->apiKey)) {
            throw new RuntimeException('OpenAI API key not configured');
        }

        $prompt = $this->buildTitlePrompt($url, $currentTitle);

        try {
            $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'  => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Sei un assistente che genera titoli brevi e descrittivi per pagine web in italiano. I titoli devono essere chiari, professionali e ottimizzati per SEO.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => 60, // Aumentato da 30 per titoli più lunghi
                    'temperature' => $this->temperature,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException('OpenAI API error: HTTP ' . $response->getStatusCode());
            }

            $body = json_decode((string)$response->getBody(), true);

            // Estrai il testo dalla risposta di OpenAI
            $text = $body['choices'][0]['message']['content'] ?? '';

            return trim($this->sanitizeTitleOutput($text));
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $body = json_decode($response->getBody(), true);
            $errorMessage = $body['error']['message'] ?? 'Unknown error';
            throw new RuntimeException('OpenAI API error: ' . $errorMessage);
        } catch (\Exception $e) {
            throw new RuntimeException('AI service error: ' . $e->getMessage());
        }
    }

    private function buildTitlePrompt(string $url, string $currentTitle): string
    {
        $prompt = "Genera un titolo descrittivo e completo (massimo 150 caratteri) in italiano per questa pagina web.\n";

        if (!empty($currentTitle)) {
            $prompt .= "Titolo attuale: $currentTitle\n";
        }

        $prompt .= "URL: $url\n\n";
        $prompt .= "Il titolo deve essere:\n";
        $prompt .= "- Descrittivo e COMPLETO del contenuto della pagina\n";
        $prompt .= "- Professionale e chiaro\n";
        $prompt .= "- Ottimizzato per SEO\n";
        $prompt .= "- In italiano\n";
        $prompt .= "- NON troncato o abbreviato\n\n";
        $prompt .= "Rispondi solo con il titolo completo, senza prefissi o spiegazioni.";

        return $prompt;
    }

    private function sanitizeTitleOutput(string $text): string
    {
        // Rimuove eventuali ritorni a capo e doppi spazi
        $text = preg_replace('/\s+/', ' ', $text);
        // Rimuove virgolette di troppo
        $text = trim($text, "\"' \t\n\r\0\x0B");
        // Aumentiamo il limite a 200 caratteri per i titoli (era 60)
        // La colonna nel DB supporta fino a 255 caratteri
        if (strlen($text) > 200) {
            $text = substr($text, 0, 197) . '...';
        }
        return $text;
    }

    /**
     * Classifica il tipo di URL basandosi sul contenuto
     */
    public function classifyUrlType(string $url, string $title = ''): string
    {
        // Debug
        error_log("AiDescriptionService::classifyUrlType - URL: $url");

        if (!$this->enabled) {
            throw new RuntimeException('OpenAI service is disabled');
        }

        if (empty($this->apiKey)) {
            throw new RuntimeException('OpenAI API key not configured');
        }

        $prompt = $this->buildClassificationPrompt($url, $title);

        try {
            $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'  => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Sei un assistente che classifica le pagine web in categorie predefinite. Rispondi SOLO con una delle categorie indicate, senza spiegazioni aggiuntive.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => 10,
                    'temperature' => 0.3, // Bassa temperatura per risultati più consistenti
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException('OpenAI API error: HTTP ' . $response->getStatusCode());
            }

            $body = json_decode((string)$response->getBody(), true);

            // Estrai il tipo dalla risposta
            $type = trim($body['choices'][0]['message']['content'] ?? '');

            // Valida che sia uno dei tipi consentiti
            $validTypes = ['HOMEPAGE', 'CATEGORY', 'PRODUCT', 'GUIDE', 'POLICY', 'SUPPORT', 'BLOG', 'OTHER'];

            $type = strtoupper($type);
            if (!in_array($type, $validTypes)) {
                error_log("Tipo non valido ricevuto dall'AI: $type, usando OTHER");
                return 'OTHER';
            }

            return $type;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $body = json_decode($response->getBody(), true);
            $errorMessage = $body['error']['message'] ?? 'Unknown error';
            throw new RuntimeException('OpenAI API error: ' . $errorMessage);
        } catch (\Exception $e) {
            throw new RuntimeException('AI service error: ' . $e->getMessage());
        }
    }

    private function buildClassificationPrompt(string $url, string $title): string
    {
        $prompt = "Classifica questa pagina web in UNA delle seguenti categorie:\n\n";
        $prompt .= "- HOMEPAGE (pagina principale del sito)\n";
        $prompt .= "- CATEGORY (categoria di prodotti o servizi)\n";
        $prompt .= "- PRODUCT (pagina di prodotto o servizio specifico)\n";
        $prompt .= "- GUIDE (guide, tutorial, how-to)\n";
        $prompt .= "- POLICY (termini, privacy, cookie, policy)\n";
        $prompt .= "- SUPPORT (supporto, FAQ, contatti, assistenza)\n";
        $prompt .= "- BLOG (articoli blog, news, aggiornamenti)\n";
        $prompt .= "- OTHER (altro)\n\n";

        $prompt .= "URL: $url\n";
        if (!empty($title)) {
            $prompt .= "Titolo: $title\n";
        }

        // Analizza l'URL per dare indizi
        $urlLower = strtolower($url);
        $prompt .= "\nSuggerimenti basati sull'URL:\n";

        if (strpos($urlLower, 'privacy') !== false || strpos($urlLower, 'terms') !== false ||
            strpos($urlLower, 'cookie') !== false || strpos($urlLower, 'policy') !== false) {
            $prompt .= "- L'URL contiene parole relative a policy\n";
        }
        if (strpos($urlLower, 'category') !== false || strpos($urlLower, 'categor') !== false) {
            $prompt .= "- L'URL contiene la parola category\n";
        }
        if (strpos($urlLower, 'product') !== false || strpos($urlLower, 'prodott') !== false) {
            $prompt .= "- L'URL contiene la parola product\n";
        }
        if (strpos($urlLower, 'guide') !== false || strpos($urlLower, 'tutorial') !== false ||
            strpos($urlLower, 'how-to') !== false) {
            $prompt .= "- L'URL contiene parole relative a guide\n";
        }
        if (strpos($urlLower, 'support') !== false || strpos($urlLower, 'faq') !== false ||
            strpos($urlLower, 'contact') !== false || strpos($urlLower, 'contatt') !== false) {
            $prompt .= "- L'URL contiene parole relative a supporto\n";
        }
        if (strpos($urlLower, 'blog') !== false || strpos($urlLower, 'news') !== false ||
            strpos($urlLower, 'article') !== false) {
            $prompt .= "- L'URL contiene parole relative a blog\n";
        }
        if (parse_url($url, PHP_URL_PATH) === '/' || parse_url($url, PHP_URL_PATH) === '') {
            $prompt .= "- Sembra essere la homepage (path = /)\n";
        }

        $prompt .= "\nRispondi con UNA SOLA PAROLA tra quelle indicate sopra.";

        return $prompt;
    }

    /**
     * Genera una descrizione usando il contenuto reale della pagina
     */
    public function generateShortDescriptionWithContent(string $title, string $url, ?string $content = null, ?string $metaDescription = null): string
    {
        if (!$this->enabled) {
            throw new RuntimeException('OpenAI service is disabled');
        }

        if (empty($this->apiKey)) {
            throw new RuntimeException('OpenAI API key not configured');
        }

        $prompt = $this->buildDescriptionPromptWithContent($title, $url, $content, $metaDescription);

        try {
            $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'  => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Sei un assistente che genera descrizioni brevi, accurate e pertinenti per pagine web in italiano basandoti sul contenuto reale della pagina.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => 60,
                    'temperature' => $this->temperature,
                ],
            ]);

            $body = json_decode((string)$response->getBody(), true);

            // Traccia l'uso per i costi
            if (isset($body['usage'])) {
                AiUsage::log([
                    'operation_type' => 'description_with_content',
                    'model' => $this->model,
                    'input_tokens' => $body['usage']['prompt_tokens'] ?? 0,
                    'output_tokens' => $body['usage']['completion_tokens'] ?? 0,
                    'success' => 1,
                ]);
            }

            $text = $body['choices'][0]['message']['content'] ?? '';
            return trim($this->sanitizeOutput($text));

        } catch (\Exception $e) {
            throw new RuntimeException('AI service error: ' . $e->getMessage());
        }
    }

    /**
     * Genera un titolo usando il contenuto reale della pagina
     */
    public function generateTitleWithContent(string $url, ?string $currentTitle = null, ?string $content = null): string
    {
        if (!$this->enabled) {
            throw new RuntimeException('OpenAI service is disabled');
        }

        if (empty($this->apiKey)) {
            throw new RuntimeException('OpenAI API key not configured');
        }

        $prompt = $this->buildTitlePromptWithContent($url, $currentTitle, $content);

        try {
            $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'  => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Sei un assistente che genera titoli accurati e descrittivi per pagine web in italiano basandoti sul contenuto reale.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => 60,
                    'temperature' => $this->temperature,
                ],
            ]);

            $body = json_decode((string)$response->getBody(), true);

            // Traccia l'uso
            if (isset($body['usage'])) {
                AiUsage::log([
                    'operation_type' => 'title_with_content',
                    'model' => $this->model,
                    'input_tokens' => $body['usage']['prompt_tokens'] ?? 0,
                    'output_tokens' => $body['usage']['completion_tokens'] ?? 0,
                    'success' => 1,
                ]);
            }

            $text = $body['choices'][0]['message']['content'] ?? '';
            return trim($this->sanitizeTitleOutput($text));

        } catch (\Exception $e) {
            throw new RuntimeException('AI service error: ' . $e->getMessage());
        }
    }

    /**
     * Classifica il tipo di URL usando il contenuto reale
     */
    public function classifyUrlTypeWithContent(string $url, ?string $title = null, ?string $content = null): string
    {
        if (!$this->enabled) {
            throw new RuntimeException('OpenAI service is disabled');
        }

        if (empty($this->apiKey)) {
            throw new RuntimeException('OpenAI API key not configured');
        }

        $prompt = $this->buildClassificationPromptWithContent($url, $title, $content);

        try {
            $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'  => $this->model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Sei un assistente che classifica pagine web in categorie predefinite basandoti sul contenuto reale. Rispondi SOLO con la categoria.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => 10,
                    'temperature' => 0.3,
                ],
            ]);

            $body = json_decode((string)$response->getBody(), true);
            $type = strtoupper(trim($body['choices'][0]['message']['content'] ?? ''));

            $validTypes = ['HOMEPAGE', 'CATEGORY', 'PRODUCT', 'GUIDE', 'POLICY', 'SUPPORT', 'BLOG', 'OTHER'];
            if (!in_array($type, $validTypes)) {
                return 'OTHER';
            }

            return $type;

        } catch (\Exception $e) {
            throw new RuntimeException('AI service error: ' . $e->getMessage());
        }
    }

    /**
     * Costruisce prompt per descrizione con contenuto reale
     */
    private function buildDescriptionPromptWithContent(string $title, string $url, ?string $content, ?string $metaDescription): string
    {
        $prompt = "Genera una descrizione breve e accurata (massimo 150 caratteri) in italiano per questa pagina web.\n\n";

        $prompt .= "URL: $url\n";
        $prompt .= "Titolo: $title\n";

        if (!empty($metaDescription)) {
            $prompt .= "Meta description esistente: $metaDescription\n";
        }

        if (!empty($content)) {
            // Limita il contenuto a 1000 caratteri per non superare i limiti dei token
            $contentPreview = substr($content, 0, 1000);
            $prompt .= "\nContenuto della pagina (anteprima):\n$contentPreview\n";
        }

        $prompt .= "\nLa descrizione deve essere:\n";
        $prompt .= "- Accurata e basata sul contenuto reale\n";
        $prompt .= "- Professionale e informativa\n";
        $prompt .= "- Specifica per questa pagina\n";
        $prompt .= "- In italiano corretto\n";
        $prompt .= "- NON generica o vaga\n\n";
        $prompt .= "Rispondi solo con la descrizione, senza prefissi.";

        return $prompt;
    }

    /**
     * Costruisce prompt per titolo con contenuto reale
     */
    private function buildTitlePromptWithContent(string $url, ?string $currentTitle, ?string $content): string
    {
        $prompt = "Genera un titolo descrittivo e completo (massimo 150 caratteri) in italiano per questa pagina web.\n\n";

        $prompt .= "URL: $url\n";

        if (!empty($currentTitle)) {
            $prompt .= "Titolo attuale: $currentTitle\n";
        }

        if (!empty($content)) {
            // Estrai i primi 800 caratteri del contenuto
            $contentPreview = substr($content, 0, 800);
            $prompt .= "\nContenuto della pagina (anteprima):\n$contentPreview\n";
        }

        $prompt .= "\nIl titolo deve essere:\n";
        $prompt .= "- Descrittivo del contenuto reale della pagina\n";
        $prompt .= "- Professionale e chiaro\n";
        $prompt .= "- Ottimizzato per SEO\n";
        $prompt .= "- In italiano\n";
        $prompt .= "- Specifico, non generico\n\n";
        $prompt .= "Rispondi solo con il titolo.";

        return $prompt;
    }

    /**
     * Costruisce prompt per classificazione con contenuto reale
     */
    private function buildClassificationPromptWithContent(string $url, ?string $title, ?string $content): string
    {
        $prompt = "Classifica questa pagina web in UNA delle seguenti categorie:\n\n";
        $prompt .= "- HOMEPAGE (pagina principale del sito)\n";
        $prompt .= "- CATEGORY (categoria di prodotti o servizi)\n";
        $prompt .= "- PRODUCT (pagina di prodotto o servizio specifico)\n";
        $prompt .= "- GUIDE (guide, tutorial, how-to)\n";
        $prompt .= "- POLICY (termini, privacy, cookie, policy)\n";
        $prompt .= "- SUPPORT (supporto, FAQ, contatti, assistenza)\n";
        $prompt .= "- BLOG (articoli blog, news, aggiornamenti)\n";
        $prompt .= "- OTHER (altro)\n\n";

        $prompt .= "URL: $url\n";

        if (!empty($title)) {
            $prompt .= "Titolo: $title\n";
        }

        if (!empty($content)) {
            // Usa i primi 600 caratteri per la classificazione
            $contentPreview = substr($content, 0, 600);
            $prompt .= "\nContenuto (anteprima):\n$contentPreview\n";
        }

        $prompt .= "\nRispondi con UNA SOLA PAROLA tra quelle indicate.";

        return $prompt;
    }
}