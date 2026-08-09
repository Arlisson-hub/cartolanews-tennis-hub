<?php
defined('ABSPATH') || exit;
?>
<h2>Tamanhos de imagem registrados</h2>
<p class="description">Consulte <code>docs/IMAGE-GUIDE.md</code> para orientações completas de enquadramento. Gerados automaticamente pelo WordPress a partir da imagem original enviada em cada jogador/lenda.</p>
<table class="widefat striped">
    <thead><tr><th>Nome</th><th>Resolução</th><th>Proporção</th><th>Uso</th></tr></thead>
    <tbody>
        <tr><td><code>cn-tennis-hero</code></td><td>1920×640</td><td>3:1</td><td>Hero da central</td></tr>
        <tr><td><code>cn-tennis-player-card</code></td><td>800×1000</td><td>4:5</td><td>Perfil do jogador, destaques</td></tr>
        <tr><td><code>cn-tennis-legend-card</code></td><td>800×1000</td><td>4:5</td><td>Cards de lendas</td></tr>
        <tr><td><code>cn-tennis-avatar</code></td><td>320×320</td><td>1:1</td><td>Rankings, listas, brasileiros</td></tr>
        <tr><td><code>cn-tennis-tournament-card</code></td><td>1200×675</td><td>16:9</td><td>Cards de torneio</td></tr>
        <tr><td><code>cn-tennis-surface-card</code></td><td>800×500</td><td>8:5</td><td>Especial por superfície</td></tr>
    </tbody>
</table>

<h2>Posição focal</h2>
<p>Ajustável por jogador/lenda na tela de edição (campos X/Y em %). Controla <code>object-position</code> via as variáveis CSS <code>--cn-focal-x</code>/<code>--cn-focal-y</code>, sem precisar reeditar o arquivo de imagem.</p>

<h2>Placeholder</h2>
<p>Quando não há foto cadastrada, o plugin nunca exibe uma imagem quebrada: mostra automaticamente as iniciais do jogador sobre um gradiente azul CartolaNews com uma silhueta genérica de fundo (sem depender de nenhum arquivo externo).</p>

<h2>Importações do Wikimedia Commons</h2>
<?php
global $wpdb;
$imported = $wpdb->get_results(
    "SELECT p.ID, p.post_title, m1.meta_value AS author, m2.meta_value AS license
     FROM {$wpdb->posts} p
     LEFT JOIN {$wpdb->postmeta} m1 ON m1.post_id = p.ID AND m1.meta_key = '_cn_tennis_wikimedia_author'
     LEFT JOIN {$wpdb->postmeta} m2 ON m2.post_id = p.ID AND m2.meta_key = '_cn_tennis_wikimedia_license'
     INNER JOIN {$wpdb->postmeta} m3 ON m3.post_id = p.ID AND m3.meta_key = '_cn_tennis_wikimedia_imported_at'
     ORDER BY p.ID DESC LIMIT 30"
);
?>
<table class="widefat striped">
    <thead><tr><th>Imagem</th><th>Autor</th><th>Licença</th></tr></thead>
    <tbody>
        <?php if (!$imported): ?><tr><td colspan="3">Nenhuma imagem importada do Wikimedia Commons ainda.</td></tr><?php endif; ?>
        <?php foreach ($imported as $row): ?>
            <tr>
                <td><?php echo wp_get_attachment_image((int) $row->ID, [60, 60]); ?> <?php echo esc_html($row->post_title); ?></td>
                <td><?php echo esc_html((string) $row->author); ?></td>
                <td><?php echo esc_html((string) $row->license); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
