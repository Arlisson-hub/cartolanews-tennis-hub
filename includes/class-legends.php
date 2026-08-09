<?php
defined('ABSPATH') || exit;

/**
 * Repositório de lendas do tênis (wp_cn_tennis_legends).
 *
 * Conteúdo híbrido: cadastro manual pelo admin (CRUD completo abaixo) ou
 * importação em lote via JSON (seção 11 / CN_Tennis_Import_Export). Nunca
 * preenche estatística sem "source" informado.
 */
final class CN_Tennis_Legends {
    public static function table(): string {
        return CN_Tennis_Database::tables()['legends'];
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

    public static function all(string $status = 'published'): array {
        global $wpdb;
        $table = self::table();
        if ($status === '') {
            return $wpdb->get_results("SELECT * FROM {$table} ORDER BY sort_order ASC, name ASC", ARRAY_A) ?: [];
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status=%s ORDER BY sort_order ASC, name ASC",
            $status
        ), ARRAY_A) ?: [];
    }

    /** @return array{ok:bool,id?:int,message?:string} */
    public static function save(array $data, int $id = 0): array {
        global $wpdb;
        $table = self::table();
        $name = sanitize_text_field((string) ($data['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Nome é obrigatório.'];
        }

        $now = current_time('mysql', true);
        $row = [
            'name' => $name,
            'country_code' => preg_match('/^[A-Za-z]{2,3}$/', (string) ($data['country_code'] ?? '')) ? strtoupper($data['country_code']) : null,
            'country' => sanitize_text_field((string) ($data['country'] ?? '')),
            'birth_date' => self::valid_date($data['birth_date'] ?? null),
            'death_date' => self::valid_date($data['death_date'] ?? null),
            'pro_period_start' => isset($data['pro_period_start']) ? absint($data['pro_period_start']) ?: null : null,
            'pro_period_end' => isset($data['pro_period_end']) ? absint($data['pro_period_end']) ?: null : null,
            'grand_slams_count' => isset($data['grand_slams_count']) && $data['grand_slams_count'] !== '' ? absint($data['grand_slams_count']) : null,
            'titles_count' => isset($data['titles_count']) && $data['titles_count'] !== '' ? absint($data['titles_count']) : null,
            'best_rank' => isset($data['best_rank']) && $data['best_rank'] !== '' ? absint($data['best_rank']) : null,
            'best_rank_date' => self::valid_date($data['best_rank_date'] ?? null),
            'best_surface' => in_array($data['best_surface'] ?? '', ['hard', 'clay', 'grass', 'indoor'], true) ? $data['best_surface'] : null,
            'description' => wp_kses_post((string) ($data['description'] ?? '')),
            'photo_attachment_id' => isset($data['photo_attachment_id']) ? absint($data['photo_attachment_id']) ?: null : null,
            'photo_credit_author' => sanitize_text_field((string) ($data['photo_credit_author'] ?? '')),
            'photo_credit_license' => sanitize_text_field((string) ($data['photo_credit_license'] ?? '')),
            'photo_credit_license_url' => esc_url_raw((string) ($data['photo_credit_license_url'] ?? '')),
            'photo_credit_source_url' => esc_url_raw((string) ($data['photo_credit_source_url'] ?? '')),
            'source' => sanitize_text_field((string) ($data['source'] ?? '')),
            'source_url' => esc_url_raw((string) ($data['source_url'] ?? '')),
            'status' => in_array($data['status'] ?? '', ['published', 'draft'], true) ? $data['status'] : 'draft',
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            'updated_at' => $now,
        ];

        if ($id > 0) {
            $wpdb->update($table, $row, ['id' => $id]);
            return ['ok' => true, 'id' => $id];
        }

        $row['slug'] = self::unique_slug($name);
        $row['created_at'] = $now;
        $wpdb->insert($table, $row);
        return ['ok' => true, 'id' => (int) $wpdb->insert_id];
    }

    public static function delete(int $id): bool {
        global $wpdb;
        return (bool) $wpdb->delete(self::table(), ['id' => $id]);
    }

    private static function valid_date(mixed $value): ?string {
        $value = (string) ($value ?? '');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private static function unique_slug(string $name): string {
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
}
