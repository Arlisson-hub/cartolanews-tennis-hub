<?php
defined('ABSPATH') || exit;

$editing_id = absint($_GET['edit'] ?? 0);
$editing = $editing_id ? CN_Tennis_Legends::find($editing_id) : [];
$is_new = !$editing_id;
?>
<h2><?php echo $is_new ? 'Nova lenda' : 'Editar lenda: ' . esc_html($editing['name']); ?></h2>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cn-tennis-admin__form">
    <?php wp_nonce_field('cn_tennis_save_legend'); ?>
    <input type="hidden" name="action" value="cn_tennis_save_legend">
    <input type="hidden" name="legend_id" value="<?php echo (int) $editing_id; ?>">
    <table class="form-table">
        <tr><th><label>Nome</label></th><td><input required type="text" name="name" value="<?php echo esc_attr($editing['name'] ?? ''); ?>" class="regular-text"></td></tr>
        <tr><th><label>País</label></th><td><input type="text" name="country" value="<?php echo esc_attr($editing['country'] ?? ''); ?>"> <input type="text" maxlength="3" placeholder="BRA" name="country_code" value="<?php echo esc_attr($editing['country_code'] ?? ''); ?>" style="width:70px"></td></tr>
        <tr><th><label>Nascimento</label></th><td><input type="date" name="birth_date" value="<?php echo esc_attr($editing['birth_date'] ?? ''); ?>"></td></tr>
        <tr><th><label>Falecimento (se aplicável)</label></th><td><input type="date" name="death_date" value="<?php echo esc_attr($editing['death_date'] ?? ''); ?>"></td></tr>
        <tr><th><label>Período profissional</label></th><td>De <input type="number" name="pro_period_start" value="<?php echo esc_attr((string) ($editing['pro_period_start'] ?? '')); ?>" style="width:90px"> até <input type="number" name="pro_period_end" value="<?php echo esc_attr((string) ($editing['pro_period_end'] ?? '')); ?>" style="width:90px" placeholder="deixe vazio se ativo"></td></tr>
        <tr><th><label>Grand Slams</label></th><td><input type="number" name="grand_slams_count" value="<?php echo esc_attr((string) ($editing['grand_slams_count'] ?? '')); ?>" style="width:90px"></td></tr>
        <tr><th><label>Títulos</label></th><td><input type="number" name="titles_count" value="<?php echo esc_attr((string) ($editing['titles_count'] ?? '')); ?>" style="width:90px"></td></tr>
        <tr><th><label>Melhor ranking</label></th><td>#<input type="number" name="best_rank" value="<?php echo esc_attr((string) ($editing['best_rank'] ?? '')); ?>" style="width:90px"> em <input type="date" name="best_rank_date" value="<?php echo esc_attr($editing['best_rank_date'] ?? ''); ?>"></td></tr>
        <tr><th><label>Superfície de maior destaque</label></th><td>
            <select name="best_surface">
                <option value="">—</option>
                <?php foreach (['hard' => 'Quadra dura', 'clay' => 'Saibro', 'grass' => 'Grama', 'indoor' => 'Indoor'] as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($editing['best_surface'] ?? '', $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </td></tr>
        <tr><th><label>Descrição</label></th><td><textarea name="description" rows="4" class="large-text"><?php echo esc_textarea($editing['description'] ?? ''); ?></textarea></td></tr>
        <tr><th><label>Foto (Biblioteca de Mídia)</label></th><td>
            <div class="cn-tennis-admin__photo-picker" data-cnt-photo-picker>
                <div class="cn-tennis-admin__photo-preview"><?php echo CN_Tennis_Images::render($editing, 'cn-tennis-avatar', 'name'); ?></div>
                <input type="hidden" name="photo_attachment_id" value="<?php echo (int) ($editing['photo_attachment_id'] ?? 0); ?>" data-cnt-photo-id>
                <button type="button" class="button" data-cnt-photo-select>Selecionar da Biblioteca de Mídia</button>
            </div>
        </td></tr>
        <tr><th><label>Créditos da imagem</label></th><td>
            Autor: <input type="text" name="photo_credit_author" value="<?php echo esc_attr($editing['photo_credit_author'] ?? ''); ?>"><br>
            Licença: <input type="text" name="photo_credit_license" value="<?php echo esc_attr($editing['photo_credit_license'] ?? ''); ?>"> URL: <input type="url" name="photo_credit_license_url" value="<?php echo esc_attr($editing['photo_credit_license_url'] ?? ''); ?>" class="regular-text"><br>
            Página de origem: <input type="url" name="photo_credit_source_url" value="<?php echo esc_attr($editing['photo_credit_source_url'] ?? ''); ?>" class="regular-text">
        </td></tr>
        <tr><th><label>Fonte dos dados</label></th><td>
            <input type="text" name="source" value="<?php echo esc_attr($editing['source'] ?? ''); ?>" placeholder="Ex.: ATP Tour, Wikipédia"> <input type="url" name="source_url" value="<?php echo esc_attr($editing['source_url'] ?? ''); ?>" class="regular-text" placeholder="URL da fonte">
            <p class="description">Não preencha estatísticas sem indicar a fonte (seção 11).</p>
        </td></tr>
        <tr><th><label>Status</label></th><td>
            <select name="status">
                <option value="published" <?php selected($editing['status'] ?? 'draft', 'published'); ?>>Publicado</option>
                <option value="draft" <?php selected($editing['status'] ?? 'draft', 'draft'); ?>>Rascunho</option>
            </select>
            Ordem: <input type="number" name="sort_order" value="<?php echo esc_attr((string) ($editing['sort_order'] ?? 0)); ?>" style="width:80px">
        </td></tr>
    </table>
    <p class="submit"><button type="submit" class="button button-primary">Salvar lenda</button></p>
</form>

<?php if (!$is_new): ?>
    <h3>Importar foto do Wikimedia Commons</h3>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('cn_tennis_wikimedia_import'); ?>
        <input type="hidden" name="action" value="cn_tennis_wikimedia_import">
        <input type="hidden" name="entity" value="legend">
        <input type="hidden" name="entity_id" value="<?php echo (int) $editing_id; ?>">
        <input type="text" name="file_title" class="regular-text" placeholder="File:Roger Federer 2019.jpg">
        <button type="submit" class="button">Importar</button>
    </form>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Remover esta lenda definitivamente?');" style="margin-top:10px;">
        <?php wp_nonce_field('cn_tennis_delete_legend'); ?>
        <input type="hidden" name="action" value="cn_tennis_delete_legend">
        <input type="hidden" name="legend_id" value="<?php echo (int) $editing_id; ?>">
        <button type="submit" class="button button-link-delete">Excluir lenda</button>
    </form>
<?php endif; ?>

<hr>
<h2>Todas as lendas</h2>
<table class="widefat striped">
    <thead><tr><th>Nome</th><th>País</th><th>Grand Slams</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php $all = CN_Tennis_Legends::all(''); ?>
    <?php if (!$all): ?><tr><td colspan="5">Nenhuma lenda cadastrada ainda.</td></tr><?php endif; ?>
    <?php foreach ($all as $legend): ?>
        <tr>
            <td><strong><?php echo esc_html($legend['name']); ?></strong></td>
            <td><?php echo esc_html($legend['country_code'] ?? ''); ?></td>
            <td><?php echo $legend['grand_slams_count'] !== null ? (int) $legend['grand_slams_count'] : '—'; ?></td>
            <td><?php echo esc_html($legend['status']); ?></td>
            <td><a href="<?php echo esc_url(admin_url('admin.php?page=cn-tennis-legends&edit=' . (int) $legend['id'])); ?>">Editar</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
