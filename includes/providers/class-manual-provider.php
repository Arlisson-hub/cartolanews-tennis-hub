<?php
defined('ABSPATH') || exit;

/**
 * Provider "manual": não busca nada pela rede — apenas expõe, no formato
 * padronizado, os registros já cadastrados pelo administrador via painel
 * (ou importados por JSON). Usado quando não existe fonte automática
 * disponível para um dado, ou como base do override manual (seção 36).
 */
final class CN_Tennis_Manual_Provider implements CN_Tennis_Provider_Interface {
    public function get_id(): string {
        return 'manual';
    }

    public function get_players(array $args = []): array {
        return CN_Tennis_Players::query(array_merge($args, ['limit' => 500]));
    }

    public function get_rankings(array $args = []): array {
        return [];
    }

    public function get_matches(array $args = []): array {
        global $wpdb;
        $table = CN_Tennis_Database::tables()['matches'];
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE provider='manual' ORDER BY scheduled_at DESC LIMIT 100"), ARRAY_A) ?: [];
    }

    public function get_tournaments(array $args = []): array {
        global $wpdb;
        $table = CN_Tennis_Database::tables()['tournaments'];
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE provider='manual' ORDER BY starts_at DESC LIMIT 100"), ARRAY_A) ?: [];
    }

    public function health_check(): array {
        return ['ok' => true, 'message' => 'Dados cadastrados manualmente pelo administrador.'];
    }
}
