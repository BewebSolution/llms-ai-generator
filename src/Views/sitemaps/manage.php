<h2>Gestione sitemap per il progetto: <?= htmlspecialchars($project['name']) ?></h2>

<h3>Aggiungi sitemap</h3>

<form method="post" action="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/sitemaps/add">
    <p>
        <label>URL o path sitemap<br>
            <input type="text" name="url" required placeholder="https://esempio.com/sitemap.xml">
        </label>
    </p>
    <p>
        <button type="submit">Aggiungi sitemap</button>
    </p>
</form>

<h3>Sitemap esistenti</h3>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>URL</th>
        <th>Ultimo parsing</th>
        <th>Azioni</th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($sitemaps)): ?>
        <tr>
            <td colspan="4">Nessuna sitemap aggiunta</td>
        </tr>
    <?php else: ?>
        <?php foreach ($sitemaps as $sitemap): ?>
            <tr>
                <td><?= (int)$sitemap['id'] ?></td>
                <td><?= htmlspecialchars($sitemap['url']) ?></td>
                <td><?= htmlspecialchars($sitemap['last_parsed_at'] ?? '-') ?></td>
                <td>
                    <form method="post" action="<?= htmlspecialchars($baseUrl) ?>/sitemaps/<?= (int)$sitemap['id'] ?>/parse" style="display:inline;">
                        <button type="submit">Parsa sitemap</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<p>
    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>">Torna al progetto</a>
</p>