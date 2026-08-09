<?php
defined('ABSPATH') || exit;

/**
 * Administração: menu "CartolaNews Tênis" com os submenus da seção 34 (mais
 * Diagnóstico, seção 49). Cada submenu é uma página própria (não apenas uma
 * aba via query string), como pedido no briefing.
 */
final class CN_Tennis_Admin {
    private const CAP = 'manage_options';

    private const PAGES = [
        'cn-tennis-hub' => ['Dashboard', 'dashboard'],
        'cn-tennis-players' => ['Jogadores', 'players'],
        'cn-tennis-legends' => ['Lendas', 'legends'],
        'cn-tennis-tournaments' => ['Torneios', 'tournaments'],
        'cn-tennis-rankings' => ['Rankings', 'rankings'],
        'cn-tennis-matches' => ['Jogos', 'matches'],
        'cn-tennis-sources' => ['Fontes', 'sources'],
        'cn-tennis-images' => ['Imagens', 'images'],
        'cn-tennis-logs' => ['Logs', 'logs'],
        'cn-tennis-settings' => ['Configurações', 'settings'],
        'cn-tennis-diagnostics' => ['Diagnóstico', 'diagnostics'],
    ];

    public function register(): void {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [CN_Tennis_Settings::class, 'register']);
        add_action('admin_notices', [$this, 'notices']);

