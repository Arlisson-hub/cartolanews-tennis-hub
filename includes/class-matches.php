<?php
defined('ABSPATH') || exit;

/**
 * Repositório de partidas (wp_cn_tennis_matches).
 *
 * Regra crítica (seção 13): o status "live" só é confiável enquanto
 * source_updated_at estiver dentro da janela configurada em
 * live_stale_minutes. is_currently_live() é a ÚNICA função que deve decidir
 * se o selo "AO VIVO" aparece — nunca inferir isso apenas pelo horário
 * agendado.
 */
final class CN_Tennis_Matches {
    public static function table(): string {
        return CN_Tennis_Database::tables()['matches'];
    }

    public static function upsert_from_provider(array $data): int {
        global $wpdb;
        $table = self::table();
        $provider = sanitize_key((string) ($data['provider'] ?? 'manual'));
        $external_id = sanitize_text_field((string) ($data['external_id'] ?? ''));
        if ($external_id === '') {
            return 0; // Sem identificador estável não é possível deduplicar com segurança.
        }
        if (empty($data['scheduled_at']) || empty($data['player1_name']) || empty($data['player2_name'])) {
            return 0; // Validação mínima (seção 27).
        }
        if (trim((string) $data['player1_name']) === trim((string) $data['player2_name'])) {
            return 0; // Adversários precisam ser diferentes.
        }

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE provider=%s AND external_id=%s",
            $provider,
            $external_id
        ), ARRAY_A);

        $now = current_time('mysql', true);
        $status = self::normalize_status((string) ($data['status'] ?? 'scheduled'));

        $row = [
            'tournament_id' => isset($data['tournament_id']) ? absint($data['tournament_id']) ?: null : null,
            'round_name' => sanitize_text_field((string) ($data['round_name'] ?? '')) ?: null,
            'gender' => in_array($data['gender'] ?? '', ['male', 'female'], true) ? $data['gender'] : 'male',
            'match_type' => in_array($data['match_type'] ?? '', ['singles', 'doubles'], true) ? $data['match_type'] : 'singles',
            'surface' => in_array($data['surface'] ?? '', ['hard', 'clay', 'grass', 'indoor'], true) ? $data['surface'] : null,
            'player1_id' => isset($data['player1_id']) ? absint($data['player1_id']) ?: null : null,
            'player2_id' => isset($data['player2_id']) ? absint($data['player2_id']) ?: null : null,
            'player1_name' => sanitize_text_field((string) $data['player1_name']),
            'player2_name' => sanitize_text_field((string) $data['player2_name']),
            'player1_country' => self::country_code((string) ($data['player1_country'] ?? '')),
            'player2_country' => self::country_code((string) ($data['player2_country'] ?? '')),
            'player1_rank' => isset($data['player1_rank']) ? absint($data['player1_rank']) ?: null : null,
            'player2_rank' => isset($data['player2_rank']) ? absint($data['player2_rank']) ?: null : null,
            'scheduled_at' => gmdate('Y-m-d H:i:s', strtotime((string) $data['scheduled_at']) ?: time()),
            'status' => $status,
            'winner' => in_array($data['winner'] ?? null, [1, 2], true) ? (int) $data['winner'] : null,
            'score_json' => wp_json_encode($data['score'] ?? null),
            'duration_minutes' => isset($data['duration_minutes']) ? absint($data['duration_minutes']) : null,
            'is_live_confirmed' => $status === 'live' ? 1 : 0,
            'source_updated_at' => $now,
            'metadata' => wp_json_encode($data['metadata'] ?? []),
            'updated_at' => $now,
        ];

        if ($existing) {
            $wpdb->update($table, $row, ['id' => (int) $existing['id']]);
            return (int) $existing['id'];
        }

        $row['provider'] = $provider;
        $row['external_id'] = $external_id;
        $row['created_at'] = $now;
        $wpdb->insert($table, $row);
        return (int) $wpdb->insert_id;
    }

    private static function normalize_status(string $status): string {
        $status = strtolower($status);
        return match (true) {
            in_array($status, ['live', 'in_progress', 'playing'], true) => 'live',
            in_array($status, ['finished', 'completed', 'ft'], true) => 'finished',
            in_array($status, ['postponed', 'delayed'], true) => 'postponed',
            in_array($status, ['cancelled', 'canceled', 'walkover'], true) => 'cancelled',
            default => 'scheduled',
        };
    }

    private static function country_code(string $value): ?string {
        $value = strtoupper(trim($value));
        return preg_match('/^[A-Z]{2,3}$/', $value) ? $value : null;
    }

    /**
     * Único ponto de decisão sobre exibir "AO VIVO". Ver aviso de classe.
     */
    public static function is_currently_live(array $match): bool {
        if (($match['status'] ?? '') !== 'live' || empty($match['is_live_confirmed'])) {
            return false;
        }
        $stale_minutes = (int) CN_Tennis_Settings::get('live_stale_minutes');
        // source_updated_at é gravado em UTC (current_time('mysql', true));
        // forçar o fuso aqui evita depender do timezone padrão do PHP.
        $updated = strtotime((string) ($match['source_updated_at'] ?? '') . ' UTC');
        if (!$updated) {
            return false;
        }
        return (time() - $updated) <= ($stale_minutes * MINUTE_IN_SECONDS);
    }

    public static function is_live_stale(array $match): bool {
        return ($match['status'] ?? '') === 'live' && !self::is_currently_live($match);
    }

    /**
     * @param array $args from, to (datetime SQL UTC), gender(all|male|female), match_type(all|singles|doubles), status, limit
     */
    public static function query(array $args = []): array {
        global $wpdb;
        $table = self::table();
        $tournaments = CN_Tennis_Calendar::table();
        $where = ['1=1'];
        $params = [];

        if (!empty($args['from'])) {
            $where[] = 'm.scheduled_at >= %s';
            $params[] = $args['from'];
        }
        if (!empty($args['to'])) {
            $where[] = 'm.scheduled_at <= %s';
            $params[] = $args['to'];
        }
        if (in_array($args['gender'] ?? '', ['male', 'female'], true)) {
            $where[] = 'm.gender=%s';
            $params[] = $args['gender'];
        }
        if (in_array($args['match_type'] ?? '', ['singles', 'doubles'], true)) {
            $where[] = 'm.match_type=%s';
            $params[] = $args['match_type'];
        }
        if (in_array($args['status'] ?? '', ['scheduled', 'live', 'finished', 'postponed', 'cancelled'], true)) {
            $where[] = 'm.status=%s';
            $params[] = $args['status'];
        }
        if (!empty($args['surface'])) {
            $where[] = 'm.surface=%s';
            $params[] = $args['surface'];
        }
        if (!empty($args['tournament_id'])) {
            $where[] = 'm.tournament_id=%d';
            $params[] = (int) $args['tournament_id'];
        }

        $limit = max(1, min(200, (int) ($args['limit'] ?? 30)));
        $params[] = $limit;

        $sql = "SELECT m.*, t.name tournament_name, t.category tournament_category, t.surface tournament_surface
                FROM {$table} m
                LEFT JOIN {$tournaments} t ON t.id = m.tournament_id
                WHERE " . implode(' AND ', $where) . '
                ORDER BY m.scheduled_at ASC
                LIMIT %d';
        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];
    }

    public static function count_today(): int {
        global $wpdb;
        $tz = wp_timezone();
        $today = new DateTimeImmutable('today', $tz);
        $from = $today->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $to = $today->setTime(23, 59, 59)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::table() . ' WHERE scheduled_at BETWEEN %s AND %s',
            $from,
            $to
        ));
    }

    /** Jogos finalizados de um jogador nos últimos N dias, mais recentes primeiro (usado pelo Power Ranking e pelo H2H). */
    public static function finished_by_player(int $player_id, int $days): array {
        global $wpdb;
        $table = self::table();
        $since = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $sql = "SELECT * FROM {$table}
                WHERE status='finished' AND scheduled_at >= %s
                AND (player1_id=%d OR player2_id=%d)
                ORDER BY scheduled_at DESC";
        return $wpdb->get_results($wpdb->prepare($sql, $since, $player_id, $player_id), ARRAY_A) ?: [];
    }

    /** Confrontos diretos confirmados entre dois jogadores (nunca estimados — seção 18). */
    public static function head_to_head(int $player_a, int $player_b): array {
        global $wpdb;
        $table = self::table();
        $sql = "SELECT * FROM {$table}
                WHERE status='finished'
                AND ((player1_id=%d AND player2_id=%d) OR (player1_id=%d AND player2_id=%d))
                ORDER BY scheduled_at DESC";
        return $wpdb->get_results($wpdb->prepare($sql, $player_a, $player_b, $player_b, $player_a), ARRAY_A) ?: [];
    }
}
