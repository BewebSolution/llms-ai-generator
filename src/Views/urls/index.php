<h2>URL per il progetto: <?= htmlspecialchars($project['name']) ?></h2>

<!-- Controllo se OpenAI è abilitato -->
<?php
// Funzione helper PHP per i colori dei tipi
function getTypeColorPHP($type) {
    $colors = [
        'HOMEPAGE' => '#007bff',
        'CATEGORY' => '#28a745',
        'PRODUCT' => '#17a2b8',
        'GUIDE' => '#ffc107',
        'POLICY' => '#dc3545',
        'SUPPORT' => '#6610f2',
        'BLOG' => '#e83e8c',
        'OTHER' => '#6c757d'
    ];
    return $colors[$type] ?? '#6c757d';
}

// LEGGI TUTTO DIRETTAMENTE DAL DATABASE per evitare problemi di cache
$pdo = \LlmsApp\Config\Database::getConnection();

// OpenAI enabled
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'openai_enabled'");
$stmt->execute();
$dbValue = $stmt->fetch()['setting_value'] ?? 'false';
$openaiEnabled = ($dbValue === 'true' || $dbValue === '1');

// OpenAI API Key - LEGGI DAL DATABASE
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'openai_api_key'");
$stmt->execute();
$openaiKey = $stmt->fetch()['setting_value'] ?? '';

// Debug info (rimuovi in produzione)
error_log("OpenAI Debug - DB Value: " . var_export($dbValue, true) . ", Enabled: " . var_export($openaiEnabled, true) . ", Key: " . (!empty($openaiKey) ? 'presente' : 'mancante'));
?>

<?php if ($openaiEnabled && !empty($openaiKey)): ?>
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
    <h3 style="margin-top: 0;">🤖 Generazione Descrizioni con AI</h3>
    <p>OpenAI è configurato e pronto! Puoi generare automaticamente le descrizioni per gli URL.</p>

    <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
        <button onclick="generateAIForSelected()"
                id="btnGenerateSelected"
                style="background: white; color: #667eea; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">
            ✨ Genera AI per Selezionati
        </button>
        <button onclick="generateTitlesOnly()"
                id="btnGenerateTitles"
                style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">
            📝 Genera Solo Titoli Mancanti
        </button>
        <button onclick="classifyTypesOnly()"
                id="btnClassifyTypes"
                style="background: #6610f2; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">
            🏷️ Classifica Tipi con AI
        </button>
        <button onclick="generateAIForAll()"
                id="btnGenerateAll"
                style="background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border: 2px solid white; border-radius: 5px; cursor: pointer; font-weight: bold;">
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

<!-- Contatore risultati -->
<div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <strong>📊 Statistiche URL:</strong>
        <span style="margin-left: 20px;">
            Totale: <span style="background: #2196f3; color: white; padding: 2px 8px; border-radius: 3px;" id="totalCount"><?= count($urls) ?></span>
        </span>
        <span style="margin-left: 20px;">
            Selezionati: <span style="background: #4caf50; color: white; padding: 2px 8px; border-radius: 3px;" id="selectedCount"><?= $selectedCount ?? 0 ?></span>
        </span>
        <span style="margin-left: 20px;">
            Pagina <?= $currentPage ?? 1 ?> di <?= $totalPages ?? 1 ?>
        </span>
    </div>
    <div>
        <span style="color: #666; font-size: 14px;">
            <?php if (isset($currentPerPage) && isset($start)): ?>
                Mostrando <?= min($start + 1, count($urls)) ?>-<?= min($start + $currentPerPage, count($urls)) ?> di <?= count($urls) ?> risultati
            <?php endif; ?>
        </span>
    </div>
</div>

<!-- Controlli tabella -->
<form method="get" action="" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <label>Tipo:
            <select name="type" style="padding: 5px;">
                <option value="">Tutti</option>
                <option value="POLICY" <?= ($filters['type'] ?? '') === 'POLICY' ? 'selected' : '' ?>>Policy</option>
                <option value="CATEGORY" <?= ($filters['type'] ?? '') === 'CATEGORY' ? 'selected' : '' ?>>Categoria</option>
                <option value="GUIDE" <?= ($filters['type'] ?? '') === 'GUIDE' ? 'selected' : '' ?>>Guide</option>
                <option value="SUPPORT" <?= ($filters['type'] ?? '') === 'SUPPORT' ? 'selected' : '' ?>>Supporto</option>
                <option value="OTHER" <?= ($filters['type'] ?? '') === 'OTHER' ? 'selected' : '' ?>>Altro</option>
            </select>
        </label>

        <label>Selezionati:
            <select name="is_selected" style="padding: 5px;">
                <option value="">Tutti</option>
                <option value="1" <?= (isset($filters['is_selected']) && $filters['is_selected'] === 1) ? 'selected' : '' ?>>Solo selezionati</option>
                <option value="0" <?= (isset($filters['is_selected']) && $filters['is_selected'] === 0) ? 'selected' : '' ?>>Non selezionati</option>
            </select>
        </label>

        <label>Mostra:
            <select name="per_page" style="padding: 5px;" onchange="this.form.submit()">
                <?php
                $perPageOptions = [10, 30, 50, 100, 500, 1000];
                $currentPerPage = (int)($_GET['per_page'] ?? 30);
                foreach ($perPageOptions as $opt): ?>
                    <option value="<?= $opt ?>" <?= $currentPerPage === $opt ? 'selected' : '' ?>><?= $opt ?> righe</option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Cerca:
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Cerca URL..." style="padding: 5px;">
        </label>

        <button type="submit" style="background: #007bff; color: white; padding: 5px 15px; border: none; border-radius: 3px;">Filtra</button>

        <div style="margin-left: auto;">
            <button type="button" onclick="selectAllVisible()" id="btnSelectAll" style="background: #28a745; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer;">
                ✓ Seleziona Tutti
            </button>
            <button type="button" onclick="deselectAllVisible()" id="btnDeselectAll" style="background: #dc3545; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer;">
                ✗ Deseleziona Tutti
            </button>
            <!-- Pulsante di emergenza con metodo alternativo -->
            <button type="button" onclick="forceSelectAll()" style="background: #ffc107; color: black; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer; display: none;" id="btnForceSelect">
                ⚡ Forza Selezione
            </button>
        </div>
    </div>
</form>

<!-- Paginazione Top -->
<?php
$currentPage = (int)($_GET['page'] ?? 1);
$totalUrls = count($urls); // Assumendo che $urls contenga tutti i risultati filtrati
$totalPages = ceil($totalUrls / $currentPerPage);

// Calcola range di pagine da mostrare
$maxVisible = 7; // Numero massimo di pagine da mostrare
$halfVisible = floor($maxVisible / 2);

