<h2>Nuovo progetto</h2>

<form method="post" action="<?= htmlspecialchars($baseUrl) ?>/projects/store" id="create-project-form">
    <div class="form-section">
        <h3>Informazioni base</h3>

        <p>
            <label>Nome progetto<br>
                <input type="text" name="name" required placeholder="Es. Il mio sito web">
            </label>
        </p>

        <p>
            <label>URL Homepage<br>
                <input type="url" name="homepage_url" id="homepage_url" required
                       placeholder="https://www.esempio.com"
                       onchange="extractDomain(this.value)">
            </label>
            <small>Inserisci l'URL completo della homepage del sito da scansionare</small>
        </p>

        <p>
            <label>Dominio<br>
                <input type="text" name="domain" id="domain" required readonly
                       placeholder="Estratto automaticamente dall'URL">
            </label>
        </p>

        <p>
            <label>Slug (se vuoto viene generato)<br>
                <input type="text" name="slug" placeholder="nome-progetto">
            </label>
        </p>
    </div>

    <div class="form-section">
        <h3>Opzioni di scansione</h3>

        <p>
            <label>Profondità di scansione<br>
                <select name="crawl_depth">
                    <option value="1">1 - Solo homepage</option>
                    <option value="2">2 - Homepage + link diretti</option>
                    <option value="3" selected>3 - Normale (consigliato)</option>
                    <option value="4">4 - Approfondita</option>
                    <option value="5">5 - Completa</option>
                </select>
            </label>
            <small>Numero massimo di livelli di link da seguire</small>
        </p>

        <p>
            <label>Limite massimo URL<br>
                <select name="max_urls">
                    <option value="100">100 URL</option>
                    <option value="250">250 URL</option>
                    <option value="500" selected>500 URL</option>
                    <option value="1000">1000 URL</option>
                    <option value="2000">2000 URL</option>
                </select>
            </label>
            <small>Numero massimo di pagine da scansionare</small>
        </p>
    </div>

    <div class="form-section">
        <h3>Descrizioni (opzionali)</h3>

        <p>
            <label>Riassunto sito (1-2 frasi per il blockquote)<br>
                <textarea name="site_summary" rows="3"
                          placeholder="Una breve descrizione del sito che apparirà nel file llms.txt"></textarea>
            </label>
        </p>

        <p>
            <label>Descrizione aggiuntiva<br>
                <textarea name="description" rows="4"
                          placeholder="Informazioni aggiuntive sul progetto (uso interno)"></textarea>
            </label>
        </p>
    </div>

    <p class="form-actions">
        <button type="submit" class="btn btn-primary">Crea progetto</button>
        <a href="<?= htmlspecialchars($baseUrl) ?>/" class="btn btn-secondary">Annulla</a>
    </p>
</form>

<script>
function extractDomain(url) {
    try {
        const urlObj = new URL(url);
        document.getElementById('domain').value = urlObj.hostname;
    } catch (e) {
        document.getElementById('domain').value = '';
    }
}

// Auto-extract on page load if URL is already filled
document.addEventListener('DOMContentLoaded', function() {
    const urlInput = document.getElementById('homepage_url');
    if (urlInput.value) {
        extractDomain(urlInput.value);
    }
});
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

.form-section input[readonly] {
    background-color: #f5f5f5;
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
