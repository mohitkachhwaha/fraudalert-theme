<?php
/**
 * 404 Error Page Template
 *
 * @package FraudAlert
 */

defined('ABSPATH') || exit;

get_header();
?>

<main class="error-404-wrap" id="main-content">
  <div class="error-inner">

    <div class="error-code" aria-hidden="true">404</div>
    <h1 class="error-title">Page नहीं मिला</h1>
    <p class="error-desc">
      आप जो page ढूंढ रहे हैं वह exist नहीं करता, या move हो गया है।<br>
      Homepage पर जाएं या search करें।
    </p>

    <!-- Search form on 404 -->
    <div class="search-form-wrap" style="justify-content:center;margin-bottom:1.5rem;">
      <?php echo get_search_form(false); ?>
    </div>

    <div class="error-links">
      <a href="<?php echo esc_url(home_url('/')); ?>" class="btn-primary">🏠 होम पर जाएं</a>
      <a href="<?php echo esc_url(home_url('/alerts')); ?>" class="btn-secondary">🚨 Latest Alerts</a>
    </div>

    <!-- Quick links to popular sections -->
    <div style="margin-top:2rem;padding-top:2rem;border-top:1px solid var(--border);">
      <p style="font-family:var(--fu);font-size:13px;color:var(--muted);margin-bottom:.75rem;">Popular Pages:</p>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;justify-content:center;">
        <a href="<?php echo esc_url(home_url('/scam-types')); ?>" class="post-tag">Scam Types</a>
        <a href="<?php echo esc_url(home_url('/guides')); ?>" class="post-tag">How-To Guides</a>
        <a href="<?php echo esc_url(home_url('/faq')); ?>" class="post-tag">FAQ</a>
        <a href="<?php echo esc_url(home_url('/report-fraud')); ?>" class="post-tag">Report Fraud</a>
        <a href="tel:1930" class="post-tag">📞 1930 Helpline</a>
      </div>
    </div>

  </div>
</main>

<?php get_footer(); ?>
