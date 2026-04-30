<?php
/**
 * Archive Template — Child Theme
 */
defined('ABSPATH') || exit;

get_header(); // This will use the child header.php with ps_ad_after_header hook
?>

<main class="archive-wrap" id="main-content">
  <div class="archive-layout">

    <div class="archive-main">
      <div class="archive-header">
        <?php if (is_category()) : ?>
          <h1 class="archive-title">
            <span aria-hidden="true">🚨</span> <?php single_cat_title(); ?>
          </h1>
          <?php $desc = category_description(); if ($desc) : ?>
            <div class="archive-desc"><?php echo wp_kses_post($desc); ?></div>
          <?php endif; ?>
        <?php elseif (is_tag()) : ?>
          <h1 class="archive-title">Tag: #<?php single_tag_title(); ?></h1>
        <?php elseif (is_date()) : ?>
          <h1 class="archive-title">📅 <?php echo get_the_date('F Y'); ?></h1>
        <?php else : ?>
          <h1 class="archive-title">All Alerts &amp; Guides</h1>
        <?php endif; ?>

        <?php 
        /**
         * Ad Manager Archive Header Hook
         */
        do_action('ps_ad_archive_header'); 
        ?>
      </div>

      <?php if (have_posts()) : ?>
        <div class="post-card-grid">
          <?php while (have_posts()) : the_post(); ?>
            <a href="<?php the_permalink(); ?>" class="post-card">
              <div class="post-card-thumb">
                <?php if (has_post_thumbnail()) : the_post_thumbnail('fraudalert-archive', ['loading' => 'lazy', 'alt' => esc_attr(get_the_title())]); endif; ?>
              </div>
              <div class="post-card-body">
                <div class="post-card-title"><?php the_title(); ?></div>
                <div class="post-card-excerpt"><?php echo wp_trim_words(get_the_excerpt(), 18, '...'); ?></div>
                <div class="post-card-meta">
                  <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo get_the_date(); ?></time>
                  <span>· <?php echo esc_html(get_the_author()); ?></span>
                </div>
              </div>
            </a>
          <?php endwhile; ?>
        </div>
        <div class="pagination"><?php echo paginate_links(['prev_text' => '‹ Prev', 'next_text' => 'Next ›']); ?></div>
      <?php endif; ?>
    </div>

    <aside class="alerts-sidebar">
      <?php 
      /**
       * Ad Manager Sidebar Hook
       */
      do_action('ps_ad_sidebar'); 
      ?>
      
      <div class="helpline-widget">
        <div class="hw-icon">📞</div>
        <div class="hw-num">1930</div>
        <a href="tel:1930" class="hw-btn">अभी Call करें</a>
      </div>
      <?php fraudalert_ad_zone('ad-sidebar'); ?>
    </aside>

  </div>
</main>

<?php get_footer(); ?>