if ($totalPages <= $maxVisible) {
    $startPage = 1;
    $endPage = $totalPages;
} else {
    if ($currentPage <= $halfVisible) {
        $startPage = 1;
        $endPage = $maxVisible;
    } elseif ($currentPage > $totalPages - $halfVisible) {
        $startPage = $totalPages - $maxVisible + 1;
        $endPage = $totalPages;
    } else {
        $startPage = $currentPage - $halfVisible;
        $endPage = $currentPage + $halfVisible;
    }
}

// URL base per la paginazione
$baseParams = http_build_query([
    'per_page' => $currentPerPage,
    'type' => $filters['type'] ?? '',
    'is_selected' => $filters['is_selected'] ?? '',
    'search' => $filters['search'] ?? ''
]);
?>

<?php if ($totalPages > 1): ?>
<div class="pagination-container" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 10px; background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">

    <!-- Info pagina sinistra -->
    <div style="color: #666; font-size: 14px;">
        Pagina <strong><?= $currentPage ?></strong> di <strong><?= $totalPages ?></strong>
        (<?= number_format($totalUrls) ?> URL totali)
    </div>

    <!-- Controlli paginazione centro -->
    <div style="display: flex; align-items: center; gap: 5px;">

        <!-- Prima pagina -->
        <?php if ($currentPage > 1): ?>
            <a href="?page=1&<?= $baseParams ?>"
               class="pagination-btn"
               title="Prima pagina"
               style="padding: 6px 12px; background: #f8f9fa; color: #333; text-decoration: none; border-radius: 4px; border: 1px solid #dee2e6; display: inline-flex; align-items: center; transition: all 0.2s;">
                ⏮️
            </a>
        <?php else: ?>
            <span style="padding: 6px 12px; background: #e9ecef; color: #aaa; border-radius: 4px; border: 1px solid #dee2e6; display: inline-flex; align-items: center;">⏮️</span>
        <?php endif; ?>

        <!-- Pagina precedente -->
        <?php if ($currentPage > 1): ?>
            <a href="?page=<?= $currentPage - 1 ?>&<?= $baseParams ?>"
               class="pagination-btn"
               title="Pagina precedente"
               style="padding: 6px 12px; background: #f8f9fa; color: #333; text-decoration: none; border-radius: 4px; border: 1px solid #dee2e6; display: inline-flex; align-items: center; transition: all 0.2s;">
                ◀️ Prec
            </a>
        <?php else: ?>
            <span style="padding: 6px 12px; background: #e9ecef; color: #aaa; border-radius: 4px; border: 1px solid #dee2e6; display: inline-flex; align-items: center;">◀️ Prec</span>
        <?php endif; ?>

        <!-- Numeri pagina con ellipsis -->
        <?php if ($startPage > 1): ?>
            <span style="padding: 0 5px; color: #999;">...</span>
        <?php endif; ?>

        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <?php if ($i == $currentPage): ?>
                <span style="padding: 6px 12px; background: #007bff; color: white; border-radius: 4px; font-weight: bold;">
                    <?= $i ?>
                </span>
            <?php else: ?>
                <a href="?page=<?= $i ?>&<?= $baseParams ?>"
                   class="pagination-btn pagination-number"
                   style="padding: 6px 12px; background: #f8f9fa; color: #333; text-decoration: none; border-radius: 4px; border: 1px solid #dee2e6; transition: all 0.2s;">
                    <?= $i ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($endPage < $totalPages): ?>
            <span style="padding: 0 5px; color: #999;">...</span>
        <?php endif; ?>

        <!-- Pagina successiva -->
        <?php if ($currentPage < $totalPages): ?>
            <a href="?page=<?= $currentPage + 1 ?>&<?= $baseParams ?>"
               class="pagination-btn"
               title="Pagina successiva"
               style="padding: 6px 12px; background: #f8f9fa; color: #333; text-decoration: none; border-radius: 4px; border: 1px solid #dee2e6; display: inline-flex; align-items: center; transition: all 0.2s;">
                Succ ▶️
            </a>
        <?php else: ?>
            <span style="padding: 6px 12px; background: #e9ecef; color: #aaa; border-radius: 4px; border: 1px solid #dee2e6; display: inline-flex; align-items: center;">Succ ▶️</span>
        <?php endif; ?>

        <!-- Ultima pagina -->
        <?php if ($currentPage < $totalPages): ?>
            <a href="?page=<?= $totalPages ?>&<?= $baseParams ?>"
               class="pagination-btn"
               title="Ultima pagina"
               style="padding: 6px 12px; background: #f8f9fa; color: #333; text-decoration: none; border-radius: 4px; border: 1px solid #dee2e6; display: inline-flex; align-items: center; transition: all 0.2s;">
                ⏭️
            </a>
        <?php else: ?>
            <span style="padding: 6px 12px; background: #e9ecef; color: #aaa; border-radius: 4px; border: 1px solid #dee2e6; display: inline-flex; align-items: center;">⏭️</span>
        <?php endif; ?>
    </div>

    <!-- Jump to page destra -->
    <div style="display: flex; align-items: center; gap: 10px;">
        <label style="color: #666; font-size: 14px;">Vai a:</label>
        <input type="number"
               id="jumpToPage"
               min="1"
               max="<?= $totalPages ?>"
               value="<?= $currentPage ?>"
               style="width: 60px; padding: 4px 8px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;">
        <button onclick="jumpToPage()"
                type="button"
                style="padding: 4px 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
            Vai
        </button>
    </div>
</div>

<style>
/* Stili hover per i pulsanti di paginazione */
.pagination-btn:hover {
    background: #007bff !important;
    color: white !important;
    border-color: #007bff !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,123,255,0.2);
}

.pagination-number:hover {
    font-weight: bold;
}

/* Animazione per il jump input */
#jumpToPage:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
}
</style>

<script>
function jumpToPage() {
    const input = document.getElementById('jumpToPage');
    const page = parseInt(input.value);
    const maxPage = <?= $totalPages ?>;

    if (page >= 1 && page <= maxPage) {
        window.location.href = '?page=' + page + '&<?= $baseParams ?>';
    } else {
        alert('Inserisci un numero di pagina valido tra 1 e ' + maxPage);
        input.value = <?= $currentPage ?>;
    }
}

// Permetti di premere Enter nel campo jump to page
document.getElementById('jumpToPage').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        jumpToPage();
    }
});
</script>
<?php endif; ?>

