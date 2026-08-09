<?php
defined('ABSPATH') || exit;

$editing_id = absint($_GET['edit'] ?? 0);
$editing = $editing_id ? CN_Tennis_Calendar::find($editing_id) : [];
$is_new = !$editing_id;
?>
<h2><?php echo $is_new ? 'Novo torneio' : 'Editar torneio: ' . esc_html($editing['name']); ?></h2>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cn-tennis-admin__form">
    <?php wp_nonce_field('cn_tennis_save_tournament'); ?>
    <input type="hidden" name="action" value="cn_tennis_save_tournament">
    <input type="hidden" name="tournament_id" value="<?php echo (int) $editing_id; ?>">
    <input type="hidden" name="external_id" value="<?php echo esc_attr($editing['external_id'] ?? ''); ?>">
    <table class="form-table">
        <tr><th>Nome</th><td><input required type="text" name="name" value="<?php echo esc_attr($editing['name'] ?? ''); ?>" class="regular-text"></td></tr>
        <tr><th>Cidade / País</th><td><input type="text" name="city" placeholder="Cidade" value="<?php echo esc_attr($editing['city'] ?? ''); ?>"> <input type="text" name="country" placeholder="País" value="<?php echo esc_attr($editing['country'] ?? ''); ?>"> <input type="text" name="country_code" maxlength="3" placeholder="BRA" value="<?php echo esc_attr($editing['country_code'] ?? ''); ?>" style="width:70px"></td></tr>
        <tr><th>Início / Fim</th><td><input required type="date" name="starts_at" value="<?php echo esc_attr($editing['starts_at'] ?? ''); ?>"> até <input type="date" name="ends_at" value="<?php echo esc_attr($editing['ends_at'] ?? ''); ?>"></td></tr>
        <tr><th>Tour</th><td>
            <select name="tour">
                <?php foreach (['atp' => 'ATP', 'wta' => 'WTA', 'both' => 'ATP / WTA'] as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($editing['tour'] ?? 'atp', $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </td></tr>
        <tr><th>Categoria</th><td>
            <select name="category">
                <option value="">—</option>
                <?php foreach (['grand_slam' => 'Grand Slam', 'atp1000' => 'Masters 1000 / WTA 1000', 'atp500' => 'ATP/WTA 500', 'atp250' => 'ATP/WTA 250', 'challenger' => 'Challenger', 'itf' => 'ITF'] as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($editing['category'] ?? '', $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </td></tr>
        <tr><th>Superfície</th><td>
            <select name="surface">
                <option value="">—</option>
                <?php foreach (['hard' => 'Quadra dura', 'clay' => 'Saibro', 'grass' => 'Grama', 'indoor' => 'Indoor'] as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($editing['surface'] ?? '', $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </td></tr>
        <tr><th>Premiação (opcional)</th><td><input type="number" name="prize_money" value="<?php echo esc_attr((string) ($editing['prize_money'] ?? '')); ?>"> <input type="text" name="prize_currency" placeholder="USD" value="<?php echo esc_attr($editing['prize_currency'] ?? ''); ?>" style="width:80px"></td></tr>
        <tr><th>Tamanho do chaveamento</th><td><input type="number" name="draw_size" value="<?php echo esc_attr((string) ($editing['draw_size'] ?? '')); ?>"></td></tr>
        <tr><th>Status</th><td>
            <select name="status">
                <?php foreach (['upcoming' => 'Próximo', 'ongoing' => 'Em andamento', 'finished' => 'Encerrado', 'cancelled' => 'Cancelado'] as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($editing['status'] ?? 'upcoming', $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </td></tr>
    </table>
    <p class="submit"><button type="submit" class="button button-primary">Salvar torneio</button></p>
</form>

<hr>
<h2>Calendário cadastrado</h2>
<table class="widefat striped">
    <thead><tr><th>Torneio</th><th>Início</th><th>Categoria</th><th>Status</th><th>Origem</th><th></th></tr></thead>
    <tbody>
    <?php $rows = CN_Tennis_Calendar::query(['limit' => 100, 'from' => gmdate('Y-m-d', time() - 2 * YEAR_IN_SECONDS)]); ?>
    <?php if (!$rows): ?><tr><td colspan="6">Nenhum torneio cadastrado ainda.</td></tr><?php endif; ?>
    <?php foreach ($rows as $t): ?>
        <tr>
            <td><strong><?php echo esc_html($t['name']); ?></strong></td>
            <td><?php echo $t['starts_at'] ? esc_html(mysql2date('d/m/Y', $t['starts_at'])) : '—'; ?></td>
            <td><?php echo esc_html(CN_Tennis_Helpers::category_label((string) $t['category'])); ?></td>
            <td><?php echo esc_html(CN_Tennis_Helpers::tournament_status_label($t['status'])); ?></td>
            <td><?php echo esc_html($t['provider']); ?><?php echo $t['manual_override'] ? ' 🔒' : ''; ?></td>
            <td><a href="<?php echo esc_url(admin_url('admin.php?page=cn-tennis-tournaments&edit=' . (int) $t['id'])); ?>">Editar</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
