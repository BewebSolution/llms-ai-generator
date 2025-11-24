<?php

namespace LlmsApp\Services;

class UrlClassifier
{
    /**
     * URL type constants
     */
    public const TYPE_HOMEPAGE = 'HOMEPAGE';
    public const TYPE_LANGUAGE = 'LANGUAGE';
    public const TYPE_COUNTRY = 'COUNTRY';
    public const TYPE_PROMOTION = 'PROMOTION';
    public const TYPE_CATEGORY = 'CATEGORY';
    public const TYPE_PRODUCT = 'PRODUCT';
    public const TYPE_BRAND = 'BRAND';
    public const TYPE_FEATURE = 'FEATURE';
    public const TYPE_SUPPORT = 'SUPPORT';
    public const TYPE_POLICY = 'POLICY';
    public const TYPE_COMPANY = 'COMPANY';
    public const TYPE_BLOG = 'BLOG';
    public const TYPE_OTHER = 'OTHER';

    /**
     * Get all available types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_HOMEPAGE,
            self::TYPE_LANGUAGE,
            self::TYPE_COUNTRY,
            self::TYPE_PROMOTION,
            self::TYPE_CATEGORY,
            self::TYPE_PRODUCT,
            self::TYPE_BRAND,
            self::TYPE_FEATURE,
            self::TYPE_SUPPORT,
            self::TYPE_POLICY,
            self::TYPE_COMPANY,
            self::TYPE_BLOG,
            self::TYPE_OTHER,
        ];
    }

    /**
     * Get section labels for llms.txt generation
     */
    public static function getSectionLabels(): array
    {
        return [
            self::TYPE_HOMEPAGE => 'Homepage',
            self::TYPE_LANGUAGE => 'Language Options',
            self::TYPE_COUNTRY => 'Country-Specific Pages',
            self::TYPE_PROMOTION => 'Promotions',
            self::TYPE_CATEGORY => 'Product Categories',
            self::TYPE_PRODUCT => 'Products',
            self::TYPE_BRAND => 'Brands',
            self::TYPE_FEATURE => 'Additional Features',
            self::TYPE_SUPPORT => 'Customer Support',
            self::TYPE_POLICY => 'Terms & Policies',
            self::TYPE_COMPANY => 'Company Information',
            self::TYPE_BLOG => 'Blog & Magazine',
            self::TYPE_OTHER => 'Other Pages',
        ];
    }

