<?php
/** @var array $live_matches */
defined('ABSPATH') || exit;
?>
<section class="cnt-live" id="cnt-ao-vivo" aria-labelledby="cnt-ao-vivo-title" data-cnt-live-refresh>
    <div class="cnt-live__head">
        <h2 id="cnt-ao-vivo-title">Tênis ao Vivo</h2>
        <?php if ($live_matches): ?><span class="cnt-badge cnt-badge--live">AO VIVO</span><?php endif; ?>
    </div>
    <?php if (!$live_matches): ?>
        <p class="cnt-empty">Nenhuma partida ao vivo neste momento. O selo "AO VIVO" só aparece quando a fonte de dados confirma que o jogo está em andamento.</p>
    <?php else: ?>
        <div class="cnt-matches-grid">
            <?php foreach ($live_matches as $match) echo CN_Tennis_Shortcodes::match_card($match); ?>
        </div>
    <?php endif; ?>
</section>