<form method="post" action="<?= htmlspecialchars($baseUrl) ?>/projects/<?= (int)$project['id'] ?>/urls/bulk-update" id="urlForm">
    <table style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <thead style="background: #f8f9fa;">
        <tr>
            <th style="padding: 10px; text-align: left; width: 40px;">
                <input type="checkbox" id="selectAll" onchange="toggleAll(this)">
            </th>
            <th style="padding: 10px; text-align: left;">URL</th>
            <th style="padding: 10px; text-align: left; width: 100px;">Tipo</th>
            <th style="padding: 10px; text-align: left;">Titolo</th>
            <th style="padding: 10px; text-align: left;">
                Descrizione breve
                <?php if ($openaiEnabled && !empty($openaiKey)): ?>
                <span style="font-weight: normal; font-size: 12px; color: #667eea;">(AI disponibile)</span>
                <?php endif; ?>
            </th>
            <th style="padding: 10px; text-align: center; width: 120px;">Azioni</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($urls)): ?>
            <tr>
                <td colspan="6" style="padding: 40px; text-align: center; color: #999;">
                    Nessun URL trovato. Aggiungi e parsa una sitemap per iniziare.
                </td>
            </tr>
        <?php else: ?>
            <?php
            // Paginazione
            $start = ($currentPage - 1) * $currentPerPage;
            $paginatedUrls = array_slice($urls, $start, $currentPerPage);

            foreach ($paginatedUrls as $url):
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
                        <a href="<?= htmlspecialchars($loc) ?>" target="_blank" rel="noopener"
                           title="<?= htmlspecialchars($loc) ?>"
                           style="color: #007bff; text-decoration: none;">
                            <?= htmlspecialchars(substr($loc, 0, 50)) ?><?= strlen($loc) > 50 ? '...' : '' ?>
                        </a>
                    </td>
                    <td style="padding: 10px;">
                        <div style="position: relative;">
                            <span class="url-type-badge"
                                  onclick="toggleTypeSelect(<?= $rowId ?>)"
                                  style="background: <?= getTypeColorPHP($url['type']) ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px; transition: all 0.3s; cursor: pointer; display: inline-block;">
                                <?= htmlspecialchars($url['type']) ?> ▼
                            </span>
                            <select class="url-type-select"
                                    id="type-select-<?= $rowId ?>"
                                    name="urls[<?= $rowId ?>][type]"
                                    onchange="updateTypeBadge(<?= $rowId ?>, this.value)"
                                    style="display: none; position: absolute; top: 0; left: 0; padding: 3px 8px; font-size: 12px; border-radius: 3px; cursor: pointer;">
                                <option value="HOMEPAGE" <?= $url['type'] === 'HOMEPAGE' ? 'selected' : '' ?>>🏠 HOMEPAGE</option>
                                <option value="CATEGORY" <?= $url['type'] === 'CATEGORY' ? 'selected' : '' ?>>📁 CATEGORY</option>
                                <option value="PRODUCT" <?= $url['type'] === 'PRODUCT' ? 'selected' : '' ?>>📦 PRODUCT</option>
                                <option value="GUIDE" <?= $url['type'] === 'GUIDE' ? 'selected' : '' ?>>📚 GUIDE</option>
                                <option value="POLICY" <?= $url['type'] === 'POLICY' ? 'selected' : '' ?>>📜 POLICY</option>
                                <option value="SUPPORT" <?= $url['type'] === 'SUPPORT' ? 'selected' : '' ?>>🆘 SUPPORT</option>
                                <option value="BLOG" <?= $url['type'] === 'BLOG' ? 'selected' : '' ?>>📝 BLOG</option>
                                <option value="OTHER" <?= $url['type'] === 'OTHER' ? 'selected' : '' ?>>❓ OTHER</option>
                            </select>
                        </div>
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
                        <div style="display: flex; gap: 5px; justify-content: center;">
                            <?php if ($openaiEnabled && !empty($openaiKey)): ?>
                            <button type="button"
                                    class="btn-ai-desc"
                                    onclick="generateAIForRow(<?= $rowId ?>)"
                                    data-row-id="<?= $rowId ?>"
                                    title="Genera descrizione con AI"
                                    style="background: #667eea; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer;">
                                🤖
                            </button>
                            <?php endif; ?>
                            <button type="button"
                                    onclick="deleteUrl(<?= $rowId ?>)"
                                    title="Elimina URL"
                                    style="background: #dc3545; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer;">
                                🗑️
                            </button>
                        </div>
                        <span class="ai-status" data-row-id="<?= $rowId ?>" style="font-size: 12px;"></span>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if (!empty($urls)): ?>
        <!-- Controlli batch sotto la tabella -->
        <div style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 5px;">
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <button type="submit" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;"
                        title="Salva le modifiche manuali effettuate nei campi (le modifiche AI sono già salvate automaticamente)">
                    💾 Salva modifiche manuali
                </button>

                <button type="button"
                        onclick="deleteSelectedUrls()"
                        style="background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;"
                        title="Elimina permanentemente gli URL selezionati">
                    🗑️ Elimina URL Selezionate
                </button>

                <?php if ($openaiEnabled && !empty($openaiKey)): ?>
                <div style="margin-left: auto; display: flex; gap: 10px;">
                    <button type="button"
                            onclick="generateAIForSelected()"
                            style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;"
                            title="Genera titoli, descrizioni e classificazioni con AI per gli URL selezionati (salvataggio automatico)">
                        🤖 Processa Selezionati con AI
                    </button>
                    <button type="button"
                            onclick="generateAIForVisible()"
                            style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;"
                            title="Genera titoli, descrizioni e classificazioni con AI per tutti gli URL di questa pagina (salvataggio automatico)">
                        📝 Processa Pagina Corrente con AI
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Info box per chiarire il funzionamento -->
            <div style="margin-top: 15px; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; color: #0c5460;">
                <small>
                    ℹ️ <strong>Note:</strong>
                    • I risultati generati dall'AI vengono <strong>salvati automaticamente</strong> nel database
                    • Il bottone "Salva modifiche manuali" serve solo per salvare le modifiche fatte manualmente nei campi
                    • Il file llms.txt usa sempre e solo gli URL con checkbox ✅ selezionata
                </small>
            </div>
        </div>
    <?php endif; ?>
</form>

