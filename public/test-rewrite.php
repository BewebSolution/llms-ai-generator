<?php
echo "<h1>Test completo mod_rewrite e .htaccess</h1>";

// 1. Verifica mod_rewrite
echo "<h2>1. Stato mod_rewrite:</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    if (in_array('mod_rewrite', $modules)) {
        echo "<p style='color:green'>✅ mod_rewrite è CARICATO</p>";
    } else {
        echo "<p style='color:red'>❌ mod_rewrite NON è caricato</p>";
    }
} else {
    echo "<p style='color:orange'>⚠️ Non posso verificare (probabilmente non Apache)</p>";
}

// 2. Verifica .htaccess
echo "<h2>2. File .htaccess:</h2>";
$htaccess_path = __DIR__ . '/.htaccess';
if (file_exists($htaccess_path)) {
    echo "<p style='color:green'>✅ .htaccess ESISTE</p>";
    echo "<h3>Contenuto:</h3>";
    echo "<pre style='background:#f0f0f0; padding:10px'>";
    echo htmlspecialchars(file_get_contents($htaccess_path));
    echo "</pre>";
} else {
    echo "<p style='color:red'>❌ .htaccess NON TROVATO!</p>";
}

// 3. Test rewrite effettivo
echo "<h2>3. Test Rewrite:</h2>";
echo "<p>Se mod_rewrite funziona, questo link dovrebbe funzionare:</p>";
echo "<ul>";
echo "<li><a href='/llms-generate/public/test-url-that-does-not-exist'>Test URL (dovrebbe essere gestito da index.php)</a></li>";
echo "<li><a href='/llms-generate/public/projects/1'>Vai a Project 1</a></li>";
echo "</ul>";

// 4. Informazioni richiesta
echo "<h2>4. Informazioni Richiesta Corrente:</h2>";
echo "<pre style='background:#f0f0f0; padding:10px'>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'non impostato') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'non impostato') . "\n";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'non impostato') . "\n";
echo "PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'non impostato') . "\n";
echo "QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'non impostato') . "\n";
echo "</pre>";

// 5. Test AllowOverride
echo "<h2>5. Test AllowOverride:</h2>";
echo "<p>Se .htaccess NON viene letto, potrebbe essere disabilitato AllowOverride nel httpd.conf</p>";
echo "<p>Verifica che nel file httpd.conf ci sia:</p>";
echo "<pre style='background:#fffacd; padding:10px'>";
echo htmlspecialchars('
<Directory "C:/laragon/www">
    Options Indexes FollowSymLinks
    AllowOverride All    # <-- IMPORTANTE: deve essere "All" non "None"
    Require all granted
</Directory>');
echo "</pre>";

// 6. Link alternativi
echo "<h2>6. Link per testare:</h2>";
echo "<ul>";
echo "<li><a href='/llms-generate/public/index.php'>index.php diretto</a></li>";
echo "<li><a href='/llms-generate/public/'>Home con mod_rewrite</a></li>";
echo "<li><a href='/llms-generate/public/router.php?route=/'>Router alternativo</a></li>";
echo "<li><a href='/llms-generate/public/start.php'>Pagina Start</a></li>";
echo "</ul>";
?>