<?php
/** @var WP_Query $query */
defined('ABSPATH') || exit;
if (!$query->have_posts()) {
    return;
}
?>
<section class="cnt-news" id="cnt-noticias" aria-labelledby="cnt-noticias-title">
    <h2 id="cnt-noticias-title">Últimas Notícias de Tênis</h2>
    <div class="cnt-news__grid">
        <?php while ($query->have_posts()): $query->the_post(); ?>
        <article class="cnt-news-card">
            <?php if (has_post_thumbnail()): ?>
                <a href="<?php the_permalink(); ?>" class="cnt-news-card__media">
                    <?php the_post_thumbnail('medium', ['loading' => 'lazy', 'class' => 'cn-tennis-image', 'alt' => get_the_title()]); ?>
                </a>
            <?php endif; ?>
            <div class="cnt-news-card__body">
                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time>
                <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 20)); ?></p>
            </div>
        </article>
        <?php endwhile; ?>
    </div>
</section>
