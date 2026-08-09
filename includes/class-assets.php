<?php
defined('ABSPATH') || exit;

/**
 * Enfileira CSS/JS do plugin. Carregado somente nas páginas onde algum
 * shortcode do Tennis Hub está presente (seção 41 — performance), com
 * fallback no wp_footer para shortcodes injetados por Elementor/widgets que
 * não passam pelo conteúdo padrão do post.
 */
final class CN_Tennis_Assets {
    private const HANDLE = 'cn-tennis-public';
    private static bool $enqueued = false;

    public function register(): void {
        add_action('init', [$this, 'register_assets']);
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue']);
        add_action('wp_footer', [$this, 'maybe_enqueue'], 1);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
    }

    public function register_assets(): void {
        wp_register_style(self::HANDLE, CN_TENNIS_URL . 'public/css/cn-tennis-public.css', [], CN_TENNIS_VERSION);
        wp_register_script(self::HANDLE, CN_TENNIS_URL . 'public/js/cn-tennis-public.js', [], CN_TENNIS_VERSION, true);
    }

    public function maybe_enqueue(): void {
        if (self::$enqueued || is_admin()) {
            return;
        }
        if (!$this->current_content_has_shortcode()) {
            return;
        }
        self::enqueue();
    }

    public static function enqueue(): void {
        if (self::$enqueued) {
            return;
        }
        self::$enqueued = true;
        wp_enqueue_style(self::HANDLE);
        wp_enqueue_script(self::HANDLE);

        $settings = CN_Tennis_Settings::all();
        wp_add_inline_style(self::HANDLE, sprintf(
            ':root{--cnt-primary:%s;--cnt-accent:%s;}',
            esc_attr($settings['primary_color']),
            esc_attr($settings['accent_color'])
        ));

        wp_localize_script(self::HANDLE, 'cnTennisData', [
            'restUrl' => esc_url_raw(rest_url('cartolanews-tennis/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'cacheEpoch' => CN_Tennis_Cache::epoch(),
            'i18n' => [
                'updatedAgo' => 'Atualizado',
                'liveUnavailable' => 'Atualização temporariamente indisponível',
                'noData' => 'Sem dados no momento.',
            ],
        ]);
    }

    private function current_content_has_shortcode(): bool {
        global $post;
        $tags = array_keys(CN_Tennis_Shortcodes::MAP);
        if ($post instanceof WP_Post) {
            foreach ($tags as $tag) {
                if (has_shortcode((string) $post->post_content, $tag)) {
                    return true;
                }
            }
            $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
            if (is_string($elementor_data)) {
                foreach ($tags as $tag) {
                    if (str_contains($elementor_data, $tag)) {
                        return true;
                    }
                }
            }
        }
        return (bool) apply_filters('cn_tennis_force_assets', false);
    }

    public function admin_assets(string $hook): void {
        if (!str_contains($hook, 'cn-tennis')) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_style('cn-tennis-admin', CN_TENNIS_URL . 'admin/css/cn-tennis-admin.css', [], CN_TENNIS_VERSION);
        wp_enqueue_script('cn-tennis-admin', CN_TENNIS_URL . 'admin/js/cn-tennis-admin.js', ['jquery', 'wp-color-picker'], CN_TENNIS_VERSION, true);
        wp_localize_script('cn-tennis-admin', 'cnTennisAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => esc_url_raw(rest_url('cartolanews-tennis/v1')),
        ]);
    }
}
