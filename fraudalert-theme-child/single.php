<?php
/**
 * Single Post Template — Child Theme
 */
defined('ABSPATH') || exit;

get_header(); // This will use the child header.php
?>

<main class="single-post-wrap" id="main-content">
  <div class="single-layout">

    <?php while(have_posts()): the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class('single-article'); ?>>
      <?php fraudalert_breadcrumbs(); ?>

      <header class="post-header">
        <h1 class="post-title"><?php the_title(); ?></h1>
        <?php 
        $summary = get_the_excerpt();
        if (!empty($summary)) : 
        ?>
          <div class="post-excerpt" style="font-family:var(--fu, sans-serif); font-size:18px; color:var(--muted, #666); line-height:1.6; margin-top:0.5rem; margin-bottom:1.5rem;">
            <?php echo $summary; ?>
          </div>
        <?php endif; ?>
        <?php fraudalert_meta_info_box(); ?>
      </header>

      <?php if (has_post_thumbnail()) : ?>
        <div class="post-thumb"><?php the_post_thumbnail('fraudalert-hero', ['loading' => 'eager']); ?></div>
      <?php endif; ?>

      <!-- Ad Zone — Post Top -->
      <?php if(function_exists('fraudalert_ad_zone')) fraudalert_ad_zone('ad-post-top', 'ad-zone-post-top'); ?>

      <div class="post-content">
        <?php the_content(); ?>
      </div>

      <!-- Post Tags -->
      <?php
      $tags = get_the_tags();
      if ($tags) {
          echo '<div class="post-tags" style="margin: 2rem 0; display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center;"><strong>Tags:</strong>';
          foreach ($tags as $tag) {
              echo '<a href="' . esc_url(get_tag_link($tag->term_id)) . '" class="bdg bdg-tag" style="background:#eee; padding:4px 10px; border-radius:4px; text-decoration:none; color:#333; font-size:13px;">' . esc_html($tag->name) . '</a>';
          }
          echo '</div>';
      }
      ?>

      <!-- Ad Zone — Post Bottom -->
      <?php if(function_exists('fraudalert_ad_zone')) fraudalert_ad_zone('ad-post-bottom', 'ad-zone-post-bottom'); ?>

      <?php fraudalert_emergency_cta_banner(); ?>
      <?php fraudalert_whatsapp_channel_btn(); ?>
      <?php fraudalert_share_buttons(); ?>

      <div class="author-card" style="display:flex; gap:1rem; background:#f9f9f9; padding:1.5rem; border-radius:12px; margin:2rem 0;">
        <?php echo get_avatar(get_the_author_meta('ID'), 60); ?>
        <div>
          <div style="font-weight:bold;"><?php echo esc_html(get_the_author()); ?></div>
          <div style="font-size:13px; color:#666;"><?php echo get_the_author_meta('description'); ?></div>
        </div>
      </div>

      <?php fraudalert_related_posts(3); ?>
    </article>
    <?php endwhile; ?>

    <aside class="alerts-sidebar">
      <?php fraudalert_sidebar_helpline_widget(); ?>
      <div class="sticky-sidebar-ad" style="position:sticky; top:100px;">
        <?php 
        /**
         * Ad Manager Sidebar Hook
         */
        do_action('ps_ad_sidebar'); 
        ?>
        <?php if(function_exists('fraudalert_ad_zone')) fraudalert_ad_zone('ad-sidebar'); ?>
      </div>
    </aside>

  </div>
</main>

<?php get_footer(); ?>
