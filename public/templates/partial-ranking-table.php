<?php
/**
 * @var string $gender
 * @var array $rows
 * @var string|null $last_update
 * @var string $title
 * @var int $default_top
 */
defined('ABSPATH') || exit;
$updated_label = $last_update ? CN_Tennis_Helpers::time_ago($last_update) : 'sem sincronização ainda';
?>
<div class="cnt-ranking-table" data-cnt-ranking-panel="<?php echo esc_attr($gender); ?>" data-cnt-ranking-visible="<?php echo (int) $default_top; ?>">
    <div class="cnt-ranking-table__head">
        <h3><?php echo esc_html($title); ?></h3>
        <div class="cnt-ranking-table__controls">
            <label class="cnt-visually-hidden" for="cnt-top-<?php echo esc_attr($gender); ?>">Quantidade exibida</label>
            <select data-cnt-top-select data-cnt-gender="<?php echo esc_attr($gender); ?>" id="cnt-top-<?php echo esc_attr($gender); ?>">
                <option value="10"<?php selected($default_top, 10); ?>>Top 10</option>
                <option value="20"<?php selected($default_top, 20); ?>>Top 20</option>
                <option value="50"<?php selected($default_top, 50); ?>>Top 50</option>
                <option value="100"<?php selected($default_top, 100); ?>>Top 100</option>
            </select>
        </div>
    </div>
    <p class="cnt-note">Atualizado <?php echo esc_html($updated_label); ?>.</p>

    <?php if (!$rows): ?>
        <p class="cnt-empty">Ranking ainda não sincronizado. Assim que a primeira sincronização automática rodar, os jogadores aparecerão aqui.</p>
    <?php else: ?>
        <div class="cnt-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Pos.</th>
                        <th></th>
                        <th>Jogador</th>
                        <th>País</th>
                        <th>Idade</th>
                        <th>Pontos</th>
                        <th>Variação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $i => $row):
                        $pos = (int) $row['rank_position'];
                        $prev = (int) ($row['previous_rank'] ?? 0);
                        $delta = $prev ? $prev - $pos : 0;
                        $age = CN_Tennis_Helpers::calculate_age($row['birth_date'] ?? null);
                        $extra_row = $i >= $default_top ? ' class="cnt-ranking-extra" hidden' : '';
                    ?>
                    <tr data-cnt-rank-row data-index="<?php echo (int) $i; ?>"<?php echo $extra_row; ?>>
                        <td><strong><?php echo $pos; ?></strong></td>
                        <td><?php echo CN_Tennis_Images::render($row, 'cn-tennis-avatar', 'player_name', [], true); ?></td>
                        <td>
                            <?php if (!empty($row['player_slug'])): ?>
                                <a href="<?php echo esc_url(CN_Tennis_Player_Profile::url($row['player_slug'])); ?>"><?php echo esc_html($row['player_name']); ?></a>
                            <?php else: ?>
                                <?php echo esc_html($row['player_name']); ?>
                            <?php endif; ?>
                            <?php if (!empty($row['is_brazilian'])): ?><span class="cnt-tag cnt-tag--br">BRA</span><?php endif; ?>
                        </td>
                        <td><?php echo wp_kses(CN_Tennis_Helpers::flag($row['country_code'] ?? ''), ['span' => []]); ?> <?php echo esc_html($row['country_code'] ?? '—'); ?></td>
                        <td><?php echo $age ? esc_html((string) $age) : '—'; ?></td>
                        <td><?php echo esc_html(number_format_i18n((int) $row['points'])); ?></td>
                        <td class="cnt-trend cnt-trend--<?php echo $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'same'); ?>">
                            <?php echo $delta > 0 ? '▲ ' . esc_html((string) $delta) : ($delta < 0 ? '▼ ' . esc_html((string) abs($delta)) : '—'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($rows) > $default_top): ?>
            <button type="button" class="cnt-ranking-toggle" data-cnt-ranking-toggle aria-expanded="false">Ver ranking completo</button>
        <?php endif; ?>
    <?php endif; ?>
</div>
