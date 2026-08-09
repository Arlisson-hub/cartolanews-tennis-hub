<?php
/**
 * @var array $male
 * @var array $female
 */
defined('ABSPATH') || exit;

$render_group = static function (array $rows, string $label): void {
    ?>
    <div class="cnt-brazilians__group">
        <h3><?php echo esc_html($label); ?></h3>
        <?php if (!$rows): ?>
            <p class="cnt-empty">Nenhum brasileiro no ranking sincronizado ainda.</p>
        <?php else: ?>
            <ul class="cnt-brazilians__list">
                <?php foreach ($rows as $row):
                    $prev = (int) ($row['previous_rank'] ?? 0);
                    $pos = (int) $row['rank_position'];
                    $delta = $prev ? $prev - $pos : 0;
                    $age = CN_Tennis_Helpers::calculate_age($row['birth_date'] ?? null);
                ?>
                <li class="cnt-brazilians__item">
                    <?php echo CN_Tennis_Images::render($row, 'cn-tennis-avatar', 'player_name'); ?>
                    <div class="cnt-brazilians__info">
                        <?php if (!empty($row['player_slug'])): ?>
                            <a href="<?php echo esc_url(CN_Tennis_Player_Profile::url($row['player_slug'])); ?>"><strong><?php echo esc_html($row['player_name']); ?></strong></a>
                        <?php else: ?>
                            <strong><?php echo esc_html($row['player_name']); ?></strong>
                        <?php endif; ?>
                        <span>#<?php echo $pos; ?> mundial <?php echo $age ? '· ' . esc_html((string) $age) . ' anos' : ''; ?></span>
                        <span><?php echo esc_html(number_format_i18n((int) $row['points'])); ?> pts</span>
                    </div>
                    <span class="cnt-trend cnt-trend--<?php echo $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'same'); ?>">
                        <?php echo $delta > 0 ? '▲' . esc_html((string) $delta) : ($delta < 0 ? '▼' . esc_html((string) abs($delta)) : '—'); ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
};
?>
<section class="cnt-brazilians" id="cnt-brasileiros" aria-labelledby="cnt-brasileiros-title">
    <h2 id="cnt-brasileiros-title">Brasileiros no Ranking Mundial</h2>
    <div class="cnt-brazilians__grid">
        <?php $render_group($male, 'Masculino'); ?>
        <?php $render_group($female, 'Feminino'); ?>
    </div>
</section>
