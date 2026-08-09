<?php
/**
 * Executor de testes sem dependências externas.
 * Uso: php tests/php/run.php
 */
declare(strict_types=1);

require_once __DIR__ . '/framework.php';
require_once __DIR__ . '/bootstrap.php';

$test_files = glob(__DIR__ . '/test-*.php');
sort($test_files);
foreach ($test_files as $file) {
    require $file;
}

$passed = 0;
$failed = 0;
foreach ($GLOBALS['cn_tests'] as [$name, $fn]) {
    try {
        $fn();
        $passed++;
        echo "  OK  {$name}\n";
    } catch (Throwable $error) {
        $failed++;
        echo "FALHOU  {$name}\n        " . $error->getMessage() . "\n";
    }
}

echo "\n" . ($passed + $failed) . " teste(s), {$passed} passou(aram), {$failed} falhou(aram).\n";
exit($failed > 0 ? 1 : 0);
