<?php
defined('ABSPATH') || exit;

/**
 * Ativação (seção 51): cria tabelas, registra opções padrão, configura
 * rewrite rules e agenda o cron. Instalável normalmente por
 * Plugins → Adicionar plugin → Enviar plugin, sem passo manual no banco.
 */
final class CN_Tennis_Activator {
    public static function activate(): void {
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            deactivate_plugins(CN_TENNIS_BASENAME);
            wp_die('CartolaNews Tennis Hub requer PHP 8.1 ou superior. Versão atual: ' . PHP_VERSION);
        }

        require_once CN_TENNIS_PATH . 'includes/class-database.php';
        require_once CN_TENNIS_PATH . 'includes/class-sources.php';
        require_once CN_TENNIS_PATH . 'includes/class-settings.php';
        require_once CN_TENNIS_PATH . 'includes/class-images.php';
        require_once CN_TENNIS_PATH . 'includes/class-player-profile.php';
        require_once CN_TENNIS_PATH . 'includes/class-cron.php';

        CN_Tennis_Database::install();
        CN_Tennis_Sources::ensure_defaults();

        if (get_option('cn_tennis_settings', null) === null) {
            add_option('cn_tennis_settings', CN_Tennis_Settings::defaults(), '', false);
        }
        add_option('cn_tennis_db_version', CN_TENNIS_DB_VERSION, '', false);
        add_option('cn_tennis_uninstall_notice_seen', 0, '', false);

        (new CN_Tennis_Images())->register_sizes();

        $profile = new CN_Tennis_Player_Profile();
        $profile->add_rewrite_rule();

        $cron = new CN_Tennis_Cron();
        add_filter('cron_schedules', [$cron, 'add_schedules']);
        $cron->schedule_all();
        wp_schedule_single_event(time() + 60, 'cn_tennis_sync_rankings_male');
        wp_schedule_single_event(time() + 120, 'cn_tennis_sync_rankings_female');
        wp_schedule_single_event(time() + 180, 'cn_tennis_sync_calendar');

        flush_rewrite_rules(false);
    }
}
