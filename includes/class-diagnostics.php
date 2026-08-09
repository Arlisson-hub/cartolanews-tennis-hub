<?php
defined('ABSPATH') || exit;

/**
 * "Diagnóstico do Tênis" (seção 49) — testa os 10 pontos exigidos e devolve
 * um resultado visual ok/atencao/erro para cada um. Nunca lança exceção:
 * qualquer falha de um teste vira um item "erro" na lista, os demais
 * continuam rodando.
 */
final class CN_Tennis_Diagnostics {
    public function run(): array {
        $checks = [
            'database' => $this->check_database(),
            'rest_api' => $this->check_rest_api(),
            'sources' => $this->check_sources(),
            'github_snapshot' => $this->check_github_feeds(),
            'cache' => $this->check_cache(),
            'images' => $this->check_images(),
            'cron' => $this->check_cron(),
            'permissions' => $this->check_permissions(),
            'rewrite_urls' => $this->check_rewrite(),
            'last_sync' => $this->check_last_sync(),
        ];

        $overall = 'ok';
        foreach ($checks as $check) {
            if ($check['status'] === 'erro') {
                $overall = 'erro';
                break;
            }
            if ($check['status'] === 'atencao') {
                $overall = 'atencao';
            }
        }

        return ['overall' => $overall, 'checked_at' => current_time('mysql'), 'checks' => $checks];
    }

    private function check_database(): array {
        if (!CN_Tennis_Database::ready()) {
            return ['status' => 'erro', 'message' => 'Tabelas do plugin não encontradas. Desative e reative o plugin.'];
        }
        return ['status' => 'ok', 'message' => 'Todas as tabelas do plugin existem.'];
    }

    private function check_rest_api(): array {
        $response = wp_safe_remote_get(rest_url('cartolanews-tennis/v1/health'), ['timeout' => 8]);
        if (is_wp_error($response)) {
            return ['status' => 'atencao', 'message' => 'Não foi possível chamar a própria REST API: ' . $response->get_error_message()];
        }
        $code = wp_remote_retrieve_response_code($response);
        return $code === 200
            ? ['status' => 'ok', 'message' => 'REST API respondendo (HTTP 200).']
            : ['status' => 'atencao', 'message' => "REST API respondeu HTTP {$code}."];
    }

    private function check_sources(): array {
        $sources = CN_Tennis_Sources::all();
        if (!$sources) {
            return ['status' => 'atencao', 'message' => 'Nenhuma fonte cadastrada ainda.'];
        }
        $errors = count(array_filter($sources, static fn($s) => $s['status'] === 'error'));
        if ($errors > 0) {
            return ['status' => 'atencao', 'message' => "{$errors} fonte(s) com erro no último sync. Veja CartolaNews Tênis → Fontes."];
        }
        return ['status' => 'ok', 'message' => count($sources) . ' fonte(s) configurada(s).'];
    }

    private function check_github_feeds(): array {
        $settings = CN_Tennis_Settings::all();
        $configured = array_filter([$settings['feed_rankings_male_url'], $settings['feed_rankings_female_url'], $settings['feed_calendar_url']]);
        if (!$configured) {
            return ['status' => 'atencao', 'message' => 'Nenhum feed do GitHub configurado ainda em Configurações.'];
        }
        return ['status' => 'ok', 'message' => count($configured) . ' feed(s) do GitHub configurado(s).'];
    }

    private function check_cache(): array {
        $test_key = 'diagnostics_' . wp_rand();
        $value = CN_Tennis_Cache::remember($test_key, 30, static fn() => 'ok');
        CN_Tennis_Cache::forget($test_key);
        return $value === 'ok'
            ? ['status' => 'ok', 'message' => 'Transients funcionando normalmente.']
            : ['status' => 'erro', 'message' => 'Falha ao gravar/ler transient de teste.'];
    }

    private function check_images(): array {
        $sizes = wp_list_pluck((array) wp_get_registered_image_subsizes(), 'width');
        $missing = array_diff(array_keys(CN_Tennis_Images::SIZES), array_keys($sizes));
        return $missing
            ? ['status' => 'atencao', 'message' => 'Tamanhos ainda não registrados: ' . implode(', ', $missing) . '. Normal logo após ativar; se persistir, verifique after_setup_theme.']
            : ['status' => 'ok', 'message' => 'Todos os tamanhos de imagem do plugin estão registrados.'];
    }

    private function check_cron(): array {
        // CN_Tennis_Cron::HOOKS é [hook => recorrência] — precisa iterar as
        // CHAVES (nomes dos hooks), não os valores (nomes de recorrência).
        $missing = array_filter(array_keys(CN_Tennis_Cron::HOOKS), static fn($hook) => !wp_next_scheduled($hook));
        return $missing
            ? ['status' => 'atencao', 'message' => 'Eventos de cron não agendados: ' . implode(', ', $missing) . '.']
            : ['status' => 'ok', 'message' => 'Todos os eventos de cron estão agendados.'];
    }

    private function check_permissions(): array {
        return current_user_can('manage_options')
            ? ['status' => 'ok', 'message' => 'Usuário atual tem permissão manage_options.']
            : ['status' => 'atencao', 'message' => 'Usuário atual não tem manage_options (ok se não for administrador).'];
    }

    private function check_rewrite(): array {
        $rules = get_option('rewrite_rules');
        $found = false;
        if (is_array($rules)) {
            foreach ($rules as $pattern => $rewrite) {
                if (str_contains($pattern, 'tenis/jogador')) {
                    $found = true;
                    break;
                }
            }
        }
        return $found
            ? ['status' => 'ok', 'message' => 'Regra de URL /tenis/jogador/ registrada.']
            : ['status' => 'atencao', 'message' => 'Regra de URL de perfil não encontrada. Acesse Configurações > Links permanentes e clique em Salvar para atualizar.'];
    }

    private function check_last_sync(): array {
        $last = null;
        foreach (['sync_rankings_male', 'sync_rankings_female', 'sync_calendar', 'sync_matches'] as $task) {
            $value = CN_Tennis_Logger::last_success($task);
            if ($value && (!$last || $value > $last)) {
                $last = $value;
            }
        }
        if (!$last) {
            return ['status' => 'atencao', 'message' => 'Nenhuma sincronização bem-sucedida registrada ainda.'];
        }
        $hours_ago = (time() - strtotime($last . ' UTC')) / HOUR_IN_SECONDS;
        return $hours_ago > 48
            ? ['status' => 'atencao', 'message' => 'Última sincronização há mais de 48h (' . CN_Tennis_Helpers::time_ago($last) . ').']
            : ['status' => 'ok', 'message' => 'Última sincronização ' . CN_Tennis_Helpers::time_ago($last) . '.'];
    }
}
