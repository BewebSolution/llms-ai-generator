<?php

namespace LlmsApp\Services;

use GuzzleHttp\Client;
use SimpleXMLElement;
use Exception;

class SitemapParserService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; llms-app/1.0)'
            ]
        ]);
    }

    /**
     * Effettua il parsing di una sitemap XML e restituisce un array di URL
     *
     * @param string $sitemapUrl URL della sitemap
     * @return array Array di URL con loc e lastmod
     * @throws Exception
     */
    public function parse(string $sitemapUrl): array
    {
        try {
            // Scarica la sitemap
            $response = $this->client->get($sitemapUrl);

            if ($response->getStatusCode() !== 200) {
                throw new Exception('Errore nel download della sitemap: HTTP ' . $response->getStatusCode());
            }

            $xmlContent = (string) $response->getBody();

            // Parsa il contenuto XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent);

            if ($xml === false) {
                $errors = libxml_get_errors();
                $errorMessage = 'Errore nel parsing XML: ';
                foreach ($errors as $error) {
                    $errorMessage .= $error->message . ' ';
                }
                throw new Exception($errorMessage);
            }

            // Determina il tipo di sitemap (normale o index)
            $urls = [];

            // Controlla se è una sitemap index
            if ($xml->getName() === 'sitemapindex') {
                // Parsa ogni sitemap nell'index
                foreach ($xml->sitemap as $sitemapEntry) {
                    $subSitemapUrl = (string) $sitemapEntry->loc;
                    if (!empty($subSitemapUrl)) {
                        // Ricorsivamente parsa la sub-sitemap
                        try {
                            $subUrls = $this->parse($subSitemapUrl);
                            $urls = array_merge($urls, $subUrls);
                        } catch (Exception $e) {
                            // Log errore ma continua con le altre sitemap
                            error_log('Errore parsing sub-sitemap ' . $subSitemapUrl . ': ' . $e->getMessage());
                        }
                    }
                }
            } else {
                // Sitemap normale con URL
                foreach ($xml->url as $urlEntry) {
                    $loc = (string) $urlEntry->loc;

                    if (empty($loc)) {
                        continue;
                    }

                    $urlData = [
                        'loc' => $loc,
                        'lastmod' => null,
                        'changefreq' => null,
                        'priority' => null
                    ];

                    // Estrai lastmod se presente
                    if (isset($urlEntry->lastmod)) {
                        $lastmod = (string) $urlEntry->lastmod;
                        if (!empty($lastmod)) {
                            // Converti in formato MySQL datetime
                            try {
                                $date = new \DateTime($lastmod);
                                $urlData['lastmod'] = $date->format('Y-m-d H:i:s');
                            } catch (Exception $e) {
                                // Ignora date non valide
                            }
                        }
                    }

                    // Estrai altri campi opzionali
                    if (isset($urlEntry->changefreq)) {
                        $urlData['changefreq'] = (string) $urlEntry->changefreq;
                    }
                    if (isset($urlEntry->priority)) {
                        $urlData['priority'] = (float) $urlEntry->priority;
                    }

                    $urls[] = $urlData;
                }
            }

            return $urls;
        } catch (Exception $e) {
            throw new Exception('Errore durante il parsing della sitemap: ' . $e->getMessage());
        }
    }

    /**
     * Verifica se una URL è valida e raggiungibile
     *
     * @param string $url
     * @return bool
     */
    public function validateUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        try {
            $response = $this->client->head($url, [
                'http_errors' => false,
                'timeout' => 5
            ]);

            return $response->getStatusCode() < 400;
        } catch (Exception $e) {
            return false;
        }
    }
}