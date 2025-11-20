<?php /** @var array $project */ ?>
<h2>Modifica progetto</h2>

<form method="post" action="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/update">
    <p>
        <label>Nome<br>
            <input type="text" name="name" value="<?= htmlspecialchars($project['name']) ?>" required>
        </label>
    </p>
    <p>
        <label>Dominio (es. www.amevista.com)<br>
            <input type="text" name="domain" value="<?= htmlspecialchars($project['domain']) ?>" required>
        </label>
    </p>
    <p>
        <label>Slug<br>
            <input type="text" name="slug" value="<?= htmlspecialchars($project['slug']) ?>" required>
        </label>
    </p>
    <p>
        <label>Riassunto sito (1-2 frasi per il blockquote)<br>
            <textarea name="site_summary" rows="3"><?= htmlspecialchars($project['site_summary'] ?? '') ?></textarea>
        </label>
    </p>
    <p>
        <label>Descrizione aggiuntiva (paragrafo opzionale)<br>
            <textarea name="description" rows="5"><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
        </label>
    </p>
    <p>
        <button type="submit">Aggiorna</button>
        <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>">Annulla</a>
    </p>
</form>