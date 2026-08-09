<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/class-helpers.php';
require_once __DIR__ . '/../../includes/class-settings.php';
require_once __DIR__ . '/../../includes/class-power-ranking.php';

/**
 * Testa a fórmula documentada em includes/class-power-ranking.php via
 * Reflection, já que score_player()/normalized_weights() são privados por
 * design (não fazem parte da API pública da classe) mas concentram a
 * lógica matemática que mais precisa de cobertura de teste.
 */
function cn_call_private(object $object, string $method, array $args) {
    // setAccessible() não é mais necessário desde o PHP 8.1 (Reflection já
    // permite invocar métodos privados diretamente).
    return (new ReflectionMethod($object, $method))->invokeArgs($object, $args);
}

function cn_default_weights(): array {
    $power = new CN_Tennis_Power_Ranking();
    return cn_call_private($power, 'normalized_weights', [CN_Tennis_Settings::defaults()]);
}

function cn_fake_match(int $player_id, bool $won, int $days_ago, ?int $opponent_rank, string $category = 'atp250', string $surface = 'hard'): array {
    return [
        'player1_id' => $player_id,
        'player2_id' => 999,
        'winner' => $won ? 1 : 2,
        'scheduled_at' => gmdate('Y-m-d H:i:s', time() - $days_ago * DAY_IN_SECONDS),
        'player1_rank' => null,
        'player2_rank' => $opponent_rank,
        'tournament_category' => $category,
        'surface' => $surface,
    ];
}

test('tournament_weight nunca inventa peso para categoria desconhecida (usa neutro 50)', function () {
    $power = new CN_Tennis_Power_Ranking();
    assert_equal(50.0, cn_call_private($power, 'tournament_weight', ['']));
    assert_equal(100.0, cn_call_private($power, 'tournament_weight', ['grand_slam']));
});

test('normalized_weights sempre soma 100, mesmo se a config não somar', function () {
    $power = new CN_Tennis_Power_Ranking();
    $settings = CN_Tennis_Settings::defaults();
    $settings['power_ranking_weight_recent_form'] = 10;
    $settings['power_ranking_weight_opponent_strength'] = 10;
    $settings['power_ranking_weight_tournament_importance'] = 10;
    $settings['power_ranking_weight_surface'] = 10;
    $settings['power_ranking_weight_streak'] = 10; // soma 50, não 100

    $weights = cn_call_private($power, 'normalized_weights', [$settings]);
    $sum = array_sum($weights);
    assert_true(abs($sum - 100.0) < 0.001, "pesos normalizados deveriam somar 100, somaram {$sum}");
});

test('score_player: 5 vitórias seguidas contra oponentes medianos gera nota alta mas não sempre 100', function () {
    $power = new CN_Tennis_Power_Ranking();
    $weights = cn_default_weights();
    $matches = [];
    for ($i = 0; $i < 5; $i++) {
        $matches[] = cn_fake_match(1, true, $i + 1, 50);
    }

    $result = cn_call_private($power, 'score_player', [1, $matches, $weights, gmdate('Y-m-d')]);

    assert_in_range($result['total'], 0.0, 100.0, 'nota sempre entre 0 e 100');
    assert_true($result['total'] > 70.0, 'sequência perfeita deveria render nota alta, obteve ' . $result['total']);
    assert_true(str_contains($result['explanation'], '5/5'), 'explicação deveria citar o retrospecto 5/5');
});

test('score_player: 5 derrotas seguidas gera nota consideravelmente menor que 5 vitórias', function () {
    $power = new CN_Tennis_Power_Ranking();
    $weights = cn_default_weights();

    $wins = [];
    $losses = [];
    for ($i = 0; $i < 5; $i++) {
        $wins[] = cn_fake_match(1, true, $i + 1, 50);
        $losses[] = cn_fake_match(1, false, $i + 1, 50);
    }

    $win_score = cn_call_private($power, 'score_player', [1, $wins, $weights, gmdate('Y-m-d')])['total'];
    $loss_score = cn_call_private($power, 'score_player', [1, $losses, $weights, gmdate('Y-m-d')])['total'];

    assert_true($win_score > $loss_score, "vitórias ({$win_score}) deveriam pontuar mais que derrotas ({$loss_score})");
});

test('score_player: vencer adversário mais bem ranqueado pesa mais que vencer um pior ranqueado', function () {
    $power = new CN_Tennis_Power_Ranking();
    $weights = cn_default_weights();

    $vs_top_player = [cn_fake_match(1, true, 1, 2)];      // venceu o nº2 do mundo
    $vs_low_player = [cn_fake_match(1, true, 1, 900)];     // venceu alguém fora do top 900
    // completar com partidas neutras para atingir jogos mínimos não é necessário aqui: score_player não aplica o mínimo (isso é responsabilidade de calculate()).

    $score_top = cn_call_private($power, 'score_player', [1, $vs_top_player, $weights, gmdate('Y-m-d')])['total'];
    $score_low = cn_call_private($power, 'score_player', [1, $vs_low_player, $weights, gmdate('Y-m-d')])['total'];

    assert_true($score_top > $score_low, "vencer o nº2 ({$score_top}) deveria pontuar mais que vencer alguém sem ranking ({$score_low})");
});
