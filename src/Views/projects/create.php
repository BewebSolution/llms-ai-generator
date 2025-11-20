<h2>Nuovo progetto</h2>

<form method="post" action="<?= htmlspecialchars($baseUrl) ?>/projects/store">
    <p>
        <label>Nome<br>
            <input type="text" name="name" required>
        </label>
    </p>
    <p>
        <label>Dominio (es. www.amevista.com)<br>
            <input type="text" name="domain" required>
        </label>
    </p>
    <p>
        <label>Slug (se vuoto viene generato)<br>
            <input type="text" name="slug">
        </label>
    </p>
    <p>
        <label>Riassunto sito (1-2 frasi per il blockquote)<br>
            <textarea name="site_summary" rows="3"></textarea>
        </label>
    </p>
    <p>
        <label>Descrizione aggiuntiva (paragrafo opzionale)<br>
            <textarea name="description" rows="5"></textarea>
        </label>
    </p>
    <p>
        <button type="submit">Salva</button>
    </p>
</form>