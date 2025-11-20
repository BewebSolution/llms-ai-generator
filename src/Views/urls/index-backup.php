<h2>URL per il progetto: <?= htmlspecialchars($project['name']) ?></h2>

<!-- Controllo se OpenAI è abilitato -->
<?php
$config = \LlmsApp\Services\ConfigService::getInstance();
$openaiEnabledValue = $config->get('openai_enabled', 'false');
$openaiEnabled = $openaiEnabledValue === 'true' ||
                 $openaiEnabledValue === true ||
                 $openaiEnabledValue === '1' ||
                 $openaiEnabledValue === 1;
$openaiKey = $config->get('openai_api_key', '');
?>

<?php if ($openaiEnabled && !empty($openaiKey)): ?>
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
    <h3 style="margin-top: 0;">🤖 Generazione Descrizioni con AI</h3>
    <p>OpenAI è configurato e pronto! Puoi generare automaticamente le descrizioni per gli URL selezionati.</p>

    <div style="display: flex; gap: 10px; margin-top: 15px;">
        <button onclick="generateAIForSelected()" style="background: white; color: #667eea; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">
            ✨ Genera per URL Selezionati
        </button>
        <button onclick="generateAIForAll()" style="background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border: 2px solid white; border-radius: 5px; cursor: pointer; font-weight: bold;">
            🚀 Genera per TUTTI gli URL
        </button>
    </div>

    <div id="ai-progress" style="display: none; margin-top: 20px;">
        <div style="background: rgba(255,255,255,0.2); border-radius: 10px; padding: 2px;">
            <div id="ai-progress-bar" style="background: white; height: 20px; border-radius: 8px; width: 0%; transition: width 0.3s;"></div>
        </div>
        <p id="ai-status" style="margin-top: 10px;">Preparazione...</p>
    </div>
</div>
<?php else: ?>
<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
    <strong>⚠️ AI non configurata</strong>
    <p>Per generare automaticamente le descrizioni, configura OpenAI nelle <a href="<?= htmlspecialchars($baseUrl) ?>/settings">impostazioni</a>.</p>
</div>
<?php endif; ?>

<!-- Filtri esistenti -->
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

    <button type="button" onclick="selectAllVisible()" style="background: #28a745; color: white; padding: 5px 10px; border: none; border-radius: 3px; margin-left: 10px;">
        ✓ Seleziona Tutti
    </button>

    <button type="button" onclick="deselectAllVisible()" style="background: #dc3545; color: white; padding: 5px 10px; border: none; border-radius: 3px;">
        ✗ Deseleziona Tutti
    </button>
</form>

<form method="post" action="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/urls/bulk-update" id="urlForm">
    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <thead style="background: #f8f9fa;">
        <tr>
            <th style="padding: 10px; text-align: left;">
                <input type="checkbox" id="selectAll" onchange="toggleAll(this)">
            </th>
            <th style="padding: 10px; text-align: left;">URL</th>
            <th style="padding: 10px; text-align: left;">Tipo</th>
            <th style="padding: 10px; text-align: left;">Titolo</th>
            <th style="padding: 10px; text-align: left;">
                Descrizione breve
                <span style="font-weight: normal; font-size: 12px;">(AI disponibile)</span>
            </th>
            <th style="padding: 10px; text-align: center;">AI</th>
            <th style="padding: 10px; text-align: left;">Sezione</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($urls)): ?>
            <tr>
                <td colspan="7" style="padding: 20px; text-align: center; color: #999;">
                    Nessun URL trovato. Aggiungi e parsa una sitemap per iniziare.
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($urls as $url): ?>
                <?php
                    $rowId = (int)$url['id'];
                    $titleValue = $url['title'] ?? '';
                    $loc = $url['loc'];
                    $hasDescription = !empty($url['short_description']);
                ?>
                <tr data-url-row-id="<?= $rowId ?>" style="border-bottom: 1px solid #dee2e6;">
                    <td style="padding: 10px;">
                        <input type="checkbox"
                               name="urls[<?= $rowId ?>][is_selected]"
                               value="1"
                               class="url-checkbox"
                               <?= $url['is_selected'] ? 'checked' : '' ?>>
                    </td>
                    <td style="padding: 10px;">
                        <a href="<?= htmlspecialchars($loc) ?>" target="_blank" rel="noopener" title="<?= htmlspecialchars($loc) ?>" style="color: #007bff; text-decoration: none;">
                            <?= htmlspecialchars(substr($loc, 0, 50)) ?><?= strlen($loc) > 50 ? '...' : '' ?>
                        </a>
                    </td>
                    <td style="padding: 10px;">
                        <span style="background: #e9ecef; padding: 3px 8px; border-radius: 3px; font-size: 12px;">
                            <?= htmlspecialchars($url['type']) ?>
                        </span>
                    </td>
                    <td style="padding: 10px;">
                        <input type="text"
                               class="url-title-input"
                               name="urls[<?= $rowId ?>][title]"
                               value="<?= htmlspecialchars($titleValue) ?>"
                               style="width: 100%; padding: 5px; border: 1px solid #ced4da; border-radius: 3px;"
                               data-url="<?= htmlspecialchars($loc) ?>">
                    </td>
                    <td style="padding: 10px;">
                        <textarea
                               class="url-desc-input"
                               name="urls[<?= $rowId ?>][short_description]"
                               rows="2"
                               style="width: 100%; padding: 5px; border: 1px solid #ced4da; border-radius: 3px; resize: vertical;"><?= htmlspecialchars($url['short_description'] ?? '') ?></textarea>
                        <?php if ($hasDescription): ?>
                            <small style="color: #28a745;">✓ Ha descrizione</small>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <?php if ($openaiEnabled && !empty($openaiKey)): ?>
                        <button type="button"
                                class="btn-ai-desc"
                                onclick="generateAIForRow(<?= $rowId ?>)"
                                data-row-id="<?= $rowId ?>"
                                data-url="<?= htmlspecialchars($loc) ?>"
                                style="background: #667eea; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer;">
                            🤖
                        </button>
                        <span class="ai-status" data-row-id="<?= $rowId ?>"></span>
                        <?php else: ?>
                        <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 10px;">
                        <select name="section[<?= $rowId ?>]" style="padding: 5px; border: 1px solid #ced4da; border-radius: 3px;">
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
        <div style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 5px;">
            <button type="submit" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
                💾 Salva tutte le modifiche
            </button>
        </div>
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

