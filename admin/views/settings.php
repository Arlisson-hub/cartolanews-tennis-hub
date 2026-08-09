<?php
defined('ABSPATH') || exit;
$s = CN_Tennis_Settings::all();
$weight_sum = $s['power_ranking_weight_recent_form'] + $s['power_ranking_weight_opponent_strength']
    + $s['power_ranking_weight_tournament_importance'] + $s['power_ranking_weight_surface'] + $s['power_ranking_weight_streak'];
?>
<form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
    <?php settings_fields('cn_tennis_settings_group'); ?>
    <h2>Fontes de dados (feeds do GitHub)</h2>
    <p class="description">URLs "Raw" dos snapshots JSON publicados pelo GitHub Actions (ver <code>docs/GITHUB-ACTIONS.md</code>). Priorize sempre fontes gratuitas.</p>
    <table class="form-table">
        <tr><th>Ranking ATP (masculino)</th><td><input type="url" name="cn_tennis_settings[feed_rankings_male_url]" value="<?php echo esc_attr($s['feed_rankings_male_url']); ?>" class="large-text" placeholder="https://raw.githubusercontent.com/usuario/repo/main/data/tennis/rankings-atp.json"></td></tr>
        <tr><th>Ranking WTA (feminino)</th><td><input type="url" name="cn_tennis_settings[feed_rankings_female_url]" value="<?php echo esc_attr($s['feed_rankings_female_url']); ?>" class="large-text"></td></tr>
        <tr><th>Calendário</th><td><input type="url" name="cn_tennis_settings[feed_calendar_url]" value="<?php echo esc_attr($s['feed_calendar_url']); ?>" class="large-text"></td></tr>
        <tr><th>Jogadores (opcional)</th><td><input type="url" name="cn_tennis_settings[feed_players_url]" value="<?php echo esc_attr($s['feed_players_url']); ?>" class="large-text"></td></tr>
        <tr><th>Chave TheSportsDB</th><td><input type="text" name="cn_tennis_settings[thesportsdb_api_key]" value="<?php echo esc_attr($s['thesportsdb_api_key']); ?>"> <span class="description">Padrão "123" (chave pública de teste, gratuita).</span></td></tr>
    </table>

    <h2>Cache e "Ao Vivo"</h2>
    <table class="form-table">
        <tr><th>Cache do ranking (min)</th><td><input type="number" name="cn_tennis_settings[cache_rankings_minutes]" value="<?php echo (int) $s['cache_rankings_minutes']; ?>"></td></tr>
        <tr><th>Cache do calendário (min)</th><td><input type="number" name="cn_tennis_settings[cache_calendar_minutes]" value="<?php echo (int) $s['cache_calendar_minutes']; ?>"></td></tr>
        <tr><th>Cache de jogos (min)</th><td><input type="number" name="cn_tennis_settings[cache_matches_minutes]" value="<?php echo (int) $s['cache_matches_minutes']; ?>"></td></tr>
        <tr><th>Validade do "AO VIVO" (min)</th><td><input type="number" name="cn_tennis_settings[live_stale_minutes]" value="<?php echo (int) $s['live_stale_minutes']; ?>"> <span class="description">Após esse tempo sem atualização da fonte, o selo some (seção 13).</span></td></tr>
    </table>

    <h2>Fórmula do Power Ranking</h2>
    <p class="description">Pesos somam <strong><?php echo esc_html((string) $weight_sum); ?></strong> (o cálculo normaliza automaticamente se não somar 100). Ver fórmula completa comentada em <code>includes/class-power-ranking.php</code>.</p>
    <table class="form-table">
        <tr><th>Janela (dias)</th><td><input type="number" name="cn_tennis_settings[power_ranking_window_days]" value="<?php echo (int) $s['power_ranking_window_days']; ?>"></td></tr>
        <tr><th>Mínimo de partidas</th><td><input type="number" name="cn_tennis_settings[power_ranking_min_matches]" value="<?php echo (int) $s['power_ranking_min_matches']; ?>"></td></tr>
        <tr><th>Peso — Forma recente</th><td><input type="number" name="cn_tennis_settings[power_ranking_weight_recent_form]" value="<?php echo (int) $s['power_ranking_weight_recent_form']; ?>"></td></tr>
        <tr><th>Peso — Força dos adversários</th><td><input type="number" name="cn_tennis_settings[power_ranking_weight_opponent_strength]" value="<?php echo (int) $s['power_ranking_weight_opponent_strength']; ?>"></td></tr>
        <tr><th>Peso — Importância dos torneios</th><td><input type="number" name="cn_tennis_settings[power_ranking_weight_tournament_importance]" value="<?php echo (int) $s['power_ranking_weight_tournament_importance']; ?>"></td></tr>
        <tr><th>Peso — Superfície</th><td><input type="number" name="cn_tennis_settings[power_ranking_weight_surface]" value="<?php echo (int) $s['power_ranking_weight_surface']; ?>"></td></tr>
        <tr><th>Peso — Sequência (streak)</th><td><input type="number" name="cn_tennis_settings[power_ranking_weight_streak]" value="<?php echo (int) $s['power_ranking_weight_streak']; ?>"></td></tr>
    </table>

    <h2>Aparência</h2>
    <table class="form-table">
        <tr><th>Cor primária</th><td><input type="text" name="cn_tennis_settings[primary_color]" value="<?php echo esc_attr($s['primary_color']); ?>" class="cnt-color-field"></td></tr>
        <tr><th>Cor de destaque</th><td><input type="text" name="cn_tennis_settings[accent_color]" value="<?php echo esc_attr($s['accent_color']); ?>" class="cnt-color-field"></td></tr>
    </table>

    <h2>Notícias</h2>
    <table class="form-table">
        <tr><th>Categoria de notícias</th><td><input type="text" name="cn_tennis_settings[news_category]" value="<?php echo esc_attr($s['news_category']); ?>" placeholder="slug da categoria, ex: tenis"></td></tr>
        <tr><th>Quantidade exibida</th><td><input type="number" name="cn_tennis_settings[news_limit]" value="<?php echo (int) $s['news_limit']; ?>"></td></tr>
    </table>

    <h2>Ciclo de vida</h2>
    <table class="form-table">
        <tr><th>Ao desinstalar</th><td><label><input type="checkbox" name="cn_tennis_settings[uninstall_keep_data]" value="1" <?php checked($s['uninstall_keep_data'], 1); ?>> Manter todos os dados no banco (recomendado)</label></td></tr>
        <tr><th>Fallback automático</th><td><label><input type="checkbox" name="cn_tennis_settings[fallback_enabled]" value="1" <?php checked($s['fallback_enabled'], 1); ?>> Ativado</label></td></tr>
    </table>

    <?php submit_button('Salvar configurações'); ?>
