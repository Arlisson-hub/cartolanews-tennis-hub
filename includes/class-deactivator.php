<?php
defined('ABSPATH') || exit;

/**
 * Desativação: apenas limpa o agendamento do cron. Os dados (jogadores,
 * rankings, jogos, lendas, logs) NUNCA são apagados aqui (seção 51) — isso
 * só é decidido no uninstall.php, e mesmo assim de forma opcional.
 */
final class CN_Tennis_Deactivator {
    public static function deactivate(): void {
        require_once CN_TENNIS_PATH . 'includes/class-cron.php';
        CN_Tennis_Cron::clear_all();
        flush_rewrite_rules(false);
    }
}
