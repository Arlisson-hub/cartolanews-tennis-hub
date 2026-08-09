<?php
defined('ABSPATH') || exit;
$gender = in_array($_GET['gender'] ?? '', ['male', 'female'], true) ? $_GET['gender'] : 'male';
?>
<h2>Ranking Mundial — visão administrativa</h2>
<p class="description">Este ranking é preenchido automaticamente pelas fontes ATP/WTA. Para corrigir um jogador específico, edite-o em Jogadores e marque os campos como travados.</p>

<nav class="nav-tab-wrapper">
    <a class="nav-tab <?php echo $gender === 'male' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=cn-tennis-rankings&gender=male')); ?>">Masculino</a>
    <a class="nav-tab <?php echo $gender === 'female' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=cn-tennis-rankings&gender=female')); ?>">Feminino</a>
</nav>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:14px 0;">
    <?php wp_nonce_field('cn_tennis_sync_now'); ?>
    <input type="hidden" name="action" value="cn_tennis_sync_now">
    <input type="hidden" name="target" value="rankings_<?php echo esc_attr($gender); ?>">
    <button type="submit" class="button">Sincronizar agora</button>
</form>

<table class="widefat striped">
    <thead><tr><th>Pos.</th><th>Jogador</th><th>País</th><th>Pontos</th><th>Variação</th><th>Atualizado</th></tr></thead>
    <tbody>
    <?php $rows = CN_Tennis_Rankings::query($gender, 100); ?>
    <?php if (!$rows): ?><tr><td colspan="6">Ranking ainda não sincronizado.</td></tr><?php endif; ?>
    <?php foreach ($rows as $row):
        $prev = (int) ($row['previous_rank'] ?? 0);
        $pos = (int) $row['rank_position'];
        $delta = $prev ? $prev - $pos : 0;
    ?>
        <tr>
            <td><?php echo $pos; ?></td>
            <td><a href="<?php echo esc_url(admin_url('admin.php?page=cn-tennis-players&edit=' . (int) $row['player_id'])); ?>"><?php echo esc_html($row['player_name']); ?></a></td>
            <td><?php echo esc_html($row['country_code'] ?? ''); ?></td>
            <td><?php echo esc_html(number_format_i18n((int) $row['points'])); ?></td>
            <td><?php echo $delta > 0 ? '▲' . $delta : ($delta < 0 ? '▼' . abs($delta) : '—'); ?></td>
            <td><?php echo esc_html(CN_Tennis_Helpers::time_ago($row['updated_at'])); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
