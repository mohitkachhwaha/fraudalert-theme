<?php
/**
 * Static Page Template
 *
 * @package FraudAlert
 */

defined('ABSPATH') || exit;

get_header();
?>

<main class="single-post-wrap" id="main-content">
  <div class="section-inner" style="padding-top:2rem;padding-bottom:3rem;">

    <?php while (have_posts()) : the_post(); ?>

      <article id="page-<?php the_ID(); ?>" <?php post_class(); ?>>
        <header class="post-header">
          <h1 class="post-title"><?php the_title(); ?></h1>
        </header>

        <?php if (has_post_thumbnail()) : ?>
          <div class="post-thumb">
            <?php the_post_thumbnail('fraudalert-hero', ['loading' => 'eager', 'alt' => esc_attr(get_the_title())]); ?>
          </div>
        <?php endif; ?>

        <div class="post-content">
          <?php the_content(); ?>
        </div>
      </article>

    <?php endwhile; ?>

  </div>
</main>

<?php get_footer(); ?>
