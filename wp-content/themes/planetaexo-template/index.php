<?php get_header(); ?>

<main class="main-content container">

    <?php if (have_posts()) : ?>
        <div class="posts-grid">
            <?php while (have_posts()) : the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('post-card'); ?>>
                    <?php if (has_post_thumbnail()) : ?>
                        <a class="post-card__thumb" href="<?php the_permalink(); ?>">
                            <?php the_post_thumbnail('medium_large'); ?>
                        </a>
                    <?php endif; ?>
                    <div class="post-card__body">
                        <h2 class="post-card__title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h2>
                        <div class="post-card__excerpt"><?php the_excerpt(); ?></div>
                        <a class="btn btn--primary" href="<?php the_permalink(); ?>">Leia mais</a>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>

        <div class="pagination">
            <?php the_posts_pagination(['mid_size' => 2]); ?>
        </div>

    <?php else : ?>
        <p class="no-content">Nenhum conteúdo encontrado.</p>
    <?php endif; ?>

</main>

<?php get_footer(); ?>
