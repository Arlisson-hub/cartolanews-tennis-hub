<?php
/** @var array $legends */
defined('ABSPATH') || exit;
?>
<section class="cnt-legends" id="cnt-lendas" aria-labelledby="cnt-lendas-title">
    <h2 id="cnt-lendas-title">Lendas do Tênis</h2>
    <?php if (!$legends): ?>
        <p class="cnt-empty">Nenhuma lenda cadastrada ainda. Cadastre em CartolaNews Tênis → Lendas.</p>
    <?php else: ?>
        <div class="cnt-legends__grid">
            <?php foreach ($legends as $legend): ?>
            <article class="cnt-legend-card">
                <div class="cnt-legend-card__media">
                    <?php echo CN_Tennis_Images::render($legend, 'cn-tennis-legend-card', 'name'); ?>
                </div>
                <div class="cnt-legend-card__body">
                    <h3><?php echo esc_html($legend['name']); ?></h3>
                    <p class="cnt-legend-card__meta">
                        <?php echo wp_kses(CN_Tennis_Helpers::flag($legend['country_code'] ?? ''), ['span' => []]); ?>
                        <?php echo esc_html($legend['country'] ?: ($legend['country_code'] ?? '')); ?>
                        <?php if ($legend['pro_period_start']): ?>
                            · <?php echo esc_html((string) $legend['pro_period_start']); ?>–<?php echo esc_html($legend['pro_period_end'] ? (string) $legend['pro_period_end'] : 'presente'); ?>
                        <?php endif; ?>
                    </p>
                    <div class="cnt-legend-card__stats">
                        <?php if ($legend['grand_slams_count'] !== null): ?>
                            <span><strong><?php echo (int) $legend['grand_slams_count']; ?></strong> Grand Slams</span>
                        <?php endif; ?>
                        <?php if ($legend['titles_count'] !== null): ?>
                            <span><strong><?php echo (int) $legend['titles_count']; ?></strong> títulos</span>
                        <?php endif; ?>
                        <?php if ($legend['best_rank'] !== null): ?>
                            <span>Melhor ranking: <strong>#<?php echo (int) $legend['best_rank']; ?></strong></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($legend['description']): ?>
                        <p class="cnt-legend-card__desc"><?php echo esc_html(wp_trim_words(wp_strip_all_tags($legend['description']), 26)); ?></p>
                    <?php endif; ?>
                    <?php echo CN_Tennis_Images::credit_html($legend); ?>
                    <?php if ($legend['source']): ?>
                        <small class="cnt-legend-card__source">Fonte: <?php echo esc_html($legend['source']); ?></small>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
