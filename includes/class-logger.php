<?php
defined('ABSPATH') || exit;

/**
 * Log estruturado de sincronização, gravado em wp_cn_tennis_sync_logs.
 *
 * Nunca registra segredos (API key, senha, Application Password) — qualquer
 * valor de configuração sensível é removido da mensagem antes de gravar.
 */
final class CN_Tennis_Logger {
    public static function log(string $task, string $status, array $data = []): void {
        global $wpdb;
        $table = CN_Tennis_Database::tables()['sync_logs'];
        $now = current_time('mysql', true);

        $message = (string) ($data['message'] ?? '');
        foreach (self::secrets() as $secret) {
            if ($secret !== '') {
                $message = str_replace($secret, '[redacted]', $message);
            }
        }

        $wpdb->insert($table, [
            'task' => sanitize_key($task),
            'provider' => isset($data['provider']) ? sanitize_key((string) $data['provider']) : null,
            'endpoint' => isset($data['endpoint']) ? sanitize_text_field((string) $data['endpoint']) : null,
            'status' => sanitize_key($status),
            'http_code' => isset($data['http_code']) ? (int) $data['http_code'] : null,
            'duration_ms' => isset($data['duration_ms']) ? (int) $data['duration_ms'] : null,
            'received' => (int) ($data['received'] ?? 0),
            'created_count' => (int) ($data['created'] ?? 0),
            'updated_count' => (int) ($data['updated'] ?? 0),
            'message' => $message !== '' ? substr($message, 0, 2000) : null,
            'created_at' => $now,
        ]);
    }

    private static function secrets(): array {
        $settings = CN_Tennis_Settings::all();
        return array_filter([
            (string) ($settings['thesportsdb_api_key'] ?? ''),
            (string) ($settings['sync_shared_secret'] ?? ''),
        ]);
    }

    public static function recent(int $limit = 50, string $task = '', string $status = ''): array {
        global $wpdb;
        $table = CN_Tennis_Database::tables()['sync_logs'];
        $where = ['1=1'];
        $params = [];
        if ($task !== '') {
            $where[] = 'task = %s';
            $params[] = $task;
        }
        if ($status !== '') {
            $where[] = 'status = %s';
            $params[] = $status;
        }
        $params[] = $limit;
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT %d';
        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];
    }

    public static function last_success(string $task): ?string {
        global $wpdb;
        $table = CN_Tennis_Database::tables()['sync_logs'];
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(created_at) FROM {$table} WHERE task = %s AND status = 'success'",
            $task
        ));
        return $value ?: null;
    }

    /** Remove logs mais antigos que N dias (executado semanalmente pelo cron). */
    public static function cleanup(int $days = 60): int {
        global $wpdb;
        $table = CN_Tennis_Database::tables()['sync_logs'];
        $before = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        return (int) $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at < %s", $before));
    }
}
