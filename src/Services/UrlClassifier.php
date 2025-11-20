<?php

namespace LlmsApp\Services;

class UrlClassifier
{
    public function classify(string $urlPath): string
    {
        $path = strtolower($urlPath);

        $policyKeywords = [
            'termini', 'condizioni', 'terms', 'conditions',
            'privacy', 'cookie', 'cookies', 'resi', 'res',
            'refund', 'spedizioni', 'shipping', 'pagamenti', 'payments'
        ];

        foreach ($policyKeywords as $kw) {
            if (strpos($path, $kw) !== false) {
                return 'POLICY';
            }
        }

        $categoryKeywords = [
            '/categoria', '/category', 'occhiali-', '/prodotti', '/products'
        ];
        foreach ($categoryKeywords as $kw) {
            if (strpos($path, $kw) !== false) {
                return 'CATEGORY';
            }
        }

        $guideKeywords = [
            '/blog', '/magazine', 'guida', 'guide', 'how-to', 'come-'
        ];
        foreach ($guideKeywords as $kw) {
            if (strpos($path, $kw) !== false) {
                return 'GUIDE';
            }
        }

        $supportKeywords = [
            'contatti', 'contact', 'faq', 'assistenza', 'support', 'help'
        ];
        foreach ($supportKeywords as $kw) {
            if (strpos($path, $kw) !== false) {
                return 'SUPPORT';
            }
        }

        return 'OTHER';
    }
}