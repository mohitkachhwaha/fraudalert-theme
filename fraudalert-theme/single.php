<?php
/**
 * Single Post Template
 * Displays individual post with sidebar, related posts, share buttons
 *
 * @package FraudAlert
 */

defined('ABSPATH') || exit;

get_header();
?>

<main class="single-post-wrap" id="main-content">
  <div class="single-layout">

    <!-- ==========================================
         MAIN CONTENT COLUMN
         ========================================== -->
    <?php while(have_posts()): the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class('single-article'); ?>>

      <!-- Breadcrumbs -->
      <?php fraudalert_breadcrumbs(); ?>

      <!-- Post Header -->
      <header class="post-header">
        <div class="post-cats">
          <?php
          $cats = get_the_category();
          foreach ($cats as $i => $cat) :
          ?>
            <a href="<?php echo esc_url(get_category_link($cat->term_id)); ?>"
               class="bdg bdg-r">
              <?php echo esc_html($cat->name); ?>
            </a>
          <?php endforeach; ?>
        </div>

        <h1 class="post-title"><?php the_title(); ?></h1>

        <div class="post-meta">
          <span class="post-meta-author">
            <span aria-hidden="true">✍️</span>
            <a href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))); ?>" style="text-decoration:none; color:inherit; font-weight:600;">
              <?php echo esc_html(get_the_author()); ?>
            </a>
          </span>
          <span>·</span>
          <time datetime="<?php echo esc_attr(get_the_date('c')); ?>">
            <?php echo get_the_date(); ?>
          </time>
          <?php if (get_the_modified_date() !== get_the_date()) : ?>
            <span>· Updated: <time datetime="<?php echo esc_attr(get_the_modified_date('c')); ?>"><?php echo get_the_modified_date(); ?></time></span>
          <?php endif; ?>
          <span>· <?php echo esc_html(get_the_reading_time()); ?> min read</span>
        </div>
      </header>

      <!-- Featured Image -->
      <?php if (has_post_thumbnail()) : ?>
        <div class="post-thumb">
          <?php the_post_thumbnail('fraudalert-hero', [
              'loading' => 'eager',
              'alt'     => esc_attr(get_the_title()),
          ]); ?>
        </div>
      <?php endif; ?>

      <!-- Ad Zone — Post Top -->
      <?php fraudalert_ad_zone('ad-post-top', 'ad-zone-post-top'); ?>

      <!-- Post Content -->
      <div class="post-content">
        <?php the_content(); ?>
      </div>

      <!-- Post Tags -->
      <?php
      $tags = get_the_tags();
      if ($tags) :
      ?>
      <div class="post-tags" aria-label="Post tags">
        <?php foreach ($tags as $tag) : ?>
          <a href="<?php echo esc_url(get_tag_link($tag->term_id)); ?>"
             class="post-tag">
            #<?php echo esc_html($tag->name); ?>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Share Buttons -->
      <?php fraudalert_share_buttons(); ?>

      <!-- WhatsApp Channel Join -->
      <?php fraudalert_whatsapp_channel_btn(); ?>

      <!-- Author Card -->
      <a href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))); ?>" class="author-card" style="display:flex; gap:1.25rem; align-items:center; background:var(--white); border:1.5px solid var(--border); border-radius:12px; padding:1.5rem; margin:2.5rem 0; text-decoration:none;">
        <div class="author-avatar" style="flex-shrink:0;">
          <?php echo get_avatar(get_the_author_meta('ID'), 80, '', '', ['style' => 'border-radius:50%;']); ?>
        </div>
        <div class="author-info">
          <div class="author-name" style="font-family:var(--fh); font-size:18px; font-weight:800; color:var(--navy); margin-bottom:.25rem;"><?php echo esc_html(get_the_author()); ?></div>
          <div class="author-bio" style="font-family:var(--fu); font-size:13.5px; color:var(--muted); line-height:1.6;"><?php echo get_the_author_meta('description') ?: 'Cyber Security & Fraud Awareness Expert at FraudAlert India. Stay safe, stay alert.'; ?></div>
        </div>
      </a>

      <!-- Related Posts for Engagement -->
      <div style="margin-top: 1rem; margin-bottom: 2.5rem;">
        <?php fraudalert_related_posts(3); ?>
      </div>

      <!-- ==========================================
           REPORT CTA
           ========================================== -->
      <section class="report-cta report-cta--mobile-only" aria-label="Report fraud call to action" style="margin-bottom: 2.5rem; border-radius: 12px; overflow: hidden;">
        <div class="report-inner">
          <div class="report-text">
            <h2>फ्रॉड या धोखाधड़ी होने पर तुरंत रिपोर्ट करें!</h2>
            <p>हर मिनट कीमती है। 72 घंटे के अंदर report करने पर refund की संभावना सबसे ज़्यादा रहती है。</p>
          </div>
          <div class="report-btns">
            <a href="tel:1930" class="btn-white">📞 1930 Call करें</a>
            <a href="https://cybercrime.gov.in" class="btn-outline-white" target="_blank" rel="noopener noreferrer">🌐 Online Complaint</a>
          </div>
        </div>
      </section>

      <!-- ==========================================
           RESOURCES GRID
           ========================================== -->
      <section class="resources" aria-labelledby="resources-heading" style="margin-top: 2.5rem; margin-bottom: 2.5rem; padding: 0;">
        <div class="section-inner" style="padding: 0;">
          <div class="sec-header" style="margin-bottom: 1.5rem;">
            <div>
              <div class="sec-title" id="resources-heading" style="font-family:var(--fh); font-size:20px; font-weight:800; color:var(--navy);">📚 Useful <span>Resources</span></div>
              <div class="sec-sub" style="font-family:var(--fu); font-size:13.5px; color:var(--muted);">Guides, tools, और official links — एक जगह पर</div>
            </div>
          </div>

          <div class="resources-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
            <?php
            $resources = [
                ['icon' => '🛡️', 'cat' => 'Official Portal',  'title' => 'Cyber Crime Reporting Portal',           'desc' => 'cybercrime.gov.in — Government of India का official fraud reporting portal।',           'link' => 'https://cybercrime.gov.in',               'cta' => 'Visit Portal →',  'ext' => true],
                ['icon' => '📋', 'cat' => 'Complete Guide',   'title' => 'Fraud के बाद — Complete Action Plan',   'desc' => 'Step-by-step guide: fraud के बाद पहले 24 घंटे में क्या करें।',                         'link' => home_url('/fraud-action-plan'),            'cta' => 'Guide पढ़ें →',  'ext' => false],
                ['icon' => '🏦', 'cat' => 'Bank Helplines',   'title' => 'सभी Banks के Fraud Helpline Numbers',   'desc' => 'SBI, HDFC, ICICI, Axis समेत सभी major banks के 24×7 fraud helpline numbers।',          'link' => home_url('/bank-helplines'),               'cta' => 'Numbers देखें →', 'ext' => false],
                ['icon' => '📱', 'cat' => 'App Safety',       'title' => 'Safe vs Unsafe App — कैसे पहचानें?',   'desc' => 'RBI registered loan apps की list, fake app के लक्षण, और safe app ढूंढने का तरीका।',     'link' => home_url('/app-safety-guide'),             'cta' => 'Check करें →',   'ext' => false],
                ['icon' => '📊', 'cat' => 'Awareness',        'title' => 'India Cyber Crime Report 2023',          'desc' => 'Latest statistics, most common scams, state-wise data — NCRB official data।',           'link' => home_url('/cyber-crime-report-2023'),     'cta' => 'Report देखें →', 'ext' => false],
                ['icon' => '🎯', 'cat' => 'Quiz',             'title' => 'Scam Awareness Quiz — Test Yourself',   'desc' => '10 सवालों का quiz — जांचें कि आप कितने scams पहचान सकते हैं।',                       'link' => home_url('/scam-quiz'),                   'cta' => 'Quiz लें →',     'ext' => false],
            ];
            foreach ($resources as $res) :
            ?>
            <a href="<?php echo esc_url($res['link']); ?>"
               class="res-card"
               <?php echo $res['ext'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
              <div class="res-icon" aria-hidden="true"><?php echo $res['icon']; ?></div>
              <div class="res-category"><?php echo esc_html($res['cat']); ?></div>
              <div class="res-title"><?php echo esc_html($res['title']); ?></div>
              <div class="res-desc"><?php echo esc_html($res['desc']); ?></div>
              <div class="res-arrow"><?php echo esc_html($res['cta']); ?></div>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <!-- Ad Zone — Post Bottom -->
      <?php fraudalert_ad_zone('ad-post-bottom', 'ad-zone-post-bottom'); ?>

    </article>
    <?php endwhile; ?>

    <!-- ==========================================
         SIDEBAR COLUMN
         ========================================== -->
    <aside class="alerts-sidebar" aria-label="Post sidebar">

      <!-- Sticky Helpline Widget -->
      <div class="helpline-widget" style="position:sticky;top:80px;">
        <div class="hw-icon" aria-hidden="true">📞</div>
        <div class="hw-num">1930</div>
        <div class="hw-label">National Cyber Crime Helpline</div>
        <div class="hw-sub">24×7 उपलब्ध — free call</div>
        <a href="tel:1930" class="hw-btn">अभी Call करें</a>
        <div class="hw-divider"></div>
        <a href="https://cybercrime.gov.in" class="hw-link" target="_blank" rel="noopener noreferrer">
          <span class="hw-link-text">🌐 Online Complaint दर्ज करें</span>
          <span class="hw-link-arr" aria-hidden="true">→</span>
        </a>
        <a href="<?php echo esc_url(home_url('/report-fraud')); ?>" class="hw-link">
          <span class="hw-link-text">🚨 Fraud Report Form</span>
          <span class="hw-link-arr" aria-hidden="true">→</span>
        </a>
      </div>

      <!-- Sidebar Ad -->
      <?php fraudalert_ad_zone('ad-sidebar'); ?>

      <!-- Recent Posts Widget -->
      <?php
      $recent = new WP_Query([
          'posts_per_page' => 5,
          'post_status'    => 'publish',
          'post__not_in'   => [get_the_ID()],
          'no_found_rows'  => true,
      ]);
      if ($recent->have_posts()) :
      ?>
      <div class="widget">
        <div class="widget-hd"><h3>🔥 Recent Alerts</h3></div>
        <div class="widget-body">
          <div class="alerts-list" style="gap:.4rem;">
            <?php while ($recent->have_posts()) : $recent->the_post(); ?>
              <a href="<?php the_permalink(); ?>" class="alert-item" style="padding:.6rem;">
                <div class="ai-content">
                  <div class="ai-title" style="font-size:12.5px;"><?php the_title(); ?></div>
                  <div class="ai-meta"><?php echo get_the_date(); ?></div>
                </div>
                <span class="ai-arrow" aria-hidden="true">›</span>
              </a>
            <?php endwhile; wp_reset_postdata(); ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </aside>

  </div><!-- /.single-layout -->
</main>

<?php get_footer(); ?>
