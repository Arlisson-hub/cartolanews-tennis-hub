<?php
/**
 * @var array $rows
 * @var string $title
 * @var array $a
 */
defined('ABSPATH') || exit;
?>
<div class="cnt-power-ranking">
    <?php if (($a['titulo'] ?? 'sim') !== 'nao'): ?><h3><?php echo esc_html($title); ?></h3><?php endif; ?>
    <?php if (!$rows): ?>
        <p class="cnt-empty">Dados insuficientes para calcular. É necessário um número mínimo de partidas finalizadas recentes registradas no banco.</p>
    <?php else: ?>
        <ol class="cnt-power-ranking__list">
            <?php foreach ($rows as $row):
                $prev = (int) ($row['previous_rank'] ?? 0);
                $pos = (int) $row['rank_position'];
                $delta = $prev ? $prev - $pos : 0;
            ?>
            <li class="cnt-power-ranking__item">
                <span class="cnt-power-ranking__pos"><?php echo $pos; ?></span>
                <?php echo CN_Tennis_Images::render($row, 'cn-tennis-avatar', 'player_name'); ?>
                <div class="cnt-power-ranking__info">
                    <?php if (!empty($row['player_slug'])): ?>
                        <a href="<?php echo esc_url(CN_Tennis_Player_Profile::url($row['player_slug'])); ?>"><strong><?php echo esc_html($row['player_name']); ?></strong></a>
                    <?php else: ?>
                        <strong><?php echo esc_html($row['player_name']); ?></strong>
                    <?php endif; ?>
                    <span>Forma: <strong><?php echo esc_html(number_format_i18n((float) $row['score'], 0)); ?></strong></span>
                </div>
                <span class="cnt-trend cnt-trend--<?php echo $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'same'); ?>">
                    <?php echo $delta > 0 ? '▲' . esc_html((string) $delta) : ($delta < 0 ? '▼' . esc_html((string) abs($delta)) : '—'); ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>
