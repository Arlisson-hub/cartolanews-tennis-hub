<?php
defined('ABSPATH') || exit;

/**
 * "Melhores do Momento CartolaNews" — Power Ranking calculado matematicamente
 * a partir de partidas finalizadas armazenadas no banco (wp_cn_tennis_matches).
 * NUNCA atribui nota por estimativa: se não houver partidas finalizadas
 * suficientes na janela configurada, o jogador fica marcado como
 * `insufficient_data=1` e a nota (`score`) é gravada como NULL — o frontend
 * exibe "Dados insuficientes para calcular" (seção 9).
 *
 * ==========================================================================
 * FÓRMULA (documentação obrigatória — seção 9)
 * ==========================================================================
 * Só entram no cálculo partidas com status='finished' dos últimos
 * `power_ranking_window_days` dias (padrão 90). É exigido um mínimo de
 * `power_ranking_min_matches` partidas nesse período (padrão 5).
 *
 * Nota final (0–100) = média ponderada de 5 componentes, cada um também de
 * 0 a 100, com pesos configuráveis em Configurações (a soma dos pesos deve
 * ser 100; se o admin salvar pesos que não somem 100, o cálculo normaliza
 * proporcionalmente para não distorcer a escala):
 *
 * 1) FORMA RECENTE (peso padrão 30)
 *    Mistura o aproveitamento (vitórias / partidas) nos últimos 30 dias
 *    (peso 60% deste componente) com o aproveitamento na janela completa de
 *    90 dias (peso 40%). Cobre literalmente os dois recortes pedidos no
 *    briefing ("desempenho nos últimos 30 dias" e "nos últimos 90 dias")
 *    sem precisar de um peso extra na configuração.
 *
 * 2) FORÇA DOS ADVERSÁRIOS (peso padrão 25)
 *    Para cada partida, o "nível" do adversário é estimado a partir do
 *    ranking dele no momento do jogo (rank 1 ≈ 100 pontos, decaindo
 *    suavemente por exp(-(rank-1)/100)). Vitórias contam o valor cheio;
 *    derrotas contam apenas 30% do valor (perder para o nº1 do mundo ainda
 *    conta um pouco a favor, perder para alguém fora do ranking quase não
 *    pesa). Partidas sem ranking conhecido do adversário usam 50 (neutro).
 *
 * 3) IMPORTÂNCIA DOS TORNEIOS (peso padrão 20)
 *    Média do peso da categoria de cada torneio disputado na janela:
 *    Grand Slam=100, Masters1000/WTA1000=85, ATP/WTA 500=70, 250=55,
 *    Challenger/ITF=35. Partidas sem torneio vinculado usam 50 (neutro).
 *
 * 4) SUPERFÍCIE (peso padrão 10)
 *    Aproveitamento do jogador na superfície em que ele mais jogou dentro
 *    da janela (recompensa quem está em boa fase na superfície do momento,
 *    não numa média genérica de carreira que o plugin não tem como validar).
 *
 * 5) SEQUÊNCIA ATUAL / STREAK (peso padrão 15)
 *    Conta vitórias (ou derrotas) consecutivas mais recentes.
 *    score = 50 + 10×sequência (vitórias) ou 50 − 10×sequência (derrotas),
 *    limitado a [0,100].
 *
 * O resultado é arredondado em 2 casas decimais e salvo com timestamp de
 * cálculo. Um histórico é gravado em wp_cn_tennis_ranking_snapshots
 * (snapshot_type='power') para permitir gráfico de evolução no futuro.
 */
final class CN_Tennis_Power_Ranking {
    private const TOURNAMENT_WEIGHTS = [
        'grand_slam' => 100.0,
        'atp1000' => 85.0,
        'wta1000' => 85.0,
        'masters1000' => 85.0,
        'atp500' => 70.0,
        'wta500' => 70.0,
        'atp250' => 55.0,
        'wta250' => 55.0,
        'challenger' => 35.0,
        'itf' => 35.0,
    ];

    public function calculate_all(): array {
        return ['male' => $this->calculate('male'), 'female' => $this->calculate('female')];
    }

