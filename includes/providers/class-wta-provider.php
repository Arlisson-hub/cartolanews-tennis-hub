<?php
defined('ABSPATH') || exit;

/**
 * Equivalente feminino de CN_Tennis_ATP_Provider — mesma lógica, mesma
 * origem de dados (snapshot JSON publicado via GitHub Actions), apenas
 * apontando para o feed de ranking/calendário WTA.
 */
final class CN_Tennis_WTA_Provider implements CN_Tennis_Provider_Interface {
    public function get_id(): string {
        return 'wta';
    }

    public function get_players(array $args = []): array {
        return [];
    }

    public function get_rankings(array $args = []): array {
        $url = (string) CN_Tennis_Settings::get('feed_rankings_female_url');
        if ($url === '') {
            throw new RuntimeException('Feed do ranking WTA não configurado em CartolaNews Tênis → Configurações.');
        }
        $envelope = CN_Tennis_GitHub_Provider::fetch_envelope($url);
        $rows = [];
        foreach ((array) $envelope['data'] as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $normalized = CN_Tennis_Data_Normalizer::ranking_row($raw, 'female', $envelope);
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
            if (!is_array($raw) || !in_array($raw['tour'] ?? 'atp', ['wta', 'both'], true)) {
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
        $url = (string) CN_Tennis_Settings::get('feed_rankings_female_url');
        if ($url === '') {
            return ['ok' => false, 'message' => 'Feed do ranking WTA não configurado.'];
        }
        try {
            $envelope = CN_Tennis_GitHub_Provider::fetch_envelope($url, 10);
            return ['ok' => true, 'message' => count($envelope['data']) . ' jogadoras no snapshot (' . ($envelope['generated_at'] ?? '?') . ').'];
        } catch (Throwable $error) {
            return ['ok' => false, 'message' => $error->getMessage()];
        }
    }
}
