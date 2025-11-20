<?php
echo "<h1>PHP Info - mod_rewrite check</h1>";

// Check if mod_rewrite is loaded
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "<p style='color: green;'>✅ mod_rewrite è abilitato</p>";
    } else {
        echo "<p style='color: red;'>❌ mod_rewrite NON è abilitato</p>";
    }
    echo "<h3>Moduli Apache caricati:</h3>";
    echo "<pre>" . print_r($modules, true) . "</pre>";
} else {
    echo "<p>Impossibile verificare mod_rewrite (non Apache o funzione non disponibile)</p>";
}

echo "<h3>Server Info:</h3>";
echo "<pre>";
echo "SERVER_SOFTWARE: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'not set') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'not set') . "\n";
echo "</pre>";

// Show phpinfo for more details
phpinfo();