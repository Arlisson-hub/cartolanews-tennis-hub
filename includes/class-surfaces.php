<?php
defined('ABSPATH') || exit;

/**
 * "Especialistas por Superfície" (seção 10). Calculado com partidas reais e
 * finalizadas — nunca por reputação genérica. Ranking simples de
 * aproveitamento (vitórias/jogos) na superfície, dentro da mesma janela de
 * dias usada pelo Power Ranking, com o mesmo mínimo de partidas.
 */
final class CN_Tennis_Surfaces {
    public static function leaderboard(string $surface, string $gender = '', int $limit = 5): array {
        if (!in_array($surface, ['hard', 'clay', 'grass', 'indoor'], true)) {
            return [];
        }
        $cache_key = "surfaces_{$surface}_{$gender}_{$limit}";
        return CN_Tennis_Cache::remember($cache_key, 15 * MINUTE_IN_SECONDS, static function () use ($surface, $gender, $limit) {
            global $wpdb;
            $matches = CN_Tennis_Matches::table();
            $players = CN_Tennis_Players::table();
            $settings = CN_Tennis_Settings::all();
            $since = gmdate('Y-m-d H:i:s', time() - ((int) $settings['power_ranking_window_days']) * DAY_IN_SECONDS);
            $min_matches = max(3, (int) $settings['power_ranking_min_matches']);

            $gender_clause = in_array($gender, ['male', 'female'], true) ? $wpdb->prepare('AND m.gender=%s', $gender) : '';

            $sql = "
                SELECT p.id, p.slug, p.name, p.country_code, p.photo_attachment_id, p.gender,
                       SUM(CASE WHEN (m.player1_id=p.id AND m.winner=1) OR (m.player2_id=p.id AND m.winner=2) THEN 1 ELSE 0 END) wins,
                       COUNT(*) total
                FROM {$matches} m
                INNER JOIN {$players} p ON p.id IN (m.player1_id, m.player2_id)
                WHERE m.status='finished' AND m.surface=%s AND m.scheduled_at >= %s {$gender_clause}
                GROUP BY p.id
                HAVING total >= %d
                ORDER BY (wins / total) DESC, total DESC
                LIMIT %d
            ";
            $rows = $wpdb->get_results($wpdb->prepare($sql, $surface, $since, $min_matches, $limit), ARRAY_A) ?: [];
            foreach ($rows as &$row) {
                $row['win_rate'] = $row['total'] > 0 ? round(($row['wins'] / $row['total']) * 100, 1) : 0;
            }
            return $rows;
        });
    }
}
