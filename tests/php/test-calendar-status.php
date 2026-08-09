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
