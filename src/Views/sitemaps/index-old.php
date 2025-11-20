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

    <!-- Form per aggiungere una sitemap -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Aggiungi Sitemap</h2>
        <form method="POST" action="<?php echo $baseUrl; ?>/projects/<?php echo $project['id']; ?>/sitemaps/add" class="flex gap-3">
            <input type="url"
                   name="sitemap_url"
                   placeholder="https://example.com/sitemap.xml"
                   class="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   required>
            <button type="submit"
                    class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition">
                <i class="fas fa-plus mr-2"></i>Aggiungi
            </button>
        </form>
        <p class="text-sm text-gray-500 mt-2">
            Inserisci l'URL completo della sitemap XML del sito
        </p>
    </div>

    <!-- Lista sitemap esistenti -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-6 border-b">
            <h2 class="text-xl font-semibold">Sitemap Configurate</h2>
        </div>

        <?php if (empty($sitemaps)): ?>
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-sitemap text-4xl mb-3 opacity-50"></i>
                <p>Nessuna sitemap configurata</p>
                <p class="text-sm mt-1">Aggiungi la prima sitemap usando il form sopra</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">URL</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Ultimo Parsing</th>
                            <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($sitemaps as $sitemap): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm">
                                        <a href="<?php echo htmlspecialchars($sitemap['url']); ?>"
                                           target="_blank"
                                           class="text-blue-500 hover:underline">
                                            <?php echo htmlspecialchars($sitemap['url']); ?>
                                            <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                                        </a>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php if ($sitemap['last_parsed_at']): ?>
                                        <?php echo date('d/m/Y H:i', strtotime($sitemap['last_parsed_at'])); ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">Mai effettuato</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button onclick="parseSitemap(<?php echo $sitemap['id']; ?>)"
                                            class="bg-green-500 text-white px-4 py-2 rounded text-sm hover:bg-green-600 transition">
                                        <i class="fas fa-sync-alt mr-1"></i>
                                        Parsing
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Link per gestire gli URL -->
    <?php
    $pdo = \LlmsApp\Config\Database::getConnection();
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM urls WHERE project_id = :pid');
    $stmt->execute(['pid' => $project['id']]);
    $urlCount = $stmt->fetch()['total'];
    ?>

    <?php if ($urlCount > 0): ?>
    <div class="mt-6 text-center">
        <a href="<?php echo $baseUrl; ?>/projects/<?php echo $project['id']; ?>/urls"
           class="inline-block bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600 transition">
            <i class="fas fa-link mr-2"></i>
            Gestisci <?php echo $urlCount; ?> URL
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
function parseSitemap(sitemapId) {
    if (!confirm('Vuoi effettuare il parsing di questa sitemap? Questa operazione potrebbe richiedere alcuni secondi.')) {
        return;
    }

    // Mostra un loader
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Parsing in corso...';

    fetch('<?php echo $baseUrl; ?>/sitemaps/' + sitemapId + '/parse', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Errore: ' + data.message);
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        alert('Errore durante il parsing: ' + error);
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}
</script>