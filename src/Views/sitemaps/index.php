<div class="container mx-auto px-4 py-8 max-w-7xl">
    <div class="mb-6">
        <nav class="text-sm mb-4">
            <a href="<?php echo $baseUrl; ?>/" class="text-blue-500 hover:underline">Progetti</a>
            <span class="mx-2">/</span>
            <a href="<?php echo $baseUrl; ?>/projects/<?php echo $project['id']; ?>" class="text-blue-500 hover:underline"><?php echo htmlspecialchars($project['name']); ?></a>
            <span class="mx-2">/</span>
            <span class="text-gray-600">Sitemap</span>
        </nav>

        <h1 class="text-3xl font-bold text-gray-800 mb-2">Gestione Sitemap</h1>
        <p class="text-gray-600">Aggiungi e gestisci le sitemap per <?php echo htmlspecialchars($project['name']); ?></p>
    </div>

    <!-- Messaggio di successo/errore -->
    <?php if (isset($_SESSION['success'])): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo htmlspecialchars($_SESSION['success']); ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo htmlspecialchars($_SESSION['error']); ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Form per aggiungere una sitemap -->
    <div style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 30px;">
        <h2 style="font-size: 20px; font-weight: bold; margin-bottom: 15px;">Aggiungi Sitemap</h2>

        <form method="POST" action="<?php echo $baseUrl; ?>/projects/<?php echo $project['id']; ?>/sitemaps/add">
            <div style="display: flex; gap: 10px;">
                <input type="url"
                       name="sitemap_url"
                       placeholder="https://example.com/sitemap.xml"
                       style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"
                       required>
                <button type="submit"
                        style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                    ➕ Aggiungi Sitemap
                </button>
            </div>
            <p style="color: #666; font-size: 14px; margin-top: 10px;">
                Inserisci l'URL completo della sitemap XML (es: https://sito.com/sitemap.xml o sitemap_index.xml)
            </p>
        </form>
    </div>

    <!-- Lista sitemap esistenti -->
    <div style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden;">
        <div style="padding: 20px; border-bottom: 1px solid #e0e0e0;">
            <h2 style="font-size: 20px; font-weight: bold;">Sitemap Configurate</h2>
        </div>

        <?php if (empty($sitemaps)): ?>
            <div style="padding: 40px; text-align: center; color: #999;">
                <p style="font-size: 48px; margin-bottom: 20px;">📍</p>
                <p>Nessuna sitemap configurata</p>
                <p style="font-size: 14px; margin-top: 10px;">Aggiungi la prima sitemap usando il form sopra</p>
            </div>
        <?php else: ?>
            <table style="width: 100%;">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th style="padding: 15px; text-align: left; font-size: 12px; text-transform: uppercase; color: #666;">URL Sitemap</th>
                        <th style="padding: 15px; text-align: left; font-size: 12px; text-transform: uppercase; color: #666;">Ultimo Parsing</th>
                        <th style="padding: 15px; text-align: center; font-size: 12px; text-transform: uppercase; color: #666;">URL Trovati</th>
                        <th style="padding: 15px; text-align: right; font-size: 12px; text-transform: uppercase; color: #666;">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sitemaps as $sitemap): ?>
                        <?php
                        // Conta gli URL per questa sitemap
                        $pdo = \LlmsApp\Config\Database::getConnection();
                        $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM urls WHERE project_id = :pid');
                        $stmt->execute(['pid' => $project['id']]);
                        $urlCount = $stmt->fetch()['total'];
                        ?>
                        <tr style="border-bottom: 1px solid #e0e0e0;">
                            <td style="padding: 15px;">
                                <a href="<?php echo htmlspecialchars($sitemap['url']); ?>"
                                   target="_blank"
                                   style="color: #007bff; text-decoration: none;">
                                    <?php echo htmlspecialchars($sitemap['url']); ?>
                                    🔗
                                </a>
                            </td>
                            <td style="padding: 15px; color: #666;">
                                <?php if ($sitemap['last_parsed_at']): ?>
                                    ✅ <?php echo date('d/m/Y H:i', strtotime($sitemap['last_parsed_at'])); ?>
                                <?php else: ?>
                                    <span style="color: #999;">⏳ Mai effettuato</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px; text-align: center;">
                                <?php if ($urlCount > 0): ?>
                                    <span style="background: #28a745; color: white; padding: 3px 10px; border-radius: 12px; font-size: 14px;">
                                        <?php echo $urlCount; ?> URL
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px; text-align: right;">
                                <button onclick="parseSitemap(<?php echo $sitemap['id']; ?>)"
                                        style="background: #28a745; color: white; padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer; margin-right: 5px;">
                                    🔄 Avvia Parsing
                                </button>
                                <button onclick="deleteSitemap(<?php echo $sitemap['id']; ?>)"
                                        style="background: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 5px; cursor: pointer;">
                                    🗑️ Elimina
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Link per gestire gli URL -->
    <?php
    $pdo = \LlmsApp\Config\Database::getConnection();
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM urls WHERE project_id = :pid');
    $stmt->execute(['pid' => $project['id']]);
    $totalUrls = $stmt->fetch()['total'];
    ?>

    <?php if ($totalUrls > 0): ?>
    <div style="margin-top: 30px; text-align: center;">
        <a href="<?php echo $baseUrl; ?>/projects/<?php echo $project['id']; ?>/urls"
           style="display: inline-block; background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 18px;">
            📋 Gestisci <?php echo $totalUrls; ?> URL trovati →
        </a>
    </div>
    <?php endif; ?>

    <!-- Informazioni sul flusso -->
    <div style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin-top: 30px;">
        <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 15px;">📚 Come funziona:</h3>
        <ol style="margin-left: 20px;">
            <li style="margin-bottom: 10px;">
                <strong>Aggiungi Sitemap:</strong> Inserisci l'URL della sitemap XML del sito
            </li>
            <li style="margin-bottom: 10px;">
                <strong>Avvia Parsing:</strong> Clicca su "🔄 Avvia Parsing" per estrarre tutti gli URL
            </li>
            <li style="margin-bottom: 10px;">
                <strong>Gestisci URL:</strong> Vai nella sezione URL per selezionare quali includere
            </li>
            <li style="margin-bottom: 10px;">
                <strong>Genera llms.txt:</strong> Dalla pagina URL, genera il file finale
            </li>
        </ol>
    </div>
