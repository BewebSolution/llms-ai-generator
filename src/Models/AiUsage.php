<?php

namespace LlmsApp\Models;

use LlmsApp\Config\Database;
use PDO;

class AiUsage
{
    // Prezzi OpenAI per 1K token (in USD)
    private const PRICING = [
        'gpt-3.5-turbo' => [
            'input' => 0.0005,   // $0.50 per 1M token
            'output' => 0.0015,  // $1.50 per 1M token
        ],
        'gpt-4' => [
            'input' => 0.03,     // $30 per 1M token
            'output' => 0.06,    // $60 per 1M token
        ],
        'gpt-4-turbo-preview' => [
            'input' => 0.01,     // $10 per 1M token
            'output' => 0.03,    // $30 per 1M token
        ],
    ];

    /**
     * Log di una chiamata AI
     */
    public static function log(array $data): int
    {
        $pdo = Database::getConnection();

        // Calcola il costo stimato
        $model = $data['model'] ?? 'gpt-3.5-turbo';
        $inputTokens = $data['input_tokens'] ?? 0;
        $outputTokens = $data['output_tokens'] ?? 0;

        $cost = self::calculateCost($model, $inputTokens, $outputTokens);

        $stmt = $pdo->prepare('
            INSERT INTO ai_usage_log (
                project_id, url_id, operation_type, model,
                input_tokens, output_tokens, total_tokens,
                estimated_cost, success, error_message
            ) VALUES (
                :project_id, :url_id, :operation_type, :model,
                :input_tokens, :output_tokens, :total_tokens,
                :estimated_cost, :success, :error_message
            )
        ');

        $stmt->execute([
            'project_id' => $data['project_id'] ?? null,
            'url_id' => $data['url_id'] ?? null,
            'operation_type' => $data['operation_type'],
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $inputTokens + $outputTokens,
            'estimated_cost' => $cost,
            'success' => $data['success'] ?? 1,
            'error_message' => $data['error_message'] ?? null,
        ]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * Calcola il costo stimato
     */
    private static function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $pricing = self::PRICING[$model] ?? self::PRICING['gpt-3.5-turbo'];

        $inputCost = ($inputTokens / 1000) * $pricing['input'];
        $outputCost = ($outputTokens / 1000) * $pricing['output'];

        return $inputCost + $outputCost;
    }

    /**
     * Ottieni statistiche per progetto
     */
    public static function getProjectStats(int $projectId): array
    {
        $pdo = Database::getConnection();

        // Statistiche totali
        $stmt = $pdo->prepare('
            SELECT
                COUNT(*) as total_calls,
                SUM(total_tokens) as total_tokens,
                SUM(estimated_cost) as total_cost,
                SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_calls,
                SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_calls
            FROM ai_usage_log
            WHERE project_id = :project_id
        ');
        $stmt->execute(['project_id' => $projectId]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        // Statistiche per tipo di operazione
        $stmt = $pdo->prepare('
            SELECT
                operation_type,
                COUNT(*) as count,
                SUM(total_tokens) as tokens,
                SUM(estimated_cost) as cost
            FROM ai_usage_log
            WHERE project_id = :project_id
            GROUP BY operation_type
        ');
        $stmt->execute(['project_id' => $projectId]);
        $byType = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Statistiche degli ultimi 30 giorni
        $stmt = $pdo->prepare('
            SELECT
                DATE(created_at) as date,
                COUNT(*) as calls,
                SUM(estimated_cost) as cost
            FROM ai_usage_log
            WHERE project_id = :project_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ');
        $stmt->execute(['project_id' => $projectId]);
        $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'totals' => $totals,
            'by_type' => $byType,
            'daily' => $daily,
        ];
    }

    /**
     * Ottieni statistiche globali
     */
    public static function getGlobalStats(): array
    {
        $pdo = Database::getConnection();

        // Totali globali
        $stmt = $pdo->query('
            SELECT
                COUNT(*) as total_calls,
                SUM(total_tokens) as total_tokens,
                SUM(estimated_cost) as total_cost,
                MIN(created_at) as first_call,
                MAX(created_at) as last_call
            FROM ai_usage_log
        ');
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        // Per progetto
        $stmt = $pdo->query('
            SELECT
                p.name as project_name,
                COUNT(*) as calls,
                SUM(al.estimated_cost) as cost
            FROM ai_usage_log al
            LEFT JOIN projects p ON al.project_id = p.id
            GROUP BY al.project_id
            ORDER BY cost DESC
        ');
        $byProject = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Questo mese vs mese scorso
        $stmt = $pdo->query('
            SELECT
                CASE
                    WHEN MONTH(created_at) = MONTH(NOW()) THEN "current_month"
                    ELSE "last_month"
                END as period,
                SUM(estimated_cost) as cost
            FROM ai_usage_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH)
            GROUP BY period
        ');
        $monthly = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'totals' => $totals,
            'by_project' => $byProject,
            'monthly_comparison' => $monthly,
        ];
    }

    /**
     * Stima il costo per un batch di URL
     */
    public static function estimateBatchCost(int $urlCount, array $operations = ['title', 'description', 'classification']): array
    {
        $model = Setting::get('openai_model', 'gpt-3.5-turbo');
        $pricing = self::PRICING[$model] ?? self::PRICING['gpt-3.5-turbo'];

        // Stima token medi per operazione
        $avgTokens = [
            'title' => ['input' => 150, 'output' => 20],
            'description' => ['input' => 200, 'output' => 40],
            'classification' => ['input' => 180, 'output' => 5],
        ];

        $totalCost = 0;
        $totalTokens = 0;

        foreach ($operations as $op) {
            if (isset($avgTokens[$op])) {
                $inputCost = ($avgTokens[$op]['input'] / 1000) * $pricing['input'] * $urlCount;
                $outputCost = ($avgTokens[$op]['output'] / 1000) * $pricing['output'] * $urlCount;
                $totalCost += $inputCost + $outputCost;
                $totalTokens += ($avgTokens[$op]['input'] + $avgTokens[$op]['output']) * $urlCount;
            }
        }

        return [
            'estimated_cost' => $totalCost,
            'estimated_tokens' => $totalTokens,
            'url_count' => $urlCount,
            'operations' => $operations,
            'model' => $model,
            'cost_per_url' => $urlCount > 0 ? $totalCost / $urlCount : 0,
        ];
    }
}