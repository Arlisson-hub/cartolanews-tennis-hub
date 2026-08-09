<?php
defined('ABSPATH') || exit;

/**
 * Fonte "oficial pública" do ranking/calendário masculino (Prioridade 2).
 *
 * Não faz scraping do site da ATP diretamente — consome o snapshot JSON já
 * normalizado e publicado pelo coletor Python (tools/tennis/providers/atp.py)
 * via GitHub Actions, respeitando a regra de que raspagem pesada não roda no
 * WordPress (seção 21). A URL do feed é configurável em Configurações, sem
 * precisar editar o plugin (seção 22).
 */
final class CN_Tennis_ATP_Provider implements CN_Tennis_Provider_Interface {
    public function get_id(): string {
        return 'atp';
    }

    public function get_players(array $args = []): array {
        return [];
    }

    public function get_rankings(array $args = []): array {
        $url = (string) CN_Tennis_Settings::get('feed_rankings_male_url');
        if ($url === '') {
            throw new RuntimeException('Feed do ranking ATP não configurado em CartolaNews Tênis → Configurações.');
        }
        $envelope = CN_Tennis_GitHub_Provider::fetch_envelope($url);
        $rows = [];
        foreach ((array) $envelope['data'] as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $normalized = CN_Tennis_Data_Normalizer::ranking_row($raw, 'male', $envelope);
            if ($normalized) {
                $rows[] = $normalized;
            }
        }
        return $rows;
    }

    public function get_matches(array $args = []): array {
        return [];
    }

    public function get_tournaments(array $args = []): array {
        $url = (string) CN_Tennis_Settings::get('feed_calendar_url');
        if ($url === '') {
            return [];
        }
        $envelope = CN_Tennis_GitHub_Provider::fetch_envelope($url);
        $rows = [];
        foreach ((array) $envelope['data'] as $raw) {
            if (!is_array($raw) || !in_array($raw['tour'] ?? 'atp', ['atp', 'both'], true)) {
                continue;
            }
            $normalized = CN_Tennis_Data_Normalizer::tournament_row($raw);
            if ($normalized) {
                $rows[] = $normalized;
            }
        }
        return $rows;
    }

    public function health_check(): array {
        $url = (string) CN_Tennis_Settings::get('feed_rankings_male_url');
        if ($url === '') {
            return ['ok' => false, 'message' => 'Feed do ranking ATP não configurado.'];
        }
        try {
            $envelope = CN_Tennis_GitHub_Provider::fetch_envelope($url, 10);
            return ['ok' => true, 'message' => count($envelope['data']) . ' jogadores no snapshot (' . ($envelope['generated_at'] ?? '?') . ').'];
        } catch (Throwable $error) {
            return ['ok' => false, 'message' => $error->getMessage()];
        }
    }
}
