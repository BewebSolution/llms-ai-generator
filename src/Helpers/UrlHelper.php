<?php

namespace LlmsApp\Helpers;

class UrlHelper
{
    /**
     * Genera un URL compatibile sia con mod_rewrite che senza
     */
    public static function url(string $path = ''): string
    {
        // Controlla se stiamo usando il router alternativo
        if (isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === 'router.php') {
            // Modalità senza mod_rewrite
            return '/llms-generate/public/router.php?route=' . $path;
        }

        // Modalità normale con mod_rewrite
        $scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $basePath = ($scriptPath === '/' || $scriptPath === '\\') ? '' : $scriptPath;

        return $basePath . $path;
    }

    /**
     * Controlla se mod_rewrite è disponibile
     */
    public static function hasModRewrite(): bool
    {
        if (function_exists('apache_get_modules')) {
            return in_array('mod_rewrite', apache_get_modules());
        }

        // Se non possiamo verificare, assumiamo che non sia disponibile
        // se stiamo usando router.php
        return !isset($_SERVER['SCRIPT_NAME']) || basename($_SERVER['SCRIPT_NAME']) !== 'router.php';
    }
}