    /**
     * Classify a URL based on its path
     */
    public function classify(string $url): string
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? $url);

        // Check for homepage
        if ($path === '/' || $path === '' || preg_match('/^\/[a-z]{2}(-[a-z]{2})?\/?$/', $path)) {
            // Could be homepage or country page - check pattern
            if (preg_match('/^\/[a-z]{2}\/?$/', $path)) {
                return self::TYPE_COUNTRY;
            }
            if (preg_match('/^\/[a-z]{2}-[a-z]{2}\/?$/', $path)) {
                return self::TYPE_LANGUAGE;
            }
            return self::TYPE_HOMEPAGE;
        }

        // Language/locale pages (e.g., /us-en, /us-it)
        if (preg_match('/\/[a-z]{2}-[a-z]{2}(\/|$)/', $path)) {
            // Check if it's just the language selector or a deeper page
            if (preg_match('/^\/[a-z]{2}-[a-z]{2}\/?$/', $path)) {
                return self::TYPE_LANGUAGE;
            }
        }

        // Country pages (e.g., /us, /it, /fr - two letter codes at root)
        if (preg_match('/^\/[a-z]{2}\/?$/', $path)) {
            return self::TYPE_COUNTRY;
        }

        // Promotions
        $promotionKeywords = [
            '/promo', '/promozioni', '/offerte', '/offers', '/deals',
            '/sale', '/saldi', '/discount', '/sconto', '/coupon',
            'black-friday', 'black-month', 'cyber-monday'
        ];
        foreach ($promotionKeywords as $kw) {
            if (strpos($path, $kw) !== false) {
                return self::TYPE_PROMOTION;
            }
        }

        // Brands - check before categories as brands are often in category paths
        $brandKeywords = [
            '/ray-ban', '/oakley', '/prada', '/gucci', '/dior', '/chanel',
            '/tom-ford', '/versace', '/armani', '/fendi', '/celine',
            '/miu-miu', '/michael-kors', '/burberry', '/dolce-gabbana',
            '/produttori', '/brands', '/marche', '/designer'
        ];
        foreach ($brandKeywords as $kw) {
            if (strpos($path, $kw) !== false) {
                return self::TYPE_BRAND;
            }
        }

        // Categories
        $categoryKeywords = [
            '/categoria', '/category', '/categories', '/collezione', '/collection',
            '/sunglasses', '/eyeglasses', '/occhiali', '/lenti', '/lenses',
            '/woman', '/man', '/kid', '/donna', '/uomo', '/bambino',
            '/polarized', '/photochromic', '/mirror', '/sport',
            '/masks', '/maschere', '/ski-masks'
        ];
        foreach ($categoryKeywords as $kw) {
            if (strpos($path, $kw) !== false) {
                return self::TYPE_CATEGORY;
            }
        }

        // Features/Tools
        $featureKeywords = [
            'virtual-try', 'prova-virtuale', '3d-viewer', '3d-view',
            '/size-guide', '/guida-taglie', '/lens-guide', '/guida-lenti',
            '/customise', '/customize', '/personalizza', '/configurator'
        ];
        foreach ($featureKeywords as $kw) {
            if (strpos($path, $kw) !== false) {
                return self::TYPE_FEATURE;
            }
        }

        // Support
        $supportKeywords = [
            '/faq', '/help', '/aiuto', '/assistenza', '/support',
            '/contatti', '/contact', '/order-info', '/tracking',
            '/shipping', '/spedizione', '/return', '/reso', '/refund'
        ];
        foreach ($supportKeywords as $kw) {
            if (strpos($path, $kw) !== false) {
                return self::TYPE_SUPPORT;
            }
        }

        // Policies
        $policyKeywords = [
            '/termini', '/terms', '/condizioni', '/conditions',
            '/privacy', '/cookie', '/cookies', '/gdpr',
            '/garanzia', '/warranty', '/legal', '/disclaimer'
        ];
        foreach ($policyKeywords as $kw) {
            if (strpos($path, $kw) !== false) {
                return self::TYPE_POLICY;
            }
        }

        // Company info
        $companyKeywords = [
            '/chi-siamo', '/about', '/about-us', '/azienda', '/company',
            '/careers', '/lavora-con-noi', '/jobs', '/team',
            '/partner', '/affiliates', '/press', '/stampa',
            '/newsletter', '/subscribe'
        ];
        foreach ($companyKeywords as $kw) {
            if (strpos($path, $kw) !== false) {
                return self::TYPE_COMPANY;
            }
        }

        // Blog/Magazine
        $blogKeywords = [
            '/blog', '/magazine', '/news', '/articoli', '/articles',
            '/guide', '/guida', '/how-to', '/tutorial', '/tips'
        ];
        foreach ($blogKeywords as $kw) {
            if (strpos($path, $kw) !== false) {
                return self::TYPE_BLOG;
            }
        }

        // Product pages (typically have product codes or long slugs)
        if (preg_match('/\/[a-z0-9]+-[a-z0-9]+-[a-z0-9]+/', $path) ||
            preg_match('/\/product\//', $path) ||
            preg_match('/\/p\/[0-9]+/', $path)) {
            return self::TYPE_PRODUCT;
        }

        return self::TYPE_OTHER;
    }

    /**
     * Get priority for sorting sections in llms.txt
     */
    public static function getSectionPriority(string $type): int
    {
        $priorities = [
            self::TYPE_HOMEPAGE => 1,
            self::TYPE_LANGUAGE => 2,
            self::TYPE_COUNTRY => 3,
            self::TYPE_PROMOTION => 4,
            self::TYPE_CATEGORY => 5,
            self::TYPE_PRODUCT => 6,
            self::TYPE_BRAND => 7,
            self::TYPE_FEATURE => 8,
            self::TYPE_SUPPORT => 9,
            self::TYPE_POLICY => 10,
            self::TYPE_COMPANY => 11,
            self::TYPE_BLOG => 12,
            self::TYPE_OTHER => 99,
        ];

        return $priorities[$type] ?? 99;
    }
}
