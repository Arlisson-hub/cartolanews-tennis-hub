<?php
defined('ABSPATH') || exit;

/**
 * Provider genérico para os snapshots JSON publicados pelo coletor Python
 * (tools/tennis/) via GitHub Actions e hospedados como Raw do GitHub.
 *
 * Este é o mecanismo de transporte da "Prioridade 2" (fontes oficiais
 * públicas) e também pode carregar o calendário. A raspagem pesada NUNCA
 * acontece aqui — ela roda fora do WordPress (seção 21) e o resultado já
 * normalizado chega neste provider como um arquivo JSON estático.
 *
 * Envelope esperado (igual ao gerado por tools/tennis/normalize.py):
 * {"schema_version":1,"generated_at":"...","source":"...","source_url":"...","verified_at":"...","data":[...]}
 */
final class CN_Tennis_GitHub_Provider implements CN_Tennis_Provider_Interface {
    public function get_id(): string {
        return 'github';
    }

    public function get_players(array $args = []): array {
        $url = (string) CN_Tennis_Settings::get('feed_players_url');
        return $url ? self::fetch_envelope($url)['data'] : [];
    }

    public function get_rankings(array $args = []): array {
        $gender = $args['gender'] ?? 'male';
        $url = (string) CN_Tennis_Settings::get($gender === 'female' ? 'feed_rankings_female_url' : 'feed_rankings_male_url');
        return $url ? self::fetch_envelope($url)['data'] : [];
    }

    public function get_matches(array $args = []): array {
        return []; // O feed do GitHub não cobre jogos/ao vivo (ver CN_Tennis_TheSportsDB_Provider).
    }

    public function get_tournaments(array $args = []): array {
        $url = (string) CN_Tennis_Settings::get('feed_calendar_url');
        return $url ? self::fetch_envelope($url)['data'] : [];
    }

    public function health_check(): array {
        $configured = array_filter([
            CN_Tennis_Settings::get('feed_rankings_male_url'),
            CN_Tennis_Settings::get('feed_rankings_female_url'),
            CN_Tennis_Settings::get('feed_calendar_url'),
        ]);
        return [
            'ok' => true,
            'message' => $configured
                ? count($configured) . ' feed(s) configurado(s) via GitHub.'
                : 'Nenhum feed do GitHub configurado ainda em Configurações.',
        ];
    }

    /**
     * Busca e valida o envelope do snapshot. Lança RuntimeException em caso
     * de resposta HTTP inválida ou schema incompatível — quem chama decide
     * o que fazer (normalmente: cair para o fallback/último cache válido).
     */
    public static function fetch_envelope(string $url, int $timeout = 20): array {
        $request_url = add_query_arg('cn_refresh', time(), self::normalize_github_url($url));
        $response = wp_safe_remote_get($request_url, [
            'timeout' => $timeout,
            'redirection' => 3,
            'headers' => ['Accept' => 'application/json'],
            'user-agent' => 'CartolaNews-Tennis-Hub/' . CN_TENNIS_VERSION . ' (+https://cartolanews.com.br/)',
        ]);
        if (is_wp_error($response)) {
            throw new RuntimeException($response->get_error_message());
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException("Feed respondeu HTTP {$code}.");
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || (int) ($data['schema_version'] ?? 0) < 1 || !isset($data['data']) || !is_array($data['data'])) {
            throw new RuntimeException('Envelope JSON inválido (schema_version/data ausente).');
        }
        return $data;
    }

    private static function normalize_github_url(string $url): string {
        $parts = wp_parse_url($url);
        if (($parts['host'] ?? '') !== 'github.com') {
            return $url;
        }
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $segments = explode('/', $path);
        if (count($segments) >= 5 && ($segments[2] ?? '') === 'blob') {
            [$owner, $repository] = $segments;
            return 'https://raw.githubusercontent.com/' . $owner . '/' . $repository . '/' . implode('/', array_slice($segments, 3));
        }
        return $url;
    }
}