</form>

<hr>
<h2>Importar / Exportar</h2>
<div class="cn-tennis-admin__grid">
    <div>
        <h3>Exportar</h3>
        <p>
            <a class="button" href="<?php echo esc_url(add_query_arg(['cn_tennis_export' => 'players'], admin_url('admin.php?page=cn-tennis-settings'))); ?>">Jogadores (JSON)</a>
            <a class="button" href="<?php echo esc_url(add_query_arg(['cn_tennis_export' => 'legends'], admin_url('admin.php?page=cn-tennis-settings'))); ?>">Lendas (JSON)</a>
            <a class="button" href="<?php echo esc_url(add_query_arg(['cn_tennis_export' => 'tournaments'], admin_url('admin.php?page=cn-tennis-settings'))); ?>">Torneios (JSON)</a>
            <a class="button" href="<?php echo esc_url(add_query_arg(['cn_tennis_export' => 'settings'], admin_url('admin.php?page=cn-tennis-settings'))); ?>">Configurações (JSON)</a>
        </p>
    </div>
    <div>
        <h3>Importar</h3>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field('cn_tennis_import'); ?>
            <input type="hidden" name="action" value="cn_tennis_import">
            <select name="import_type">
                <option value="players">Jogadores</option>
                <option value="legends">Lendas</option>
                <option value="tournaments">Torneios</option>
            </select>
            <input type="file" name="import_file" accept="application/json">
            <p><textarea name="import_json" rows="4" class="large-text" placeholder="ou cole o JSON aqui"></textarea></p>
            <button type="submit" class="button button-primary">Importar (com validação e preview de contagem)</button>
        </form>
    </div>
</div>
<?php
if (!empty($_GET['cn_tennis_export']) && current_user_can('manage_options')) {
    $type = sanitize_key((string) $_GET['cn_tennis_export']);
    $exporter = new CN_Tennis_Import_Export();
    $json = match ($type) {
        'players' => $exporter->export_players(),
        'legends' => $exporter->export_legends(),
        'tournaments' => $exporter->export_tournaments(),
        'settings' => $exporter->export_settings(),
        default => '',
    };
    if ($json !== '') {
        echo '<script>window.addEventListener("load",function(){var a=document.createElement("a");a.href="data:application/json;charset=utf-8,"+encodeURIComponent(' . wp_json_encode($json) . ');a.download="cartolanews-tennis-' . esc_js($type) . '.json";document.body.appendChild(a);a.click();a.remove();});</script>';
    }
}
?>
