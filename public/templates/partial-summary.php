<?php
/**
 * @var int $today_games
 * @var int $ongoing_tournaments
 * @var array|null $number_one_male
 * @var array|null $number_one_female
 * @var array|null $best_brazilian
 */
defined('ABSPATH') || exit;
?>
<section class="cnt-summary" aria-labelledby="cnt-summary-title">
    <h2 id="cnt-summary-title" class="cnt-visually-hidden">Resumo rápido</h2>
    <div class="cnt-summary__grid">

        <article class="cnt-summary-card">
            <span class="cnt-summary-card__value"><?php echo (int) $today_games; ?></span>
            <span class="cnt-summary-card__label">Jogos de hoje</span>
        </article>

        <article class="cnt-summary-card">
            <span class="cnt-summary-card__value"><?php echo (int) $ongoing_tournaments; ?></span>
            <span class="cnt-summary-card__label">Torneios acontecendo</span>
        </article>

        <article class="cnt-summary-card cnt-summary-card--player">
            <span class="cnt-summary-card__label">Nº 1 masculino</span>
            <?php if ($number_one_male): ?>
                <div class="cnt-summary-card__player">
                    <?php echo CN_Tennis_Images::render($number_one_male, 'cn-tennis-avatar', 'player_name'); ?>
                    <div>
                        <strong><?php echo esc_html($number_one_male['player_name']); ?></strong>
                        <span><?php echo wp_kses(CN_Tennis_Helpers::flag($number_one_male['country_code'] ?? ''), ['span' => []]); ?> <?php echo esc_html($number_one_male['country_code'] ?? ''); ?></span>
                        <span><?php echo (int) $number_one_male['points']; ?> pts</span>
                    </div>
                </div>
            <?php else: ?>
                <p class="cnt-empty">Ranking ainda não sincronizado.</p>
            <?php endif; ?>
        </article>

        <article class="cnt-summary-card cnt-summary-card--player">
            <span class="cnt-summary-card__label">Nº 1 feminino</span>
            <?php if ($number_one_female): ?>
                <div class="cnt-summary-card__player">
                    <?php echo CN_Tennis_Images::render($number_one_female, 'cn-tennis-avatar', 'player_name'); ?>
                    <div>
                        <strong><?php echo esc_html($number_one_female['player_name']); ?></strong>
                        <span><?php echo wp_kses(CN_Tennis_Helpers::flag($number_one_female['country_code'] ?? ''), ['span' => []]); ?> <?php echo esc_html($number_one_female['country_code'] ?? ''); ?></span>
                        <span><?php echo (int) $number_one_female['points']; ?> pts</span>
                    </div>
                </div>
            <?php else: ?>
                <p class="cnt-empty">Ranking ainda não sincronizado.</p>
            <?php endif; ?>
        </article>

        <article class="cnt-summary-card cnt-summary-card--player">
            <span class="cnt-summary-card__label">Melhor brasileiro</span>
            <?php if ($best_brazilian): ?>
                <div class="cnt-summary-card__player">
                    <?php echo CN_Tennis_Images::render($best_brazilian, 'cn-tennis-avatar', 'player_name'); ?>
                    <div>
                        <strong><?php echo esc_html($best_brazilian['player_name']); ?></strong>
                        <span>#<?php echo (int) $best_brazilian['rank_position']; ?></span>
                        <span><?php echo (int) $best_brazilian['points']; ?> pts</span>
                    </div>
                </div>
            <?php else: ?>
                <p class="cnt-empty">Nenhum brasileiro no ranking sincronizado ainda.</p>
            <?php endif; ?>
        </article>

    </div>
</section>