    public function calculate(string $gender): array {
        global $wpdb;
        $settings = CN_Tennis_Settings::all();
        $window_days = (int) $settings['power_ranking_window_days'];
        $min_matches = (int) $settings['power_ranking_min_matches'];
        $weights = $this->normalized_weights($settings);

        $players = CN_Tennis_Players::query(['gender' => $gender, 'limit' => 500]);
        $now = current_time('mysql', true);
        $today = current_time('Y-m-d');
        $ranked = [];
        $insufficient = 0;

        foreach ($players as $player) {
            $player_id = (int) $player['id'];
            $matches = CN_Tennis_Matches::finished_by_player($player_id, $window_days);

            if (count($matches) < $min_matches) {
                $this->store($player_id, $gender, null, count($matches), true, 'Dados insuficientes para calcular (mínimo ' . $min_matches . ' partidas finalizadas nos últimos ' . $window_days . ' dias).', $now);
                $insufficient++;
                continue;
            }

            $score = $this->score_player($player_id, $matches, $weights, $today);
            $ranked[] = ['player_id' => $player_id, 'score' => $score['total'], 'matches' => count($matches), 'explanation' => $score['explanation']];
        }

        usort($ranked, static fn($a, $b) => $b['score'] <=> $a['score']);

        $table = CN_Tennis_Database::tables()['power_rankings'];
        foreach ($ranked as $index => $entry) {
            $previous = $wpdb->get_var($wpdb->prepare("SELECT rank_position FROM {$table} WHERE gender=%s AND player_id=%d", $gender, $entry['player_id']));
            $this->store($entry['player_id'], $gender, $entry['score'], $entry['matches'], false, $entry['explanation'], $now, $index + 1, $previous ? (int) $previous : null);
        }

        CN_Tennis_Cache::flush();
        CN_Tennis_Logger::log('calculate_power_ranking', 'success', ['received' => count($ranked) + $insufficient, 'updated' => count($ranked)]);

        return ['ok' => true, 'ranked' => count($ranked), 'insufficient_data' => $insufficient];
    }

    private function normalized_weights(array $settings): array {
        $raw = [
            'recent_form' => (float) $settings['power_ranking_weight_recent_form'],
            'opponent_strength' => (float) $settings['power_ranking_weight_opponent_strength'],
            'tournament_importance' => (float) $settings['power_ranking_weight_tournament_importance'],
            'surface' => (float) $settings['power_ranking_weight_surface'],
            'streak' => (float) $settings['power_ranking_weight_streak'],
        ];
        $sum = array_sum($raw) ?: 1.0;
        foreach ($raw as $key => $value) {
            $raw[$key] = $value / $sum * 100.0;
        }
        return $raw;
    }

