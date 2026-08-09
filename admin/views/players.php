<?php
defined('ABSPATH') || exit;

$editing_id = absint($_GET['edit'] ?? 0);
$editing = $editing_id ? CN_Tennis_Players::find($editing_id) : null;

if ($editing):
    $locked = (array) json_decode((string) $editing['manual_override'], true);
    ?>
    <h2>Editar jogador: <?php echo esc_html($editing['name']); ?></h2>
    <p class="description">Campos marcados como "travado" nunca são sobrescritos pela sincronização automática (override manual — seção 36) até você desmarcar.</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="cn-tennis-admin__form">
        <?php wp_nonce_field('cn_tennis_save_player'); ?>
        <input type="hidden" name="action" value="cn_tennis_save_player">
        <input type="hidden" name="player_id" value="<?php echo (int) $editing['id']; ?>">

        <table class="form-table">
            <?php
            $field = static function (string $key, string $label, string $type = 'text') use ($editing, $locked) {
                $value = esc_attr((string) ($editing[$key] ?? ''));
                echo '<tr><th><label for="cnt-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
                echo '<input type="' . esc_attr($type) . '" id="cnt-' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . $value . '" class="regular-text">';
                echo ' <label><input type="checkbox" name="manual_override[]" value="' . esc_attr($key) . '"' . checked(in_array($key, $locked, true), true, false) . '> travado</label>';
                echo '</td></tr>';
            };
            $field('name', 'Nome de exibição');
            $field('full_name', 'Nome completo');
            $field('country', 'País');
            $field('country_code', 'Código do país (3 letras, ex: BRA)');
            $field('birth_date', 'Nascimento', 'date');
            $field('height_cm', 'Altura (cm)', 'number');
            ?>
            <tr>
                <th><label for="cnt-plays">Mão dominante</label></th>
                <td>
                    <select id="cnt-plays" name="plays">
                        <option value="">—</option>
                        <option value="right" <?php selected($editing['plays'], 'right'); ?>>Destro</option>
                        <option value="left" <?php selected($editing['plays'], 'left'); ?>>Canhoto</option>
                    </select>
                    <label><input type="checkbox" name="manual_override[]" value="plays" <?php checked(in_array('plays', $locked, true), true); ?>> travado</label>
                </td>
            </tr>
            <tr>
                <th>Foto</th>
                <td>
                    <div class="cn-tennis-admin__photo-picker" data-cnt-photo-picker>
                        <div class="cn-tennis-admin__photo-preview">
                            <?php echo CN_Tennis_Images::render($editing, 'cn-tennis-avatar', 'name'); ?>
                        </div>
                        <input type="hidden" name="photo_attachment_id" value="<?php echo (int) $editing['photo_attachment_id']; ?>" data-cnt-photo-id>
                        <button type="button" class="button" data-cnt-photo-select>Selecionar da Biblioteca de Mídia</button>
                        <label><input type="checkbox" name="manual_override[]" value="photo_attachment_id" <?php checked(in_array('photo_attachment_id', $locked, true), true); ?>> travado</label>
                    </div>
                    <p class="description">
                        Posição focal (seção 31) — X: <input type="number" min="0" max="100" name="photo_focal_x" value="<?php echo (int) $editing['photo_focal_x']; ?>" style="width:70px"> %
                        Y: <input type="number" min="0" max="100" name="photo_focal_y" value="<?php echo (int) $editing['photo_focal_y']; ?>" style="width:70px"> %
                    </p>
                </td>
            </tr>
        </table>
        <p class="submit"><button type="submit" class="button button-primary">Salvar jogador</button> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=cn-tennis-players')); ?>">Cancelar</a></p>
    </form>

    <h3>Importar foto do Wikimedia Commons</h3>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('cn_tennis_wikimedia_import'); ?>
        <input type="hidden" name="action" value="cn_tennis_wikimedia_import">
        <input type="hidden" name="entity" value="player">
        <input type="hidden" name="entity_id" value="<?php echo (int) $editing['id']; ?>">
        <input type="text" name="file_title" class="regular-text" placeholder="File:Carlos Alcaraz 2023.jpg">
        <button type="submit" class="button">Importar</button>
        <p class="description">Aceita apenas licenças Creative Commons/domínio público reconhecidas automaticamente; o autor e a licença são gravados como créditos obrigatórios (seção 29).</p>
    </form>

<?php else: ?>
    <h2>Jogadores</h2>
    <form method="get" style="margin-bottom:12px;">
        <input type="hidden" name="page" value="cn-tennis-players">
        <input type="search" name="s" value="<?php echo esc_attr((string) ($_GET['s'] ?? '')); ?>" placeholder="Buscar jogador...">
        <select name="gender">
            <option value="">Todos os gêneros</option>
            <option value="male" <?php selected($_GET['gender'] ?? '', 'male'); ?>>Masculino</option>
            <option value="female" <?php selected($_GET['gender'] ?? '', 'female'); ?>>Feminino</option>
        </select>
        <button class="button">Filtrar</button>
    </form>
    <table class="widefat striped">
        <thead><tr><th>Ranking</th><th>Nome</th><th>País</th><th>Gênero</th><th>Provider</th><th></th></tr></thead>
        <tbody>
        <?php
        $rows = CN_Tennis_Players::query([
            'gender' => in_array($_GET['gender'] ?? '', ['male', 'female'], true) ? $_GET['gender'] : '',
            'search' => sanitize_text_field((string) ($_GET['s'] ?? '')),
            'limit' => 200,
        ]);
        if (!$rows): ?>
            <tr><td colspan="6">Nenhum jogador cadastrado ainda. Eles aparecem automaticamente após a primeira sincronização do ranking.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?php echo $row['current_rank_singles'] ? '#' . (int) $row['current_rank_singles'] : '—'; ?></td>
                <td><strong><?php echo esc_html($row['name']); ?></strong></td>
                <td><?php echo esc_html($row['country_code'] ?? ''); ?></td>
                <td><?php echo esc_html(CN_Tennis_Helpers::gender_label($row['gender'])); ?></td>
                <td><?php echo esc_html($row['provider']); ?></td>
                <td><a href="<?php echo esc_url(admin_url('admin.php?page=cn-tennis-players&edit=' . (int) $row['id'])); ?>">Editar</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
