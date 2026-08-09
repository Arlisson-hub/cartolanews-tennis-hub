<?php
/**
 * Executado apenas quando o administrador usa "Excluir" no painel de
 * Plugins (nunca em uma simples desativação). Respeita a preferência salva
 * em Configurações → "Manter dados ao desinstalar" (seção 51). Por padrão,
 * os dados são preservados — só são removidos se o admin desmarcou essa
 * opção explicitamente antes de excluir o plugin.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

$settings = get_option('cn_tennis_settings', []);
$keep_data = !isset($settings['uninstall_keep_data']) || (int) $settings['uninstall_keep_data'] === 1;

if ($keep_data) {
    return;
}

$tables = [
    'players', 'rankings', 'tournaments', 'matches', 'legends',
    'sources', 'sync_logs', 'power_rankings', 'ranking_snapshots',
];
foreach ($tables as $name) {
    $table = $wpdb->prefix . 'cn_tennis_' . $name;
    $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
}

$options = [
    'cn_tennis_settings', 'cn_tennis_db_version', 'cn_tennis_version',
    'cn_tennis_cache_epoch', 'cn_tennis_uninstall_notice_seen',
];
foreach ($options as $option) {
    delete_option($option);
}

$like = $wpdb->esc_like('_transient_cn_tennis_cache_') . '%';
$like_timeout = $wpdb->esc_like('_transient_timeout_cn_tennis_cache_') . '%';
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $like_timeout));
