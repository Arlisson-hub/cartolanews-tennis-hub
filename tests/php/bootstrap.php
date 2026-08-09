<?php
/**
 * Stubs mínimos de funções do WordPress — o suficiente para carregar e
 * testar as classes de lógica pura do plugin FORA de um WordPress real.
 * Nunca usado em produção (só nos testes).
 */
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('CN_TENNIS_VERSION', 'test');

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key(string $value): string { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)) ?? ''); }
}
if (!function_exists('sanitize_title')) {
    function sanitize_title(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
if (!function_exists('absint')) {
    function absint(mixed $value): int { return abs((int) $value); }
}
if (!function_exists('esc_html')) {
    function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES); }
}
if (!function_exists('esc_attr')) {
    function esc_attr(string $value): string { return htmlspecialchars($value, ENT_QUOTES); }
}
if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $value): string { return filter_var($value, FILTER_SANITIZE_URL) ?: ''; }
}
if (!function_exists('wp_kses_post')) {
    function wp_kses_post(string $value): string { return strip_tags($value, '<p><a><strong><em><br>'); }
}
if (!function_exists('remove_accents')) {
    function remove_accents(string $value): string {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        return $transliterated !== false ? $transliterated : $value;
    }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value, int $flags = 0): string|false { return json_encode($value, $flags); }
}
if (!function_exists('wp_parse_args')) {
    function wp_parse_args(array $args, array $defaults = []): array { return array_merge($defaults, $args); }
}
if (!function_exists('get_option')) {
    function get_option(string $name, mixed $default = false): mixed { return $GLOBALS['cn_test_options'][$name] ?? $default; }
}
if (!function_exists('update_option')) {
    function update_option(string $name, mixed $value, mixed $autoload = null): bool { $GLOBALS['cn_test_options'][$name] = $value; return true; }
}
if (!function_exists('current_time')) {
    function current_time(string $type, bool $gmt = false): string|int {
        if ($type === 'timestamp') return time();
        return $type === 'Y-m-d' ? gmdate('Y-m-d') : gmdate('Y-m-d H:i:s');
    }
}
if (!function_exists('get_date_from_gmt')) {
    function get_date_from_gmt(string $mysql_datetime, string $format = 'Y-m-d H:i:s'): string {
        $timestamp = strtotime($mysql_datetime . ' UTC');
        return $timestamp ? gmdate($format, $timestamp) : '';
    }
}
if (!function_exists('mysql2date')) {
    function mysql2date(string $format, string $mysql_datetime): string {
        $timestamp = strtotime($mysql_datetime);
        return $timestamp ? date($format, $timestamp) : '';
    }
}
