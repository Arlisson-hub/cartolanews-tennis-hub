<?php
defined('ABSPATH') || exit;

/**
 * Configurações do plugin (wp_options: cn_tennis_settings), registradas via
 * Settings API do WordPress para reaproveitar sanitização, nonces e telas
 * padrão de admin.
 */
final class CN_Tennis_Settings {
    public const OPTION = 'cn_tennis_settings';

    public static function defaults(): array {
        return [
            // Fontes gratuitas (Prioridade 1) — ver docs/DATA-SOURCES.md
            'thesportsdb_api_key' => '123',
            // Feeds JSON publicados pelo GitHub Actions (Prioridade 2 — fontes
            // oficiais públicas coletadas fora do WordPress, ver seção 21/22).
            'feed_rankings_male_url' => '',
            'feed_rankings_female_url' => '',
            'feed_calendar_url' => '',
            'feed_players_url' => '',
            // Cache
            'cache_rankings_minutes' => 720,
            'cache_calendar_minutes' => 360,
            'cache_matches_minutes' => 15,
            // Ao vivo: depois desse tempo sem atualização da fonte, o selo
            // AO VIVO é removido automaticamente (seção 13).
            'live_stale_minutes' => 20,
            // Power Ranking — pesos devem somar 100. Fórmula documentada em
            // includes/class-power-ranking.php.
            'power_ranking_window_days' => 90,
            'power_ranking_min_matches' => 5,
            'power_ranking_weight_recent_form' => 30,
            'power_ranking_weight_opponent_strength' => 25,
            'power_ranking_weight_tournament_importance' => 20,
            'power_ranking_weight_surface' => 10,
            'power_ranking_weight_streak' => 15,
            // Notícias (posts nativos do WordPress, nunca de terceiros)
            'news_category' => '',
            'news_limit' => 6,
            // Aparência
            'primary_color' => '#144b9b',
            'accent_color' => '#7494c4',
            // Ciclo de vida
            'uninstall_keep_data' => 1,
            'fallback_enabled' => 1,
        ];
    }

    public static function all(): array {
        return wp_parse_args((array) get_option(self::OPTION, []), self::defaults());
    }

    public static function get(string $key): mixed {
        $all = self::all();
        return $all[$key] ?? null;
    }

    public static function register(): void {
        register_setting('cn_tennis_settings_group', self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize'],
            'default' => self::defaults(),
        ]);
    }

    public static function sanitize(array $input): array {
        $current = self::all();
        $out = $current;

        $text_fields = ['feed_rankings_male_url', 'feed_rankings_female_url', 'feed_calendar_url', 'feed_players_url'];
        foreach ($text_fields as $field) {
            if (isset($input[$field])) {
                $out[$field] = esc_url_raw(trim((string) $input[$field]));
            }
        }

        if (isset($input['thesportsdb_api_key'])) {
            $out['thesportsdb_api_key'] = preg_replace('/[^A-Za-z0-9]/', '', (string) $input['thesportsdb_api_key']) ?: '123';
        }

        $minute_fields = [
            'cache_rankings_minutes' => [15, 10080],
            'cache_calendar_minutes' => [15, 10080],
            'cache_matches_minutes' => [1, 1440],
            'live_stale_minutes' => [2, 180],
            'power_ranking_window_days' => [7, 365],
            'power_ranking_min_matches' => [1, 50],
            'news_limit' => [1, 24],
        ];
        foreach ($minute_fields as $field => [$min, $max]) {
            if (isset($input[$field])) {
                $out[$field] = max($min, min($max, absint($input[$field])));
            }
        }

        $weight_fields = [
            'power_ranking_weight_recent_form',
            'power_ranking_weight_opponent_strength',
            'power_ranking_weight_tournament_importance',
            'power_ranking_weight_surface',
            'power_ranking_weight_streak',
        ];
        foreach ($weight_fields as $field) {
            if (isset($input[$field])) {
                $out[$field] = max(0, min(100, absint($input[$field])));
            }
        }

        if (isset($input['news_category'])) {
            $out['news_category'] = sanitize_title((string) $input['news_category']);
        }

        foreach (['primary_color', 'accent_color'] as $field) {
            if (isset($input[$field])) {
                $color = sanitize_hex_color((string) $input[$field]);
                if ($color) {
                    $out[$field] = $color;
                }
            }
        }

        $out['uninstall_keep_data'] = !empty($input['uninstall_keep_data']) ? 1 : 0;
        $out['fallback_enabled'] = !empty($input['fallback_enabled']) ? 1 : 0;

        return $out;
    }
}
