<?php /** @var array $project */ ?>
<h2>Progetto: <?= htmlspecialchars($project['name']) ?></h2>

<dl>
    <dt>Dominio</dt>
    <dd><?= htmlspecialchars($project['domain']) ?></dd>

    <dt>Slug</dt>
    <dd><?= htmlspecialchars($project['slug']) ?></dd>

    <dt>Riassunto sito</dt>
    <dd><?= !empty($project['site_summary']) ? nl2br(htmlspecialchars($project['site_summary'])) : '' ?></dd>

    <dt>Descrizione</dt>
    <dd><?= !empty($project['description']) ? nl2br(htmlspecialchars($project['description'])) : '' ?></dd>

    <dt>URL pubblico llms.txt</dt>
    <dd>
        <code><?= htmlspecialchars($baseUrl) ?>/llms/<?= htmlspecialchars($project['slug']) ?>.txt</code>
    </dd>
</dl>

<p>
    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/edit">✏️ Modifica</a> |
    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/sitemaps">🗺️ Gestisci Sitemap</a> |
    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/urls">🔗 Gestisci URL</a> |
    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/llms/preview">📄 Preview llms.txt</a> |
    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/costs" style="color: #f5576c;">💰 Costi AI</a> |
    <a href="#" onclick="deleteProject()" style="color: #dc3545;">🗑️ Elimina Progetto</a>
</p>

<p>
    <a href="<?= htmlspecialchars($baseUrl) ?>/">← Torna ai progetti</a>
</p>

<!-- Form nascosto per eliminazione -->
<form id="deleteForm" method="POST" action="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/delete" style="display: none;">
</form>

<script>
function deleteProject() {
    if (confirm('Sei sicuro di voler eliminare il progetto "<?= htmlspecialchars(addslashes($project['name'])) ?>"?\n\nQuesta azione eliminerà:\n• Tutte le sitemap associate\n• Tutti gli URL salvati\n• Tutte le configurazioni del progetto\n\nL\'operazione è IRREVERSIBILE!')) {
        document.getElementById('deleteForm').submit();
    }
    return false;
}
</script>