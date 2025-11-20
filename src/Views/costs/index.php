<?php
use LlmsApp\Models\AiUsage;

$globalStats = AiUsage::getGlobalStats();
$totals = $globalStats['totals'];
$byProject = $globalStats['by_project'];
$monthly = $globalStats['monthly_comparison'];

// Formatta i costi in EUR (assumendo cambio 1:1 per semplicità)
function formatCost($cost) {
    if ($cost < 0.01) {
        return '€ ' . number_format($cost, 4);
    }
    return '€ ' . number_format($cost, 2);
}

function formatNumber($num) {
    if ($num > 1000000) {
        return number_format($num / 1000000, 2) . 'M';
    } elseif ($num > 1000) {
        return number_format($num / 1000, 1) . 'K';
    }
    return number_format($num);
}
?>

<h1>💰 Monitoraggio Costi AI</h1>

<!-- Statistiche Principali -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px;">
        <h3 style="margin: 0;">Costo Totale</h3>
        <p style="font-size: 2em; margin: 10px 0; font-weight: bold;">
            <?= formatCost($totals['total_cost'] ?? 0) ?>
        </p>
        <small>Dall'inizio</small>
    </div>

    <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 20px; border-radius: 10px;">
        <h3 style="margin: 0;">Chiamate API</h3>
        <p style="font-size: 2em; margin: 10px 0; font-weight: bold;">
            <?= formatNumber($totals['total_calls'] ?? 0) ?>
        </p>
        <small>Totali</small>
    </div>

    <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 10px;">
        <h3 style="margin: 0;">Token Utilizzati</h3>
        <p style="font-size: 2em; margin: 10px 0; font-weight: bold;">
            <?= formatNumber($totals['total_tokens'] ?? 0) ?>
        </p>
        <small>Totali</small>
    </div>

    <div style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 20px; border-radius: 10px;">
        <h3 style="margin: 0;">Questo Mese</h3>
        <p style="font-size: 2em; margin: 10px 0; font-weight: bold;">
            <?= formatCost($monthly['current_month'] ?? 0) ?>
        </p>
        <small>
            <?php if (isset($monthly['last_month'])): ?>
                <?php
                $diff = ($monthly['current_month'] ?? 0) - ($monthly['last_month'] ?? 0);
                $percent = $monthly['last_month'] > 0 ? ($diff / $monthly['last_month']) * 100 : 0;
                ?>
                <?= $diff >= 0 ? '↑' : '↓' ?> <?= abs(round($percent)) ?>% vs mese scorso
            <?php else: ?>
                Primo mese
            <?php endif; ?>
        </small>
    </div>
</div>

<!-- Costi per Progetto -->
<div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 30px;">
    <h2>📊 Costi per Progetto</h2>

    <?php if (!empty($byProject)): ?>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8f9fa;">
                <th style="padding: 10px; text-align: left;">Progetto</th>
                <th style="padding: 10px; text-align: right;">Chiamate</th>
                <th style="padding: 10px; text-align: right;">Costo</th>
                <th style="padding: 10px; text-align: right;">Media per Chiamata</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($byProject as $proj): ?>
            <tr style="border-bottom: 1px solid #dee2e6;">
                <td style="padding: 10px;">
                    <?= htmlspecialchars($proj['project_name'] ?? 'N/A') ?>
                </td>
                <td style="padding: 10px; text-align: right;">
                    <?= number_format($proj['calls']) ?>
                </td>
                <td style="padding: 10px; text-align: right; font-weight: bold;">
                    <?= formatCost($proj['cost']) ?>
                </td>
                <td style="padding: 10px; text-align: right;">
                    <?= formatCost($proj['cost'] / max(1, $proj['calls'])) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f8f9fa; font-weight: bold;">
                <td style="padding: 10px;">TOTALE</td>
                <td style="padding: 10px; text-align: right;">
                    <?= number_format($totals['total_calls'] ?? 0) ?>
                </td>
                <td style="padding: 10px; text-align: right;">
                    <?= formatCost($totals['total_cost'] ?? 0) ?>
                </td>
                <td style="padding: 10px; text-align: right;">
                    <?= formatCost(($totals['total_cost'] ?? 0) / max(1, $totals['total_calls'] ?? 1)) ?>
                </td>
            </tr>
        </tfoot>
    </table>
    <?php else: ?>
    <p style="color: #666;">Nessuna chiamata AI registrata ancora.</p>
    <?php endif; ?>
</div>

<!-- Stima Costi per Batch Grandi -->
<div style="background: #fff3cd; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
    <h2>⚠️ Gestione Progetti Grandi (3000+ URL)</h2>

    <div style="background: white; padding: 15px; border-radius: 5px; margin-top: 15px;">
        <h3>Stima Costi per 3000 URL:</h3>
        <?php
        $estimate3000 = AiUsage::estimateBatchCost(3000);
        ?>
        <ul>
            <li>🔹 Costo stimato totale: <strong><?= formatCost($estimate3000['estimated_cost']) ?></strong></li>
            <li>🔹 Token stimati: <strong><?= formatNumber($estimate3000['estimated_tokens']) ?></strong></li>
            <li>🔹 Costo per URL: <strong><?= formatCost($estimate3000['cost_per_url']) ?></strong></li>
            <li>🔹 Modello utilizzato: <strong><?= htmlspecialchars($estimate3000['model']) ?></strong></li>
        </ul>

        <div style="background: #f8d7da; padding: 10px; border-radius: 5px; margin-top: 15px;">
            <strong>⚠️ ATTENZIONE:</strong> Per progetti con più di 3000 URL:
            <ul>
                <li>Il sistema processerà in batch di 50-100 URL per volta</li>
                <li>Pause automatiche tra batch per evitare rate limiting</li>
                <li>Tempo stimato: ~15-30 minuti per 3000 URL</li>
                <li>Consiglio: Usa la classificazione AI solo dove necessario</li>
            </ul>
        </div>
    </div>
</div>

<!-- Consigli per Risparmiare -->
<div style="background: #d4edda; padding: 20px; border-radius: 10px;">
    <h2>💡 Consigli per Ottimizzare i Costi</h2>
    <ol>
        <li><strong>Usa gpt-3.5-turbo</strong> invece di gpt-4 (10x più economico)</li>
        <li><strong>Genera solo quello che serve:</strong>
            <ul>
                <li>Se hai già i titoli, genera solo le descrizioni</li>
                <li>Classifica i tipi solo se necessario</li>
            </ul>
        </li>
        <li><strong>Per progetti grandi (3000+ URL):</strong>
            <ul>
                <li>Filtra prima gli URL importanti</li>
                <li>Genera AI solo per gli URL selezionati</li>
                <li>Usa il batch processing notturno</li>
            </ul>
        </li>
        <li><strong>Riusa i dati esistenti:</strong> I titoli e descrizioni vengono salvati, non rigenerarli</li>
    </ol>
</div>