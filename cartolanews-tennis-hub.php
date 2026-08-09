<?php
/**
 * Plugin Name: CartolaNews Tennis Hub
 * Plugin URI: https://cartolanews.com.br/
 * Description: Central completa de Tênis do CartolaNews — ranking mundial, jogos, ao vivo, calendário, lendas, superfícies e perfis de jogadores, com sincronização automática por providers plugáveis.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: CartolaNews
 * Author URI: https://cartolanews.com.br/
 * Text Domain: cartolanews-tennis-hub
 * Domain Path: /languages
 * License: GPLv2 or later
 */

defined('ABSPATH') || exit;

define('CN_TENNIS_VERSION', '1.0.0');
define('CN_TENNIS_DB_VERSION', '1.0.0');
define('CN_TENNIS_FILE', __FILE__);
define('CN_TENNIS_PATH', plugin_dir_path(__FILE__));
define('CN_TENNIS_URL', plugin_dir_url(__FILE__));
define('CN_TENNIS_BASENAME', plugin_basename(__FILE__));

// Compatibilidade com o gerenciador de consentimento do site (mesmo padrão dos
// outros plugins CartolaNews, evita bloqueio de scripts pelo Consent API).
add_filter("wp_consent_api_registered_" . CN_TENNIS_BASENAME, '__return_true');

/**
 * Autoloader por prefixo de classe (não usa PHP namespaces, conforme padrão
 * exigido para este plugin: prefixo obrigatório CN_Tennis_). Resolve
 * CN_Tennis_Foo_Bar -> class-foo-bar.php, procurando em includes/,
 * includes/providers/ e admin/.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'CN_Tennis_';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file_name = 'class-' . strtolower(str_replace('_', '-', $relative)) . '.php';

    $search_dirs = [
        CN_TENNIS_PATH . 'includes/',
        CN_TENNIS_PATH . 'includes/providers/',
        CN_TENNIS_PATH . 'admin/',
    ];

    foreach ($search_dirs as $dir) {
        $path = $dir . $file_name;
        if (is_readable($path)) {
            require_once $path;
            return;
        }
    }
});

require_once CN_TENNIS_PATH . 'includes/providers/interface-cn-tennis-provider.php';

register_activation_hook(__FILE__, ['CN_Tennis_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['CN_Tennis_Deactivator', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    try {
        CN_Tennis_Plugin::instance()->boot();
    } catch (Throwable $error) {
        error_log('CartolaNews Tennis Hub bootstrap: ' . $error->getMessage());
        if (is_admin()) {
            add_action('admin_notices', static function () use ($error): void {
                if (!current_user_can('activate_plugins')) {
                    return;
                }
                echo '<div class="notice notice-error"><p><strong>CartolaNews Tennis Hub:</strong> ' .
                    esc_html('O plugin entrou em modo seguro: ' . $error->getMessage()) .
                    '</p></div>';
            });
        }
    }
});
