<?php

namespace LlmsApp\Services;

use GuzzleHttp\Client;
use LlmsApp\Models\Url;
use LlmsApp\Models\Sitemap;

class SitemapParser
{
    private Client $client;
    private UrlClassifier $classifier;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 15,
        ]);
        $this->classifier = new UrlClassifier();
    }

    public function parseSitemap(int $sitemapId, int $projectId, string $sitemapUrl): void
    {
        $xmlContent = $this->fetchSitemap($sitemapUrl);

        if ($xmlContent === null) {
            return;
        }

        $xml = @simplexml_load_string($xmlContent);
        if ($xml === false) {
            return;
        }

        $this->processXmlNode($xml, $projectId);

        Sitemap::updateLastParsed($sitemapId);
    }

    private function fetchSitemap(string $url): ?string
    {
        if (preg_match('#^https?://#i', $url)) {
            try {
                $response = $this->client->get($url);
                if ($response->getStatusCode() !== 200) {
                    return null;
                }
                return (string)$response->getBody();
            } catch (\Exception $e) {
                return null;
            }
        }

        if (is_file($url) && is_readable($url)) {
            return file_get_contents($url);
        }

        return null;
    }

    private function processXmlNode(\SimpleXMLElement $xml, int $projectId): void
    {
        if (isset($xml->url)) {
            foreach ($xml->url as $urlNode) {
                $loc = (string)$urlNode->loc;
                $lastmod = null;

                // Gestisci il formato lastmod
                if (isset($urlNode->lastmod)) {
                    $lastmodStr = (string)$urlNode->lastmod;
                    if (!empty($lastmodStr)) {
                        try {
                            // Prova a parsare la data e convertirla in formato MySQL
                            $date = new \DateTime($lastmodStr);
                            $lastmod = $date->format('Y-m-d H:i:s');
                        } catch (\Exception $e) {
                            // Se il parsing fallisce, lascia lastmod a null
                            $lastmod = null;
                        }
                    }
                }

                $path = parse_url($loc, PHP_URL_PATH) ?? '/';
                $type = $this->classifier->classify($path);

                if ($this->shouldSkipUrl($path)) {
                    continue;
                }

                Url::upsert($projectId, $loc, $lastmod, $type);
            }
        } elseif (isset($xml->sitemap)) {
            foreach ($xml->sitemap as $sitemapNode) {
                $loc = (string)$sitemapNode->loc;
                $content = $this->fetchSitemap($loc);
                if ($content) {
                    $childXml = @simplexml_load_string($content);
                    if ($childXml !== false) {
                        $this->processXmlNode($childXml, $projectId);
                    }
                }
            }
        }
    }

    private function shouldSkipUrl(string $path): bool
    {
        if (strpos($path, '?') !== false) {
            return true;
        }

        if (preg_match('#/page/\d+#', $path)) {
            return true;
        }

        $technical = ['/cart', '/checkout', '/login', '/account', '/my-account'];
        foreach ($technical as $t) {
            if (strpos($path, $t) !== false) {
                return true;
            }
        }

        return false;
    }
}