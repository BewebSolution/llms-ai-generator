<?php

namespace LlmsApp\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class WebScraperService
{
    private Client $client;
    private int $timeout;
    private int $maxContentLength;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 10, // 10 secondi timeout
            'verify' => false, // Disabilita verifica SSL per siti con certificati problematici
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; LLMS.txt Generator/1.0; +https://github.com)'
            ]
        ]);

        $this->timeout = 10;
        $this->maxContentLength = 50000; // Max 50KB di contenuto testuale
    }

    /**
     * Fa scraping leggero di una URL per estrarre informazioni chiave
     */
    public function scrapeUrl(string $url): ?array
    {
        try {
            // Fai richiesta GET alla pagina
            $response = $this->client->get($url, [
                'timeout' => $this->timeout,
                'allow_redirects' => true,
                'http_errors' => false
            ]);

            // Verifica status code
            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                error_log("Scraping failed for $url: HTTP $statusCode");
                return null;
            }

            // Ottieni il contenuto HTML
            $html = (string) $response->getBody();

            // Se il contenuto è troppo grande, prendine solo una parte
            if (strlen($html) > 200000) { // 200KB limit for HTML
                $html = substr($html, 0, 200000);
            }

            // Estrai informazioni dalla pagina
            return $this->extractPageInfo($html, $url);

        } catch (RequestException $e) {
            error_log("Scraping error for $url: " . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            error_log("Unexpected scraping error for $url: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Estrae informazioni chiave dall'HTML
     */
    private function extractPageInfo(string $html, string $url): array
    {
        $result = [
            'title' => '',
            'meta_description' => '',
            'meta_keywords' => '',
            'h1' => [],
            'h2' => [],
            'content' => '',
            'og_title' => '',
            'og_description' => '',
            'og_image' => ''
        ];

        // Usa DOMDocument per parsare HTML
        $dom = new \DOMDocument();

        // Sopprimi warning per HTML malformato
        libxml_use_internal_errors(true);

        // Carica HTML con encoding UTF-8
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Estrai TITLE
        $titleNodes = $xpath->query('//title');
        if ($titleNodes->length > 0) {
            $result['title'] = trim($titleNodes->item(0)->textContent);
        }

        // Estrai META DESCRIPTION
        $metaDesc = $xpath->query('//meta[@name="description"]/@content');
        if ($metaDesc->length > 0) {
            $result['meta_description'] = trim($metaDesc->item(0)->nodeValue);
        }

        // Estrai META KEYWORDS
        $metaKeywords = $xpath->query('//meta[@name="keywords"]/@content');
        if ($metaKeywords->length > 0) {
            $result['meta_keywords'] = trim($metaKeywords->item(0)->nodeValue);
        }

        // Estrai Open Graph tags
        $ogTitle = $xpath->query('//meta[@property="og:title"]/@content');
        if ($ogTitle->length > 0) {
            $result['og_title'] = trim($ogTitle->item(0)->nodeValue);
        }

        $ogDesc = $xpath->query('//meta[@property="og:description"]/@content');
        if ($ogDesc->length > 0) {
            $result['og_description'] = trim($ogDesc->item(0)->nodeValue);
        }

        $ogImage = $xpath->query('//meta[@property="og:image"]/@content');
        if ($ogImage->length > 0) {
            $result['og_image'] = trim($ogImage->item(0)->nodeValue);
        }

        // Estrai H1
        $h1Nodes = $xpath->query('//h1');
        foreach ($h1Nodes as $h1) {
            $text = trim($h1->textContent);
            if (!empty($text) && strlen($text) < 200) {
                $result['h1'][] = $text;
            }
        }

        // Estrai H2 (massimo 5)
        $h2Nodes = $xpath->query('//h2');
        $h2Count = 0;
        foreach ($h2Nodes as $h2) {
            if ($h2Count >= 5) break;
            $text = trim($h2->textContent);
            if (!empty($text) && strlen($text) < 200) {
                $result['h2'][] = $text;
                $h2Count++;
            }
        }

        // Estrai contenuto testuale principale
        $result['content'] = $this->extractMainContent($dom, $xpath);

        // Se non c'è title, usa og:title o h1
        if (empty($result['title'])) {
            if (!empty($result['og_title'])) {
                $result['title'] = $result['og_title'];
            } elseif (!empty($result['h1'][0])) {
                $result['title'] = $result['h1'][0];
            }
        }

        // Se non c'è meta description, usa og:description
        if (empty($result['meta_description']) && !empty($result['og_description'])) {
            $result['meta_description'] = $result['og_description'];
        }

        return $result;
    }

    /**
     * Estrae il contenuto testuale principale dalla pagina
     */
    private function extractMainContent(\DOMDocument $dom, \DOMXPath $xpath): string
    {
        // Rimuovi script, style, e altri elementi non testuali
        $elementsToRemove = $xpath->query('//script | //style | //noscript | //iframe | //object | //embed | //applet');
        foreach ($elementsToRemove as $element) {
            $element->parentNode->removeChild($element);
        }

        // Cerca contenuto principale in ordine di priorità
        $contentSelectors = [
            '//main',
            '//article',
            '//*[@id="content"]',
            '//*[@id="main"]',
            '//*[@class="content"]',
            '//*[@class="main"]',
            '//*[@role="main"]',
            '//div[@class="container"]',
            '//body'
        ];

        $mainContent = '';

        foreach ($contentSelectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $mainContent = $this->getTextContent($nodes->item(0));
                if (strlen($mainContent) > 100) { // Se troviamo contenuto significativo
                    break;
                }
            }
        }

        // Se ancora non abbiamo contenuto, prendi tutto il body
        if (strlen($mainContent) < 100) {
            $bodyNodes = $xpath->query('//body');
            if ($bodyNodes->length > 0) {
                $mainContent = $this->getTextContent($bodyNodes->item(0));
            }
        }

        // Pulisci e tronca il contenuto
        $mainContent = preg_replace('/\s+/', ' ', $mainContent); // Normalizza spazi
        $mainContent = trim($mainContent);

        // Limita lunghezza
        if (strlen($mainContent) > $this->maxContentLength) {
            $mainContent = substr($mainContent, 0, $this->maxContentLength) . '...';
        }

        return $mainContent;
    }

    /**
     * Estrae solo il testo da un nodo DOM
     */
    private function getTextContent(\DOMNode $node): string
    {
        $text = '';

        if ($node->nodeType === XML_TEXT_NODE) {
            $text = $node->nodeValue;
        } else {
            foreach ($node->childNodes as $child) {
                $text .= $this->getTextContent($child) . ' ';
            }
        }

        return trim($text);
    }

    /**
     * Verifica se una URL è raggiungibile
     */
    public function isUrlAccessible(string $url): bool
    {
        try {
            $response = $this->client->head($url, [
                'timeout' => 5,
                'allow_redirects' => true,
                'http_errors' => false
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Estrae SOLO meta tags (versione leggera, no contenuto)
     */
    public function scrapeMetaOnly(string $url): ?array
    {
        try {
            // Configura client con timeout ridotto
            $client = new \GuzzleHttp\Client([
                'timeout' => 5, // Solo 5 secondi
                'verify' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; LLMS.txt Generator/1.0)',
                    'Range' => 'bytes=0-50000' // Scarica solo primi 50KB
                ]
            ]);

            $response = $client->get($url);

            if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 206) {
                return null;
            }

            // Prendi solo i primi 50KB di HTML
            $html = substr((string) $response->getBody(), 0, 50000);

            // Estrai solo head section
            if (preg_match('/<head[^>]*>(.*?)<\/head>/si', $html, $headMatch)) {
                $headHtml = $headMatch[1];
            } else {
                $headHtml = $html; // Fallback
            }

            $result = [
                'title' => '',
                'meta_description' => '',
                'og_title' => '',
                'og_description' => ''
            ];

            // Estrai TITLE
            if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $headHtml, $titleMatch)) {
                $result['title'] = html_entity_decode(trim(strip_tags($titleMatch[1])), ENT_QUOTES, 'UTF-8');
            }

            // Estrai META DESCRIPTION (gestisce entrambi gli ordini di attributi)
            if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/si', $headHtml, $descMatch)) {
                $result['meta_description'] = html_entity_decode(trim($descMatch[1]), ENT_QUOTES, 'UTF-8');
            } elseif (preg_match('/<meta\s+content=["\'](.*?)["\']\s+name=["\']description["\']/si', $headHtml, $descMatch)) {
                $result['meta_description'] = html_entity_decode(trim($descMatch[1]), ENT_QUOTES, 'UTF-8');
            }

            // Estrai Open Graph (opzionale)
            if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/si', $headHtml, $ogTitleMatch)) {
                $result['og_title'] = html_entity_decode(trim($ogTitleMatch[1]), ENT_QUOTES, 'UTF-8');
            } elseif (preg_match('/<meta\s+content=["\'](.*?)["\']\s+property=["\']og:title["\']/si', $headHtml, $ogTitleMatch)) {
                $result['og_title'] = html_entity_decode(trim($ogTitleMatch[1]), ENT_QUOTES, 'UTF-8');
            }

            if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/si', $headHtml, $ogDescMatch)) {
                $result['og_description'] = html_entity_decode(trim($ogDescMatch[1]), ENT_QUOTES, 'UTF-8');
            } elseif (preg_match('/<meta\s+content=["\'](.*?)["\']\s+property=["\']og:description["\']/si', $headHtml, $ogDescMatch)) {
                $result['og_description'] = html_entity_decode(trim($ogDescMatch[1]), ENT_QUOTES, 'UTF-8');
            }

            // Usa OG come fallback se non ci sono meta standard
            if (empty($result['title']) && !empty($result['og_title'])) {
                $result['title'] = $result['og_title'];
            }

            if (empty($result['meta_description']) && !empty($result['og_description'])) {
                $result['meta_description'] = $result['og_description'];
            }

            return $result;

        } catch (\Exception $e) {
            error_log("Errore scraping meta per $url: " . $e->getMessage());
            return null;
        }
    }
}