<!-- Paginazione Bottom -->
<?php if ($totalPages > 1): ?>
<div class="pagination-container" style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 10px; background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">

    <!-- Info pagina sinistra -->
    <div style="color: #666; font-size: 14px;">
        Pagina <strong><?= $currentPage ?></strong> di <strong><?= $totalPages ?></strong>
        (<?= number_format($totalUrls) ?> URL totali)
    </div>

    <!-- Controlli paginazione centro -->
    <div style="display: flex; align-items: center; gap: 5px;">

        <!-- Prima pagina -->
        <?php if ($currentPage > 1): ?>
            <a href="?page=1&<?= $baseParams ?>"
               class="pagination-btn"
               title="Prima pagina"
               style="padding: 6px 12px; background: #f8f9fa; color: #333; text-decoration: none; border-radius: 4px; border: 1px solid #dee2e6; display: inline-flex; align-items: center; transition: all 0.2s;">
                ⏮️
            </a>
        <?php else: ?>
            <span style="padding: 6px 12px; background: #e9ecef; color: #aaa; border-radius: 4px; border: 1px solid #dee2e6; display: inline-flex; align-items: center;">⏮️</span>
        <?php endif; ?>

        <!-- Pagina precedente -->
        <?php if ($currentPage > 1): ?>
            <a href="?page=<?= $currentPage - 1 ?>&<?= $baseParams ?>"
               class="pagination-btn"
               title="Pagina precedente"
               style="padding: 6px 12px; background: #f8f9fa; color: #333; text-decoration: none; border-radius: 4px; border: 1px solid #dee2e6; display: inline-flex; align-items: center; transition: all 0.2s;">
                ◀️ Prec
            </a>
        <?php else: ?>
            <span style="padding: 6px 12px; background: #e9ecef; color: #aaa; border-radius: 4px; border: 1px solid #dee2e6; display: inline-flex; align-items: center;">◀️ Prec</span>
        <?php endif; ?>

        <!-- Numeri pagina con ellipsis -->
        <?php if ($startPage > 1): ?>
            <span style="padding: 0 5px; color: #999;">...</span>
        <?php endif; ?>

        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <?php if ($i == $currentPage): ?>
                <span style="padding: 6px 12px; background: #007bff; color: white; border-radius: 4px; font-weight: bold;">
                    <?= $i ?>
                </span>
            <?php else: ?>
                <a href="?page=<?= $i ?>&<?= $baseParams ?>"
                   class="pagination-btn pagination-number"
                   style="padding: 6px 12px; background: #f8f9fa; color: #333; text-decoration: none; border-radius: 4px; border: 1px solid #dee2e6; transition: all 0.2s;">
                    <?= $i ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($endPage < $totalPages): ?>
            <span style="padding: 0 5px; color: #999;">...</span>
        <?php endif; ?>

        <!-- Pagina successiva -->
        <?php if ($currentPage < $totalPages): ?>
            <a href="?page=<?= $currentPage + 1 ?>&<?= $baseParams ?>"
               class="pagination-btn"
               title="Pagina successiva"
               style="padding: 6px 12px; background: #f8f9fa; color: #333; text-decoration: none; border-radius: 4px; border: 1px solid #dee2e6; display: inline-flex; align-items: center; transition: all 0.2s;">
                Succ ▶️
            </a>
        <?php else: ?>
            <span style="padding: 6px 12px; background: #e9ecef; color: #aaa; border-radius: 4px; border: 1px solid #dee2e6; display: inline-flex; align-items: center;">Succ ▶️</span>
        <?php endif; ?>

        <!-- Ultima pagina -->
        <?php if ($currentPage < $totalPages): ?>
            <a href="?page=<?= $totalPages ?>&<?= $baseParams ?>"
               class="pagination-btn"
               title="Ultima pagina"
               style="padding: 6px 12px; background: #f8f9fa; color: #333; text-decoration: none; border-radius: 4px; border: 1px solid #dee2e6; display: inline-flex; align-items: center; transition: all 0.2s;">
                ⏭️
            </a>
        <?php else: ?>
            <span style="padding: 6px 12px; background: #e9ecef; color: #aaa; border-radius: 4px; border: 1px solid #dee2e6; display: inline-flex; align-items: center;">⏭️</span>
        <?php endif; ?>
    </div>

    <!-- Jump to page destra -->
    <div style="display: flex; align-items: center; gap: 10px;">
        <label style="color: #666; font-size: 14px;">Vai a:</label>
        <input type="number"
               id="jumpToPageBottom"
               min="1"
               max="<?= $totalPages ?>"
               value="<?= $currentPage ?>"
               style="width: 60px; padding: 4px 8px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;">
        <button onclick="jumpToPageBottom()"
                type="button"
                style="padding: 4px 12px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
            Vai
        </button>
    </div>
</div>

<script>
function jumpToPageBottom() {
    const input = document.getElementById('jumpToPageBottom');
    const page = parseInt(input.value);
    const maxPage = <?= $totalPages ?>;

    if (page >= 1 && page <= maxPage) {
        window.location.href = '?page=' + page + '&<?= $baseParams ?>';
    } else {
        alert('Inserisci un numero di pagina valido tra 1 e ' + maxPage);
        input.value = <?= $currentPage ?>;
    }
}

// Permetti di premere Enter nel campo jump to page bottom
document.getElementById('jumpToPageBottom').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        jumpToPageBottom();
    }
});
</script>
<?php endif; ?>

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
// FUNZIONE AGGIORNAMENTO CONTATORE - RIFATTA E TESTATA
function updateSelectedCount() {
    console.log('--- Aggiornamento contatore ---');

    // Prova metodi multipli per trovare i checkbox
    let allCheckboxes = document.querySelectorAll('input[type="checkbox"].url-checkbox');
    if (allCheckboxes.length === 0) {
        allCheckboxes = document.querySelectorAll('.url-checkbox');
    }
    if (allCheckboxes.length === 0) {
        allCheckboxes = document.querySelectorAll('input[type="checkbox"][name*="is_selected"]');
    }

    // Conta manualmente i checked
    let checkedCount = 0;
    for (let i = 0; i < allCheckboxes.length; i++) {
        if (allCheckboxes[i].checked === true) {
            checkedCount++;
        }
    }

    const totalCount = allCheckboxes.length;
    console.log(`Conteggio: ${checkedCount}/${totalCount} selezionati`);

    // Aggiorna il contatore nell'UI
    const counterElement = document.getElementById('selectedCount');
    if (counterElement) {
        counterElement.textContent = checkedCount;

        // Cambia colore del badge in base alla selezione
        if (checkedCount === 0) {
            counterElement.style.background = '#9e9e9e'; // Grigio
        } else if (checkedCount === totalCount && totalCount > 0) {
            counterElement.style.background = '#ff9800'; // Arancione se tutti
        } else {
            counterElement.style.background = '#4caf50'; // Verde parziale
        }
    }

    // Sincronizza il checkbox "Seleziona tutti" nell'header
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        if (totalCount === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedCount === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedCount === totalCount) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true; // Stato intermedio
        }
    }
}

