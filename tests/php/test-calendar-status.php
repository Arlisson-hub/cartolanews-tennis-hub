<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/class-settings.php';
require_once __DIR__ . '/../../includes/class-calendar.php';

/**
 * Regressão: um torneio sem data de término confirmada (comum na fonte via
 * Wikipédia, que só informa a semana de início) ficava marcado "ongoing"
 * para sempre, mesmo meses depois de ter claramente acabado — visto ao
 * vivo em produção (65 torneios "acontecendo" simultaneamente, número
 * impossível no tênis real). Corrigido assumindo no máximo 14 dias de
 * duração só para classificar o status de exibição.
 */
function cn_call_static(string $class, string $method, array $args) {
    return (new ReflectionMethod($class, $method))->invoke(null, ...$args);
}

test('torneio sem data de término some de "ongoing" depois de ~2 semanas do início', function () {
    $long_ago = gmdate('Y-m-d', strtotime('-60 days'));
    $status = cn_call_static('CN_Tennis_Calendar', 'status_from_dates', [$long_ago, null, '']);
    assert_equal('finished', $status, 'torneio de 60 dias atrás sem data de fim não pode continuar "ongoing"');
});

test('torneio sem data de término mas iniciado recentemente ainda conta como ongoing', function () {
    $recently = gmdate('Y-m-d', strtotime('-2 days'));
    $status = cn_call_static('CN_Tennis_Calendar', 'status_from_dates', [$recently, null, '']);
    assert_equal('ongoing', $status);
});

test('torneio com data de início futura é upcoming, mesmo sem data de término', function () {
    $future = gmdate('Y-m-d', strtotime('+10 days'));
    $status = cn_call_static('CN_Tennis_Calendar', 'status_from_dates', [$future, null, '']);
    assert_equal('upcoming', $status);
});

test('com data de término explícita, ela sempre prevalece sobre a suposição de 14 dias', function () {
    $starts = gmdate('Y-m-d', strtotime('-3 days'));
    $ends = gmdate('Y-m-d', strtotime('+1 day'));
    assert_equal('ongoing', cn_call_static('CN_Tennis_Calendar', 'status_from_dates', [$starts, $ends, '']));

    $ended = gmdate('Y-m-d', strtotime('-1 day'));
    $started_earlier = gmdate('Y-m-d', strtotime('-5 days'));
    assert_equal('finished', cn_call_static('CN_Tennis_Calendar', 'status_from_dates', [$started_earlier, $ended, '']));
});

test('status declarado explicitamente (ex.: cancelled) nunca é sobrescrito pela heurística de datas', function () {
    $status = cn_call_static('CN_Tennis_Calendar', 'status_from_dates', [gmdate('Y-m-d', strtotime('-100 days')), null, 'cancelled']);
    assert_equal('cancelled', $status);
});

test('calendario oculta duplicata manual legada sem apagar dados', function () {
    $rows = [
        ['id' => 1, 'name' => 'Rio Open', 'starts_at' => '2026-02-16', 'tour' => 'atp', 'provider' => 'manual', 'manual_override' => 0],
        ['id' => 2, 'name' => 'Rio Open', 'starts_at' => '2026-02-16', 'tour' => 'atp', 'provider' => 'github', 'manual_override' => 0],
        ['id' => 3, 'name' => 'Wimbledon', 'starts_at' => '2026-06-29', 'tour' => 'both', 'provider' => 'github', 'manual_override' => 0],
    ];

    $deduplicated = cn_call_static('CN_Tennis_Calendar', 'deduplicate_rows', [$rows, 10]);
    assert_equal(2, count($deduplicated));
    assert_equal(2, (int) $deduplicated[0]['id'], 'feed automatico deve vencer o manual legado equivalente');
});

test('edicao manual bloqueada vence o feed automatico equivalente', function () {
    $rows = [
        ['id' => 10, 'name' => 'US Open', 'starts_at' => '2026-08-31', 'tour' => 'both', 'provider' => 'github', 'manual_override' => 0],
        ['id' => 11, 'name' => 'US Open', 'starts_at' => '2026-08-31', 'tour' => 'both', 'provider' => 'manual', 'manual_override' => 1],
    ];

    $deduplicated = cn_call_static('CN_Tennis_Calendar', 'deduplicate_rows', [$rows, 10]);
    assert_equal(1, count($deduplicated));
    assert_equal(11, (int) $deduplicated[0]['id']);
});

test('filtros de data não tratam torneio antigo sem final como atual', function () {
    $flags = CN_Tennis_Calendar::period_flags('2026-01-05', null, '2026-08-09');
    assert_equal(['today' => false, 'week' => false, 'month' => false], $flags);
});

test('filtros hoje semana e mês usam janelas corretas', function () {
    assert_equal(
        ['today' => true, 'week' => true, 'month' => true],
        CN_Tennis_Calendar::period_flags('2026-08-09', null, '2026-08-09')
    );
    assert_equal(
        ['today' => false, 'week' => true, 'month' => true],
        CN_Tennis_Calendar::period_flags('2026-08-15', null, '2026-08-09')
    );
    assert_equal(
        ['today' => false, 'week' => false, 'month' => true],
        CN_Tennis_Calendar::period_flags('2026-08-25', null, '2026-08-09')
    );
});
