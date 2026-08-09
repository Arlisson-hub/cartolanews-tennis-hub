<?php
defined('ABSPATH') || exit;

/**
 * Agendamento de sincronização (seção 23 — as frequências reais de coleta
 * pesada rodam no GitHub Actions; este cron do WordPress cobre apenas
 * chamadas leves e o consumo dos feeds/APIs gratuitas, respeitando limites).
 */
final class CN_Tennis_Cron {
    public const HOOKS = [
        'cn_tennis_sync_rankings_male' => 'cn_tennis_daily',
        'cn_tennis_sync_rankings_female' => 'cn_tennis_daily',
        'cn_tennis_sync_calendar' => 'cn_tennis_8hours',
        'cn_tennis_sync_matches' => 'cn_tennis_30min',
        'cn_tennis_cleanup_logs' => 'weekly',
    ];

    public function register(): void {
        add_filter('cron_schedules', [$this, 'add_schedules']);
        add_action('init', [$this, 'schedule_all']);

        add_action('cn_tennis_sync_rankings_male', static fn() => (new CN_Tennis_Sync())->rankings('male'));
        add_action('cn_tennis_sync_rankings_female', static fn() => (new CN_Tennis_Sync())->rankings('female'));
        add_action('cn_tennis_sync_calendar', static fn() => (new CN_Tennis_Sync())->calendar());
        add_action('cn_tennis_sync_matches', static fn() => (new CN_Tennis_Sync())->matches('today'));
        add_action('cn_tennis_cleanup_logs', static fn() => CN_Tennis_Logger::cleanup(60));
    }

    public function add_schedules(array $schedules): array {
        $schedules['cn_tennis_30min'] = ['interval' => 30 * MINUTE_IN_SECONDS, 'display' => 'A cada 30 minutos (CartolaNews Tênis)'];
        $schedules['cn_tennis_8hours'] = ['interval' => 8 * HOUR_IN_SECONDS, 'display' => 'A cada 8 horas (CartolaNews Tênis)'];
        $schedules['cn_tennis_daily'] = ['interval' => DAY_IN_SECONDS, 'display' => 'Diariamente (CartolaNews Tênis)'];
        return $schedules;
    }

    public function schedule_all(): void {
        foreach (self::HOOKS as $hook => $recurrence) {
            if (!wp_next_scheduled($hook)) {
                wp_schedule_event(time() + wp_rand(60, 900), $recurrence, $hook);
            }
        }
    }

    public static function clear_all(): void {
        foreach (array_keys(self::HOOKS) as $hook) {
            $timestamp = wp_next_scheduled($hook);
            while ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
                $timestamp = wp_next_scheduled($hook);
            }
        }
    }
}