// Inizializza il contatore al caricamento della pagina
document.addEventListener('DOMContentLoaded', function() {
    console.log('=== INIZIALIZZAZIONE PAGINA URL ===');

    // TEST DIAGNOSTICO - Verifica che i checkbox siano trovati
    console.log('TEST 1: Checkbox con classe url-checkbox:', document.querySelectorAll('.url-checkbox').length);
    console.log('TEST 2: Input checkbox con classe:', document.querySelectorAll('input[type="checkbox"].url-checkbox').length);
    console.log('TEST 3: Checkbox con name is_selected:', document.querySelectorAll('input[type="checkbox"][name*="is_selected"]').length);

    // Mostra il primo checkbox trovato per debug
    const firstCheckbox = document.querySelector('.url-checkbox');
    if (firstCheckbox) {
        console.log('Primo checkbox trovato:', firstCheckbox);
        console.log('HTML del checkbox:', firstCheckbox.outerHTML);
    } else {
        console.error('ATTENZIONE: Nessun checkbox trovato con classe .url-checkbox!');
    }

    // Aggiorna contatore iniziale
    updateSelectedCount();

    // Aggiungi listener a TUTTI i checkbox (con fallback multipli)
    let checkboxes = document.querySelectorAll('.url-checkbox');
    if (checkboxes.length === 0) {
        checkboxes = document.querySelectorAll('input[type="checkbox"][name*="is_selected"]');
    }

    console.log(`Aggiungo listener a ${checkboxes.length} checkbox`);
    checkboxes.forEach((cb, index) => {
        cb.addEventListener('change', function() {
            console.log(`Checkbox ${index + 1} cambiato a:`, this.checked);
            updateSelectedCount();
        });
    });

    console.log('=== INIZIALIZZAZIONE COMPLETATA ===');
});

// FUNZIONI DI SELEZIONE - COMPLETAMENTE RIFATTE E TESTATE

// Funzione per SELEZIONARE TUTTI
function selectAllVisible() {
    console.log('=== INIZIO selectAllVisible ===');

    // Metodo 1: Query diretta con classe E tipo
    let checkboxes = document.querySelectorAll('input[type="checkbox"].url-checkbox');
    console.log('Metodo 1 - Trovati checkbox:', checkboxes.length);

    // Se non trova nulla, prova metodo alternativo
    if (checkboxes.length === 0) {
        checkboxes = document.querySelectorAll('.url-checkbox');
        console.log('Metodo 2 - Trovati checkbox:', checkboxes.length);
    }

    // Se ancora non trova nulla, prova con name attribute
    if (checkboxes.length === 0) {
        checkboxes = document.querySelectorAll('input[type="checkbox"][name*="is_selected"]');
        console.log('Metodo 3 - Trovati checkbox con name:', checkboxes.length);
    }

    // Seleziona TUTTI i checkbox trovati
    let count = 0;
    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = true;
        count++;
        console.log(`Checkbox ${i+1} selezionato`);
    }

    console.log(`TOTALE selezionati: ${count}`);

    // Aggiorna header checkbox
    const headerCb = document.getElementById('selectAll');
    if (headerCb) {
        headerCb.checked = true;
        headerCb.indeterminate = false;
    }

    // FORZA aggiornamento contatore
    updateSelectedCount();
    console.log('=== FINE selectAllVisible ===');
}

// Funzione per DESELEZIONARE TUTTI
function deselectAllVisible() {
    console.log('=== INIZIO deselectAllVisible ===');

    // Metodo 1: Query diretta con classe E tipo
    let checkboxes = document.querySelectorAll('input[type="checkbox"].url-checkbox');
    console.log('Metodo 1 - Trovati checkbox:', checkboxes.length);

    // Se non trova nulla, prova metodo alternativo
    if (checkboxes.length === 0) {
        checkboxes = document.querySelectorAll('.url-checkbox');
        console.log('Metodo 2 - Trovati checkbox:', checkboxes.length);
    }

    // Se ancora non trova nulla, prova con name attribute
    if (checkboxes.length === 0) {
        checkboxes = document.querySelectorAll('input[type="checkbox"][name*="is_selected"]');
        console.log('Metodo 3 - Trovati checkbox con name:', checkboxes.length);
    }

    // Deseleziona TUTTI i checkbox trovati
    let count = 0;
    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = false;
        count++;
        console.log(`Checkbox ${i+1} deselezionato`);
    }

    console.log(`TOTALE deselezionati: ${count}`);

    // Aggiorna header checkbox
    const headerCb = document.getElementById('selectAll');
    if (headerCb) {
        headerCb.checked = false;
        headerCb.indeterminate = false;
    }

    // FORZA aggiornamento contatore
    updateSelectedCount();
    console.log('=== FINE deselectAllVisible ===');
}

// Funzione per TOGGLE dal checkbox header
function toggleAll(source) {
    console.log('=== INIZIO toggleAll ===');
    console.log('Stato source checkbox:', source.checked);

    // Usa lo stesso metodo delle altre funzioni
    let checkboxes = document.querySelectorAll('input[type="checkbox"].url-checkbox');

    if (checkboxes.length === 0) {
        checkboxes = document.querySelectorAll('.url-checkbox');
    }

    if (checkboxes.length === 0) {
        checkboxes = document.querySelectorAll('input[type="checkbox"][name*="is_selected"]');
    }

    console.log('Checkbox trovati:', checkboxes.length);

    // Imposta tutti i checkbox allo stesso stato del checkbox header
    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }

    // FORZA aggiornamento contatore
    updateSelectedCount();
    console.log('=== FINE toggleAll ===');
}

// METODO DI EMERGENZA - Forza selezione usando il DOM direttamente
function forceSelectAll() {
    console.log('=== FORZA SELEZIONE (METODO EMERGENZA) ===');

    // Trova TUTTI gli input nella tabella
    const table = document.querySelector('table');
    if (!table) {
        alert('Tabella non trovata!');
        return;
    }

    const allInputs = table.getElementsByTagName('input');
    let count = 0;

    for (let i = 0; i < allInputs.length; i++) {
        const input = allInputs[i];
        // Verifica che sia un checkbox e NON sia il checkbox header
        if (input.type === 'checkbox' && input.id !== 'selectAll') {
            input.checked = true;
            count++;
            console.log(`Forzato checkbox ${count}: ${input.name}`);
        }
    }

    console.log(`FORZATI ${count} checkbox`);

    // Aggiorna header
    const headerCb = document.getElementById('selectAll');
    if (headerCb) {
        headerCb.checked = true;
    }

    // Forza aggiornamento contatore
    updateSelectedCount();
    alert(`Selezionati ${count} URL con metodo di emergenza`);
}

// Se i metodi normali falliscono, mostra il pulsante di emergenza
window.addEventListener('load', function() {
    setTimeout(function() {
        // Verifica se i checkbox sono stati trovati
        const checkboxes = document.querySelectorAll('.url-checkbox');
        if (checkboxes.length === 0) {
            console.error('ERRORE CRITICO: Nessun checkbox trovato!');
            // Mostra il pulsante di emergenza
            const btnForce = document.getElementById('btnForceSelect');
            if (btnForce) {
                btnForce.style.display = 'inline-block';
            }
        }
    }, 1000);
});