<script>
// Funzioni di selezione
function selectAllVisible() {
    document.querySelectorAll('.url-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('selectAll').checked = true;
}

function deselectAllVisible() {
    document.querySelectorAll('.url-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
}

function toggleAll(source) {
    document.querySelectorAll('.url-checkbox').forEach(cb => cb.checked = source.checked);
}

// Funzioni AI
async function generateAIForRow(rowId) {
    const row = document.querySelector(`tr[data-url-row-id="${rowId}"]`);
    const titleInput = row.querySelector('.url-title-input');
    const descInput = row.querySelector('.url-desc-input');
    const statusSpan = row.querySelector('.ai-status');

    const title = titleInput.value || 'Untitled';
    const url = titleInput.dataset.url;

    statusSpan.innerHTML = '⏳';

    try {
        const response = await fetch('<?= htmlspecialchars($baseUrl) ?>/api/ai/description', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ title, url })
        });

        const data = await response.json();

        if (data.description) {
            descInput.value = data.description;
            statusSpan.innerHTML = '✅';
            setTimeout(() => statusSpan.innerHTML = '', 3000);
        } else {
            statusSpan.innerHTML = '❌';
            alert('Errore: ' + (data.error || 'Errore sconosciuto'));
        }
    } catch (error) {
        statusSpan.innerHTML = '❌';
        console.error('Errore AI:', error);
        alert('Errore di connessione');
    }
}

async function generateAIForSelected() {
    const selected = document.querySelectorAll('.url-checkbox:checked');
    if (selected.length === 0) {
        alert('Seleziona almeno un URL');
        return;
    }

    if (!confirm(`Generare descrizioni AI per ${selected.length} URL selezionati?`)) {
        return;
    }

    const progressDiv = document.getElementById('ai-progress');
    const progressBar = document.getElementById('ai-progress-bar');
    const statusText = document.getElementById('ai-status');

    progressDiv.style.display = 'block';
    let processed = 0;

    for (const checkbox of selected) {
        const row = checkbox.closest('tr');
        const rowId = row.dataset.urlRowId;

        statusText.textContent = `Generando ${processed + 1} di ${selected.length}...`;
        progressBar.style.width = ((processed / selected.length) * 100) + '%';

        await generateAIForRow(rowId);
        await new Promise(resolve => setTimeout(resolve, 1000)); // Pausa 1 secondo tra richieste

        processed++;
    }

    progressBar.style.width = '100%';
    statusText.textContent = `✅ Completato! Generate ${selected.length} descrizioni`;

    setTimeout(() => {
        progressDiv.style.display = 'none';
    }, 3000);
}

async function generateAIForAll() {
    const allRows = document.querySelectorAll('tr[data-url-row-id]');
    if (!confirm(`Generare descrizioni AI per TUTTI i ${allRows.length} URL? Questa operazione potrebbe richiedere tempo.`)) {
        return;
    }

    const progressDiv = document.getElementById('ai-progress');
    const progressBar = document.getElementById('ai-progress-bar');
    const statusText = document.getElementById('ai-status');

    progressDiv.style.display = 'block';
    let processed = 0;

    for (const row of allRows) {
        const rowId = row.dataset.urlRowId;

        statusText.textContent = `Generando ${processed + 1} di ${allRows.length}...`;
        progressBar.style.width = ((processed / allRows.length) * 100) + '%';

        await generateAIForRow(rowId);
        await new Promise(resolve => setTimeout(resolve, 1000)); // Pausa 1 secondo tra richieste

        processed++;
    }

    progressBar.style.width = '100%';
    statusText.textContent = `✅ Completato! Generate ${allRows.length} descrizioni`;

    setTimeout(() => {
        progressDiv.style.display = 'none';
        alert('Ricordati di salvare le modifiche!');
    }, 3000);
}
</script>