        foreach ($this->actions() as $action => $method) {
            add_action('admin_post_cn_tennis_' . $action, [$this, $method]);
        }
    }

    private function actions(): array {
        return [
            'save_player' => 'handle_save_player',
            'save_legend' => 'handle_save_legend',
            'delete_legend' => 'handle_delete_legend',
            'save_tournament' => 'handle_save_tournament',
            'save_match' => 'handle_save_match',
            'save_source' => 'handle_save_source',
            'sync_now' => 'handle_sync_now',
            'recalculate_power' => 'handle_recalculate_power',
            'import' => 'handle_import',
            'cleanup_logs' => 'handle_cleanup_logs',
            'wikimedia_import' => 'handle_wikimedia_import',
        ];
    }

    public function menu(): void {
        $first = true;
        foreach (self::PAGES as $slug => [$label, $view]) {
            if ($first) {
                add_menu_page('CartolaNews Tênis', 'CartolaNews Tênis', self::CAP, $slug, [$this, 'render'], 'dashicons-awards', 61);
                add_submenu_page($slug, $label, $label, self::CAP, $slug, [$this, 'render']);
                $first = false;
                continue;
            }
            add_submenu_page('cn-tennis-hub', $label, $label, self::CAP, $slug, [$this, 'render']);
        }
    }

    public function render(): void {
        if (!current_user_can(self::CAP)) {
            wp_die('Sem permissão.');
        }
        $slug = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : 'cn-tennis-hub';
        $view = self::PAGES[$slug][1] ?? 'dashboard';
        $file = CN_TENNIS_PATH . 'admin/views/' . $view . '.php';
        echo '<div class="wrap cn-tennis-admin">';
        echo '<h1>CartolaNews Tênis — ' . esc_html(self::PAGES[$slug][0] ?? 'Dashboard') . '</h1>';
        $this->render_tabs($slug);
        if (is_readable($file)) {
            include $file;
        }
        echo '</div>';
    }

    private function render_tabs(string $current): void {
        echo '<nav class="nav-tab-wrapper cn-tennis-admin__tabs">';
        foreach (self::PAGES as $slug => [$label]) {
            $class = $slug === $current ? 'nav-tab nav-tab-active' : 'nav-tab';
            printf('<a class="%s" href="%s">%s</a>', esc_attr($class), esc_url(admin_url('admin.php?page=' . $slug)), esc_html($label));
        }
        echo '</nav>';
    }

    public function notices(): void {
        if (empty($_GET['cn_tennis_message']) || !isset($_GET['page']) || !str_starts_with((string) $_GET['page'], 'cn-tennis')) {
            return;
        }
        $type = sanitize_key((string) ($_GET['cn_tennis_status'] ?? 'success'));
        $message = sanitize_text_field((string) wp_unslash($_GET['cn_tennis_message']));
        printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $type === 'error' ? 'error' : 'success', esc_html($message));
    }

    private function redirect_back(string $page, string $message, string $status = 'success'): void {
        wp_safe_redirect(add_query_arg([
            'page' => $page,
            'cn_tennis_message' => rawurlencode($message),
            'cn_tennis_status' => $status,
        ], admin_url('admin.php')));
        exit;
    }

    private function guard(string $nonce_action): void {
        if (!current_user_can(self::CAP) || !check_admin_referer($nonce_action)) {
            wp_die('Ação não autorizada.');
        }
    }

    // ------------------------------------------------------------------
    // Handlers
    // ------------------------------------------------------------------
    public function handle_save_player(): void {
        $this->guard('cn_tennis_save_player');
        $id = absint($_POST['player_id'] ?? 0);
        if (!$id) {
            $this->redirect_back('cn-tennis-players', 'Jogador inválido.', 'error');
        }
        $fields = ['name', 'full_name', 'country', 'country_code', 'birth_date', 'height_cm', 'plays', 'turned_pro'];
        $overrides = array_map('sanitize_key', (array) ($_POST['manual_override'] ?? []));
        global $wpdb;
        $data = [];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = sanitize_text_field((string) wp_unslash($_POST[$field]));
            }
        }
        if (isset($_POST['photo_attachment_id'])) {
            $data['photo_attachment_id'] = absint($_POST['photo_attachment_id']) ?: null;
        }
        if (isset($_POST['photo_focal_x'])) {
            $data['photo_focal_x'] = max(0, min(100, absint($_POST['photo_focal_x'])));
        }
        if (isset($_POST['photo_focal_y'])) {
            $data['photo_focal_y'] = max(0, min(100, absint($_POST['photo_focal_y'])));
        }
        $data['updated_at'] = current_time('mysql', true);
        $wpdb->update(CN_Tennis_Players::table(), $data, ['id' => $id]);
        CN_Tennis_Players::set_override($id, CN_Tennis_Players::overridable_fields(), false);
        CN_Tennis_Players::set_override($id, $overrides, true);
        CN_Tennis_Cache::flush();
        $this->redirect_back('cn-tennis-players', 'Jogador atualizado.');
    }

    public function handle_save_legend(): void {
        $this->guard('cn_tennis_save_legend');
        $id = absint($_POST['legend_id'] ?? 0);
        $data = wp_unslash($_POST);
        $result = CN_Tennis_Legends::save($data, $id);
        $this->redirect_back('cn-tennis-legends', $result['message'] ?? ($result['ok'] ? 'Lenda salva.' : 'Erro ao salvar.'), $result['ok'] ? 'success' : 'error');
    }

    public function handle_delete_legend(): void {
        $this->guard('cn_tennis_delete_legend');
        CN_Tennis_Legends::delete(absint($_POST['legend_id'] ?? 0));
        $this->redirect_back('cn-tennis-legends', 'Lenda removida.');
    }

    public function handle_save_tournament(): void {
        $this->guard('cn_tennis_save_tournament');
        $data = wp_unslash($_POST);
        $data['provider'] = 'manual';
        $data['external_id'] = $data['external_id'] ?: ('manual:' . sanitize_title((string) ($data['name'] ?? '')) . ':' . ($data['starts_at'] ?? uniqid()));
        $id = CN_Tennis_Calendar::upsert_from_provider($data);
        if ($id && !empty($_POST['tournament_id'])) {
            global $wpdb;
            $wpdb->update(CN_Tennis_Calendar::table(), ['manual_override' => 1], ['id' => absint($_POST['tournament_id'])]);
        } elseif ($id) {
            global $wpdb;
            $wpdb->update(CN_Tennis_Calendar::table(), ['manual_override' => 1], ['id' => $id]);
        }
        CN_Tennis_Cache::flush();
        $this->redirect_back('cn-tennis-tournaments', $id ? 'Torneio salvo.' : 'Não foi possível salvar (verifique nome e data de início).', $id ? 'success' : 'error');
    }

    public function handle_save_match(): void {
        $this->guard('cn_tennis_save_match');
        $data = wp_unslash($_POST);
        $data['provider'] = 'manual';
        $data['external_id'] = 'manual:' . md5(($data['player1_name'] ?? '') . ($data['player2_name'] ?? '') . ($data['scheduled_at'] ?? '') . microtime());
        $id = CN_Tennis_Matches::upsert_from_provider($data);
        CN_Tennis_Cache::flush();
        $this->redirect_back('cn-tennis-matches', $id ? 'Jogo cadastrado.' : 'Não foi possível salvar (confira os dois jogadores e o horário).', $id ? 'success' : 'error');
    }

    public function handle_save_source(): void {
        $this->guard('cn_tennis_save_source');
        $key = sanitize_key((string) ($_POST['source_key'] ?? ''));
        if ($key === '') {
            $this->redirect_back('cn-tennis-sources', 'Fonte inválida.', 'error');
        }
        CN_Tennis_Sources::update_config($key, [
            'url' => esc_url_raw((string) wp_unslash($_POST['url'] ?? '')),
            'frequency_minutes' => absint($_POST['frequency_minutes'] ?? 1440),
            'timeout_seconds' => absint($_POST['timeout_seconds'] ?? 20),
            'retries' => absint($_POST['retries'] ?? 3),
            'priority' => absint($_POST['priority'] ?? 0),
        ]);
        CN_Tennis_Sources::set_enabled($key, !empty($_POST['enabled']));
        $this->redirect_back('cn-tennis-sources', 'Fonte atualizada.');
    }

    public function handle_sync_now(): void {
        $this->guard('cn_tennis_sync_now');
        $target = sanitize_key((string) ($_POST['target'] ?? ''));
        $sync = new CN_Tennis_Sync();
        $result = match ($target) {
            'rankings_male' => $sync->rankings('male'),
            'rankings_female' => $sync->rankings('female'),
            'calendar' => $sync->calendar(),
            'matches' => $sync->matches('today'),
            default => ['ok' => false, 'message' => 'Fonte desconhecida.'],
        };
        $this->redirect_back('cn-tennis-sources', (string) ($result['message'] ?? 'Sincronização executada.'), !empty($result['ok']) ? 'success' : 'error');
    }

    public function handle_recalculate_power(): void {
        $this->guard('cn_tennis_recalculate_power');
        (new CN_Tennis_Power_Ranking())->calculate_all();
        $this->redirect_back('cn-tennis-hub', 'Melhores do Momento recalculado.');
    }

    public function handle_cleanup_logs(): void {
        $this->guard('cn_tennis_cleanup_logs');
        $removed = CN_Tennis_Logger::cleanup(absint($_POST['days'] ?? 60));
        $this->redirect_back('cn-tennis-logs', "{$removed} log(s) removido(s).");
    }

    public function handle_import(): void {
        $this->guard('cn_tennis_import');
        $type = sanitize_key((string) ($_POST['import_type'] ?? ''));
        $json = '';
        if (!empty($_FILES['import_file']['tmp_name']) && is_uploaded_file($_FILES['import_file']['tmp_name'])) {
            $json = (string) file_get_contents($_FILES['import_file']['tmp_name']);
        } elseif (!empty($_POST['import_json'])) {
            $json = wp_unslash((string) $_POST['import_json']);
        }
        $importer = new CN_Tennis_Import_Export();
        $result = match ($type) {
            'legends' => $importer->import_legends($json),
            'players' => $importer->import_players($json),
            'tournaments' => $importer->import_tournaments($json),
            default => ['ok' => false, 'message' => 'Tipo de importação desconhecido.'],
        };
        CN_Tennis_Cache::flush();
        $this->redirect_back('cn-tennis-settings', (string) $result['message'], $result['ok'] ? 'success' : 'error');
    }

    public function handle_wikimedia_import(): void {
        $this->guard('cn_tennis_wikimedia_import');
        $result = CN_Tennis_Images::import_from_wikimedia((string) wp_unslash($_POST['file_title'] ?? ''));
        $entity = sanitize_key((string) ($_POST['entity'] ?? ''));
        $entity_id = absint($_POST['entity_id'] ?? 0);
        if ($result['ok'] && $entity_id) {
            global $wpdb;
            $table = $entity === 'legend' ? CN_Tennis_Legends::table() : CN_Tennis_Players::table();
            $wpdb->update($table, [
                'photo_attachment_id' => $result['attachment_id'],
                'photo_credit_author' => $result['credit']['author'],
                'photo_credit_license' => $result['credit']['license'],
                'photo_credit_license_url' => $result['credit']['license_url'],
                'photo_credit_source_url' => $result['credit']['source_url'],
                'photo_imported_at' => current_time('mysql', true),
            ], ['id' => $entity_id]);
        }
        $page = $entity === 'legend' ? 'cn-tennis-legends' : 'cn-tennis-players';
        $this->redirect_back($page, $result['message'], $result['ok'] ? 'success' : 'error');
    }
}

if (!function_exists('cn_tennis_status_dot')) {
    /** 🟢 Funcionando · 🟡 Parcial · 🔴 Erro · ⚪ Desativado (seção 35). */
    function cn_tennis_status_dot(string $status): string {
        [$icon, $label] = match ($status) {
            'ok' => ['🟢', 'Funcionando'],
            'partial' => ['🟡', 'Parcial'],
            'error' => ['🔴', 'Erro'],
            'disabled' => ['⚪', 'Desativado'],
            default => ['⚪', 'Desconhecido'],
        };
        return $icon . ' ' . esc_html($label);
    }
}
