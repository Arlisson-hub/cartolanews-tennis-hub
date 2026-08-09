<?php
defined('ABSPATH') || exit;

/**
 * Cache em Transients (usa Object Cache automaticamente quando o host tiver
 * um driver persistente configurado, pois é isso que as funções
 * get_transient/set_transient fazem no WordPress).
 *
 * O frontend nunca deve depender de uma chamada externa acontecer durante o
 * carregamento da página: todo dado exibido vem do banco/cache, e a
 * atualização é feita por cron/REST em segundo plano.
 */
final class CN_Tennis_Cache {
    private const PREFIX = 'cn_tennis_cache_';

    public static function remember(string $key, int $ttl, callable $callback): mixed {
        $cache_key = self::PREFIX . md5($key);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
        $value = $callback();
        if ($value !== null && $value !== false) {
            set_transient($cache_key, $value, $ttl);
        }
        return $value;
    }

    public static function forget(string $key): void {
        delete_transient(self::PREFIX . md5($key));
    }

    /** Invalida todo o cache do plugin e avança a "época" usada pelo frontend para saber que precisa buscar dados novos. */
    public static function flush(): void {
        global $wpdb;
        $like = $wpdb->esc_like('_transient_' . self::PREFIX) . '%';
        $like_timeout = $wpdb->esc_like('_transient_timeout_' . self::PREFIX) . '%';
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $like_timeout));
        wp_cache_flush_group('cn_tennis');
        update_option('cn_tennis_cache_epoch', time(), false);
        self::purge_page_cache();
    }

    private static function purge_page_cache(): void {
        if (has_action('litespeed_purge_all')) {
            do_action('litespeed_purge_all');
        }
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
    }

    public static function epoch(): int {
        return (int) get_option('cn_tennis_cache_epoch', 0);
    }
}
