<?php /** @var array $project */ ?>
<h2>Modifica progetto</h2>

<form method="post" action="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/update">
    <div class="form-section">
        <h3>Informazioni base</h3>

        <p>
            <label>Nome<br>
                <input type="text" name="name" value="<?= htmlspecialchars($project['name']) ?>" required>
            </label>
        </p>

        <p>
            <label>URL Homepage<br>
                <input type="url" name="homepage_url" id="homepage_url"
                       value="<?= htmlspecialchars($project['homepage_url'] ?? '') ?>"
                       placeholder="https://www.esempio.com"
                       onchange="extractDomain(this.value)">
            </label>
            <small>Inserisci l'URL completo della homepage del sito da scansionare</small>
        </p>

        <p>
            <label>Dominio<br>
                <input type="text" name="domain" id="domain"
                       value="<?= htmlspecialchars($project['domain']) ?>" required>
            </label>
        </p>

        <p>
            <label>Slug<br>
                <input type="text" name="slug" value="<?= htmlspecialchars($project['slug']) ?>" required>
            </label>
        </p>
    </div>

    <div class="form-section">
        <h3>Opzioni di scansione</h3>

        <p>
            <label>Profondità di scansione<br>
                <select name="crawl_depth">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <option value="<?= $i ?>" <?= ((int)($project['crawl_depth'] ?? 3) === $i) ? 'selected' : '' ?>>
                        <?= $i ?> - <?= ['Solo homepage', 'Homepage + link diretti', 'Normale', 'Approfondita', 'Completa'][$i-1] ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </label>
        </p>

        <p>
            <label>Limite massimo URL<br>
                <select name="max_urls">
                    <?php
                    $limits = [100, 250, 500, 1000, 2000];
                    $currentMax = (int)($project['max_urls'] ?? 500);
                    foreach ($limits as $limit):
                    ?>
                    <option value="<?= $limit ?>" <?= ($currentMax === $limit) ? 'selected' : '' ?>>
                        <?= $limit ?> URL
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>
    </div>

    <div class="form-section">
        <h3>Descrizioni</h3>

        <p>
            <label>Riassunto sito (1-2 frasi per il blockquote)<br>
                <textarea name="site_summary" rows="3"><?= htmlspecialchars($project['site_summary'] ?? '') ?></textarea>
            </label>
        </p>

        <p>
            <label>Descrizione aggiuntiva<br>
                <textarea name="description" rows="4"><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
            </label>
        </p>
    </div>

    <p class="form-actions">
        <button type="submit" class="btn btn-primary">Aggiorna</button>
        <a href="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>" class="btn btn-secondary">Annulla</a>
    </p>
</form>

<script>
function extractDomain(url) {
    if (!url) return;
    try {
        const urlObj = new URL(url);
        document.getElementById('domain').value = urlObj.hostname;
    } catch (e) {
        // Invalid URL, don't change domain
    }
}
</script>

<style>
.form-section {
    margin-bottom: 2rem;
    padding: 1rem;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-section h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    font-size: 1.1rem;
    color: #333;
}

.form-section p {
    margin-bottom: 1rem;
}

.form-section label {
    display: block;
}

.form-section input,
.form-section select,
.form-section textarea {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 1rem;
    margin-top: 0.25rem;
}

.form-section small {
    display: block;
    color: #666;
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1rem;
    text-decoration: none;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #545b62;
}
</style>
