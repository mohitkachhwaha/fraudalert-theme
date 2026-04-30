<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<?php
/**
 * wp_head() outputs:
 * - Title tag (via Rank Math or WordPress)
 * - Meta description (via Rank Math)
 * - Canonical URL (via Rank Math)
 * - Schema markup (via Rank Math)
 * - Enqueued styles and scripts
 */
wp_head();
?>

<?php /* Google Fonts — loaded after wp_head so preconnect fires first */ ?>
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@700;800&family=Noto+Sans:wght@400;600&family=Mukta:wght@400;600&display=swap" rel="stylesheet">

</head>

<body <?php body_class(); ?>>

<?php wp_body_open(); // Required for plugins like OneSignal ?>

<!-- ==========================================
     TOP BAR
     ========================================== -->
<div class="topbar" role="banner">
  <div class="topbar-inner">
    <div class="topbar-left">
      <div class="topbar-item">🚨 Cyber Crime Helpline: <strong>1930</strong></div>
      <div class="topbar-item">📧 Report: <strong>cybercrime.gov.in</strong></div>
      <?php
      $today_count = fraudalert_get_today_count();
      if ($today_count > 0) :
      ?>
      <div class="topbar-item">📅 <strong>आज के नए Alerts: <?php echo absint($today_count); ?></strong></div>
      <?php endif; ?>
    </div>
    <div class="topbar-right">
      <a href="<?php echo esc_url(home_url('/about')); ?>">हमारे बारे में</a>
      <div class="topbar-div"></div>
      <a href="<?php echo esc_url(home_url('/contact')); ?>">Contact</a>
    </div>
  </div>
</div>

<!-- ==========================================
     HEADER
     ========================================== -->
<header class="site-header" role="banner">
  <div class="header-inner">

    <!-- Logo -->
    <?php fraudalert_logo(); ?>

    <!-- Primary Navigation (desktop) -->
    <nav class="site-nav" role="navigation" aria-label="Primary menu">
      <?php
      wp_nav_menu([
          'theme_location' => 'primary',
          'container'      => false,
          'menu_class'     => '',
          'depth'          => 1,
          'fallback_cb'    => 'fraudalert_fallback_nav',
      ]);
      ?>
    </nav>

    <!-- Header Buttons -->
    <div class="header-btns">
      <!-- Desktop Search -->
      <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" class="header-search">
        <label for="head-s" class="sr-only">Search</label>
        <div class="search-input-wrap">
          <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
          <input type="text" id="head-s" name="s" placeholder="स्कैम या अलर्ट सर्च करें..." required>
        </div>
      </form>
      <!-- Mobile menu toggle -->
      <button
        class="menu-toggle"
        aria-controls="mobile-nav"
        aria-expanded="false"
        aria-label="Toggle navigation menu"
      >☰</button>
    </div>

  </div>

  <!-- Mobile Navigation Drawer -->
  <nav id="mobile-nav" class="mobile-nav" aria-label="Mobile menu" hidden>
    <div class="mobile-search">
      <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
        <label for="mob-s" class="sr-only">Search</label>
        <div class="search-input-wrap">
          <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
          <input type="text" id="mob-s" name="s" placeholder="अलर्ट सर्च करें..." required>
        </div>
      </form>
    </div>
    <?php
    wp_nav_menu([
        'theme_location' => 'primary',
        'container'      => false,
        'menu_class'     => '',
        'depth'          => 1,
        'fallback_cb'    => 'fraudalert_fallback_nav',
    ]);
    ?>
  </nav>
</header>

<?php

/**
 * Fallback navigation when no menu is assigned.
 * Shows basic links so site doesn't break.
 */
function fraudalert_fallback_nav(): void {
    echo '<ul>';
    echo '<li><a href="' . esc_url(home_url('/')) . '">होम</a></li>';
    echo '<li><a href="' . esc_url(home_url('/alerts')) . '">Latest Alerts</a></li>';
    echo '<li><a href="' . esc_url(home_url('/scam-types')) . '">Scam Types</a></li>';
    echo '<li><a href="' . esc_url(home_url('/guides')) . '">How-To Guides</a></li>';
    echo '<li><a href="' . esc_url(home_url('/faq')) . '">FAQ</a></li>';
    echo '</ul>';
}
