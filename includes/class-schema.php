<?php
defined('ABSPATH') || exit;

/**
 * Dados estruturados (JSON-LD) — seção 43. Só emite marcação quando existem
 * dados reais e confiáveis no banco; nunca preenche campo obrigatório do
 * schema com valor inventado (se faltar, o campo é omitido).
 */
final class CN_Tennis_Schema {
    public function register(): void {
        add_action('wp_head', [$this, 'maybe_emit_ranking_list'], 25);
        add_filter('wp_robots', [$this, 'noindex_rest_and_ajax']);
    }

    public function maybe_emit_ranking_list(): void {
        global $post;
        if (!$post instanceof WP_Post) {
            return;
        }
        $has_ranking = has_shortcode((string) $post->post_content, 'cn_tenis_ranking') || has_shortcode((string) $post->post_content, 'cn_tenis_hub');
        if (!$has_ranking || !CN_Tennis_Database::ready()) {
            return;
        }
        $rows = CN_Tennis_Rankings::query('male', 10);
        if (!$rows) {
            return;
        }
        $items = [];
        foreach ($rows as $i => $row) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => (int) $row['rank_position'],
                'item' => [
                    '@type' => 'Person',
                    'name' => $row['player_name'],
                    'url' => $row['player_slug'] ? CN_Tennis_Player_Profile::url($row['player_slug']) : home_url('/tenis/'),
                ],
            ];
        }
        $this->print_ld_json([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => 'Ranking Mundial de Tênis Masculino — CartolaNews',
            'itemListElement' => $items,
        ]);
    }

    public static function person_json_ld(array $player): string {
        $data = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $player['name'] ?? null,
            'alternateName' => (!empty($player['full_name']) && $player['full_name'] !== $player['name']) ? $player['full_name'] : null,
            'birthDate' => $player['birth_date'] ?: null,
            'nationality' => $player['country'] ?: null,
            'url' => !empty($player['slug']) ? CN_Tennis_Player_Profile::url($player['slug']) : null,
            'height' => $player['height_cm'] ? $player['height_cm'] . ' cm' : null,
        ]);
        ob_start();
        echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
        return (string) ob_get_clean();
    }

    private function print_ld_json(array $data): void {
        echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
    }

    public function noindex_rest_and_ajax(array $robots): array {
        if ((defined('REST_REQUEST') && REST_REQUEST) || wp_doing_ajax()) {
            $robots['noindex'] = true;
        }
        return $robots;
    }
}
