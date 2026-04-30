<?php
/**
 * Archive Template — Categories, Tags, Date Archives
 *
 * @package FraudAlert
 */

defined('ABSPATH') || exit;

get_header();
?>

<main class="archive-wrap" id="main-content">
  <div class="archive-layout">

    <!-- Main Column -->
    <div class="archive-main">

      <!-- Archive Header -->
      <div class="archive-header">
        <?php if (is_category()) : ?>
          <h1 class="archive-title">
            <span aria-hidden="true">🚨</span> <?php single_cat_title(); ?>
          </h1>
          <?php $desc = category_description(); if ($desc) : ?>
            <div class="archive-desc"><?php echo wp_kses_post($desc); ?></div>
          <?php endif; ?>

        <?php elseif (is_tag()) : ?>
          <h1 class="archive-title">
            Tag: #<?php single_tag_title(); ?>
          </h1>

        <?php elseif (is_date()) : ?>
          <h1 class="archive-title">
            📅 <?php echo get_the_date('F Y'); ?>
          </h1>

        <?php else : ?>
          <h1 class="archive-title">All Alerts &amp; Guides</h1>
        <?php endif; ?>
      </div>

      <!-- Post Cards Loop -->
      <?php if (have_posts()) : ?>
        <div class="post-card-grid">
          <?php while (have_posts()) : the_post(); ?>
            <?php
            $cats     = get_the_category();
            $cat_name = $cats ? $cats[0]->name : '';
            ?>
            <a href="<?php the_permalink(); ?>" class="post-card">
              <div class="post-card-thumb">
                <?php if (has_post_thumbnail()) : ?>
                  <?php the_post_thumbnail('fraudalert-archive', [
                      'loading' => 'lazy',
                      'alt'     => esc_attr(get_the_title()),
                  ]); ?>
                <?php endif; ?>
              </div>
              <div class="post-card-body">
                <?php if ($cat_name) : ?>
                  <div class="post-card-cat"><?php echo esc_html($cat_name); ?></div>
                <?php endif; ?>
                <div class="post-card-title"><?php the_title(); ?></div>
                <div class="post-card-excerpt">
                  <?php echo wp_trim_words(get_the_excerpt(), 18, '...'); ?>
                </div>
                <div class="post-card-meta">
                  <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                    <?php echo get_the_date(); ?>
                  </time>
                  <span>· <?php echo esc_html(get_the_author()); ?></span>
                </div>
              </div>
            </a>
          <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <div class="pagination" role="navigation" aria-label="Posts pagination">
          <?php
          echo paginate_links([
              'prev_text' => '‹ Prev',
              'next_text' => 'Next ›',
              'type'      => 'plain',
          ]);
          ?>
        </div>

      <?php else : ?>
        <div class="no-results">
          <div class="no-results-icon" aria-hidden="true">🔍</div>
          <h2>कोई post नहीं मिली</h2>
          <p>इस category में अभी कोई post नहीं है। जल्द ही content आएगा।</p>
          <a href="<?php echo esc_url(home_url('/')); ?>" class="btn-primary" style="margin-top:1rem;">
            होम पर जाएं
          </a>
        </div>
      <?php endif; ?>

    </div><!-- /.archive-main -->

    <!-- Sidebar -->
    <aside class="alerts-sidebar" aria-label="Archive sidebar">

      <div class="helpline-widget">
        <div class="hw-icon" aria-hidden="true">📞</div>
        <div class="hw-num">1930</div>
        <div class="hw-label">National Cyber Crime Helpline</div>
        <div class="hw-sub">24×7 उपलब्ध — free call</div>
        <a href="tel:1930" class="hw-btn">अभी Call करें</a>
      </div>

      <?php fraudalert_ad_zone('ad-sidebar'); ?>

      <!-- Categories Widget -->
      <?php
      $all_cats = get_categories(['hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 10]);
      if ($all_cats) :
      ?>
      <div class="widget">
        <div class="widget-hd"><h3>📂 Categories</h3></div>
        <div class="widget-body">
          <?php foreach ($all_cats as $cat) : ?>
            <div class="check-item">
              <a href="<?php echo esc_url(get_category_link($cat->term_id)); ?>"
                 style="font-family:var(--fu);font-size:13px;color:var(--navy);text-decoration:none;flex:1;">
                <?php echo esc_html($cat->name); ?>
              </a>
              <span class="bdg bdg-b"><?php echo absint($cat->count); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </aside>

  </div>
</main>

<?php get_footer(); ?>
