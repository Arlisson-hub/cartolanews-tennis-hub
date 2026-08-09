<?php
/** @var array $rows */
defined('ABSPATH') || exit;
?>
<section class="cnt-calendar" id="cnt-calendario" aria-labelledby="cnt-calendario-title">
    <div class="cnt-calendar__head">
        <h2 id="cnt-calendario-title">Calendário do Tênis</h2>
    </div>

    <div class="cnt-tabs cnt-tabs--wrap" role="tablist" aria-label="Filtrar calendário" data-cnt-calendar-tabs>
        <button type="button" role="tab" aria-selected="true" data-cnt-cal-filter="all">Todos</button>
        <button type="button" role="tab" aria-selected="false" data-cnt-cal-filter="today">Hoje</button>
        <button type="button" role="tab" aria-selected="false" data-cnt-cal-filter="week">Esta semana</button>
        <button type="button" role="tab" aria-selected="false" data-cnt-cal-filter="month">Este mês</button>
        <button type="button" role="tab" aria-selected="false" data-cnt-cal-filter="atp">ATP</button>
        <button type="button" role="tab" aria-selected="false" data-cnt-cal-filter="wta">WTA</button>
        <button type="button" role="tab" aria-selected="false" data-cnt-cal-filter="grand_slam">Grand Slam</button>
        <button type="button" role="tab" aria-selected="false" data-cnt-cal-filter="challenger">Challenger</button>
        <button type="button" role="tab" aria-selected="false" data-cnt-cal-filter="brasil">Brasil</button>
    </div>

    <?php if (!$rows): ?>
        <p class="cnt-empty">Calendário ainda não sincronizado.</p>
    <?php else: ?>
        <div class="cnt-table-wrap">
            <table class="cnt-calendar__table" data-cnt-calendar-list>
                <thead>
                    <tr>
                        <th>Torneio</th>
                        <th>Cidade/País</th>
                        <th>Início</th>
                        <th>Final</th>
                        <th>Categoria</th>
                        <th>Superfície</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $t):
                        $today = current_time('Y-m-d');
                        $period = CN_Tennis_Calendar::period_flags($t['starts_at'] ?? null, $t['ends_at'] ?? null, $today);
                        $is_brazil = strtoupper((string) $t['country_code']) === 'BRA' || strcasecmp((string) $t['country'], 'Brasil') === 0 || strcasecmp((string) $t['country'], 'Brazil') === 0;
                    ?>
                    <tr data-cnt-cal-row
                        data-tour="<?php echo esc_attr($t['tour']); ?>"
                        data-category="<?php echo esc_attr($t['category'] ?: ''); ?>"
                        data-today="<?php echo $period['today'] ? '1' : '0'; ?>"
                        data-week="<?php echo $period['week'] ? '1' : '0'; ?>"
                        data-month="<?php echo $period['month'] ? '1' : '0'; ?>"
                        data-brasil="<?php echo $is_brazil ? '1' : '0'; ?>">
                        <td data-label="Torneio"><strong><?php echo esc_html($t['name']); ?></strong></td>
                        <td data-label="Cidade/País"><?php echo esc_html(trim(($t['city'] ? $t['city'] . ', ' : '') . $t['country'])); ?></td>
                        <td data-label="Início"><?php echo $t['starts_at'] ? esc_html(mysql2date('d/m/Y', $t['starts_at'])) : '—'; ?></td>
                        <td data-label="Final"><?php echo $t['ends_at'] ? esc_html(mysql2date('d/m/Y', $t['ends_at'])) : '—'; ?></td>
                        <td data-label="Categoria"><?php echo esc_html(CN_Tennis_Helpers::category_label((string) $t['category'])); ?> <span class="cnt-tag"><?php echo esc_html(CN_Tennis_Helpers::tour_label($t['tour'])); ?></span></td>
                        <td data-label="Superfície"><?php echo esc_html(CN_Tennis_Helpers::surface_label((string) $t['surface'])); ?></td>
                        <td data-label="Status"><strong class="cnt-status cnt-status--<?php echo esc_attr($t['status']); ?>"><?php echo esc_html(CN_Tennis_Helpers::tournament_status_label($t['status'])); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="cnt-empty" data-cnt-calendar-empty hidden>Nenhum torneio neste filtro.</p>
    <?php endif; ?>
</section>