// Funzione per eliminare URL
async function deleteUrl(urlId) {
    if (!confirm('Sei sicuro di voler eliminare questo URL?')) {
        return;
    }

    try {
        const response = await fetch('<?= htmlspecialchars($baseUrl) ?>/api/urls/' + urlId + '/delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        if (response.ok) {
            // Rimuovi la riga dalla tabella
            const row = document.querySelector(`tr[data-url-row-id="${urlId}"]`);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();

                    // Aggiorna i contatori
                    const totalBadge = document.getElementById('totalCount');
                    const currentTotal = parseInt(totalBadge.textContent);
                    totalBadge.textContent = currentTotal - 1;

                    // Aggiorna anche il contatore dei selezionati
                    updateSelectedCount();
                }, 300);
            }
        } else {
            alert('Errore durante l\'eliminazione');
        }
    } catch (error) {
        console.error('Errore:', error);
        alert('Errore di connessione');
    }
}

// Funzioni AI
async function generateAIForRow(rowId, generateTitle = true, classifyType = true) {
    const row = document.querySelector(`tr[data-url-row-id="${rowId}"]`);
    const titleInput = row.querySelector('.url-title-input');
    const descInput = row.querySelector('.url-desc-input');
    const typeSpan = row.querySelector('.url-type-badge');
    const statusSpan = row.querySelector('.ai-status');
    const button = row.querySelector('.btn-ai-desc');

    const currentTitle = titleInput.value || '';
    const url = titleInput.dataset.url;

    statusSpan.innerHTML = '⏳';
    button.disabled = true;

    try {
        const response = await fetch('<?= htmlspecialchars($baseUrl) ?>/api/ai/description', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                urlId: rowId, // IMPORTANTE: Passa l'ID per il salvataggio automatico
                title: currentTitle || 'Pagina',
                url: url,
                generateTitle: generateTitle && !currentTitle, // Genera titolo solo se vuoto
                classifyType: classifyType // Classifica sempre il tipo
            })
        });

        const data = await response.json();
        let updated = false;

        // Aggiorna il titolo se generato
        if (data.title && !currentTitle) {
            titleInput.value = data.title;
            // Evidenzia brevemente il campo
            titleInput.style.backgroundColor = '#d4edda';
            setTimeout(() => {
                titleInput.style.backgroundColor = '';
            }, 2000);
            updated = true;
        }

        // Aggiorna la descrizione se generata
        if (data.description) {
            descInput.value = data.description;
            // Evidenzia brevemente il campo
            descInput.style.backgroundColor = '#d4edda';
            setTimeout(() => {
                descInput.style.backgroundColor = '';
            }, 2000);
            updated = true;
        }

        // Aggiorna il tipo se classificato
        if (data.type && typeSpan) {
            const oldType = typeSpan.textContent.trim().replace(' ▼', ''); // Rimuovi la freccia per il confronto
            if (oldType !== data.type) {
                // Aggiorna il badge
                typeSpan.textContent = data.type + ' ▼';
                typeSpan.style.background = getTypeColor(data.type);
                typeSpan.style.color = 'white';

                // IMPORTANTE: Aggiorna anche il select dropdown nascosto
                const typeSelect = row.querySelector('.url-type-select');
                if (typeSelect) {
                    typeSelect.value = data.type;
                    // Assicurati che il valore sia davvero selezionato
                    for (let option of typeSelect.options) {
                        if (option.value === data.type) {
                            option.selected = true;
                            break;
                        }
                    }
                    console.log(`Select aggiornato a ${data.type} per riga ${rowId}`);
                }

                // Evidenzia il cambiamento
                typeSpan.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    typeSpan.style.transform = 'scale(1)';
                }, 500);

                updated = true;
                console.log(`Tipo cambiato da ${oldType} a ${data.type} per URL: ${url}`);
            }
        }

        if (updated) {
            // Mostra icona diversa se salvato nel DB
            if (data.saved === true) {
                statusSpan.innerHTML = '✅💾'; // Checkmark + disco per indicare salvato
                statusSpan.title = 'Generato e salvato automaticamente nel database';
                console.log('✅ Dati AI salvati automaticamente nel database per URL ID:', rowId);
            } else if (data.saved === false) {
                statusSpan.innerHTML = '✅⚠️'; // Generato ma non salvato
                statusSpan.title = 'Generato ma errore nel salvataggio automatico';
                console.warn('⚠️ Dati generati ma non salvati automaticamente');
            } else {
                statusSpan.innerHTML = '✅';
                statusSpan.title = 'Generato con successo';
            }
            setTimeout(() => {
                statusSpan.innerHTML = '';
                statusSpan.title = '';
            }, 5000);
        } else {
            statusSpan.innerHTML = '❌';
            if (data.error) {
                console.error('Errore AI:', data.error);
                statusSpan.title = data.error;
                // Non mostriamo alert per non interrompere il batch processing
            }
        }
    } catch (error) {
        statusSpan.innerHTML = '❌';
        console.error('Errore AI:', error);
        // Non mostriamo alert per non interrompere il batch processing
    } finally {
        button.disabled = false;
    }
}

// Funzione helper per ottenere il colore del badge in base al tipo
function getTypeColor(type) {
    const colors = {
        'HOMEPAGE': '#007bff',
        'CATEGORY': '#28a745',
        'PRODUCT': '#17a2b8',
        'GUIDE': '#ffc107',
        'POLICY': '#dc3545',
        'SUPPORT': '#6610f2',
        'BLOG': '#e83e8c',
        'OTHER': '#6c757d'
    };
    return colors[type] || '#6c757d';
}

// Toggle dropdown per selezione tipo manuale
function toggleTypeSelect(rowId) {
    const badge = document.querySelector(`tr[data-url-row-id="${rowId}"] .url-type-badge`);
    const select = document.getElementById(`type-select-${rowId}`);

    if (select.style.display === 'none') {
        // Mostra il select e nascondi il badge
        badge.style.display = 'none';
        select.style.display = 'block';
        select.focus();

        // Chiudi quando si clicca fuori
        select.onblur = function() {
            setTimeout(() => {
                select.style.display = 'none';
                badge.style.display = 'inline-block';
            }, 200);
        };
    }
}

