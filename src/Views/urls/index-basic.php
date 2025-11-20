<h2>URL per il progetto: <?= htmlspecialchars($project['name']) ?></h2>

<form method="get" action="">
    <label>Tipo:
        <select name="type">
            <option value="">Tutti</option>
            <option value="POLICY" <?= ($filters['type'] ?? '') === 'POLICY' ? 'selected' : '' ?>>Policy</option>
            <option value="CATEGORY" <?= ($filters['type'] ?? '') === 'CATEGORY' ? 'selected' : '' ?>>Categoria</option>
            <option value="GUIDE" <?= ($filters['type'] ?? '') === 'GUIDE' ? 'selected' : '' ?>>Guide</option>
            <option value="SUPPORT" <?= ($filters['type'] ?? '') === 'SUPPORT' ? 'selected' : '' ?>>Supporto</option>
            <option value="OTHER" <?= ($filters['type'] ?? '') === 'OTHER' ? 'selected' : '' ?>>Altro</option>
        </select>
    </label>

    <label>Selezionati:
        <select name="is_selected">
            <option value="">Tutti</option>
            <option value="1" <?= (isset($filters['is_selected']) && $filters['is_selected'] === 1) ? 'selected' : '' ?>>Solo selezionati</option>
            <option value="0" <?= (isset($filters['is_selected']) && $filters['is_selected'] === 0) ? 'selected' : '' ?>>Non selezionati</option>
        </select>
    </label>

    <label>Cerca:
        <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
    </label>

    <button type="submit">Filtra</button>
</form>

<form method="post" action="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/urls/bulk-update">
    <table>
        <thead>
        <tr>
            <th>Seleziona</th>
            <th>URL</th>
            <th>Tipo</th>
            <th>Titolo</th>
            <th>Descrizione breve</th>
            <th>AI</th>
            <th>Sezione</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($urls)): ?>
            <tr>
                <td colspan="7">Nessuna URL trovata. Aggiungi e parsa una sitemap per iniziare.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($urls as $url): ?>
                <?php
                    $rowId = (int)$url['id'];
                    $titleValue = $url['title'] ?? '';
                    $loc = $url['loc'];
                ?>
                <tr data-url-row-id="<?= $rowId ?>">
                    <td>
                        <input type="checkbox"
                               name="urls[<?= $rowId ?>][is_selected]"
                               value="1" <?= $url['is_selected'] ? 'checked' : '' ?>>
                    </td>
                    <td>
                        <a href="<?= htmlspecialchars($loc) ?>" target="_blank" rel="noopener" title="<?= htmlspecialchars($loc) ?>">
                            <?= htmlspecialchars(substr($loc, 0, 50)) ?><?= strlen($loc) > 50 ? '...' : '' ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($url['type']) ?></td>
                    <td>
                        <input type="text"
                               class="url-title-input"
                               name="urls[<?= $rowId ?>][title]"
                               value="<?= htmlspecialchars($titleValue) ?>"
                               size="20"
                               data-url="<?= htmlspecialchars($loc) ?>">
                    </td>
                    <td>
                        <input type="text"
                               class="url-desc-input"
                               name="urls[<?= $rowId ?>][short_description]"
                               value="<?= htmlspecialchars($url['short_description'] ?? '') ?>"
                               size="40">
                    </td>
                    <td>
                        <button type="button"
                                class="btn-ai-desc"
                                data-row-id="<?= $rowId ?>"
                                data-url="<?= htmlspecialchars($loc) ?>">
                            AI
                        </button>
                        <span class="ai-status" data-row-id="<?= $rowId ?>"></span>
                    </td>
                    <td>
                        <select name="section[<?= $rowId ?>]">
                            <option value="">(nessuna)</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?= (int)$section['id'] ?>"><?= htmlspecialchars($section['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if (!empty($urls)): ?>
        <p>
            <button type="submit">Salva selezioni e sezioni</button>
        </p>
    <?php endif; ?>
</form>

<?php
// Conta URL selezionati
$selectedCount = 0;
foreach ($urls as $url) {
    if ($url['is_selected']) $selectedCount++;
}
?>

<?php if ($selectedCount > 0): ?>
<div style="background: #d4edda; padding: 20px; margin: 20px 0; border-radius: 5px; text-align: center;">
    <h3 style="color: #155724; margin-bottom: 15px;">✅ Hai <?= $selectedCount ?> URL selezionati</h3>
    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/llms/preview"
       style="display: inline-block; background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 18px;">
        📄 GENERA LLMS.TXT →
    </a>
</div>
<?php else: ?>
<div style="background: #fff3cd; padding: 20px; margin: 20px 0; border-radius: 5px; text-align: center;">
    <p style="color: #856404;">⚠️ Seleziona almeno un URL per generare il file llms.txt</p>
</div>
<?php endif; ?>

<p>
    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>">← Torna al progetto</a> |
    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/sitemaps">🗺️ Gestisci Sitemap</a> |
    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/llms/preview">📄 Preview llms.txt</a>
</p>