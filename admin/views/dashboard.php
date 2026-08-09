<?php
defined('ABSPATH') || exit;

$sources = CN_Tennis_Sources::all();
$players_male = CN_Tennis_Players::count_active('male');
$players_female = CN_Tennis_Players::count_active('female');
$legends_count = count(CN_Tennis_Legends::all(''));
$logs = CN_Tennis_Logger::recent(8);
$errors_24h = 0;
foreach (CN_Tennis_Logger::recent(100, '', 'error') as $log) {
    if (strtotime($log['created_at'] . ' UTC') > time() - DAY_IN_SECONDS) {
        $errors_24h++;
    }
}
?>
<div class="cn-tennis-admin__grid">
    <div class="cn-tennis-admin__card">
        <span class="cn-tennis-admin__stat"><?php echo esc_html((string) ($players_male + $players_female)); ?></span>
        <span>Jogadores cadastrados</span>
    </div>
    <div class="cn-tennis-admin__card">
        <span class="cn-tennis-admin__stat"><?php echo esc_html((string) $legends_count); ?></span>
        <span>Lendas cadastradas</span>
    </div>
    <div class="cn-tennis-admin__card">
        <span class="cn-tennis-admin__stat"><?php echo esc_html((string) CN_Tennis_Matches::count_today()); ?></span>
        <span>Jogos hoje</span>
    </div>
    <div class="cn-tennis-admin__card <?php echo $errors_24h ? 'is-alert' : ''; ?>">
        <span class="cn-tennis-admin__stat"><?php echo esc_html((string) $errors_24h); ?></span>
        <span>Erros nas últimas 24h</span>
    </div>
</div>

<h2>Fontes</h2>
<table class="widefat striped">
    <thead><tr><th>Fonte</th><th>Provider</th><th>Status</th><th>Última atualização</th><th>Registros</th></tr></thead>
    <tbody>
    <?php foreach ($sources as $source): ?>
        <tr>
            <td><?php echo esc_html($source['label']); ?></td>
            <td><?php echo esc_html($source['provider']); ?></td>
            <td><?php echo cn_tennis_status_dot(empty($source['enabled']) ? 'disabled' : $source['status']); ?></td>
            <td><?php echo $source['last_success_at'] ? esc_html(CN_Tennis_Helpers::time_ago($source['last_success_at'])) : '—'; ?></td>
            <td><?php echo esc_html((string) ($source['last_records'] ?? 0)); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p><a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=cn-tennis-sources')); ?>">Ver painel completo de fontes →</a></p>

<h2>Últimos registros de sincronização</h2>
<table class="widefat striped">
    <thead><tr><th>Data</th><th>Tarefa</th><th>Status</th><th>Registros</th><th>Mensagem</th></tr></thead>
    <tbody>
    <?php if (!$logs): ?>
        <tr><td colspan="5">Nenhum registro ainda.</td></tr>
    <?php endif; ?>
    <?php foreach ($logs as $log): ?>
        <tr>
            <td><?php echo esc_html(get_date_from_gmt($log['created_at'], 'd/m H:i')); ?></td>
            <td><?php echo esc_html($log['task']); ?></td>
            <td><?php echo esc_html($log['status']); ?></td>
            <td><?php echo esc_html((string) $log['received']); ?></td>
            <td><?php echo esc_html((string) ($log['message'] ?? '')); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p><a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=cn-tennis-logs')); ?>">Ver todos os logs →</a></p>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:20px;">
    <?php wp_nonce_field('cn_tennis_recalculate_power'); ?>
    <input type="hidden" name="action" value="cn_tennis_recalculate_power">
    <button type="submit" class="button button-primary">Recalcular Melhores do Momento agora</button>
</form>