// Aggiorna il badge quando si cambia il tipo
function updateTypeBadge(rowId, newType) {
    const badge = document.querySelector(`tr[data-url-row-id="${rowId}"] .url-type-badge`);
    const select = document.getElementById(`type-select-${rowId}`);

    // Aggiorna il testo e colore del badge
    badge.textContent = newType + ' ▼';
    badge.style.background = getTypeColor(newType);

    // Nascondi il select e mostra il badge
    select.style.display = 'none';
    badge.style.display = 'inline-block';

    // Animazione per mostrare il cambiamento
    badge.style.transform = 'scale(1.2)';
    setTimeout(() => {
        badge.style.transform = 'scale(1)';
    }, 300);

    console.log(`Tipo cambiato manualmente a ${newType} per riga ${rowId}`);
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
    document.getElementById('btnGenerateSelected').disabled = true;
    document.getElementById('btnGenerateAll').disabled = true;

    let processed = 0;
    let successful = 0;
    let failed = 0;

    for (const checkbox of selected) {
        const row = checkbox.closest('tr');
        const rowId = row.dataset.urlRowId;

        statusText.textContent = `Generando ${processed + 1} di ${selected.length}... (✅ ${successful} | ❌ ${failed})`;
        progressBar.style.width = ((processed / selected.length) * 100) + '%';

        try {
            await generateAIForRow(rowId);
            successful++;
        } catch (error) {
            failed++;
        }

        await new Promise(resolve => setTimeout(resolve, 1000)); // Pausa 1 secondo tra richieste

        processed++;
    }

    progressBar.style.width = '100%';
    statusText.textContent = `✅ Completato! Generate ${successful} descrizioni${failed > 0 ? `, ${failed} errori` : ''}`;

    setTimeout(() => {
        progressDiv.style.display = 'none';
        document.getElementById('btnGenerateSelected').disabled = false;
        document.getElementById('btnGenerateAll').disabled = false;
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
    document.getElementById('btnGenerateSelected').disabled = true;
    document.getElementById('btnGenerateAll').disabled = true;

    let processed = 0;
    let successful = 0;
    let failed = 0;

    for (const row of allRows) {
        const rowId = row.dataset.urlRowId;

        statusText.textContent = `Generando ${processed + 1} di ${allRows.length}... (✅ ${successful} | ❌ ${failed})`;
        progressBar.style.width = ((processed / allRows.length) * 100) + '%';

        try {
            await generateAIForRow(rowId);
            successful++;
        } catch (error) {
            failed++;
        }

        await new Promise(resolve => setTimeout(resolve, 1000)); // Pausa 1 secondo tra richieste

        processed++;
    }

    progressBar.style.width = '100%';
    statusText.textContent = `✅ Completato! Generate ${successful} descrizioni${failed > 0 ? `, ${failed} errori` : ''}`;

    setTimeout(() => {
        progressDiv.style.display = 'none';
        document.getElementById('btnGenerateSelected').disabled = false;
        document.getElementById('btnGenerateAll').disabled = false;
        // Non serve più l'alert perché salviamo automaticamente!
        console.log('✅ Tutte le modifiche AI sono state salvate automaticamente nel database');
    }, 3000);
}

async function generateTitlesOnly() {
    // Trova tutte le righe con titoli vuoti
    const allRows = document.querySelectorAll('tr[data-url-row-id]');
    const rowsWithoutTitles = [];

    for (const row of allRows) {
        const titleInput = row.querySelector('.url-title-input');
        if (!titleInput.value || titleInput.value.trim() === '') {
            rowsWithoutTitles.push(row);
        }
    }

    if (rowsWithoutTitles.length === 0) {
        alert('Tutti gli URL hanno già un titolo!');
        return;
    }

    if (!confirm(`Generare titoli AI per ${rowsWithoutTitles.length} URL senza titolo?`)) {
        return;
    }

    const progressDiv = document.getElementById('ai-progress');
    const progressBar = document.getElementById('ai-progress-bar');
    const statusText = document.getElementById('ai-status');

    progressDiv.style.display = 'block';
    document.getElementById('btnGenerateSelected').disabled = true;
    document.getElementById('btnGenerateTitles').disabled = true;
    document.getElementById('btnGenerateAll').disabled = true;

    let processed = 0;
    let successful = 0;
    let failed = 0;

    for (const row of rowsWithoutTitles) {
        const rowId = row.dataset.urlRowId;
        const titleInput = row.querySelector('.url-title-input');
        const url = titleInput.dataset.url;
        const statusSpan = row.querySelector('.ai-status');

        statusText.textContent = `Generando titolo ${processed + 1} di ${rowsWithoutTitles.length}... (✅ ${successful} | ❌ ${failed})`;
        progressBar.style.width = ((processed / rowsWithoutTitles.length) * 100) + '%';

        statusSpan.innerHTML = '⏳';

        try {
            const response = await fetch('<?= htmlspecialchars($baseUrl) ?>/api/ai/description', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    title: '',
                    url: url,
                    generateTitle: true
                })
            });

            const data = await response.json();

            if (data.title) {
                titleInput.value = data.title;
                titleInput.style.backgroundColor = '#d4edda';
                setTimeout(() => {
                    titleInput.style.backgroundColor = '';
                }, 2000);
                statusSpan.innerHTML = '✅';
                successful++;
            } else {
                statusSpan.innerHTML = '❌';
                failed++;
            }
        } catch (error) {
            statusSpan.innerHTML = '❌';
            failed++;
        }

        setTimeout(() => {
            statusSpan.innerHTML = '';
        }, 2000);

        await new Promise(resolve => setTimeout(resolve, 1000)); // Pausa 1 secondo tra richieste
        processed++;
    }

    progressBar.style.width = '100%';
    statusText.textContent = `✅ Completato! Generati ${successful} titoli${failed > 0 ? `, ${failed} errori` : ''}`;

    setTimeout(() => {
        progressDiv.style.display = 'none';
        document.getElementById('btnGenerateSelected').disabled = false;
        document.getElementById('btnGenerateTitles').disabled = false;
        document.getElementById('btnGenerateAll').disabled = false;
        if (successful > 0) {
            alert('Ricordati di salvare le modifiche!');
        }
    }, 3000);
}

