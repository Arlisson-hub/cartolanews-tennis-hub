<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/class-data-normalizer.php';

$envelope = ['generated_at' => '2099-01-01T00:00:00+00:00', 'source' => 'Fonte de Teste', 'source_url' => 'https://example.invalid/'];

test('ranking_row aceita linha válida e preenche o envelope', function () use ($envelope) {
    $row = CN_Tennis_Data_Normalizer::ranking_row(['rank' => 1, 'name' => 'Jogador Teste', 'points' => 1000], 'male', $envelope);
    assert_true($row !== null);
    assert_equal(1, $row['rank_position']);
    assert_equal(1000, $row['points']);
    assert_equal('male', $row['gender']);
    assert_equal('Fonte de Teste', $row['source']);
});

test('ranking_row rejeita nome vazio', function () use ($envelope) {
    assert_null(CN_Tennis_Data_Normalizer::ranking_row(['rank' => 1, 'name' => '', 'points' => 100], 'male', $envelope));
});

test('ranking_row rejeita rank não positivo', function () use ($envelope) {
    assert_null(CN_Tennis_Data_Normalizer::ranking_row(['rank' => 0, 'name' => 'X', 'points' => 100], 'male', $envelope));
});

test('ranking_row rejeita pontos não numéricos', function () use ($envelope) {
    assert_null(CN_Tennis_Data_Normalizer::ranking_row(['rank' => 1, 'name' => 'X', 'points' => 'muitos'], 'male', $envelope));
});

test('tournament_row exige nome e data de início válida (AAAA-MM-DD)', function () {
    $valid = CN_Tennis_Data_Normalizer::tournament_row(['name' => 'Torneio Teste', 'starts_at' => '2099-02-02']);
    assert_true($valid !== null);
    assert_equal('Torneio Teste', $valid['name']);

    assert_null(CN_Tennis_Data_Normalizer::tournament_row(['name' => 'Torneio Teste', 'starts_at' => '02/02/2099']));
    assert_null(CN_Tennis_Data_Normalizer::tournament_row(['name' => '', 'starts_at' => '2099-02-02']));
});

test('match_row rejeita quando os dois jogadores são o mesmo nome', function () {
    $row = CN_Tennis_Data_Normalizer::match_row([
        'player1_name' => 'Mesmo Jogador',
        'player2_name' => 'Mesmo Jogador',
        'scheduled_at' => '2099-02-02 12:00:00',
    ]);
    assert_null($row, 'adversários precisam ser diferentes (seção 27)');
});

test('match_row rejeita data agendada inválida', function () {
    $row = CN_Tennis_Data_Normalizer::match_row([
        'player1_name' => 'Jogador A',
        'player2_name' => 'Jogador B',
        'scheduled_at' => 'data-invalida',
    ]);
    assert_null($row);
});

test('match_row aceita linha válida e resolve player_id via lookup', function () {
    $lookup = [CN_Tennis_Data_Normalizer::name_key('Jogador A') => 42];
    $row = CN_Tennis_Data_Normalizer::match_row([
        'player1_name' => 'Jogador A',
        'player2_name' => 'Jogador B',
        'scheduled_at' => '2099-02-02 12:00:00',
    ], $lookup);
    assert_true($row !== null);
    assert_equal(42, $row['player1_id']);
    assert_null($row['player2_id']); // não cadastrado ainda — não inventamos um ID
});
