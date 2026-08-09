<?php
defined('ABSPATH') || exit;

/**
 * Página de perfil do jogador em URL amigável e permanente:
 * /tenis/jogador/{slug}/ (seção 17).
 */
final class CN_Tennis_Player_Profile {
    public const QUERY_VAR = 'cn_tennis_player';

    public function register(): void {
        add_action('init', [$this, 'add_rewrite_rule']);
        add_filter('query_vars', [$this, 'add_query_var']);
        add_filter('template_include', [$this, 'maybe_render']);
    }

    public function add_rewrite_rule(): void {
        add_rewrite_rule('^tenis/jogador/([^/]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top');
    }

    public function add_query_var(array $vars): array {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    public function maybe_render(string $template): string {
        $slug = get_query_var(self::QUERY_VAR);
        if (!$slug) {
            return $template;
        }
        $player = CN_Tennis_Players::find_by_slug(sanitize_title((string) $slug));
        if (!$player || !CN_Tennis_Database::ready()) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return get_404_template() ?: $template;
        }
        status_header(200);
        CN_Tennis_Assets::enqueue();
        $GLOBALS['cn_tennis_current_player'] = $player;
        return CN_TENNIS_PATH . 'public/templates/player-profile.php';
    }

    public static function url(string $slug): string {
        return home_url('/tenis/jogador/' . rawurlencode($slug) . '/');
    }
}