async function classifyTypesOnly() {
    // Trova tutte le righe
    const allRows = document.querySelectorAll('tr[data-url-row-id]');

    if (allRows.length === 0) {
        alert('Nessun URL da classificare!');
        return;
    }

    if (!confirm(`Classificare automaticamente il tipo per ${allRows.length} URL? Questa operazione analizzerà ogni URL e determinerà il tipo più appropriato.`)) {
        return;
    }

    const progressDiv = document.getElementById('ai-progress');
    const progressBar = document.getElementById('ai-progress-bar');
    const statusText = document.getElementById('ai-status');

    progressDiv.style.display = 'block';
    document.getElementById('btnGenerateSelected').disabled = true;
    document.getElementById('btnGenerateTitles').disabled = true;
    document.getElementById('btnClassifyTypes').disabled = true;
    document.getElementById('btnGenerateAll').disabled = true;

    let processed = 0;
    let successful = 0;
    let changed = 0;

    for (const row of allRows) {
        const rowId = row.dataset.urlRowId;
        const titleInput = row.querySelector('.url-title-input');
        const url = titleInput.dataset.url;
        const title = titleInput.value || '';
        const typeSpan = row.querySelector('.url-type-badge');
        const statusSpan = row.querySelector('.ai-status');
        const oldType = typeSpan ? typeSpan.textContent.trim() : 'OTHER';

        statusText.textContent = `Classificando tipo ${processed + 1} di ${allRows.length}... (✅ ${successful} | 🔄 ${changed})`;
        progressBar.style.width = ((processed / allRows.length) * 100) + '%';

        statusSpan.innerHTML = '⏳';

        try {
            const response = await fetch('<?= htmlspecialchars($baseUrl) ?>/api/ai/description', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    title: title || 'Pagina',
                    url: url,
                    generateTitle: false,
                    classifyType: true
                })
            });

            const data = await response.json();

            if (data.type && typeSpan) {
                if (oldType !== data.type) {
                    // Aggiorna il badge
                    typeSpan.textContent = data.type + ' ▼';
                    typeSpan.style.background = getTypeColor(data.type);
                    typeSpan.style.color = 'white';

                    // IMPORTANTE: Aggiorna anche il select nascosto
                    const typeSelect = row.querySelector('.url-type-select');
                    if (typeSelect) {
                        typeSelect.value = data.type;
                        // Forza la selezione dell'opzione corretta
                        for (let option of typeSelect.options) {
                            if (option.value === data.type) {
                                option.selected = true;
                                break;
                            }
                        }
                    }

                    // Animazione per mostrare il cambiamento
                    typeSpan.style.transform = 'scale(1.2)';
                    setTimeout(() => {
                        typeSpan.style.transform = 'scale(1)';
                    }, 300);

                    changed++;
                    console.log(`Tipo cambiato da ${oldType} a ${data.type} per: ${url}`);
                }
                statusSpan.innerHTML = '✅';
                successful++;
            } else {
                statusSpan.innerHTML = '❌';
            }
        } catch (error) {
            statusSpan.innerHTML = '❌';
            console.error('Errore classificazione:', error);
        }

        setTimeout(() => {
            statusSpan.innerHTML = '';
        }, 2000);

        await new Promise(resolve => setTimeout(resolve, 500)); // Pausa breve tra richieste
        processed++;
    }

    progressBar.style.width = '100%';
    statusText.textContent = `✅ Completato! Classificati ${successful} URL, ${changed} tipi modificati`;

    setTimeout(() => {
        progressDiv.style.display = 'none';
        document.getElementById('btnGenerateSelected').disabled = false;
        document.getElementById('btnGenerateTitles').disabled = false;
        document.getElementById('btnClassifyTypes').disabled = false;
        document.getElementById('btnGenerateAll').disabled = false;
        if (changed > 0) {
            alert(`Classificazione completata!\n\n✅ URL processati: ${successful}\n🔄 Tipi modificati: ${changed}\n\nRicordati di salvare le modifiche!`);
        } else {
            alert(`Classificazione completata!\n\n✅ URL processati: ${successful}\n\nTutti i tipi erano già corretti!`);
        }
    }, 3000);
}

async function generateAIForVisible() {
    const visibleRows = document.querySelectorAll('tr[data-url-row-id]');
    if (!confirm(`Generare descrizioni AI per i ${visibleRows.length} URL visibili in questa pagina?`)) {
        return;
    }

    const progressDiv = document.getElementById('ai-progress');
    const progressBar = document.getElementById('ai-progress-bar');
    const statusText = document.getElementById('ai-status');

    progressDiv.style.display = 'block';

    let processed = 0;

    for (const row of visibleRows) {
        const rowId = row.dataset.urlRowId;

        statusText.textContent = `Generando ${processed + 1} di ${visibleRows.length}...`;
        progressBar.style.width = ((processed / visibleRows.length) * 100) + '%';

        await generateAIForRow(rowId);
        await new Promise(resolve => setTimeout(resolve, 1000));

        processed++;
    }

    progressBar.style.width = '100%';
    statusText.textContent = `✅ Completato! Generate ${visibleRows.length} descrizioni`;

    setTimeout(() => {
        progressDiv.style.display = 'none';
    }, 3000);
}

// Funzione per eliminare URL selezionate in bulk
async function deleteSelectedUrls() {
    // Trova tutti i checkbox selezionati
    const selectedCheckboxes = document.querySelectorAll('.url-checkbox:checked');

    if (selectedCheckboxes.length === 0) {
        alert('Seleziona almeno un URL da eliminare');
        return;
    }

    // Conferma con l'utente
    const confirmMessage = `Sei sicuro di voler eliminare ${selectedCheckboxes.length} URL?\n\nQuesta azione è IRREVERSIBILE!`;
    if (!confirm(confirmMessage)) {
        return;
    }

    // Secondo livello di conferma per sicurezza
    if (selectedCheckboxes.length > 10) {
        const secondConfirm = prompt(`Stai per eliminare ${selectedCheckboxes.length} URL.\n\nScrivi "ELIMINA" per confermare:`);
        if (secondConfirm !== 'ELIMINA') {
            alert('Eliminazione annullata');
            return;
        }
    }

    // Raccogli gli ID delle URL da eliminare
    const urlIds = [];
    selectedCheckboxes.forEach(checkbox => {
        const row = checkbox.closest('tr[data-url-row-id]');
        if (row) {
            const urlId = row.dataset.urlRowId;
            urlIds.push(urlId);
        }
    });

    console.log('URL da eliminare:', urlIds);

    // Disabilita il bottone durante l'eliminazione
    const deleteButton = document.querySelector('button[onclick="deleteSelectedUrls()"]');
    const originalText = deleteButton.textContent;
    deleteButton.disabled = true;
    deleteButton.textContent = '⏳ Eliminazione in corso...';

    let deleted = 0;
    let failed = 0;

    // Elimina le URL una per una (potremmo fare anche un bulk, ma così abbiamo un feedback migliore)
    for (const urlId of urlIds) {
        try {
            const response = await fetch('<?= htmlspecialchars($baseUrl) ?>/api/urls/' + urlId + '/delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            if (response.ok) {
                // Rimuovi la riga dalla tabella con animazione
                const row = document.querySelector(`tr[data-url-row-id="${urlId}"]`);
                if (row) {
                    row.style.transition = 'all 0.3s ease-out';
                    row.style.backgroundColor = '#ffcccc';
                    row.style.opacity = '0.5';

                    setTimeout(() => {
                        row.style.transform = 'translateX(-100%)';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            updateSelectedCount(); // Aggiorna il contatore
                        }, 300);
                    }, 200);
                }
                deleted++;
            } else {
                console.error(`Errore eliminazione URL ${urlId}:`, response.statusText);
                failed++;
            }
        } catch (error) {
            console.error(`Errore eliminazione URL ${urlId}:`, error);
            failed++;
        }
    }

    // Ripristina il bottone
    deleteButton.disabled = false;
    deleteButton.textContent = originalText;

    // Mostra risultato
    let message = `✅ Eliminate ${deleted} URL`;
    if (failed > 0) {
        message += `\n⚠️ ${failed} eliminazioni fallite`;
    }

    alert(message);

    // Se tutte le URL della pagina sono state eliminate, ricarica
    const remainingRows = document.querySelectorAll('tr[data-url-row-id]');
    if (remainingRows.length === 0) {
        location.reload();
    }
}
</script>