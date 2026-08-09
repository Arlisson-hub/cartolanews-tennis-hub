<?php
defined('ABSPATH') || exit;

/**
 * Funções puras de normalização: convertem uma linha bruta vinda de um
 * provider (formato variável por fonte) no formato interno padronizado
 * consumido pelos repositórios (CN_Tennis_Players, CN_Tennis_Rankings,
 * CN_Tennis_Calendar, CN_Tennis_Matches).
 *
 * Nenhuma função aqui acessa banco de dados ou rede — só validam/mapeiam
 * campos. Uma linha considerada inválida retorna null e é descartada pelo
 * chamador sem interromper o restante do lote (seção 27).
 */
final class CN_Tennis_Data_Normalizer {
    public static function ranking_row(array $raw, string $gender, array $envelope): ?array {
        $name = sanitize_text_field((string) ($raw['name'] ?? ''));
        $rank = absint($raw['rank'] ?? 0);
        $points = $raw['points'] ?? null;
        if ($name === '' || $rank <= 0 || !is_numeric($points) || (int) $points < 0) {
            return null;
        }
        $external_id = sanitize_text_field((string) ($raw['player_external_id'] ?? ('name:' . sanitize_title($name))));
        return [
            'external_id' => $external_id,
            'provider' => 'github',
            'name' => $name,
            'full_name' => sanitize_text_field((string) ($raw['full_name'] ?? $name)),
            'gender' => $gender,
            'country_code' => (string) ($raw['country_code'] ?? ''),
            'country' => (string) ($raw['country'] ?? ''),
            'birth_date' => $raw['birth_date'] ?? null,
            'height_cm' => $raw['height_cm'] ?? null,
            'plays' => $raw['plays'] ?? null,
            'turned_pro' => $raw['turned_pro'] ?? null,
            'current_rank_singles' => $rank,
            'current_rank_points' => (int) $points,
            'rank_position' => $rank,
            'previous_rank' => isset($raw['previous_rank']) ? absint($raw['previous_rank']) : null,
            'points' => (int) $points,
            'tournaments_played' => isset($raw['tournaments_played']) ? absint($raw['tournaments_played']) : null,
            'ranking_date' => self::date_only((string) ($envelope['generated_at'] ?? '')),
            'source' => (string) ($envelope['source'] ?? ''),
            'source_url' => (string) ($envelope['source_url'] ?? ''),
            'metadata' => ['imported_from' => 'github_feed'],
        ];
    }

    public static function tournament_row(array $raw): ?array {
        $name = sanitize_text_field((string) ($raw['name'] ?? ''));
        $starts = (string) ($raw['starts_at'] ?? '');
        if ($name === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $starts)) {
            return null;
        }
        return [
            'external_id' => sanitize_text_field((string) ($raw['external_id'] ?? ('name:' . sanitize_title($name) . ':' . $starts))),
            'provider' => 'github',
            'name' => $name,
            'city' => (string) ($raw['city'] ?? ''),
            'country' => (string) ($raw['country'] ?? ''),
            'country_code' => (string) ($raw['country_code'] ?? ''),
            'tour' => (string) ($raw['tour'] ?? 'atp'),
            'category' => (string) ($raw['category'] ?? ''),
            'surface' => (string) ($raw['surface'] ?? ''),
            'starts_at' => $starts,
            'ends_at' => $raw['ends_at'] ?? null,
            'prize_money' => $raw['prize_money'] ?? null,
            'prize_currency' => $raw['prize_currency'] ?? null,
            'draw_size' => $raw['draw_size'] ?? null,
            'status' => $raw['status'] ?? '',
            'metadata' => ['champion_previous_edition' => $raw['defending_champion'] ?? null],
        ];
    }

    /** @param array $player_lookup Mapa "nome normalizado" => player_id, resolvido pelo chamador antes de normalizar. */
    public static function match_row(array $raw, array $player_lookup = []): ?array {
        $p1 = sanitize_text_field((string) ($raw['player1_name'] ?? ''));
        $p2 = sanitize_text_field((string) ($raw['player2_name'] ?? ''));
        $scheduled = (string) ($raw['scheduled_at'] ?? '');
        if ($p1 === '' || $p2 === '' || $p1 === $p2 || !strtotime($scheduled)) {
            return null;
        }
        return [
            'external_id' => sanitize_text_field((string) ($raw['external_id'] ?? '')),
            'provider' => (string) ($raw['provider'] ?? 'unknown'),
            'tournament_id' => $raw['tournament_id'] ?? null,
            'round_name' => (string) ($raw['round_name'] ?? ''),
            'gender' => (string) ($raw['gender'] ?? 'male'),
            'match_type' => (string) ($raw['match_type'] ?? 'singles'),
            'surface' => (string) ($raw['surface'] ?? ''),
            'player1_id' => $player_lookup[self::name_key($p1)] ?? null,
            'player2_id' => $player_lookup[self::name_key($p2)] ?? null,
            'player1_name' => $p1,
            'player2_name' => $p2,
            'player1_country' => (string) ($raw['player1_country'] ?? ''),
            'player2_country' => (string) ($raw['player2_country'] ?? ''),
            'player1_rank' => $raw['player1_rank'] ?? null,
            'player2_rank' => $raw['player2_rank'] ?? null,
            'scheduled_at' => $scheduled,
            'status' => (string) ($raw['status'] ?? 'scheduled'),
            'winner' => $raw['winner'] ?? null,
            'score' => $raw['score'] ?? null,
            'duration_minutes' => $raw['duration_minutes'] ?? null,
            'metadata' => $raw['metadata'] ?? [],
        ];
    }

    public static function name_key(string $name): string {
        return sanitize_title(remove_accents($name));
    }

    private static function date_only(string $iso): string {
        $timestamp = strtotime($iso);
        return $timestamp ? gmdate('Y-m-d', $timestamp) : current_time('Y-m-d');
    }
}
