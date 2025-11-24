<h2>Scansione: <?= htmlspecialchars($project['name']) ?></h2>

<div class="crawl-info">
    <p><strong>Homepage:</strong> <a href="<?= htmlspecialchars($project['homepage_url']) ?>" target="_blank"><?= htmlspecialchars($project['homepage_url']) ?></a></p>
    <p><strong>Profondità:</strong> <?= (int)$project['crawl_depth'] ?> livelli</p>
    <p><strong>Limite URL:</strong> <?= (int)$project['max_urls'] ?></p>
</div>

<div class="crawl-status" id="crawl-status">
    <h3>Stato: <span id="status-text"><?= htmlspecialchars(ucfirst($progress['status'])) ?></span></h3>

    <div class="progress-bar-container">
        <div class="progress-bar" id="progress-bar" style="width: 0%"></div>
    </div>

    <div class="stats-grid">
        <div class="stat-item">
            <span class="stat-label">URL scansionati</span>
            <span class="stat-value" id="stat-crawled"><?= (int)$progress['total_crawled'] ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-label">URL scoperti</span>
            <span class="stat-value" id="stat-discovered"><?= (int)$progress['total_discovered'] ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-label">Errori</span>
            <span class="stat-value" id="stat-failed"><?= (int)$progress['total_failed'] ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-label">In coda</span>
            <span class="stat-value" id="stat-pending"><?= (int)$progress['pending_in_queue'] ?></span>
        </div>
    </div>

    <?php if ($progress['error']): ?>
    <div class="error-message" id="error-message">
        <?= htmlspecialchars($progress['error']) ?>
    </div>
    <?php endif; ?>
</div>

<div class="crawl-actions">
    <?php if ($progress['status'] === 'pending' || $progress['status'] === 'stopped' || $progress['status'] === 'failed' || $progress['status'] === 'completed'): ?>
    <button type="button" class="btn btn-primary" id="btn-start" onclick="startCrawl()">
        <?= $progress['status'] === 'completed' ? 'Riavvia scansione' : 'Avvia scansione' ?>
    </button>
    <?php endif; ?>

    <?php if ($progress['status'] === 'in_progress'): ?>
    <button type="button" class="btn btn-danger" id="btn-stop" onclick="stopCrawl()">
        Ferma scansione
    </button>
    <?php endif; ?>

    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/urls" class="btn btn-secondary">
        Vai agli URL
    </a>

    <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>" class="btn btn-secondary">
        Torna al progetto
    </a>
</div>

<script>
const projectId = <?= (int)$project['id'] ?>;
const baseUrl = '<?= htmlspecialchars($baseUrl) ?>';
const maxUrls = <?= (int)$project['max_urls'] ?>;
let pollingInterval = null;

function startCrawl() {
    document.getElementById('btn-start').disabled = true;
    document.getElementById('btn-start').textContent = 'Avvio in corso...';
    document.getElementById('status-text').textContent = 'Avvio...';

    fetch(`${baseUrl}/projects/${projectId}/crawl/start`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Start polling for progress
            startPolling();
            updateUI('in_progress');
        } else {
            alert('Errore: ' + data.message);
            document.getElementById('btn-start').disabled = false;
            document.getElementById('btn-start').textContent = 'Avvia scansione';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Errore di connessione');
        document.getElementById('btn-start').disabled = false;
        document.getElementById('btn-start').textContent = 'Avvia scansione';
    });
}

function stopCrawl() {
    if (!confirm('Vuoi fermare la scansione?')) return;

    fetch(`${baseUrl}/projects/${projectId}/crawl/stop`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateUI('stopped');
            stopPolling();
        } else {
            alert('Errore: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Errore di connessione');
    });
}

function startPolling() {
    if (pollingInterval) return;

    pollingInterval = setInterval(() => {
        fetch(`${baseUrl}/api/projects/${projectId}/crawl/status`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateStats(data.progress);

                    // Stop polling if crawl is done
                    if (['completed', 'failed', 'stopped'].includes(data.progress.status)) {
                        stopPolling();
                        updateUI(data.progress.status);
                    }
                }
            })
            .catch(error => {
                console.error('Polling error:', error);
            });
    }, 2000); // Poll every 2 seconds
}

function stopPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
}

function updateStats(progress) {
    document.getElementById('stat-crawled').textContent = progress.total_crawled;
    document.getElementById('stat-discovered').textContent = progress.total_discovered;
    document.getElementById('stat-failed').textContent = progress.total_failed;
    document.getElementById('stat-pending').textContent = progress.pending_in_queue;
    document.getElementById('status-text').textContent = formatStatus(progress.status);

    // Update progress bar
    const percentage = Math.min((progress.total_crawled / maxUrls) * 100, 100);
    document.getElementById('progress-bar').style.width = percentage + '%';

    // Show error if any
    if (progress.error) {
        let errorDiv = document.getElementById('error-message');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.id = 'error-message';
            errorDiv.className = 'error-message';
            document.getElementById('crawl-status').appendChild(errorDiv);
        }
        errorDiv.textContent = progress.error;
    }
}

function updateUI(status) {
    const actionsDiv = document.querySelector('.crawl-actions');

    if (status === 'in_progress') {
        actionsDiv.innerHTML = `
            <button type="button" class="btn btn-danger" id="btn-stop" onclick="stopCrawl()">
                Ferma scansione
            </button>
            <a href="${baseUrl}/projects/${projectId}/urls" class="btn btn-secondary">
                Vai agli URL
            </a>
            <a href="${baseUrl}/projects/${projectId}" class="btn btn-secondary">
                Torna al progetto
            </a>
        `;
    } else {
        actionsDiv.innerHTML = `
            <button type="button" class="btn btn-primary" id="btn-start" onclick="startCrawl()">
                ${status === 'completed' ? 'Riavvia scansione' : 'Avvia scansione'}
            </button>
            <a href="${baseUrl}/projects/${projectId}/urls" class="btn btn-secondary">
                Vai agli URL
            </a>
            <a href="${baseUrl}/projects/${projectId}" class="btn btn-secondary">
                Torna al progetto
            </a>
        `;
    }

    document.getElementById('status-text').textContent = formatStatus(status);
}

function formatStatus(status) {
    const statusMap = {
        'pending': 'In attesa',
        'in_progress': 'In corso',
        'completed': 'Completato',
        'failed': 'Fallito',
        'stopped': 'Fermato'
    };
    return statusMap[status] || status;
}

// Start polling if already in progress
<?php if ($progress['status'] === 'in_progress'): ?>
document.addEventListener('DOMContentLoaded', function() {
    startPolling();
});
<?php endif; ?>
</script>

<style>
.crawl-info {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 1.5rem;
}

.crawl-info p {
    margin: 0.5rem 0;
}

.crawl-status {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.crawl-status h3 {
    margin-top: 0;
    margin-bottom: 1rem;
}

.progress-bar-container {
    width: 100%;
    height: 20px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.progress-bar {
    height: 100%;
    background: #007bff;
    transition: width 0.3s ease;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}

.stat-item {
    text-align: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 4px;
}

.stat-label {
    display: block;
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 0.5rem;
}

.stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
}

.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 1rem;
    border-radius: 4px;
    margin-top: 1rem;
}

.crawl-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1rem;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-primary:disabled {
    background-color: #ccc;
    cursor: not-allowed;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #545b62;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
}

.btn-danger:hover {
    background-color: #c82333;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
