<?php
defined('ABSPATH') || exit;
$status_filter = sanitize_key((string) ($_GET['status'] ?? ''));
$logs = CN_Tennis_Logger::recent(200, '', $status_filter);
?>
<h2>Logs de sincronização</h2>
<form method="get" style="margin-bottom:12px;">
    <input type="hidden" name="page" value="cn-tennis-logs">
    <select name="status" onchange="this.form.submit()">
        <option value="">Todos os status</option>
        <?php foreach (['success' => 'Sucesso', 'partial' => 'Parcial', 'cached' => 'Cache mantido', 'error' => 'Erro'] as $key => $label): ?>
            <option value="<?php echo esc_attr($key); ?>" <?php selected($status_filter, $key); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
    </select>
</form>

<table class="widefat striped">
    <thead><tr><th>Data</th><th>Tarefa</th><th>Provider</th><th>Status</th><th>HTTP</th><th>Duração</th><th>Registros</th><th>Mensagem</th></tr></thead>
    <tbody>
    <?php if (!$logs): ?><tr><td colspan="8">Nenhum log encontrado.</td></tr><?php endif; ?>
    <?php foreach ($logs as $log): ?>
        <tr>
            <td><?php echo esc_html(get_date_from_gmt($log['created_at'], 'd/m/Y H:i:s')); ?></td>
            <td><?php echo esc_html($log['task']); ?></td>
            <td><?php echo esc_html($log['provider'] ?? ''); ?></td>
            <td><?php echo esc_html($log['status']); ?></td>
            <td><?php echo esc_html((string) ($log['http_code'] ?? '')); ?></td>
            <td><?php echo $log['duration_ms'] ? esc_html(number_format_i18n($log['duration_ms'])) . ' ms' : '—'; ?></td>
            <td><?php echo esc_html((string) $log['received']); ?></td>
            <td><?php echo esc_html((string) ($log['message'] ?? '')); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;">
    <?php wp_nonce_field('cn_tennis_cleanup_logs'); ?>
    <input type="hidden" name="action" value="cn_tennis_cleanup_logs">
    Remover logs com mais de <input type="number" name="days" value="60" style="width:70px"> dias.
    <button type="submit" class="button">Limpar logs antigos</button>
</form>
