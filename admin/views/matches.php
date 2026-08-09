<?php
defined('ABSPATH') || exit;
?>
<h2>Cadastrar jogo manualmente</h2>
<p class="description">Use apenas quando a fonte automática não cobrir uma partida específica (ex.: torneio menor). Os jogos automáticos aparecem listados abaixo e não devem ser editados aqui.</p>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cn-tennis-admin__form">
    <?php wp_nonce_field('cn_tennis_save_match'); ?>
    <input type="hidden" name="action" value="cn_tennis_save_match">
    <table class="form-table">
        <tr><th>Jogador 1</th><td><input required type="text" name="player1_name" class="regular-text"> País: <input type="text" name="player1_country" maxlength="3" style="width:70px"> Ranking: <input type="number" name="player1_rank" style="width:90px"></td></tr>
        <tr><th>Jogador 2</th><td><input required type="text" name="player2_name" class="regular-text"> País: <input type="text" name="player2_country" maxlength="3" style="width:70px"> Ranking: <input type="number" name="player2_rank" style="width:90px"></td></tr>
        <tr><th>Data/hora</th><td><input required type="datetime-local" name="scheduled_at"></td></tr>
        <tr><th>Torneio / Rodada</th><td><input type="text" name="round_name" placeholder="Ex.: Quartas de final"></td></tr>
        <tr><th>Gênero</th><td>
            <select name="gender"><option value="male">Masculino</option><option value="female">Feminino</option></select>
        </td></tr>
        <tr><th>Tipo</th><td>
            <select name="match_type"><option value="singles">Simples</option><option value="doubles">Duplas</option></select>
        </td></tr>
        <tr><th>Superfície</th><td>
            <select name="surface">
                <option value="">—</option>
                <?php foreach (['hard' => 'Quadra dura', 'clay' => 'Saibro', 'grass' => 'Grama', 'indoor' => 'Indoor'] as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </td></tr>
        <tr><th>Status</th><td>
            <select name="status">
                <?php foreach (['scheduled' => 'Próximo', 'live' => 'Em andamento', 'finished' => 'Encerrado', 'postponed' => 'Adiado', 'cancelled' => 'Cancelado'] as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </td></tr>
        <tr><th>Vencedor</th><td>
            <select name="winner"><option value="">—</option><option value="1">Jogador 1</option><option value="2">Jogador 2</option></select>
        </td></tr>
    </table>
    <p class="submit"><button type="submit" class="button button-primary">Salvar jogo</button></p>
</form>

<hr>
<h2>Jogos recentes/próximos</h2>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:10px;">
    <?php wp_nonce_field('cn_tennis_sync_now'); ?>
    <input type="hidden" name="action" value="cn_tennis_sync_now">
    <input type="hidden" name="target" value="matches">
    <button type="submit" class="button">Sincronizar jogos agora</button>
</form>
<table class="widefat striped">
    <thead><tr><th>Data</th><th>Jogadores</th><th>Torneio</th><th>Status</th><th>Origem</th></tr></thead>
    <tbody>
    <?php $rows = CN_Tennis_Matches::query(['from' => gmdate('Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS), 'limit' => 60]); ?>
    <?php if (!$rows): ?><tr><td colspan="5">Nenhum jogo encontrado.</td></tr><?php endif; ?>
    <?php foreach ($rows as $m): ?>
        <tr>
            <td><?php echo esc_html(get_date_from_gmt($m['scheduled_at'], 'd/m H:i')); ?></td>
            <td><?php echo esc_html($m['player1_name'] . ' x ' . $m['player2_name']); ?></td>
            <td><?php echo esc_html($m['tournament_name'] ?? '—'); ?></td>
            <td><?php echo esc_html(CN_Tennis_Helpers::match_status_label($m['status'])); ?></td>
            <td><?php echo esc_html($m['provider']); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
