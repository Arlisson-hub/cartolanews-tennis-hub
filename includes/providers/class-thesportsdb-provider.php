<?php
defined('ABSPATH') || exit;

/**
 * TheSportsDB (Prioridade 1 — API pública/gratuita). Chave padrão '123' é a
 * chave de teste pública documentada pelo próprio serviço; o administrador
 * pode trocar por uma chave Patreon paga em Configurações, mas isso NUNCA é
 * obrigatório para o plugin funcionar (seção 20 — não amarrar a serviço
 * pago).
 *
 * Cobertura de tênis nessa fonte é parcial/inconsistente por natureza da API
 * (foco histórico em esportes de equipe). Por isso este provider é usado
 * como fonte de partidas do dia/ao vivo em modo best-effort: se não houver
 * eventos de tênis para a data, retorna lista vazia — nunca inventa jogo.
 */
final class CN_Tennis_TheSportsDB_Provider implements CN_Tennis_Provider_Interface {
    private const BASE = 'https://www.thesportsdb.com/api/v1/json/';

    public function get_id(): string {
        return 'thesportsdb';
    }

    public function get_players(array $args = []): array {
        return [];
    }

    public function get_rankings(array $args = []): array {
        return []; // TheSportsDB não publica ranking ATP/WTA confiável.
    }

    public function get_matches(array $args = []): array {
        $date = (string) ($args['date'] ?? current_time('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return [];
        }
        $events = $this->request('eventsday.php', ['d' => $date, 's' => 'Tennis']);
        $rows = [];
        foreach ((array) ($events['events'] ?? []) as $event) {
            if (!is_array($event) || strcasecmp((string) ($event['strSport'] ?? ''), 'Tennis') !== 0) {
                continue;
            }
            $normalized = $this->normalize_event($event);
            if ($normalized) {
                $rows[] = $normalized;
            }
        }
        return $rows;
    }

    public function get_tournaments(array $args = []): array {
        return [];
    }

    public function health_check(): array {
        try {
            $this->request('eventsday.php', ['d' => current_time('Y-m-d'), 's' => 'Tennis'], 1, 8);
            return ['ok' => true, 'message' => 'TheSportsDB respondendo normalmente.'];
        } catch (Throwable $error) {
            return ['ok' => false, 'message' => $error->getMessage()];
        }
    }

    private function normalize_event(array $event): ?array {
        $p1 = sanitize_text_field((string) ($event['strHomeTeam'] ?? ''));
        $p2 = sanitize_text_field((string) ($event['strAwayTeam'] ?? ''));
        $date = (string) ($event['dateEvent'] ?? '');
        $time = (string) ($event['strTime'] ?? '00:00:00');
        if ($p1 === '' || $p2 === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        $timestamp = strtotime($date . ' ' . $time . ' UTC');
        $status = strtoupper((string) ($event['strStatus'] ?? ''));
        return [
            'external_id' => sanitize_text_field((string) ($event['idEvent'] ?? '')),
            'provider' => 'thesportsdb',
            'round_name' => sanitize_text_field((string) ($event['strRound'] ?? '')),
            'gender' => 'male',
            'match_type' => 'singles',
            'player1_name' => $p1,
            'player2_name' => $p2,
            'scheduled_at' => $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : null,
            'status' => in_array($status, ['MATCH FINISHED', 'FT'], true) ? 'finished'
                : (in_array($status, ['LIVE', 'IN PLAY', '1ST SET', '2ND SET', '3RD SET'], true) ? 'live' : 'scheduled'),
            'metadata' => ['league' => $event['strLeague'] ?? '', 'venue' => $event['strVenue'] ?? ''],
        ];
    }

    /**
     * GET com timeout, retry e exponential backoff (seção 20 — obrigatório
     * para qualquer fonte externa consumida pelo plugin).
     */
    private function request(string $endpoint, array $query, int $attempt = 1, int $timeout = 15): array {
        $key = (string) (CN_Tennis_Settings::get('thesportsdb_api_key') ?: '123');
        $url = add_query_arg($query, self::BASE . rawurlencode($key) . '/' . $endpoint);
        $response = wp_safe_remote_get($url, [
            'timeout' => $timeout,
            'redirection' => 2,
            'headers' => ['Accept' => 'application/json'],
            'user-agent' => 'CartolaNews-Tennis-Hub/' . CN_TENNIS_VERSION . ' (+https://cartolanews.com.br/)',
        ]);

        $max_attempts = 3;
        $should_retry = is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 500;
        if ($should_retry && $attempt < $max_attempts) {
            usleep((int) (min(4, 2 ** $attempt) * 250000)); // backoff exponencial curto (250ms,500ms,1s,2s)
            return $this->request($endpoint, $query, $attempt + 1, $timeout);
        }

        if (is_wp_error($response)) {
            throw new RuntimeException($response->get_error_message());
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException("TheSportsDB respondeu HTTP {$code}.");
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : [];
    }
}
