<?php
/**
 * Template da central completa [cn_tenis_hub]. Incluído a partir de
 * CN_Tennis_Shortcodes::hub(), por isso $this é a instância de Shortcodes e
 * $a contém os atributos já processados por shortcode_atts().
 */
defined('ABSPATH') || exit;
?>
<div class="cnt-hub">
    <?php if ($a['mostrar_hero'] !== 'nao') echo $this->render_hero(); ?>

    <?php if ($a['mostrar_resumo'] !== 'nao') echo $this->render_summary(); ?>

    <?php if ($a['mostrar_ranking'] !== 'nao'): ?>
        <?php echo $this->render_surface_filter(); ?>
        <?php echo $this->ranking(['sexo' => 'ambos', 'limite' => 20]); ?>
    <?php endif; ?>

    <?php if ($a['mostrar_brasileiros'] !== 'nao') echo $this->brazilians(); ?>

    <?php if ($a['mostrar_power_ranking'] !== 'nao'): ?>
        <section class="cnt-power-ranking-grid" id="cnt-power-ranking" aria-labelledby="cnt-power-title">
            <h2 id="cnt-power-title">Melhores do Momento CartolaNews</h2>
            <p class="cnt-note">Calculado a partir de partidas finalizadas reais. Ver metodologia completa em «Como calculamos».</p>
            <div class="cnt-power-ranking-grid__cols">
                <?php echo $this->power_ranking(['sexo' => 'masculino', 'titulo' => 'sim']); ?>
                <?php echo $this->power_ranking(['sexo' => 'feminino', 'titulo' => 'sim']); ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($a['mostrar_lendas'] !== 'nao') echo $this->legends(); ?>

    <?php if ($a['mostrar_ao_vivo'] !== 'nao') echo $this->live(); ?>

    <?php if ($a['mostrar_calendario'] !== 'nao') echo $this->calendar(); ?>

    <?php if ($a['mostrar_superficies'] !== 'nao') echo $this->surfaces(); ?>

    <?php if ($a['mostrar_grand_slams'] !== 'nao'): ?>
        <?php echo $this->render_grand_slams(); ?>
    <?php endif; ?>

    <?php if ($a['mostrar_noticias'] !== 'nao') echo $this->render_news(); ?>
</div>
