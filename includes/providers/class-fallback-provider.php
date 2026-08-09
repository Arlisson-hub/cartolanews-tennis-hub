<?php
defined('ABSPATH') || exit;

/**
 * Último elo da cadeia de fallback (seção 26/60): quando a fonte principal E
 * a fonte alternativa falham, o Sync usa este provider, que devolve listas
 * vazias sem tentar nenhuma rede. A UI, ao receber "sem dados novos", mantém
 * o que já está salvo no banco — nunca zera ranking, nunca apaga jogador.
 */
final class CN_Tennis_Fallback_Provider implements CN_Tennis_Provider_Interface {
    public function get_id(): string {
        return 'fallback';
    }

    public function get_players(array $args = []): array {
        return [];
    }

    public function get_rankings(array $args = []): array {
        return [];
    }

    public function get_matches(array $args = []): array {
        return [];
    }

    public function get_tournaments(array $args = []): array {
        return [];
    }

    public function health_check(): array {
        return ['ok' => true, 'message' => 'Operando com o último dado válido salvo no banco local.'];
    }
}
