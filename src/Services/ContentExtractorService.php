<?php

namespace LlmsApp\Services;

class ContentExtractorService
{
    /**
     * Extract metadata and content from HTML
     */
    public function extract(string $html, string $url): array
    {
        $title = $this->extractTitle($html);
        $description = $this->extractDescription($html);
        $keywords = $this->extractKeywords($html);
        $content = $this->extractMainContent($html);
        $ogData = $this->extractOpenGraph($html);

        return [
            'title' => $title ?: $ogData['og:title'] ?? $this->titleFromUrl($url),
            'description' => $description ?: $ogData['og:description'] ?? null,
            'keywords' => $keywords,
            'content' => $content,
            'og_image' => $ogData['og:image'] ?? null,
            'og_type' => $ogData['og:type'] ?? null,
        ];
    }

    /**
     * Extract title from HTML
     */
    private function extractTitle(string $html): ?string
    {
        // Try <title> tag first
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            $title = trim($matches[1]);
            // Clean up common suffixes like " | Site Name" or " - Site Name"
            $title = preg_replace('/\s*[\|\-–—]\s*[^|\-–—]+$/', '', $title);
            if (!empty($title)) {
                return html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        // Try first H1
        if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $html, $matches)) {
            $h1 = trim(strip_tags($matches[1]));
            if (!empty($h1)) {
                return html_entity_decode($h1, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return null;
    }

    /**
     * Extract meta description
     */
    private function extractDescription(string $html): ?string
    {
        // Try meta description
        if (preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            return $this->cleanText($matches[1]);
        }

        // Alternative format
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']description["\'][^>]*>/i', $html, $matches)) {
            return $this->cleanText($matches[1]);
        }

        return null;
    }

    /**
     * Extract meta keywords
     */
    private function extractKeywords(string $html): array
    {
        if (preg_match('/<meta[^>]+name=["\']keywords["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $keywords = array_map('trim', explode(',', $matches[1]));
            return array_filter($keywords);
        }

        return [];
    }

    /**
     * Extract Open Graph metadata
     */
    private function extractOpenGraph(string $html): array
    {
        $ogData = [];

        // Match all og: meta tags
        preg_match_all('/<meta[^>]+property=["\']og:([^"\']+)["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $ogData['og:' . $match[1]] = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Alternative format
        preg_match_all('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:([^"\']+)["\'][^>]*>/i', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $ogData['og:' . $match[2]] = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Also get Twitter cards
        preg_match_all('/<meta[^>]+name=["\']twitter:([^"\']+)["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $ogData['twitter:' . $match[1]] = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $ogData;
    }

    /**
     * Extract main content (removing nav, header, footer, etc.)
     */
    private function extractMainContent(string $html): string
    {
        // Remove scripts and styles
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);

        // Remove common non-content elements
        $removePatterns = [
            '/<header[^>]*>.*?<\/header>/is',
            '/<footer[^>]*>.*?<\/footer>/is',
            '/<nav[^>]*>.*?<\/nav>/is',
            '/<aside[^>]*>.*?<\/aside>/is',
            '/<form[^>]*>.*?<\/form>/is',
            '/<!--.*?-->/s',
            '/<noscript[^>]*>.*?<\/noscript>/is',
        ];

        foreach ($removePatterns as $pattern) {
            $html = preg_replace($pattern, '', $html);
        }

        // Try to find main content area
        $mainContent = '';

        // Look for common content containers
        $contentSelectors = [
            '/<main[^>]*>(.*?)<\/main>/is',
            '/<article[^>]*>(.*?)<\/article>/is',
            '/<div[^>]*class=["\'][^"\']*content[^"\']*["\'][^>]*>(.*?)<\/div>/is',
            '/<div[^>]*id=["\']content["\'][^>]*>(.*?)<\/div>/is',
            '/<div[^>]*class=["\'][^"\']*main[^"\']*["\'][^>]*>(.*?)<\/div>/is',
        ];

        foreach ($contentSelectors as $selector) {
            if (preg_match($selector, $html, $matches)) {
                $mainContent = $matches[1];
                break;
            }
        }

        // If no main content found, use body
        if (empty($mainContent) && preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            $mainContent = $matches[1];
        }

        // Strip remaining HTML tags
        $text = strip_tags($mainContent);

        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Limit length
        if (strlen($text) > 5000) {
            $text = substr($text, 0, 5000) . '...';
        }

        return $text;
    }

    /**
     * Generate title from URL path
     */
    private function titleFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path || $path === '/') {
            return parse_url($url, PHP_URL_HOST) ?: 'Home';
        }

        // Get last path segment
        $segments = array_filter(explode('/', $path));
        $lastSegment = end($segments);

        // Clean up
        $title = str_replace(['-', '_', '.html', '.php', '.htm'], [' ', ' ', '', '', ''], $lastSegment);
        $title = ucwords(trim($title));

        return $title ?: 'Page';
    }

    /**
     * Clean and normalize text
     */
    private function cleanText(string $text): string
    {
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Trim
        $text = trim($text);

        // Limit length
        if (strlen($text) > 500) {
            $text = substr($text, 0, 497) . '...';
        }

        return $text;
    }

    /**
     * Extract all meta tags (for debugging/full extraction)
     */
    public function extractAllMeta(string $html): array
    {
        $meta = [];

        // Get all meta tags with name attribute
        preg_match_all('/<meta[^>]+name=["\']([^"\']+)["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $meta[$match[1]] = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Get all meta tags with property attribute (Open Graph)
        preg_match_all('/<meta[^>]+property=["\']([^"\']+)["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $meta[$match[1]] = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $meta;
    }

    /**
     * Check if page is likely a listing/category page
     */
    public function isListingPage(string $html): bool
    {
        // Count common listing indicators
        $indicators = 0;

        // Multiple product/article cards
        $cardCount = preg_match_all('/<(article|div)[^>]*class=["\'][^"\']*(?:card|product|item|post)[^"\']*["\'][^>]*>/i', $html);
        if ($cardCount > 3) {
            $indicators++;
        }

        // Pagination
        if (preg_match('/class=["\'][^"\']*pagination[^"\']*["\']|rel=["\']next["\']|rel=["\']prev["\']/', $html)) {
            $indicators++;
        }

        // Multiple similar links
        $linkPattern = '/<a[^>]+href=["\'][^"\']+["\'][^>]*>/i';
        if (preg_match_all($linkPattern, $html) > 20) {
            $indicators++;
        }

        return $indicators >= 2;
    }
}
