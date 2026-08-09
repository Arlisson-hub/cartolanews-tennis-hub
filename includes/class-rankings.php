<?php
defined('ABSPATH') || exit;

/**
 * Repositório do ranking mundial oficial (ATP/WTA), wp_cn_tennis_rankings.
 *
 * Mantém apenas a posição mais recente por jogador/gênero/tipo (upsert), e
 * grava uma cópia em wp_cn_tennis_ranking_snapshots para permitir gráficos de
 * evolução no futuro (seção 57) sem custo de armazenamento hoje.
 */
final class CN_Tennis_Rankings {
    public static function table(): string {
        return CN_Tennis_Database::tables()['rankings'];
    }

    public static function upsert(int $player_id, string $gender, array $row, string $ranking_type = 'singles'): void {
        global $wpdb;
        $table = self::table();
        if ($player_id <= 0 || empty($row['rank_position'])) {
            return;
        }
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, rank_position FROM {$table} WHERE gender=%s AND ranking_type=%s AND player_id=%d",
            $gender,
            $ranking_type,
            $player_id
        ), ARRAY_A);

        if ($existing && (int) ($existing['rank_position'] ?? 0) > 0) {
            $previous = (int) $existing['rank_position'];
        } else {
            $previous = isset($row['previous_rank']) ? absint($row['previous_rank']) : null;
        }

        $now = current_time('mysql', true);
        $data = [
            'player_id' => $player_id,
            'gender' => $gender,
            'ranking_type' => $ranking_type,
            'rank_position' => absint($row['rank_position']),
            'previous_rank' => $previous ?: null,
            'points' => absint($row['points'] ?? 0),
            'tournaments_played' => isset($row['tournaments_played']) ? absint($row['tournaments_played']) : null,
            'ranking_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($row['ranking_date'] ?? '')) ? $row['ranking_date'] : current_time('Y-m-d'),
            'source' => sanitize_text_field((string) ($row['source'] ?? '')),
            'source_url' => esc_url_raw((string) ($row['source_url'] ?? '')),
            'updated_at' => $now,
        ];

        if ($existing) {
            $wpdb->update($table, $data, ['id' => (int) $existing['id']]);
        } else {
            $data['created_at'] = $now;
            $data['manual_override'] = 0;
            $wpdb->insert($table, $data);
        }

        $wpdb->query($wpdb->prepare(
            "INSERT INTO " . CN_Tennis_Database::tables()['ranking_snapshots'] . "
             (snapshot_type, gender, player_id, rank_position, score, captured_at, created_at)
             VALUES ('official', %s, %d, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE rank_position=VALUES(rank_position), score=VALUES(score)",
            $gender,
            $player_id,
            absint($row['rank_position']),
            absint($row['points'] ?? 0),
            $data['ranking_date'],
            $now
        ));
    }

    /** @return array Linhas de ranking com dados do jogador (JOIN), ordenadas por posição. */
    public static function query(string $gender, int $limit = 100, string $ranking_type = 'singles'): array {
        global $wpdb;
        $table = self::table();
        $players = CN_Tennis_Players::table();
        $limit = max(1, min(500, $limit));
        $sql = "SELECT r.*, p.slug player_slug, p.name player_name, p.full_name player_full_name,
                       p.country_code, p.country, p.photo_attachment_id, p.is_brazilian, p.birth_date
                FROM {$table} r
                INNER JOIN {$players} p ON p.id = r.player_id
                WHERE r.gender=%s AND r.ranking_type=%s AND p.status='active'
                ORDER BY r.rank_position ASC
                LIMIT %d";
        return $wpdb->get_results($wpdb->prepare($sql, $gender, $ranking_type, $limit), ARRAY_A) ?: [];
    }

    public static function brazilians(string $gender, string $ranking_type = 'singles'): array {
        global $wpdb;
        $table = self::table();
        $players = CN_Tennis_Players::table();
        $sql = "SELECT r.*, p.slug player_slug, p.name player_name, p.country_code, p.photo_attachment_id, p.birth_date
                FROM {$table} r
                INNER JOIN {$players} p ON p.id = r.player_id
                WHERE r.gender=%s AND r.ranking_type=%s AND p.status='active' AND p.is_brazilian=1
                ORDER BY r.rank_position ASC";
        return $wpdb->get_results($wpdb->prepare($sql, $gender, $ranking_type), ARRAY_A) ?: [];
    }

    public static function number_one(string $gender, string $ranking_type = 'singles'): ?array {
        $rows = self::query($gender, 1, $ranking_type);
        return $rows[0] ?? null;
    }

    public static function best_brazilian(string $ranking_type = 'singles'): ?array {
        $male = self::brazilians('male', $ranking_type);
        $female = self::brazilians('female', $ranking_type);
        $candidates = array_filter([$male[0] ?? null, $female[0] ?? null]);
        if (!$candidates) {
            return null;
        }
        usort($candidates, static fn($a, $b) => ((int) $a['rank_position']) <=> ((int) $b['rank_position']));
        return $candidates[0];
    }

    public static function last_update(string $gender, string $ranking_type = 'singles'): ?string {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            'SELECT MAX(updated_at) FROM ' . self::table() . ' WHERE gender=%s AND ranking_type=%s',
            $gender,
            $ranking_type
        )) ?: null;
    }
}