</div>

<script>
function parseSitemap(sitemapId) {
    if (!confirm('Vuoi effettuare il parsing di questa sitemap?\nQuesta operazione scaricherà e analizzerà tutti gli URL contenuti.')) {
        return;
    }

    // Mostra un loader con progress bar
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Parsing in corso...';
    btn.style.background = '#6c757d';

    // Crea una progress bar sotto il pulsante
    const row = btn.closest('tr');
    const progressCell = row.cells[row.cells.length - 1];
    const progressDiv = document.createElement('div');
    progressDiv.innerHTML = `
        <div style="margin-top: 10px; background: #e9ecef; border-radius: 5px; overflow: hidden;">
            <div id="parse-progress-${sitemapId}"
                 style="background: linear-gradient(90deg, #007bff, #0056b3);
                        height: 6px;
                        width: 0%;
                        transition: width 0.3s;">
            </div>
        </div>
        <small id="parse-status-${sitemapId}" style="display: block; margin-top: 5px; color: #666;">
            Analizzando sitemap...
        </small>
    `;
    progressCell.appendChild(progressDiv);

    // Animazione della progress bar
    let progress = 0;
    const progressBar = document.getElementById(`parse-progress-${sitemapId}`);
    const statusText = document.getElementById(`parse-status-${sitemapId}`);

    const progressInterval = setInterval(() => {
        if (progress < 90) {
            progress += Math.random() * 15;
            progress = Math.min(progress, 90);
            progressBar.style.width = progress + '%';
        }
    }, 500);

    fetch('<?php echo $baseUrl; ?>/sitemaps/' + sitemapId + '/parse', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'sitemap_id=' + sitemapId
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        clearInterval(progressInterval);

        if (data.success) {
            // Completa la progress bar
            progressBar.style.width = '100%';
            statusText.textContent = '✅ ' + data.message;
            statusText.style.color = '#28a745';

            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            progressBar.style.background = '#dc3545';
            statusText.textContent = '❌ Errore: ' + data.message;
            statusText.style.color = '#dc3545';

            btn.disabled = false;
            btn.innerHTML = originalText;
            btn.style.background = '#28a745';
        }
    })
    .catch(error => {
        clearInterval(progressInterval);
        console.error('Error:', error);

        progressBar.style.background = '#dc3545';
        statusText.textContent = '❌ Errore di connessione';
        statusText.style.color = '#dc3545';

        btn.disabled = false;
        btn.innerHTML = originalText;
        btn.style.background = '#28a745';
    });
}

function deleteSitemap(sitemapId) {
    if (!confirm('Sei sicuro di voler eliminare questa sitemap?\nQuesta operazione NON eliminerà gli URL già parsati.')) {
        return;
    }

    // Crea un form temporaneo per inviare la richiesta POST
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo $baseUrl; ?>/sitemaps/' + sitemapId + '/delete';
    document.body.appendChild(form);
    form.submit();
}
</script>