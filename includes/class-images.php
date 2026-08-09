<?php
defined('ABSPATH') || exit;

/**
 * Sistema de imagens do plugin: tamanhos registrados, placeholder elegante
 * quando não há foto, posição focal configurável e importação com créditos
 * do Wikimedia Commons (seção 29/30/31/33).
 *
 * Nunca usa width:100%/height:100% sem object-fit — o CSS correspondente
 * está em public/css/cn-tennis-public.css (.cn-tennis-image).
 */
final class CN_Tennis_Images {
    public const SIZES = [
        'cn-tennis-hero' => [1920, 640, true],
        'cn-tennis-player-card' => [800, 1000, true],
        'cn-tennis-legend-card' => [800, 1000, true],
        'cn-tennis-avatar' => [320, 320, true],
        'cn-tennis-tournament-card' => [1200, 675, true],
        'cn-tennis-surface-card' => [800, 500, true],
    ];

    public function register(): void {
        add_action('after_setup_theme', [$this, 'register_sizes']);
    }

    public function register_sizes(): void {
        foreach (self::SIZES as $name => [$w, $h, $crop]) {
            add_image_size($name, $w, $h, $crop);
        }
    }

    /**
     * Renderiza a imagem (com srcset nativo do WordPress) ou o placeholder.
     * $record precisa ter: name (ou nome equivalente), photo_attachment_id,
     * photo_focal_x, photo_focal_y (opcionais).
     */
    public static function render(array $record, string $size, string $name_field = 'name', array $extra_attr = [], bool $lazy = true): string {
        $attachment_id = (int) ($record['photo_attachment_id'] ?? 0);
        $name = (string) ($record[$name_field] ?? '');
        $focal_x = isset($record['photo_focal_x']) ? (int) $record['photo_focal_x'] : 50;
        $focal_y = isset($record['photo_focal_y']) ? (int) $record['photo_focal_y'] : 50;
        $style = sprintf('--cn-focal-x:%d%%;--cn-focal-y:%d%%;', $focal_x, $focal_y);

        if ($attachment_id && wp_attachment_is_image($attachment_id)) {
            $attr = array_merge([
                'class' => 'cn-tennis-image',
                'style' => $style,
                'alt' => $name,
                'loading' => $lazy ? 'lazy' : 'eager',
                'decoding' => 'async',
            ], $extra_attr);
            $html = wp_get_attachment_image($attachment_id, $size, false, $attr);
            if ($html) {
                return $html;
            }
        }

        return self::placeholder($name, $size);
    }

    /** Placeholder elegante: silhueta genérica + iniciais sobre gradiente azul CartolaNews (nunca imagem quebrada). */
    public static function placeholder(string $name, string $size): string {
        $initials = CN_Tennis_Helpers::initials($name ?: '?');
        return sprintf(
            '<span class="cn-tennis-image cn-tennis-placeholder cn-tennis-placeholder--%s" role="img" aria-label="%s"><span class="cn-tennis-placeholder__initials">%s</span></span>',
            esc_attr($size),
            esc_attr($name ?: 'Jogador sem foto cadastrada'),
            esc_html($initials)
        );
    }

    public static function credit_html(array $record): string {
        $author = trim((string) ($record['photo_credit_author'] ?? ''));
        $license = trim((string) ($record['photo_credit_license'] ?? ''));
        if ($author === '' && $license === '') {
            return '';
        }
        $license_url = (string) ($record['photo_credit_license_url'] ?? '');
        $source_url = (string) ($record['photo_credit_source_url'] ?? '');
        $license_html = $license !== '' && $license_url !== ''
            ? '<a href="' . esc_url($license_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($license) . '</a>'
            : esc_html($license);
        $parts = array_filter([
            $author !== '' ? 'Foto: ' . esc_html($author) : '',
            $license_html,
            $source_url !== '' ? '<a href="' . esc_url($source_url) . '" target="_blank" rel="noopener noreferrer">fonte</a>' : '',
        ]);
        return $parts ? '<small class="cn-tennis-photo-credit">' . implode(' · ', $parts) . '</small>' : '';
    }

    /**
     * Importa uma imagem do Wikimedia Commons pelo título do arquivo
     * (ex.: "File:Carlos Alcaraz (2023).jpg") e registra os créditos
     * exigidos pela licença. Ação sempre disparada manualmente pelo
     * administrador — não há varredura automática do Commons.
     *
     * @return array{ok:bool,attachment_id?:int,message:string}
     */
    public static function import_from_wikimedia(string $file_title): array {
        $file_title = trim($file_title);
        if ($file_title === '') {
            return ['ok' => false, 'message' => 'Informe o título do arquivo no Wikimedia Commons.'];
        }
        if (!str_starts_with($file_title, 'File:') && !str_starts_with($file_title, 'Ficheiro:')) {
            $file_title = 'File:' . $file_title;
        }

        $api = add_query_arg([
            'action' => 'query',
            'titles' => $file_title,
            'prop' => 'imageinfo',
            'iiprop' => 'url|extmetadata',
            'format' => 'json',
        ], 'https://commons.wikimedia.org/w/api.php');

        $response = wp_safe_remote_get($api, [
            'timeout' => 20,
            'user-agent' => 'CartolaNews-Tennis-Hub/' . CN_TENNIS_VERSION . ' (+https://cartolanews.com.br/)',
        ]);
        if (is_wp_error($response)) {
            return ['ok' => false, 'message' => $response->get_error_message()];
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $pages = $data['query']['pages'] ?? [];
        $page = is_array($pages) ? reset($pages) : null;
        $info = $page['imageinfo'][0] ?? null;
        if (!$info || empty($info['url'])) {
            return ['ok' => false, 'message' => 'Arquivo não encontrado no Wikimedia Commons ou sem informações de imagem.'];
        }

        $meta = $info['extmetadata'] ?? [];
        $license = (string) ($meta['LicenseShortName']['value'] ?? '');
        if ($license !== '' && !preg_match('/^(CC[ -]?(BY|0)|public\s*domain|PD)/i', $license)) {
            return ['ok' => false, 'message' => "Licença '{$license}' não identificada como compatível com uso comercial/editorial. Importe manualmente após conferir os termos."];
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_sideload_image($info['url'], 0, $file_title, 'id');
        if (is_wp_error($attachment_id)) {
            return ['ok' => false, 'message' => $attachment_id->get_error_message()];
        }

        update_post_meta($attachment_id, '_cn_tennis_wikimedia_author', wp_kses((string) ($meta['Artist']['value'] ?? ''), []));
        update_post_meta($attachment_id, '_cn_tennis_wikimedia_license', sanitize_text_field($license));
        update_post_meta($attachment_id, '_cn_tennis_wikimedia_license_url', esc_url_raw((string) ($meta['LicenseUrl']['value'] ?? '')));
        update_post_meta($attachment_id, '_cn_tennis_wikimedia_source_url', esc_url_raw((string) ($info['descriptionurl'] ?? '')));
        update_post_meta($attachment_id, '_cn_tennis_wikimedia_imported_at', current_time('mysql', true));

        return [
            'ok' => true,
            'attachment_id' => (int) $attachment_id,
            'credit' => [
                'author' => wp_strip_all_tags((string) ($meta['Artist']['value'] ?? '')),
                'license' => $license,
                'license_url' => (string) ($meta['LicenseUrl']['value'] ?? ''),
                'source_url' => (string) ($info['descriptionurl'] ?? ''),
            ],
            'message' => 'Imagem importada do Wikimedia Commons com créditos registrados.',
        ];
    }
}
