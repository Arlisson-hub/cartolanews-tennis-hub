<?php
defined('ABSPATH') || exit;
$result = (new CN_Tennis_Diagnostics())->run();
$labels = [
    'database' => 'Banco de dados',
    'rest_api' => 'REST API',
    'sources' => 'Fontes',
    'github_snapshot' => 'Snapshot do GitHub',
    'cache' => 'Cache',
    'images' => 'Imagens',
    'cron' => 'Cron',
    'permissions' => 'Permissões',
    'rewrite_urls' => 'URLs (perfil de jogador)',
    'last_sync' => 'Último sync',
];
$icons = ['ok' => '✅', 'atencao' => '⚠️', 'erro' => '❌'];
?>
<h2>Diagnóstico do Tênis</h2>
<p>Status geral: <strong><?php echo esc_html($icons[$result['overall']] . ' ' . strtoupper($result['overall'])); ?></strong> — verificado em <?php echo esc_html($result['checked_at']); ?></p>
<table class="widefat striped">
    <thead><tr><th>Verificação</th><th>Status</th><th>Detalhe</th></tr></thead>
    <tbody>
    <?php foreach ($result['checks'] as $key => $check): ?>
        <tr>
            <td><?php echo esc_html($labels[$key] ?? $key); ?></td>
            <td><?php echo esc_html($icons[$check['status']] ?? '?'); ?></td>
            <td><?php echo esc_html($check['message']); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=cn-tennis-diagnostics')); ?>">Executar novamente</a></p>
