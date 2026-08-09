<?php
defined('ABSPATH') || exit;

/**
 * Repositório do "Painel de Fontes" (wp_cn_tennis_sources), exibido em
 * CartolaNews Tênis → Fontes (seção 35). Cada linha representa uma fonte de
 * sincronização e seu status mais recente (🟢/🟡/🔴/⚪).
 */
final class CN_Tennis_Sources {
    public const RANKINGS_MALE = 'rankings_male';
    public const RANKINGS_FEMALE = 'rankings_female';
    public const CALENDAR = 'calendar';
    public const MATCHES = 'matches';

    public static function table(): string {
        return CN_Tennis_Database::tables()['sources'];
    }

    public static function defaults(): array {
        return [
            self::RANKINGS_MALE => ['label' => 'Ranking Mundial Masculino (ATP)', 'provider' => 'atp', 'fallback_provider' => 'fallback', 'frequency_minutes' => 1440],
            self::RANKINGS_FEMALE => ['label' => 'Ranking Mundial Feminino (WTA)', 'provider' => 'wta', 'fallback_provider' => 'fallback', 'frequency_minutes' => 1440],
            self::CALENDAR => ['label' => 'Calendário de Torneios', 'provider' => 'github', 'fallback_provider' => 'fallback', 'frequency_minutes' => 480],
            self::MATCHES => ['label' => 'Jogos do Dia / Ao Vivo', 'provider' => 'thesportsdb', 'fallback_provider' => 'fallback', 'frequency_minutes' => 30],
        ];
    }

    public static function ensure_defaults(): void {
        global $wpdb;
        $table = self::table();
        $now = current_time('mysql', true);
        foreach (self::defaults() as $key => $row) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE source_key=%s", $key));
            if ($exists) {
                continue;
            }
            $wpdb->insert($table, [
                'source_key' => $key,
                'label' => $row['label'],
                'provider' => $row['provider'],
                'fallback_provider' => $row['fallback_provider'],
                'frequency_minutes' => $row['frequency_minutes'],
                'timeout_seconds' => 20,
                'retries' => 3,
                'priority' => 0,
                'enabled' => 1,
                'status' => 'unknown',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public static function all(): array {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . self::table() . ' ORDER BY priority DESC, label ASC', ARRAY_A) ?: [];
    }

    public static function find(string $key): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE source_key=%s', $key), ARRAY_A);
        return $row ?: null;
    }

    /** @param string $status success|partial|error|cached */
    public static function report(string $key, string $status, int $records = 0, int $duration_ms = 0, string $error = ''): void {
        global $wpdb;
        $table = self::table();
        $now = current_time('mysql', true);
        $data = [
            'status' => match ($status) {
                'success' => 'ok',
                'partial', 'cached' => 'partial',
                default => 'error',
            },
            'last_duration_ms' => $duration_ms,
            'last_records' => $records,
            'updated_at' => $now,
        ];
        if (in_array($status, ['success', 'partial', 'cached'], true)) {
            $data['last_success_at'] = $now;
        }
        if ($status === 'error') {
            $data['last_error_at'] = $now;
            $data['last_error_message'] = substr($error, 0, 500);
        }
        $wpdb->update($table, $data, ['source_key' => $key]);
    }

    public static function set_enabled(string $key, bool $enabled): void {
        global $wpdb;
        $wpdb->update(self::table(), ['enabled' => $enabled ? 1 : 0, 'updated_at' => current_time('mysql', true)], ['source_key' => $key]);
    }

    public static function update_config(string $key, array $fields): void {
        global $wpdb;
        $allowed = ['url', 'provider', 'fallback_provider', 'frequency_minutes', 'timeout_seconds', 'retries', 'priority'];
        $data = array_intersect_key($fields, array_flip($allowed));
        if (!$data) {
            return;
        }
        $data['updated_at'] = current_time('mysql', true);
        $wpdb->update(self::table(), $data, ['source_key' => $key]);
    }
}
