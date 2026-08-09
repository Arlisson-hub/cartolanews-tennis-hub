<?php
/** @var array $data key => ['label'=>string, 'top'=>array] */
defined('ABSPATH') || exit;
$icons = [
    'clay' => '🟠',
    'grass' => '🟢',
    'hard' => '🔵',
    'indoor' => '⚪',
];
?>
<section class="cnt-surfaces" id="cnt-superficies" aria-labelledby="cnt-superficies-title">
    <h2 id="cnt-superficies-title">Especialistas por Superfície</h2>
    <div class="cnt-surfaces__grid">
        <?php foreach ($data as $key => $info): ?>
        <article class="cnt-surface-card" data-cnt-surface-card="<?php echo esc_attr($key); ?>">
            <div class="cnt-surface-card__head">
                <span class="cnt-surface-card__icon" aria-hidden="true"><?php echo esc_html($icons[$key] ?? '🎾'); ?></span>
                <h3><?php echo esc_html($info['label']); ?></h3>
            </div>
            <?php if (!$info['top']): ?>
                <p class="cnt-empty">Ainda sem partidas suficientes nesta superfície.</p>
            <?php else: ?>
                <ol class="cnt-surface-card__list">
                    <?php foreach ($info['top'] as $player): ?>
                    <li>
                        <?php echo CN_Tennis_Images::render($player, 'cn-tennis-avatar', 'name'); ?>
                        <span><?php echo esc_html($player['name']); ?></span>
                        <strong><?php echo esc_html((string) $player['win_rate']); ?>%</strong>
                    </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </div>
</section>
