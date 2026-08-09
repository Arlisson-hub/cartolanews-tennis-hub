<?php
defined('ABSPATH') || exit;

/**
 * Repositório de jogadores (wp_cn_tennis_players).
 *
 * Qualquer campo listado em `manual_override` de um jogador é IGNORADO nas
 * atualizações automáticas de sync (seção 36 — override manual não pode ser
 * sobrescrito até o administrador remover o bloqueio).
 */
final class CN_Tennis_Players {
    private const OVERRIDABLE_FIELDS = [
        'name', 'full_name', 'country_code', 'country', 'birth_date', 'height_cm',
        'plays', 'turned_pro', 'photo_attachment_id', 'is_brazilian',
    ];

    public static function table(): string {
        return CN_Tennis_Database::tables()['players'];
    }

    /**
     * Cria ou atualiza um jogador a partir de dados normalizados de provider.
     * Nunca sobrescreve campos travados por manual_override.
     */
    public static function upsert_from_provider(array $data): int {
        global $wpdb;
        $table = self::table();
        $provider = sanitize_key((string) ($data['provider'] ?? 'manual'));
        $external_id = sanitize_text_field((string) ($data['external_id'] ?? ''));
        $name = sanitize_text_field((string) ($data['name'] ?? ''));
        if ($name === '') {
            return 0; // Nunca gravamos jogador sem nome (seção 27 — validação).
        }

        $existing = null;
        if ($external_id !== '') {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE provider=%s AND external_id=%s",
                $provider,
                $external_id
            ), ARRAY_A);
        }
        if (!$existing) {
            $slug_guess = sanitize_title($name);
            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE slug=%s", $slug_guess), ARRAY_A);
        }

        $locked = $existing ? self::locked_fields($existing) : [];
        $now = current_time('mysql', true);

        $row = [
            'external_id' => $external_id ?: null,
            'provider' => $provider,
            'name' => $name,
            'full_name' => sanitize_text_field((string) ($data['full_name'] ?? $name)),
            'gender' => in_array($data['gender'] ?? '', ['male', 'female'], true) ? $data['gender'] : 'male',
            'country_code' => self::country_code((string) ($data['country_code'] ?? '')),
            'country' => sanitize_text_field((string) ($data['country'] ?? '')),
            'birth_date' => self::valid_date($data['birth_date'] ?? null),
            'height_cm' => isset($data['height_cm']) ? absint($data['height_cm']) ?: null : null,
            'plays' => in_array($data['plays'] ?? '', ['right', 'left'], true) ? $data['plays'] : null,
            'turned_pro' => isset($data['turned_pro']) ? absint($data['turned_pro']) ?: null : null,
            'current_rank_singles' => isset($data['current_rank_singles']) ? absint($data['current_rank_singles']) ?: null : null,
            'current_rank_points' => isset($data['current_rank_points']) ? absint($data['current_rank_points']) : null,
            'best_rank_singles' => isset($data['best_rank_singles']) ? absint($data['best_rank_singles']) ?: null : null,
            'best_rank_date' => self::valid_date($data['best_rank_date'] ?? null),
            'titles_count' => isset($data['titles_count']) ? absint($data['titles_count']) : null,
            'is_brazilian' => strtoupper((string) ($data['country_code'] ?? '')) === 'BRA' ? 1 : 0,
            'metadata' => wp_json_encode($data['metadata'] ?? []),
            'updated_at' => $now,
        ];

        foreach ($locked as $field) {
            unset($row[$field]);
        }

        if ($existing) {
            $wpdb->update($table, $row, ['id' => (int) $existing['id']]);
            return (int) $existing['id'];
        }

        $row['slug'] = self::unique_slug($name, (int) ($data['country_code'] ? crc32((string) $data['country_code']) : 0));
        $row['status'] = 'active';
        $row['manual_override'] = wp_json_encode([]);
        $row['photo_focal_x'] = 50;
        $row['photo_focal_y'] = 50;
        $row['created_at'] = $now;
        $wpdb->insert($table, $row);
        return (int) $wpdb->insert_id;
    }

    private static function locked_fields(array $existing): array {
        $decoded = json_decode((string) ($existing['manual_override'] ?? ''), true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_intersect($decoded, self::OVERRIDABLE_FIELDS));
    }

    public static function set_override(int $player_id, array $fields, bool $locked): void {
        global $wpdb;
        $table = self::table();
        $current = $wpdb->get_var($wpdb->prepare("SELECT manual_override FROM {$table} WHERE id=%d", $player_id));
        $set = is_array(json_decode((string) $current, true)) ? json_decode((string) $current, true) : [];
        foreach ($fields as $field) {
            if (!in_array($field, self::OVERRIDABLE_FIELDS, true)) {
                continue;
            }
            $set = $locked ? array_unique([...$set, $field]) : array_values(array_diff($set, [$field]));
        }
        $wpdb->update($table, ['manual_override' => wp_json_encode(array_values($set))], ['id' => $player_id]);
    }

    public static function overridable_fields(): array {
        return self::OVERRIDABLE_FIELDS;
    }

    private static function country_code(string $value): ?string {
        $value = strtoupper(trim($value));
        return preg_match('/^[A-Z]{3}$/', $value) ? $value : null;
    }

    private static function valid_date(mixed $value): ?string {
        if (!$value) {
            return null;
        }
        $value = (string) $value;
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private static function unique_slug(string $name, int $salt = 0): string {
        global $wpdb;
        $table = self::table();
        $base = sanitize_title($name);
        $slug = $base;
        $i = 1;
        while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug=%s", $slug))) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }

    public static function find(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d', $id), ARRAY_A);
        return $row ?: null;
    }

    public static function find_by_slug(string $slug): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE slug=%s', sanitize_title($slug)), ARRAY_A);
        return $row ?: null;
    }

    /**
     * @param array $args gender, is_brazilian, search, orderby (rank|name), limit, offset
     */
    public static function query(array $args = []): array {
        global $wpdb;
        $table = self::table();
        $where = ["status='active'"];
        $params = [];

        if (in_array($args['gender'] ?? '', ['male', 'female'], true)) {
            $where[] = 'gender=%s';
            $params[] = $args['gender'];
        }
        if (!empty($args['is_brazilian'])) {
            $where[] = 'is_brazilian=1';
        }
        if (!empty($args['search'])) {
            $where[] = '(name LIKE %s OR full_name LIKE %s)';
            $like = '%' . $wpdb->esc_like((string) $args['search']) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $orderby = ($args['orderby'] ?? 'rank') === 'name'
            ? 'name ASC'
            : 'current_rank_singles IS NULL, current_rank_singles ASC';

        $limit = max(1, min(500, (int) ($args['limit'] ?? 50)));
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY {$orderby} LIMIT %d OFFSET %d";
        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];
    }

    public static function count_active(string $gender = ''): int {
        global $wpdb;
        $table = self::table();
        if (in_array($gender, ['male', 'female'], true)) {
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status='active' AND gender=%s", $gender));
        }
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status='active'");
    }
}
