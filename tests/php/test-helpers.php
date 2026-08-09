<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/class-helpers.php';

test('category_label traduz categorias conhecidas', function () {
    assert_equal('Grand Slam', CN_Tennis_Helpers::category_label('grand_slam'));
    assert_equal('ATP 250', CN_Tennis_Helpers::category_label('atp250'));
});

test('category_label nunca inventa para categoria desconhecida', function () {
    assert_equal('—', CN_Tennis_Helpers::category_label(''));
    assert_equal('Xyz', CN_Tennis_Helpers::category_label('xyz'));
});

test('match_status_label cobre todos os status usados no banco', function () {
    assert_equal('AO VIVO', CN_Tennis_Helpers::match_status_label('live'));
    assert_equal('Encerrado', CN_Tennis_Helpers::match_status_label('finished'));
    assert_equal('Adiado', CN_Tennis_Helpers::match_status_label('postponed'));
    assert_equal('Próximo', CN_Tennis_Helpers::match_status_label('scheduled'));
});

test('flag devolve vazio para código desconhecido (nunca bandeira errada)', function () {
    assert_equal('', CN_Tennis_Helpers::flag('ZZZ'));
    assert_equal('', CN_Tennis_Helpers::flag(null));
    assert_equal('', CN_Tennis_Helpers::flag(''));
});

test('flag reconhece alpha-3 comuns do tênis', function () {
    assert_true(str_contains(CN_Tennis_Helpers::flag('BRA'), '&#'), 'BRA deveria virar entidades de bandeira');
    assert_true(str_contains(CN_Tennis_Helpers::flag('ITA'), '&#'), 'ITA deveria virar entidades de bandeira');
});

test('initials extrai as iniciais certas para placeholder de foto', function () {
    assert_equal('JS', CN_Tennis_Helpers::initials('Jannik Sinner'));
    assert_equal('CG', CN_Tennis_Helpers::initials('Carlos Alcaraz Garfia')); // primeiro nome + último sobrenome
    assert_equal('?', CN_Tennis_Helpers::initials(''));
});

test('calculate_age calcula idade real a partir do nascimento', function () {
    $eighteen_years_ago = (new DateTimeImmutable('-18 years -1 day'))->format('Y-m-d');
    assert_equal(18, CN_Tennis_Helpers::calculate_age($eighteen_years_ago));
});

test('calculate_age retorna null sem inventar quando não há data', function () {
    assert_null(CN_Tennis_Helpers::calculate_age(null));
    assert_null(CN_Tennis_Helpers::calculate_age(''));
});

test('time_ago mostra "agora mesmo" para timestamps muito recentes', function () {
    $now = gmdate('Y-m-d H:i:s');
    assert_equal('agora mesmo', CN_Tennis_Helpers::time_ago($now));
});
