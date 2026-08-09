<?php
/** @var array $match */
defined('ABSPATH') || exit;

$is_live = CN_Tennis_Matches::is_currently_live($match);
$is_stale = CN_Tennis_Matches::is_live_stale($match);
$status = (string) $match['status'];
$score = json_decode((string) ($match['score_json'] ?? ''), true);
$sets = is_array($score['sets'] ?? null) ? $score['sets'] : [];
$current_game = is_array($score['current_game'] ?? null) ? $score['current_game'] : null;
$serving = $score['serving'] ?? null;

$visual_status = $is_live ? 'live' : ($is_stale ? 'stale' : $status);
$scheduled_local = get_date_from_gmt($match['scheduled_at'], 'H:i');
$scheduled_date = get_date_from_gmt($match['scheduled_at'], 'd/m');
$is_today = get_date_from_gmt($match['scheduled_at'], 'Y-m-d') === current_time('Y-m-d');
?>
<article class="cnt-match-card cnt-match-card--<?php echo esc_attr($visual_status); ?>"
         data-cnt-gender="<?php echo esc_attr($match['gender']); ?>"
         data-cnt-type="<?php echo esc_attr($match['match_type']); ?>"
         data-cnt-status="<?php echo esc_attr($status); ?>"
         data-cnt-surface="<?php echo esc_attr($match['surface'] ?: 'unknown'); ?>">
    <div class="cnt-match-card__top">
        <?php if ($is_live): ?>
            <span class="cnt-badge cnt-badge--live" aria-label="Partida ao vivo">AO VIVO</span>
            <span class="cnt-match-card__updated">Atualizado <?php echo esc_html(CN_Tennis_Helpers::time_ago($match['source_updated_at'])); ?></span>
        <?php elseif ($is_stale): ?>
            <span class="cnt-badge cnt-badge--stale">Atualização temporariamente indisponível</span>
        <?php else: ?>
            <span class="cnt-badge cnt-badge--<?php echo esc_attr($status); ?>"><?php echo esc_html(CN_Tennis_Helpers::match_status_label($status)); ?></span>
            <span class="cnt-match-card__time"><?php echo $is_today ? esc_html($scheduled_local) : esc_html($scheduled_date . ' ' . $scheduled_local); ?></span>
        <?php endif; ?>
        <span class="cnt-match-card__tournament"><?php echo esc_html($match['tournament_name'] ?? 'Tênis'); ?><?php echo $match['round_name'] ? ' · ' . esc_html($match['round_name']) : ''; ?></span>
    </div>

    <div class="cnt-match-card__body">
        <div class="cnt-match-card__players">
            <div class="cnt-match-card__player">
                <?php echo wp_kses(CN_Tennis_Helpers::flag($match['player1_country'] ?? ''), ['span' => []]); ?>
                <span class="cnt-match-card__name"><?php echo esc_html($match['player1_name']); ?><?php echo $match['player1_rank'] ? ' <small>#' . (int) $match['player1_rank'] . '</small>' : ''; ?></span>
                <?php if ($serving === 1): ?><span class="cnt-match-card__serve" aria-label="Sacando" title="Sacando">●</span><?php endif; ?>
            </div>
            <div class="cnt-match-card__player">
                <?php echo wp_kses(CN_Tennis_Helpers::flag($match['player2_country'] ?? ''), ['span' => []]); ?>
                <span class="cnt-match-card__name"><?php echo esc_html($match['player2_name']); ?><?php echo $match['player2_rank'] ? ' <small>#' . (int) $match['player2_rank'] . '</small>' : ''; ?></span>
                <?php if ($serving === 2): ?><span class="cnt-match-card__serve" aria-label="Sacando" title="Sacando">●</span><?php endif; ?>
            </div>
        </div>

        <?php if ($sets): ?>
        <div class="cnt-match-card__score" role="table" aria-label="Placar por set">
            <div class="cnt-match-card__score-row" role="row">
                <?php foreach ($sets as $set): ?>
                    <span role="cell" class="<?php echo (isset($set['p1'], $set['p2']) && $set['p1'] > $set['p2']) ? 'is-winner' : ''; ?>">
                        <?php echo isset($set['p1']) ? (int) $set['p1'] : '–'; ?><?php echo isset($set['tiebreak']) && $set['tiebreak'] !== null ? '<sup>' . (int) $set['tiebreak'] . '</sup>' : ''; ?>
                    </span>
                <?php endforeach; ?>
                <?php if ($current_game && isset($current_game['p1'])): ?><span role="cell" class="cnt-match-card__current"><?php echo esc_html((string) $current_game['p1']); ?></span><?php endif; ?>
            </div>
            <div class="cnt-match-card__score-row" role="row">
                <?php foreach ($sets as $set): ?>
                    <span role="cell" class="<?php echo (isset($set['p1'], $set['p2']) && $set['p2'] > $set['p1']) ? 'is-winner' : ''; ?>">
                        <?php echo isset($set['p2']) ? (int) $set['p2'] : '–'; ?><?php // tiebreak do adversário já implícito no par do set ?>
                    </span>
                <?php endforeach; ?>
                <?php if ($current_game && isset($current_game['p2'])): ?><span role="cell" class="cnt-match-card__current"><?php echo esc_html((string) $current_game['p2']); ?></span><?php endif; ?>
            </div>
        </div>
        <?php elseif ($status === 'scheduled'): ?>
            <span class="cnt-match-card__vs">x</span>
        <?php endif; ?>
    </div>

    <?php if ($match['duration_minutes']): ?>
        <p class="cnt-match-card__duration">Duração: <?php echo (int) $match['duration_minutes']; ?> min</p>
    <?php endif; ?>
</article>
