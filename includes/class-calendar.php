<?php
defined('ABSPATH') || exit;

/**
 * Repositório de torneios / calendário (wp_cn_tennis_tournaments).
 */
final class CN_Tennis_Calendar {
    public static function table(): string {
        return CN_Tennis_Database::tables()['tournaments'];
    }

    public static function upsert_from_provider(array $data): int {
        global $wpdb;
        $table = self::table();
        $provider = sanitize_key((string) ($data['provider'] ?? 'manual'));
        $external_id = sanitize_text_field((string) ($data['external_id'] ?? ''));
        $name = sanitize_text_field((string) ($data['name'] ?? ''));
        if ($name === '') {
            return 0;
        }

        $existing = null;
        if ($external_id !== '') {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE provider=%s AND external_id=%s",
                $provider,
                $external_id
            ), ARRAY_A);
        }

        if ($existing && !empty($existing['manual_override'])) {
            return (int) $existing['id']; // Torneio travado por edição manual do administrador.
        }

        $now = current_time('mysql', true);
        $starts_at = self::valid_date($data['starts_at'] ?? null);
        $ends_at = self::valid_date($data['ends_at'] ?? null);

        $row = [
            'external_id' => $external_id ?: null,
            'provider' => $provider,
            'name' => $name,
            'city' => sanitize_text_field((string) ($data['city'] ?? '')),
            'country' => sanitize_text_field((string) ($data['country'] ?? '')),
            'country_code' => preg_match('/^[A-Za-z]{2,3}$/', (string) ($data['country_code'] ?? '')) ? strtoupper($data['country_code']) : null,
            'tour' => in_array($data['tour'] ?? '', ['atp', 'wta', 'both'], true) ? $data['tour'] : 'atp',
            'category' => sanitize_key((string) ($data['category'] ?? '')) ?: null,
            'surface' => in_array($data['surface'] ?? '', ['hard', 'clay', 'grass', 'indoor'], true) ? $data['surface'] : null,
            'starts_at' => $starts_at,
            'ends_at' => $ends_at,
            'prize_money' => isset($data['prize_money']) ? absint($data['prize_money']) : null,
            'prize_currency' => isset($data['prize_currency']) ? sanitize_text_field($data['prize_currency']) : null,
            'draw_size' => isset($data['draw_size']) ? absint($data['draw_size']) : null,
            'status' => self::status_from_dates($starts_at, $ends_at, (string) ($data['status'] ?? '')),
            'metadata' => wp_json_encode($data['metadata'] ?? []),
            'updated_at' => $now,
        ];

        if ($existing) {
            $wpdb->update($table, $row, ['id' => (int) $existing['id']]);
            return (int) $existing['id'];
        }

        $row['slug'] = self::unique_slug($name);
        $row['manual_override'] = 0;
        $row['created_at'] = $now;
        $wpdb->insert($table, $row);
        return (int) $wpdb->insert_id;
    }

    private static function status_from_dates(?string $starts, ?string $ends, string $declared): string {
        if (in_array($declared, ['upcoming', 'ongoing', 'finished', 'cancelled'], true)) {
            return $declared;
        }
        $today = current_time('Y-m-d');
        if (!$starts) {
            return 'upcoming';
        }
        if ($ends) {
            if ($today > $ends) {
                return 'finished';
            }
            return $today >= $starts ? 'ongoing' : 'upcoming';
        }
        // Fonte sem data de término confirmada (comum no calendário via
        // Wikipédia — ver docs/DATA-SOURCES.md): sem isso, um torneio de
        // janeiro ficaria marcado "Em andamento" para sempre. Assumimos no
        // máximo 14 dias de duração (cobre até Grand Slam) só para decidir
        // a etiqueta de status — nunca gravamos essa data assumida no
        // banco como se fosse confirmada.
        $assumed_end = gmdate('Y-m-d', strtotime($starts . ' +14 days'));
        if ($today > $assumed_end) {
            return 'finished';
        }
        return $today >= $starts ? 'ongoing' : 'upcoming';
    }

    private static function valid_date(mixed $value): ?string {
        $value = (string) ($value ?? '');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private static function unique_slug(string $name): string {
        global $wpdb;
        $table = self::table();
        $base = sanitize_title($name . '-' . current_time('Y'));
        $slug = $base;
        $i = 1;
        while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug=%s", $slug))) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }

    /**
     * @param array $args from, to, tour(atp|wta|all), category, country_code(BRA p/ filtro Brasil)
     */
    public static function query(array $args = []): array {
        global $wpdb;
        $table = self::table();
        $where = ['1=1'];
        $params = [];

        if (!empty($args['from'])) {
            $where[] = '(ends_at IS NULL OR ends_at >= %s)';
            $params[] = $args['from'];
        }
        if (!empty($args['to'])) {
            $where[] = 'starts_at <= %s';
            $params[] = $args['to'];
        }
        if (in_array($args['tour'] ?? '', ['atp', 'wta'], true)) {
            $where[] = "(tour=%s OR tour='both')";
            $params[] = $args['tour'];
        }
        if (!empty($args['category'])) {
            $where[] = 'category=%s';
            $params[] = sanitize_key($args['category']);
        }
        if (!empty($args['country_code'])) {
            $where[] = 'country_code=%s';
            $params[] = strtoupper($args['country_code']);
        }

        $limit = max(1, min(300, (int) ($args['limit'] ?? 100)));
        $params[] = $limit;
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY starts_at ASC LIMIT %d";
        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];
    }

    public static function count_ongoing(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::table() . " WHERE status='ongoing'");
    }

    public static function find(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d', $id), ARRAY_A);
        return $row ?: null;
    }
}