    private function score_player(int $player_id, array $matches, array $weights, string $today): array {
        $wins_30 = 0;
        $total_30 = 0;
        $wins_90 = 0;
        $total_90 = count($matches);
        $opponent_scores = [];
        $tournament_scores = [];
        $surface_stats = [];
        $streak = 0;
        $streak_result = null;

        foreach ($matches as $index => $match) {
            $is_player1 = (int) $match['player1_id'] === $player_id;
            $won = ((int) $match['winner'] === ($is_player1 ? 1 : 2));
            $days_ago = (strtotime($today) - strtotime((string) $match['scheduled_at'])) / DAY_IN_SECONDS;

            $total_90++;
            if ($days_ago <= 30) {
                $total_30++;
                $wins_30 += $won ? 1 : 0;
            }
            $wins_90 += $won ? 1 : 0;

            $opponent_rank = $is_player1 ? $match['player2_rank'] : $match['player1_rank'];
            $opponent_quality = $opponent_rank ? 100 * exp(-(max(0, (int) $opponent_rank - 1)) / 100) : 50.0;
            $opponent_scores[] = $won ? $opponent_quality : $opponent_quality * 0.3;

            $tournament_scores[] = $this->tournament_weight((string) ($match['tournament_category'] ?? ''));

            $surface = (string) ($match['surface'] ?? '');
            if ($surface !== '') {
                $surface_stats[$surface] ??= ['wins' => 0, 'total' => 0];
                $surface_stats[$surface]['total']++;
                $surface_stats[$surface]['wins'] += $won ? 1 : 0;
            }

            if ($index === 0) {
                $streak_result = $won;
                $streak = 1;
            } elseif ($streak_result === $won) {
                $streak++;
            }
        }

        // Total real de partidas na janela (não soma incorretamente 30+90).
        $total_90 = count($matches);

        $recent_form = $total_30 > 0
            ? (($wins_30 / $total_30) * 100 * 0.6) + (($wins_90 / max(1, $total_90)) * 100 * 0.4)
            : (($wins_90 / max(1, $total_90)) * 100);

        $opponent_strength = $opponent_scores ? array_sum($opponent_scores) / count($opponent_scores) : 50.0;
        $tournament_importance = $tournament_scores ? array_sum($tournament_scores) / count($tournament_scores) : 50.0;

        $best_surface = null;
        $best_surface_total = 0;
        foreach ($surface_stats as $surface => $stat) {
            if ($stat['total'] > $best_surface_total) {
                $best_surface_total = $stat['total'];
                $best_surface = $surface;
            }
        }
        $surface_score = $best_surface ? ($surface_stats[$best_surface]['wins'] / $surface_stats[$best_surface]['total']) * 100 : 50.0;

        $streak_score = $streak_result === null ? 50.0 : max(0.0, min(100.0, 50 + ($streak_result ? 10 : -10) * $streak));

        $total = ($recent_form * $weights['recent_form']
            + $opponent_strength * $weights['opponent_strength']
            + $tournament_importance * $weights['tournament_importance']
            + $surface_score * $weights['surface']
            + $streak_score * $weights['streak']) / 100;

        $total = round(max(0, min(100, $total)), 2);

        $explanation = sprintf(
            '%d/%d vitórias em %d dias; sequência atual: %s; superfície de melhor forma: %s.',
            $wins_90,
            $total_90,
            30,
            $streak_result === null ? 'sem dados' : ($streak_result ? $streak . 'V' : $streak . 'D'),
            $best_surface ? CN_Tennis_Helpers::surface_label($best_surface) : 'sem dados'
        );

        return ['total' => $total, 'explanation' => $explanation];
    }

    private function tournament_weight(string $category): float {
        return self::TOURNAMENT_WEIGHTS[sanitize_key($category)] ?? 50.0;
    }

    private function store(int $player_id, string $gender, ?float $score, int $sample, bool $insufficient, string $explanation, string $now, ?int $rank = null, ?int $previous_rank = null): void {
        global $wpdb;
        $table = CN_Tennis_Database::tables()['power_rankings'];
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE gender=%s AND player_id=%d", $gender, $player_id));
        $data = [
            'rank_position' => $rank,
            'previous_rank' => $previous_rank,
            'score' => $score,
            'sample_matches' => $sample,
            'insufficient_data' => $insufficient ? 1 : 0,
            'explanation' => $explanation,
            'calculated_at' => $now,
            'updated_at' => $now,
        ];
        if ($existing) {
            $wpdb->update($table, $data, ['id' => (int) $existing]);
        } else {
            $data['player_id'] = $player_id;
            $data['gender'] = $gender;
            $data['created_at'] = $now;
            $wpdb->insert($table, $data);
        }
        if (!$insufficient && $score !== null) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO " . CN_Tennis_Database::tables()['ranking_snapshots'] . "
                 (snapshot_type, gender, player_id, rank_position, score, captured_at, created_at)
                 VALUES ('power', %s, %d, %d, %f, %s, %s)
                 ON DUPLICATE KEY UPDATE rank_position=VALUES(rank_position), score=VALUES(score)",
                $gender,
                $player_id,
                $rank ?: 0,
                $score,
                current_time('Y-m-d'),
                $now
            ));
        }
    }

    public static function query(string $gender, int $limit = 10): array {
        global $wpdb;
        $table = CN_Tennis_Database::tables()['power_rankings'];
        $players = CN_Tennis_Players::table();
        $sql = "SELECT pr.*, p.slug player_slug, p.name player_name, p.country_code, p.photo_attachment_id
                FROM {$table} pr
                INNER JOIN {$players} p ON p.id = pr.player_id
                WHERE pr.gender=%s AND pr.insufficient_data=0
                ORDER BY pr.rank_position ASC
                LIMIT %d";
        return $wpdb->get_results($wpdb->prepare($sql, $gender, max(1, min(100, $limit))), ARRAY_A) ?: [];
    }
}
