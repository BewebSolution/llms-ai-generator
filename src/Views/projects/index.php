<?php /** @var array $projects */ ?>
<h2>Progetti</h2>

<div style="margin-bottom: 20px;">
    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/create" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
        ➕ Crea nuovo progetto
    </a>
</div>

<?php if (empty($projects)): ?>
    <p style="color: #666;">Nessun progetto presente. Crea il tuo primo progetto!</p>
<?php else: ?>

<form id="bulkForm" method="POST" action="<?= htmlspecialchars($baseUrl) ?>/projects/bulk-delete" onsubmit="return confirmBulkDelete()">

    <!-- Azioni Bulk -->
    <div style="background: #f8f9fa; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
        <button type="button" onclick="selectAll()" style="padding: 8px 15px; margin-right: 10px;">☑️ Seleziona tutto</button>
        <button type="button" onclick="deselectAll()" style="padding: 8px 15px; margin-right: 10px;">⬜ Deseleziona tutto</button>
        <button type="submit" style="background: #dc3545; color: white; padding: 8px 15px; border: none; border-radius: 3px; cursor: pointer;">
            🗑️ Elimina selezionati
        </button>
        <span id="selectedCount" style="margin-left: 20px; color: #666;">0 selezionati</span>
    </div>

    <table style="width: 100%; border-collapse: collapse;">
        <thead>
        <tr style="background: #f0f0f0;">
            <th style="padding: 10px; text-align: left; width: 40px;">
                <input type="checkbox" id="selectAllCheck" onchange="toggleAll(this)">
            </th>
            <th style="padding: 10px; text-align: left;">Nome</th>
            <th style="padding: 10px; text-align: left;">Dominio</th>
            <th style="padding: 10px; text-align: left;">Slug</th>
            <th style="padding: 10px; text-align: left;">Azioni</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($projects as $project): ?>
            <tr style="border-bottom: 1px solid #ddd;">
                <td style="padding: 10px;">
                    <input type="checkbox"
                           name="project_ids[]"
                           value="<?= (int)$project['id'] ?>"
                           class="project-checkbox"
                           onchange="updateCount()">
                </td>
                <td style="padding: 10px;">
                    <strong><?= htmlspecialchars($project['name']) ?></strong>
                </td>
                <td style="padding: 10px;">
                    <?= htmlspecialchars($project['domain']) ?>
                </td>
                <td style="padding: 10px;">
                    <code><?= htmlspecialchars($project['slug']) ?></code>
                </td>
                <td style="padding: 10px;">
                    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>" style="color: #007bff;">👁️ Dettagli</a> |
                    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/edit" style="color: #28a745;">✏️ Modifica</a> |
                    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/sitemaps" style="color: #17a2b8;">🗺️ Sitemap</a> |
                    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/urls" style="color: #ffc107;">🔗 URL</a> |
                    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/llms/preview" style="color: #6c757d;">📄 Preview</a> |
                    <a href="#"
                       onclick="deleteProject(<?= (int)$project['id'] ?>, '<?= htmlspecialchars(addslashes($project['name'])) ?>')"
                       style="color: #dc3545;">🗑️ Elimina</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</form>

<!-- Form nascosto per eliminazione singola -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="_method" value="DELETE">
</form>

<?php endif; ?>

<script>
function selectAll() {
    document.querySelectorAll('.project-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('selectAllCheck').checked = true;
    updateCount();
}

function deselectAll() {
    document.querySelectorAll('.project-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheck').checked = false;
    updateCount();
}

function toggleAll(source) {
    document.querySelectorAll('.project-checkbox').forEach(cb => cb.checked = source.checked);
    updateCount();
}

function updateCount() {
    const count = document.querySelectorAll('.project-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count + ' selezionati';
}

function confirmBulkDelete() {
    const count = document.querySelectorAll('.project-checkbox:checked').length;
    if (count === 0) {
        alert('Seleziona almeno un progetto da eliminare');
        return false;
    }
    return confirm(`Sei sicuro di voler eliminare ${count} progett${count === 1 ? 'o' : 'i'}? Questa azione è irreversibile!`);
}

function deleteProject(id, name) {
    if (confirm(`Sei sicuro di voler eliminare il progetto "${name}"? Questa azione eliminerà anche tutte le sitemap, URL e dati associati!`)) {
        const form = document.getElementById('deleteForm');
        form.action = '<?= htmlspecialchars($baseUrl) ?>/projects/' + id + '/delete';
        form.submit();
    }
    return false;
}
</script>