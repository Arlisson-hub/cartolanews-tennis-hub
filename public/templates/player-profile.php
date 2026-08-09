<?php
defined('ABSPATH') || exit;

/** @var array $player Definido em CN_Tennis_Player_Profile::maybe_render() via $GLOBALS. */
$player = $GLOBALS['cn_tennis_current_player'];
$age = CN_Tennis_Helpers::calculate_age($player['birth_date'] ?? null);
$recent_matches = CN_Tennis_Matches::finished_by_player((int) $player['id'], 180);
$upcoming_matches = array_values(array_filter(
    CN_Tennis_Matches::query(['status' => 'scheduled', 'from' => current_time('mysql', true), 'limit' => 60]),
    static fn($m) => (int) $m['player1_id'] === (int) $player['id'] || (int) $m['player2_id'] === (int) $player['id']
));

$surface_stats = ['hard' => [0, 0], 'clay' => [0, 0], 'grass' => [0, 0], 'indoor' => [0, 0]];
foreach ($recent_matches as $match) {
    $surface = (string) ($match['surface'] ?? '');
    if (!isset($surface_stats[$surface])) {
        continue;
    }
    $is_p1 = (int) $match['player1_id'] === (int) $player['id'];
    $won = (int) $match['winner'] === ($is_p1 ? 1 : 2);
    $surface_stats[$surface][1]++;
    $surface_stats[$surface][0] += $won ? 1 : 0;
}

$page_title = esc_html($player['name']) . ' — Perfil do Jogador | Tênis CartolaNews';
get_header();
?>
<main id="cnt-player-profile" class="cnt cnt-player-profile">
    <div class="cn-tennis-container">

        <header class="cnt-player-profile__header">
            <div class="cnt-player-profile__photo">
                <?php echo CN_Tennis_Images::render($player, 'cn-tennis-player-card', 'name', [], false); ?>
            </div>
            <div class="cnt-player-profile__info">
                <p class="cnt-kicker"><?php echo wp_kses(CN_Tennis_Helpers::flag($player['country_code'] ?? ''), ['span' => []]); ?> <?php echo esc_html($player['country'] ?: ($player['country_code'] ?? '')); ?></p>
                <h1><?php echo esc_html($player['name']); ?></h1>
                <?php if ($player['full_name'] && $player['full_name'] !== $player['name']): ?>
                    <p class="cnt-player-profile__fullname"><?php echo esc_html($player['full_name']); ?></p>
                <?php endif; ?>
                <?php echo CN_Tennis_Images::credit_html($player); ?>

                <dl class="cnt-player-profile__facts">
                    <?php if ($age): ?><div><dt>Idade</dt><dd><?php echo esc_html((string) $age); ?> anos</dd></div><?php endif; ?>
                    <?php if ($player['birth_date']): ?><div><dt>Nascimento</dt><dd><?php echo esc_html(mysql2date('d/m/Y', $player['birth_date'])); ?></dd></div><?php endif; ?>
                    <?php if ($player['height_cm']): ?><div><dt>Altura</dt><dd><?php echo (int) $player['height_cm']; ?> cm</dd></div><?php endif; ?>
                    <?php if ($player['plays']): ?><div><dt>Mão dominante</dt><dd><?php echo $player['plays'] === 'left' ? 'Canhoto' : 'Destro'; ?></dd></div><?php endif; ?>
                    <?php if ($player['current_rank_singles']): ?><div><dt>Ranking atual</dt><dd>#<?php echo (int) $player['current_rank_singles']; ?></dd></div><?php endif; ?>
                    <?php if ($player['best_rank_singles']): ?><div><dt>Melhor ranking</dt><dd>#<?php echo (int) $player['best_rank_singles']; ?><?php echo $player['best_rank_date'] ? ' (' . esc_html(mysql2date('m/Y', $player['best_rank_date'])) . ')' : ''; ?></dd></div><?php endif; ?>
                    <?php if ($player['current_rank_points']): ?><div><dt>Pontos</dt><dd><?php echo esc_html(number_format_i18n((int) $player['current_rank_points'])); ?></dd></div><?php endif; ?>
                    <?php if ($player['titles_count'] !== null): ?><div><dt>Títulos</dt><dd><?php echo (int) $player['titles_count']; ?></dd></div><?php endif; ?>
                </dl>
            </div>
        </header>

        <section class="cnt-player-profile__section" aria-labelledby="cnt-pp-surfaces-title">
            <h2 id="cnt-pp-surfaces-title">Desempenho por Superfície (últimos 6 meses)</h2>
            <div class="cnt-surface-stats">
                <?php foreach ($surface_stats as $surface => [$wins, $total]): ?>
                    <div class="cnt-surface-stats__item">
                        <span><?php echo esc_html(CN_Tennis_Helpers::surface_label($surface)); ?></span>
                        <strong><?php echo $total ? esc_html($wins . '-' . ($total - $wins)) : '—'; ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="cnt-player-profile__section" aria-labelledby="cnt-pp-recent-title">
            <h2 id="cnt-pp-recent-title">Partidas Recentes</h2>
            <?php if (!$recent_matches): ?>
                <p class="cnt-empty">Nenhuma partida finalizada registrada recentemente.</p>
            <?php else: ?>
                <div class="cnt-matches-grid"><?php foreach (array_slice($recent_matches, 0, 10) as $m) echo CN_Tennis_Shortcodes::match_card($m); ?></div>
            <?php endif; ?>
        </section>

        <section class="cnt-player-profile__section" aria-labelledby="cnt-pp-upcoming-title">
            <h2 id="cnt-pp-upcoming-title">Próximos Jogos</h2>
            <?php if (!$upcoming_matches): ?>
                <p class="cnt-empty">Nenhum próximo jogo confirmado no momento.</p>
            <?php else: ?>
                <div class="cnt-matches-grid"><?php foreach (array_slice($upcoming_matches, 0, 6) as $m) echo CN_Tennis_Shortcodes::match_card($m); ?></div>
            <?php endif; ?>
        </section>

        <p class="cnt-back-link"><a href="<?php echo esc_url(home_url('/tenis/')); ?>">← Voltar para a Central de Tênis</a></p>
    </div>
</main>
<?php
echo CN_Tennis_Schema::person_json_ld($player);
get_footer();
