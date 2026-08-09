<?php
/** @var array $matched key => ['label'=>string,'tournament'=>array|null] */
defined('ABSPATH') || exit;
?>
<section class="cnt-grand-slams" id="cnt-grand-slams" aria-labelledby="cnt-grand-slams-title">
    <h2 id="cnt-grand-slams-title">Grand Slams</h2>
    <div class="cnt-grand-slams__grid">
        <?php foreach ($matched as $key => $info): $t = $info['tournament']; ?>
        <article class="cnt-grand-slam-card cnt-grand-slam-card--<?php echo esc_attr($key); ?>">
            <span class="cnt-grand-slam-card__icon" aria-hidden="true">🏆</span>
            <h3><?php echo esc_html($info['label']); ?></h3>
            <?php if ($t): ?>
                <p><?php echo esc_html(CN_Tennis_Helpers::surface_label((string) $t['surface'])); ?></p>
                <p><?php echo $t['starts_at'] ? esc_html(mysql2date('d/m', $t['starts_at']) . ' – ' . mysql2date('d/m/Y', $t['ends_at'] ?: $t['starts_at'])) : '—'; ?></p>
                <p class="cnt-status cnt-status--<?php echo esc_attr($t['status']); ?>"><?php echo esc_html(CN_Tennis_Helpers::tournament_status_label($t['status'])); ?></p>
            <?php else: ?>
                <p class="cnt-empty">Datas serão exibidas assim que o calendário for sincronizado.</p>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </div>
</section>
