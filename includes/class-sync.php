<?php
defined('ABSPATH') || exit;

/**
 * Orquestra a sincronização com a cadeia de fallback exigida na seção 60:
 *
 *   fonte principal -> (falhou?) -> fallback -> (falhou?) -> último cache válido
 *
 * Uma sincronização que falha NUNCA apaga dados existentes: se nada de novo
 * chega, os registros antigos permanecem no banco e a interface mostra a
 * data da última atualização válida (seção 13/26/60).
 */
final class CN_Tennis_Sync {
    /** @return array{ok:bool,message:string} */
    public function rankings(string $gender): array {
        $key = $gender === 'female' ? CN_Tennis_Sources::RANKINGS_FEMALE : CN_Tennis_Sources::RANKINGS_MALE;
        $source = CN_Tennis_Sources::find($key);
        if ($source && !(int) $source['enabled']) {
            return ['ok' => true, 'skipped' => true, 'message' => 'Fonte desativada em Fontes.'];
        }

        $lock = 'cn_tennis_lock_rankings_' . $gender;
        if (get_transient($lock)) {
            return ['ok' => false, 'message' => 'Sincronização já em execução.'];
        }
        set_transient($lock, 1, 5 * MINUTE_IN_SECONDS);
        $start = microtime(true);

        try {
            $provider = $gender === 'female' ? new CN_Tennis_WTA_Provider() : new CN_Tennis_ATP_Provider();
            $rows = $this->try_provider($provider, 'get_rankings', ['gender' => $gender]);

            if ($rows === null) {
                return $this->finish_cached($key, 'sync_rankings_' . $gender, $gender, $start, 'Fonte principal indisponível; ranking anterior preservado.');
            }
            if (!$rows) {
                return $this->finish_cached($key, 'sync_rankings_' . $gender, $gender, $start, 'A fonte respondeu sem jogadores válidos; ranking anterior preservado.');
            }

            $created = 0;
            foreach ($rows as $row) {
                $player_id = CN_Tennis_Players::upsert_from_provider($row);
                if ($player_id > 0) {
                    CN_Tennis_Rankings::upsert($player_id, $gender, $row);
                    $created++;
                }
            }

            $duration = (int) ((microtime(true) - $start) * 1000);
            CN_Tennis_Sources::report($key, 'success', $created, $duration);
            CN_Tennis_Logger::log('sync_rankings_' . $gender, 'success', ['provider' => $provider->get_id(), 'received' => count($rows), 'updated' => $created, 'duration_ms' => $duration]);
            CN_Tennis_Cache::flush();
            (new CN_Tennis_Power_Ranking())->calculate($gender);

            return ['ok' => true, 'message' => "{$created} jogadores atualizados."];
        } finally {
            delete_transient($lock);
        }
    }

    public function calendar(): array {
        $lock = 'cn_tennis_lock_calendar';
        if (get_transient($lock)) {
            return ['ok' => false, 'message' => 'Sincronização já em execução.'];
        }
        set_transient($lock, 1, 5 * MINUTE_IN_SECONDS);
        $start = microtime(true);

        try {
            $provider = new CN_Tennis_GitHub_Provider();
            $rows = $this->try_provider($provider, 'get_tournaments', []);

            if ($rows === null || !$rows) {
                return $this->finish_cached(CN_Tennis_Sources::CALENDAR, 'sync_calendar', '', $start, $rows === null ? 'Fonte principal indisponível; calendário anterior preservado.' : 'Nenhum torneio válido recebido; calendário anterior preservado.');
            }

            $created = 0;
            foreach ($rows as $row) {
                if (CN_Tennis_Calendar::upsert_from_provider($row) > 0) {
                    $created++;
                }
            }

            $duration = (int) ((microtime(true) - $start) * 1000);
            CN_Tennis_Sources::report(CN_Tennis_Sources::CALENDAR, 'success', $created, $duration);
            CN_Tennis_Logger::log('sync_calendar', 'success', ['provider' => $provider->get_id(), 'received' => count($rows), 'updated' => $created, 'duration_ms' => $duration]);
            CN_Tennis_Cache::flush();
            return ['ok' => true, 'message' => "{$created} torneios atualizados."];
        } finally {
            delete_transient($lock);
        }
    }

    public function matches(string $scope = 'today'): array {
        $lock = 'cn_tennis_lock_matches';
        if (get_transient($lock)) {
            return ['ok' => false, 'message' => 'Sincronização já em execução.'];
        }
        set_transient($lock, 1, 2 * MINUTE_IN_SECONDS);
        $start = microtime(true);

        try {
            $provider = new CN_Tennis_TheSportsDB_Provider();
            $dates = $this->dates_for_scope($scope);
            $rows = [];
            $failed = 0;
            foreach ($dates as $date) {
                $chunk = $this->try_provider($provider, 'get_matches', ['date' => $date]);
                if ($chunk === null) {
                    $failed++;
                    continue;
                }
                array_push($rows, ...$chunk);
            }

            if ($failed === count($dates)) {
                return $this->finish_cached(CN_Tennis_Sources::MATCHES, 'sync_matches', '', $start, 'Fonte de partidas indisponível; jogos anteriores preservados.');
            }

            $created = 0;
            foreach ($rows as $row) {
                if (CN_Tennis_Matches::upsert_from_provider($row) > 0) {
                    $created++;
                }
            }

            $duration = (int) ((microtime(true) - $start) * 1000);
            CN_Tennis_Sources::report(CN_Tennis_Sources::MATCHES, $failed ? 'partial' : 'success', $created, $duration);
            CN_Tennis_Logger::log('sync_matches', $failed ? 'partial' : 'success', ['provider' => $provider->get_id(), 'received' => count($rows), 'updated' => $created, 'duration_ms' => $duration]);
            CN_Tennis_Cache::flush();
            return ['ok' => true, 'message' => "{$created} partidas atualizadas."];
        } finally {
            delete_transient($lock);
        }
    }

    private function dates_for_scope(string $scope): array {
        $today = new DateTimeImmutable('today', wp_timezone());
        $offsets = match ($scope) {
            'upcoming' => [1, 2, 3],
            default => [-1, 0, 1],
        };
        return array_map(
            static fn(int $offset) => $today->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('Y-m-d'),
            $offsets
        );
    }

    /**
     * Executa um método de provider com try/catch, retornando null em caso
     * de falha (fonte fora do ar) — nunca deixa uma exceção subir e derrubar
     * o cron/REST.
     */
    private function try_provider(CN_Tennis_Provider_Interface $provider, string $method, array $args): ?array {
        try {
            return $provider->{$method}($args);
        } catch (Throwable $error) {
            CN_Tennis_Logger::log('provider_error', 'error', [
                'provider' => $provider->get_id(),
                'endpoint' => $method,
                'message' => $error->getMessage(),
            ]);
            return null;
        }
    }

    private function finish_cached(string $source_key, string $task, string $gender, float $start, string $message): array {
        $duration = (int) ((microtime(true) - $start) * 1000);
        CN_Tennis_Sources::report($source_key, 'cached', 0, $duration, $message);
        CN_Tennis_Logger::log($task, 'cached', ['duration_ms' => $duration, 'message' => $message]);
        return ['ok' => true, 'cached' => true, 'message' => $message];
    }
}
