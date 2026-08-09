<?php
defined('ABSPATH') || exit;

/**
 * Ponto central de bootstrap. Instancia e registra cada subsistema — nada
 * de lógica de negócio aqui, só orquestração (mesmo papel do Plugin::boot()
 * dos outros hubs CartolaNews usados como referência).
 */
final class CN_Tennis_Plugin {
    private static ?self $instance = null;

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    public function boot(): void {
        load_plugin_textdomain('cartolanews-tennis-hub', false, dirname(CN_TENNIS_BASENAME) . '/languages');

        $this->maybe_upgrade();

        (new CN_Tennis_Images())->register();
        (new CN_Tennis_Assets())->register();
        (new CN_Tennis_Shortcodes())->register();
        (new CN_Tennis_Rest())->register();
        (new CN_Tennis_Cron())->register();
        (new CN_Tennis_Schema())->register();
        (new CN_Tennis_Player_Profile())->register();

        if (is_admin()) {
            (new CN_Tennis_Admin())->register();
        }
    }

    private function maybe_upgrade(): void {
        $installed = (string) get_option('cn_tennis_db_version', '0');
        if (version_compare($installed, CN_TENNIS_DB_VERSION, '>=') && CN_Tennis_Database::ready()) {
            return;
        }
        CN_Tennis_Database::install();
        CN_Tennis_Sources::ensure_defaults();
    }

    public static function settings(): array {
        return CN_Tennis_Settings::all();
    }
}
