<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/class-cron.php';

/**
 * Regressão: um bug real em produção usava array_filter(CN_Tennis_Cron::HOOKS, ...)
 * e recebia a RECORRÊNCIA (valor) em vez do NOME DO HOOK (chave) dentro do
 * callback, fazendo o Diagnóstico reportar "cron não agendado" mesmo com o
 * cron funcionando normalmente (visto no diagnóstico real do site depois do
 * deploy — corrigido em CN_Tennis_Diagnostics::check_cron()).
 */
test('CN_Tennis_Cron::HOOKS é um mapa hook => recorrência, não uma lista', function () {
    foreach (CN_Tennis_Cron::HOOKS as $hook => $recurrence) {
        assert_true(is_string($hook) && str_starts_with($hook, 'cn_tennis_'), "chave deveria ser um nome de hook, obtido: {$hook}");
        assert_true(is_string($recurrence) && $recurrence !== '', "valor deveria ser uma recorrência não vazia para {$hook}");
    }
});

test('iterar por array_keys() (não pelos valores) é o jeito certo de checar os hooks', function () {
    $hook_names = array_keys(CN_Tennis_Cron::HOOKS);
    assert_true(in_array('cn_tennis_sync_rankings_male', $hook_names, true));
    assert_true(in_array('cn_tennis_sync_matches', $hook_names, true));
    // Nenhuma string de recorrência deve, por coincidência, parecer um nome de hook.
    foreach (CN_Tennis_Cron::HOOKS as $recurrence) {
        assert_true(!str_starts_with((string) $recurrence, 'cn_tennis_sync_'), 'recorrência não deveria ser confundida com nome de hook');
    }
});
