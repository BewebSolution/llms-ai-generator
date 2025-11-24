<?php /** @var array $project */ ?>
<h2>Progetto: <?= htmlspecialchars($project['name']) ?></h2>

<div class="project-info">
    <dl>
        <dt>Dominio</dt>
        <dd><?= htmlspecialchars($project['domain']) ?></dd>

        <?php if (!empty($project['homepage_url'])): ?>
        <dt>Homepage URL</dt>
        <dd><a href="<?= htmlspecialchars($project['homepage_url']) ?>" target="_blank"><?= htmlspecialchars($project['homepage_url']) ?></a></dd>
        <?php endif; ?>

        <dt>Slug</dt>
        <dd><?= htmlspecialchars($project['slug']) ?></dd>

        <?php if (!empty($project['site_summary'])): ?>
        <dt>Riassunto sito</dt>
        <dd><?= nl2br(htmlspecialchars($project['site_summary'])) ?></dd>
        <?php endif; ?>

        <?php if (!empty($project['description'])): ?>
        <dt>Descrizione</dt>
        <dd><?= nl2br(htmlspecialchars($project['description'])) ?></dd>
        <?php endif; ?>

        <dt>URL pubblico llms.txt</dt>
        <dd>
            <code><?= htmlspecialchars($baseUrl) ?>/llms/<?= htmlspecialchars($project['slug']) ?>.txt</code>
        </dd>
    </dl>
</div>

<?php if (!empty($project['homepage_url'])): ?>
<div class="crawl-status-box">
    <h3>Stato Scansione</h3>
    <?php
    $statusLabels = [
        'pending' => ['label' => 'In attesa', 'class' => 'status-pending'],
        'in_progress' => ['label' => 'In corso', 'class' => 'status-progress'],
        'completed' => ['label' => 'Completata', 'class' => 'status-completed'],
        'failed' => ['label' => 'Fallita', 'class' => 'status-failed'],
        'stopped' => ['label' => 'Fermata', 'class' => 'status-stopped'],
    ];
    $status = $project['crawl_status'] ?? 'pending';
    $statusInfo = $statusLabels[$status] ?? $statusLabels['pending'];
    ?>
    <p>
        <span class="status-badge <?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span>
        <?php if (!empty($project['last_crawl_at'])): ?>
        <span class="last-crawl">Ultima scansione: <?= htmlspecialchars($project['last_crawl_at']) ?></span>
        <?php endif; ?>
    </p>
    <p>
        <strong>Configurazione:</strong> Profondità <?= (int)($project['crawl_depth'] ?? 3) ?> livelli, max <?= (int)($project['max_urls'] ?? 500) ?> URL
    </p>
    <?php if (!empty($project['crawl_error'])): ?>
    <p class="error-text"><?= htmlspecialchars($project['crawl_error']) ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="project-actions">
    <h3>Azioni</h3>

    <?php if (!empty($project['homepage_url'])): ?>
    <p>
        <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/crawl" class="btn btn-primary">
            🔍 Scansiona sito
        </a>
    </p>
    <?php endif; ?>

    <p>
        <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/edit">✏️ Modifica</a> |
        <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/sitemaps">🗺️ Gestisci Sitemap</a> |
        <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/urls">🔗 Gestisci URL</a> |
        <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/llms/preview">📄 Preview llms.txt</a> |
        <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/costs" style="color: #f5576c;">💰 Costi AI</a> |
        <a href="#" onclick="deleteProject()" style="color: #dc3545;">🗑️ Elimina Progetto</a>
    </p>
</div>

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

<style>
.project-info {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1.5rem;
}

.project-info dl {
    margin: 0;
}

.project-info dt {
    font-weight: bold;
    margin-top: 0.75rem;
}

.project-info dt:first-child {
    margin-top: 0;
}

.project-info dd {
    margin-left: 0;
    margin-bottom: 0.5rem;
}

.crawl-status-box {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.crawl-status-box h3 {
    margin-top: 0;
    margin-bottom: 1rem;
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: bold;
}

.status-pending {
    background: #ffc107;
    color: #000;
}

.status-progress {
    background: #17a2b8;
    color: white;
}

.status-completed {
    background: #28a745;
    color: white;
}

.status-failed {
    background: #dc3545;
    color: white;
}

.status-stopped {
    background: #6c757d;
    color: white;
}

.last-crawl {
    margin-left: 1rem;
    color: #666;
    font-size: 0.9rem;
}

.error-text {
    color: #dc3545;
    font-size: 0.9rem;
}

.project-actions {
    margin-bottom: 1.5rem;
}

.project-actions h3 {
    margin-bottom: 1rem;
}

.btn {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    text-decoration: none;
    font-size: 1rem;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
    color: white;
}
</style>
