<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/class-settings.php';
require_once __DIR__ . '/../../includes/class-matches.php';

// Regra crítica da seção 13: só mostrar AO VIVO quando a fonte confirmou o
// status E a última atualização está dentro da janela configurada
// (padrão: live_stale_minutes = 20).

test('is_currently_live é true para status live atualizado agora mesmo', function () {
    $match = ['status' => 'live', 'is_live_confirmed' => 1, 'source_updated_at' => gmdate('Y-m-d H:i:s')];
    assert_true(CN_Tennis_Matches::is_currently_live($match));
    assert_equal(false, CN_Tennis_Matches::is_live_stale($match));
});

test('is_currently_live é false quando status não é live', function () {
    $match = ['status' => 'scheduled', 'is_live_confirmed' => 0, 'source_updated_at' => gmdate('Y-m-d H:i:s')];
    assert_equal(false, CN_Tennis_Matches::is_currently_live($match));
    assert_equal(false, CN_Tennis_Matches::is_live_stale($match)); // não é "stale ao vivo", nunca foi ao vivo
});

test('is_currently_live vira false e is_live_stale vira true após a janela de validade', function () {
    $stale_timestamp = gmdate('Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS); // > 20min padrão
    $match = ['status' => 'live', 'is_live_confirmed' => 1, 'source_updated_at' => $stale_timestamp];
    assert_equal(false, CN_Tennis_Matches::is_currently_live($match));
    assert_true(CN_Tennis_Matches::is_live_stale($match), 'depois de estale, deve avisar "atualização indisponível", nunca mostrar AO VIVO desatualizado');
});

test('is_currently_live nunca infere ao vivo só pelo horário — precisa de is_live_confirmed', function () {
    $match = ['status' => 'live', 'is_live_confirmed' => 0, 'source_updated_at' => gmdate('Y-m-d H:i:s')];
    assert_equal(false, CN_Tennis_Matches::is_currently_live($match));
});
