<?php
// Variabili disponibili: $baseUrl, $view, e quelle passate nei singoli controller
$hideNav = $hideNav ?? false;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>llms.txt Generator</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/css/app.css">
    <script src="<?= htmlspecialchars($baseUrl) ?>/js/app.js" defer></script>
</head>
<body>
<?php if (!$hideNav): ?>
<header>
    <h1><a href="<?= htmlspecialchars($baseUrl) ?>/">llms.txt Generator</a></h1>
    <nav>
        <a href="<?= htmlspecialchars($baseUrl) ?>/">Progetti</a>
        <a href="<?= htmlspecialchars($baseUrl) ?>/projects/create">Nuovo progetto</a>
        <a href="<?= htmlspecialchars($baseUrl) ?>/costs" style="background: linear-gradient(135deg, #f093fb, #f5576c); color: white; padding: 5px 10px; border-radius: 5px;">💰 Costi AI</a>
        <a href="<?= htmlspecialchars($baseUrl) ?>/settings">⚙️ Impostazioni</a>
        <a href="<?= htmlspecialchars($baseUrl) ?>/test-ai.php" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 5px 10px; border-radius: 5px;">🤖 Test AI</a>
    </nav>
</header>
<?php endif; ?>
<main>
<?php
$viewFile = __DIR__ . '/' . $view . '.php';
if (is_file($viewFile)) {
    include $viewFile;
} else {
    echo '<p>View non trovata: ' . htmlspecialchars($view) . '</p>';
}
?>
</main>
</body>
</html>