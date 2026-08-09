<?php
defined('ABSPATH') || exit;
$sources = CN_Tennis_Sources::all();
?>
<h2>Painel de Fontes</h2>
<p class="description">🟢 Funcionando · 🟡 Parcial · 🔴 Erro · ⚪ Desativado. As URLs de feed (GitHub) ficam em Configurações.</p>
<table class="widefat striped">
    <thead><tr><th>Fonte</th><th>Serviço</th><th>Status</th><th>Última atualização</th><th>Registros</th><th>Tempo</th><th>Erro</th><th>Ações</th></tr></thead>
    <tbody>
    <?php foreach ($sources as $source): ?>
        <tr>
            <td><strong><?php echo esc_html($source['label']); ?></strong></td>
            <td><?php echo esc_html($source['provider']); ?><?php echo $source['fallback_provider'] ? ' → ' . esc_html($source['fallback_provider']) : ''; ?></td>
            <td><?php echo cn_tennis_status_dot(empty($source['enabled']) ? 'disabled' : $source['status']); ?></td>
            <td><?php echo $source['last_success_at'] ? esc_html(CN_Tennis_Helpers::time_ago($source['last_success_at'])) : '—'; ?></td>
            <td><?php echo esc_html((string) ($source['last_records'] ?? 0)); ?></td>
            <td><?php echo $source['last_duration_ms'] ? esc_html(number_format_i18n($source['last_duration_ms'])) . ' ms' : '—'; ?></td>
            <td><?php echo esc_html($source['last_error_message'] ?? ''); ?></td>
            <td>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                    <?php wp_nonce_field('cn_tennis_sync_now'); ?>
                    <input type="hidden" name="action" value="cn_tennis_sync_now">
                    <input type="hidden" name="target" value="<?php echo esc_attr($source['source_key']); ?>">
                    <button type="submit" class="button button-small">Atualizar agora</button>
                </form>
            </td>
        </tr>
        <tr>
            <td colspan="8">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cn-tennis-admin__inline-form">
                    <?php wp_nonce_field('cn_tennis_save_source'); ?>
                    <input type="hidden" name="action" value="cn_tennis_save_source">
                    <input type="hidden" name="source_key" value="<?php echo esc_attr($source['source_key']); ?>">
                    URL do feed: <input type="url" name="url" value="<?php echo esc_attr($source['url'] ?? ''); ?>" class="regular-text" placeholder="https://raw.githubusercontent.com/...">
                    Frequência (min): <input type="number" name="frequency_minutes" value="<?php echo (int) $source['frequency_minutes']; ?>" style="width:90px">
                    Timeout (s): <input type="number" name="timeout_seconds" value="<?php echo (int) $source['timeout_seconds']; ?>" style="width:80px">
                    Tentativas: <input type="number" name="retries" value="<?php echo (int) $source['retries']; ?>" style="width:70px">
                    Prioridade: <input type="number" name="priority" value="<?php echo (int) $source['priority']; ?>" style="width:70px">
                    <label><input type="checkbox" name="enabled" value="1" <?php checked($source['enabled'], 1); ?>> Ativa</label>
                    <button type="submit" class="button">Salvar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
