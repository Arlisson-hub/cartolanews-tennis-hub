<?php
defined('ABSPATH') || exit;

/**
 * Contrato comum a todos os providers de dados de tênis.
 *
 * Cada provider converte os dados de UMA fonte externa (API gratuita, feed
 * JSON publicado no GitHub, importação manual, etc.) para o formato interno
 * padronizado do plugin. Nenhum provider deve ser chamado diretamente pelo
 * frontend — sempre passam por CN_Tennis_Sync, que aplica o fallback em
 * cadeia (fonte principal -> fallback -> último cache válido).
 *
 * Métodos que uma fonte não suporta devem retornar array vazio, nunca lançar
 * erro fatal nem inventar dados.
 */
interface CN_Tennis_Provider_Interface {
    /**
     * @param array $args ['gender'=>'male|female', 'limit'=>int, ...]
     * @return array Lista de jogadores normalizados.
     */
    public function get_players(array $args = []): array;

    /**
     * @param array $args ['gender'=>'male|female', 'limit'=>int]
     * @return array Lista de posições de ranking normalizadas.
     */
    public function get_rankings(array $args = []): array;

    /**
     * @param array $args ['date'=>'Y-m-d', 'gender'=>'all|male|female', ...]
     * @return array Lista de partidas normalizadas.
     */
    public function get_matches(array $args = []): array;

    /**
     * @param array $args ['from'=>'Y-m-d', 'to'=>'Y-m-d', 'tour'=>'atp|wta']
     * @return array Lista de torneios/calendário normalizados.
     */
    public function get_tournaments(array $args = []): array;

    /**
     * Verificação leve de disponibilidade da fonte (não deve custar caro).
     *
     * @return array{ok:bool,message:string}
     */
    public function health_check(): array;

    /** Identificador curto usado em provider_id/coluna "provider" no banco. */
    public function get_id(): string;
}
