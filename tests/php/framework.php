<?php
/**
 * Micro-framework de testes sem dependências (não há Composer/PHPUnit
 * disponível no ambiente de build) — suficiente para testar as classes de
 * lógica pura do plugin. Cada arquivo test-*.php chama test($nome, $fn).
 */
declare(strict_types=1);

$GLOBALS['cn_tests'] = [];

function test(string $name, callable $fn): void {
    $GLOBALS['cn_tests'][] = [$name, $fn];
}

function assert_equal(mixed $expected, mixed $actual, string $message = ''): void {
    if ($expected !== $actual) {
        throw new Exception(sprintf(
            "Esperado %s, obtido %s%s",
            var_export($expected, true),
            var_export($actual, true),
            $message ? " ({$message})" : ''
        ));
    }
}

function assert_true(mixed $condition, string $message = ''): void {
    if ($condition !== true) {
        throw new Exception("Esperado true, obtido " . var_export($condition, true) . ($message ? " ({$message})" : ''));
    }
}

function assert_null(mixed $value, string $message = ''): void {
    if ($value !== null) {
        throw new Exception("Esperado null, obtido " . var_export($value, true) . ($message ? " ({$message})" : ''));
    }
}

function assert_in_range(float $value, float $min, float $max, string $message = ''): void {
    if ($value < $min || $value > $max) {
        throw new Exception("Esperado valor entre {$min} e {$max}, obtido {$value}" . ($message ? " ({$message})" : ''));
    }
}
