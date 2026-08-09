<?php
/**
 * @var array $rows
 * @var string $title
 */
defined('ABSPATH') || exit;
?>
<section class="cnt-games" id="cnt-jogos" aria-labelledby="cnt-jogos-title">
    <h2 id="cnt-jogos-title"><?php echo esc_html($title); ?></h2>

    <div class="cnt-tabs" role="tablist" aria-label="Filtrar jogos" data-cnt-games-tabs>
        <button type="button" role="tab" aria-selected="true" data-cnt-games-tab="all">Todos</button>
        <button type="button" role="tab" aria-selected="false" data-cnt-games-tab="male">Masculino</button>
        <button type="button" role="tab" aria-selected="false" data-cnt-games-tab="female">Feminino</button>
        <button type="button" role="tab" aria-selected="false" data-cnt-games-tab="doubles">Duplas</button>
    </div>

    <?php if (!$rows): ?>
        <p class="cnt-empty">Nenhum jogo encontrado para este período. A sincronização automática verificará novamente em breve.</p>
    <?php else: ?>
        <div class="cnt-matches-grid" data-cnt-games-list>
            <?php foreach ($rows as $match) echo CN_Tennis_Shortcodes::match_card($match); ?>
        </div>
    <?php endif; ?>
</section>
