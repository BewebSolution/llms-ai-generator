<h2>Preview llms.txt per il progetto: <?= htmlspecialchars($project['name']) ?></h2>

<form method="post" action="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/llms/generate">
    <button type="submit">Salva/aggiorna file llms.txt</button>
</form>

<p>
    URL pubblico: <code><?= htmlspecialchars($baseUrl) ?>/llms/<?= htmlspecialchars($project['slug']) ?>.txt</code>
</p>

<h3>Contenuto generato</h3>
<pre style="white-space: pre-wrap; border: 1px solid #ccc; padding: 10px; background: #f9f9f9;">
<?= htmlspecialchars($content) ?>
</pre>

<p>
    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>">Torna al progetto</a> |
    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/urls">Gestisci URL</a>
</p>