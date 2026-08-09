<?php
defined('ABSPATH') || exit;

/**
 * REST API pública (leitura) + endpoint de sincronização autenticado.
 *
 * Namespace: cartolanews-tennis/v1
 * Endpoint de ingestão do GitHub Actions: POST /wp-json/cartolanews-tennis/v1/sync
 * (seção 24) — autenticado via WordPress Application Passwords (Basic Auth
 * nativo do core, permission_callback exige manage_options). Nenhum
 * endpoint administrativo é público (seção 45).
 */
final class CN_Tennis_Rest {
    private string $ns = 'cartolanews-tennis/v1';

    public function register(): void {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes(): void {
        register_rest_route($this->ns, '/players', [
            'methods' => 'GET',
            'callback' => [$this, 'players'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($this->ns, '/players/(?P<slug>[a-z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'player'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($this->ns, '/rankings', [
            'methods' => 'GET',
            'callback' => [$this, 'rankings'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($this->ns, '/rankings/brazilians', [
            'methods' => 'GET',
            'callback' => [$this, 'brazilians'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($this->ns, '/power-ranking', [
            'methods' => 'GET',
            'callback' => [$this, 'power_ranking'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($this->ns, '/matches', [
            'methods' => 'GET',
            'callback' => [$this, 'matches'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($this->ns, '/calendar', [
            'methods' => 'GET',
            'callback' => [$this, 'calendar'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($this->ns, '/legends', [
            'methods' => 'GET',
            'callback' => [$this, 'legends'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($this->ns, '/surfaces/(?P<surface>hard|clay|grass|indoor)', [
            'methods' => 'GET',
            'callback' => [$this, 'surface'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($this->ns, '/head-to-head', [
            'methods' => 'GET',
            'callback' => [$this, 'head_to_head'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($this->ns, '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'health'],
            'permission_callback' => '__return_true',
        ]);
        // Fragmento HTML já renderizado do bloco "Ao Vivo", usado pelo JS do
        // frontend para manter o selo AO VIVO/atualização sempre corretos
        // mesmo quando a página está em cache de página inteira (LiteSpeed).
        register_rest_route($this->ns, '/live-html', [
            'methods' => 'GET',
            'callback' => [$this, 'live_html'],
            'permission_callback' => '__return_true',
        ]);

        // Ações administrativas — nunca públicas.
        register_rest_route($this->ns, '/sync', [
            'methods' => 'POST',
            'callback' => [$this, 'sync_ingest'],
            'permission_callback' => [$this, 'require_manage_options'],
        ]);
        register_rest_route($this->ns, '/admin/recalculate-power-ranking', [
            'methods' => 'POST',
            'callback' => fn() => rest_ensure_response((new CN_Tennis_Power_Ranking())->calculate_all()),
            'permission_callback' => [$this, 'require_manage_options'],
        ]);
    }

    public function require_manage_options(): bool {
        return current_user_can('manage_options');
    }

    private function no_store(WP_REST_Response $response): WP_REST_Response {
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        return $response;
    }

    private function gender(mixed $value, string $default = 'all'): string {
        return in_array($value, ['male', 'female'], true) ? $value : $default;
    }

    public function players(WP_REST_Request $request): WP_REST_Response {
        $rows = CN_Tennis_Players::query([
            'gender' => $this->gender($request['gender'] ?? '', ''),
            'is_brazilian' => !empty($request['brazilian']),
            'search' => sanitize_text_field((string) ($request['search'] ?? '')),
            'orderby' => sanitize_key((string) ($request['orderby'] ?? 'rank')),
            'limit' => min(200, max(1, (int) ($request['limit'] ?? 50))),
        ]);
        return rest_ensure_response($rows);
    }

    public function player(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $player = CN_Tennis_Players::find_by_slug((string) $request['slug']);
        if (!$player) {
            return new WP_Error('cn_tennis_not_found', 'Jogador não encontrado.', ['status' => 404]);
        }
        $player['age'] = CN_Tennis_Helpers::calculate_age($player['birth_date'] ?? null);
        $player['recent_matches'] = CN_Tennis_Matches::finished_by_player((int) $player['id'], 180);
        $player['upcoming_matches'] = CN_Tennis_Matches::query([
            'status' => 'scheduled',
            'from' => current_time('mysql', true),
            'limit' => 10,
        ]);
        return rest_ensure_response($player);
    }

    public function rankings(WP_REST_Request $request): WP_REST_Response {
        $gender = $this->gender($request['gender'] ?? '', 'male');
        $limit = min(100, max(10, (int) ($request['limit'] ?? 20)));
        return rest_ensure_response([
            'gender' => $gender,
            'last_update' => CN_Tennis_Rankings::last_update($gender),
            'rows' => CN_Tennis_Rankings::query($gender, $limit),
        ]);
    }

    public function brazilians(WP_REST_Request $request): WP_REST_Response {
        return rest_ensure_response([
            'male' => CN_Tennis_Rankings::brazilians('male'),
            'female' => CN_Tennis_Rankings::brazilians('female'),
        ]);
    }

    public function power_ranking(WP_REST_Request $request): WP_REST_Response {
        $gender = $this->gender($request['gender'] ?? '', 'male');
        $limit = min(50, max(5, (int) ($request['limit'] ?? 10)));
        return rest_ensure_response(CN_Tennis_Power_Ranking::query($gender, $limit));
    }

    public function matches(WP_REST_Request $request): WP_REST_Response {
        $date = sanitize_text_field((string) ($request['date'] ?? current_time('Y-m-d')));
        $tz = wp_timezone();
        $utc = new DateTimeZone('UTC');
        try {
            $day = new DateTimeImmutable(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : 'today', $tz);
        } catch (Throwable) {
            $day = new DateTimeImmutable('today', $tz);
        }
        $rows = CN_Tennis_Matches::query([
            'from' => $day->setTime(0, 0)->setTimezone($utc)->format('Y-m-d H:i:s'),
            'to' => $day->setTime(23, 59, 59)->setTimezone($utc)->format('Y-m-d H:i:s'),
            'gender' => $this->gender($request['gender'] ?? '', ''),
            'match_type' => in_array($request['type'] ?? '', ['singles', 'doubles'], true) ? $request['type'] : '',
            'status' => sanitize_key((string) ($request['status'] ?? '')),
            'limit' => min(100, max(1, (int) ($request['limit'] ?? 40))),
        ]);
        foreach ($rows as &$row) {
            $row['is_live'] = CN_Tennis_Matches::is_currently_live($row);
            $row['live_stale'] = CN_Tennis_Matches::is_live_stale($row);
            $row['score'] = json_decode((string) $row['score_json'], true);
        }
        return $this->no_store(rest_ensure_response($rows));
    }

    public function calendar(WP_REST_Request $request): WP_REST_Response {
        $rows = CN_Tennis_Calendar::query([
            'from' => sanitize_text_field((string) ($request['from'] ?? '')),
            'to' => sanitize_text_field((string) ($request['to'] ?? '')),
            'tour' => sanitize_key((string) ($request['tour'] ?? '')),
            'category' => sanitize_key((string) ($request['category'] ?? '')),
            'country_code' => sanitize_key((string) ($request['country'] ?? '')),
            'limit' => min(200, max(1, (int) ($request['limit'] ?? 60))),
        ]);
        return rest_ensure_response($rows);
    }

    public function legends(): WP_REST_Response {
        return rest_ensure_response(CN_Tennis_Legends::all('published'));
    }

    public function surface(WP_REST_Request $request): WP_REST_Response {
        $rows = CN_Tennis_Surfaces::leaderboard((string) $request['surface'], $this->gender($request['gender'] ?? '', ''), min(20, max(3, (int) ($request['limit'] ?? 5))));
        return rest_ensure_response($rows);
    }

    public function head_to_head(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $a = CN_Tennis_Players::find_by_slug(sanitize_title((string) ($request['player_a'] ?? '')));
        $b = CN_Tennis_Players::find_by_slug(sanitize_title((string) ($request['player_b'] ?? '')));
        if (!$a || !$b) {
            return new WP_Error('cn_tennis_not_found', 'Um dos jogadores não foi encontrado.', ['status' => 404]);
        }
        $matches = CN_Tennis_Matches::head_to_head((int) $a['id'], (int) $b['id']);
        $wins_a = 0;
        $wins_b = 0;
        $by_surface = ['hard' => [0, 0], 'clay' => [0, 0], 'grass' => [0, 0], 'indoor' => [0, 0]];
        foreach ($matches as $match) {
            $a_is_p1 = (int) $match['player1_id'] === (int) $a['id'];
            $a_won = (int) $match['winner'] === ($a_is_p1 ? 1 : 2);
            $a_won ? $wins_a++ : $wins_b++;
            $surface = (string) ($match['surface'] ?? '');
            if (isset($by_surface[$surface])) {
                $by_surface[$surface][$a_won ? 0 : 1]++;
            }
        }
        return rest_ensure_response([
            'player_a' => ['slug' => $a['slug'], 'name' => $a['name']],
            'player_b' => ['slug' => $b['slug'], 'name' => $b['name']],
            'total_matches' => count($matches),
            'wins_a' => $wins_a,
            'wins_b' => $wins_b,
            'by_surface' => $by_surface,
            'last_match' => $matches[0] ?? null,
        ]);
    }

    public function live_html(): WP_REST_Response {
        $html = (new CN_Tennis_Shortcodes())->live();
        return $this->no_store(rest_ensure_response([
            'html' => $html,
            'generated_at' => current_time('c'),
            'cache_epoch' => CN_Tennis_Cache::epoch(),
        ]));
    }

    public function health(): WP_REST_Response {
        return rest_ensure_response((new CN_Tennis_Diagnostics())->run());
    }

    /**
     * Ingestão de snapshots normalizados enviados pelo GitHub Actions
     * (seção 24). Corpo esperado: mesmo envelope de data/tennis/*.json
     * { schema_version, generated_at, source, source_url, type, data:[] }.
     * "type" define o repositório de destino: rankings_male, rankings_female,
     * calendar, players.
     */
    public function sync_ingest(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            return new WP_Error('cn_tennis_bad_request', 'Corpo da requisição precisa ser JSON.', ['status' => 400]);
        }
        $type = sanitize_key((string) ($payload['type'] ?? ''));
        $envelope = [
            'schema_version' => $payload['schema_version'] ?? 0,
            'generated_at' => $payload['generated_at'] ?? '',
            'source' => $payload['source'] ?? '',
            'source_url' => $payload['source_url'] ?? '',
        ];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        if ((int) $envelope['schema_version'] < 1 || !$data) {
            return new WP_Error('cn_tennis_invalid_payload', 'Envelope inválido: schema_version/data ausente ou vazio.', ['status' => 422]);
        }

        $start = microtime(true);
        $created = 0;

        switch ($type) {
            case 'rankings_male':
            case 'rankings_female':
                $gender = $type === 'rankings_female' ? 'female' : 'male';
                foreach ($data as $raw) {
                    if (!is_array($raw)) {
                        continue;
                    }
                    $row = CN_Tennis_Data_Normalizer::ranking_row($raw, $gender, $envelope);
                    if (!$row) {
                        continue;
                    }
                    $player_id = CN_Tennis_Players::upsert_from_provider($row);
                    if ($player_id > 0) {
                        CN_Tennis_Rankings::upsert($player_id, $gender, $row);
                        $created++;
                    }
                }
                (new CN_Tennis_Power_Ranking())->calculate($gender);
                CN_Tennis_Sources::report($gender === 'female' ? CN_Tennis_Sources::RANKINGS_FEMALE : CN_Tennis_Sources::RANKINGS_MALE, 'success', $created, (int) ((microtime(true) - $start) * 1000));
                break;

            case 'calendar':
                foreach ($data as $raw) {
                    if (!is_array($raw)) {
                        continue;
                    }
                    $row = CN_Tennis_Data_Normalizer::tournament_row($raw);
                    if ($row && CN_Tennis_Calendar::upsert_from_provider($row) > 0) {
                        $created++;
                    }
                }
                CN_Tennis_Sources::report(CN_Tennis_Sources::CALENDAR, 'success', $created, (int) ((microtime(true) - $start) * 1000));
                break;

            default:
                return new WP_Error('cn_tennis_unknown_type', "Tipo '{$type}' não suportado pelo endpoint de sync.", ['status' => 422]);
        }

        CN_Tennis_Cache::flush();
        CN_Tennis_Logger::log('rest_sync_' . $type, 'success', [
            'provider' => 'github_actions',
            'endpoint' => '/sync',
            'received' => count($data),
            'updated' => $created,
            'duration_ms' => (int) ((microtime(true) - $start) * 1000),
        ]);

        return rest_ensure_response(['ok' => true, 'type' => $type, 'received' => count($data), 'updated' => $created]);
    }
}
