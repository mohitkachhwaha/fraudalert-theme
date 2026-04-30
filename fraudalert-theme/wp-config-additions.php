<?php
/**
 * FraudAlert India — wp-config.php additions
 *
 * IMPORTANT: DO NOT include this file directly.
 * Copy these constants into your wp-config.php
 * ABOVE the line: "That's all, stop editing!"
 *
 * @package FraudAlert
 */

// ============================================
// SECURITY
// ============================================

// Disable theme/plugin file editing from admin
define('DISALLOW_FILE_EDIT', true);

// Disable plugin/theme install on production
// Uncomment after setup is complete:
// define('DISALLOW_FILE_MODS', true);

// Force HTTPS for admin panel
define('FORCE_SSL_ADMIN', true);

// Disable debug on production
define('WP_DEBUG',         false);
define('WP_DEBUG_LOG',     false);
define('WP_DEBUG_DISPLAY', false);

// ============================================
// PERFORMANCE
// ============================================

// Limit post revisions (saves database space)
define('WP_POST_REVISIONS', 3);

// Increase autosave interval (default is 60s — too frequent)
define('AUTOSAVE_INTERVAL', 300); // 5 minutes

// Trash auto-empty (30 days default is fine)
define('EMPTY_TRASH_DAYS', 30);

// Disable WP Cron on page load — use real server cron instead
// After setting this, add to server crontab:
// */5 * * * * php /var/www/html/wp-cron.php > /dev/null 2>&1
define('DISABLE_WP_CRON', true);

// Memory limit (adjust based on your hosting)
define('WP_MEMORY_LIMIT',       '256M');
define('WP_MAX_MEMORY_LIMIT',   '512M');

// ============================================
// CLOUDFLARE COMPATIBILITY
// ============================================

// Fix WordPress detecting wrong URL when behind Cloudflare proxy
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// Fix home/siteurl for Cloudflare
// Only needed if WordPress detects wrong protocol:
// define('WP_HOME',    'https://fraudalertindia.com');
// define('WP_SITEURL', 'https://fraudalertindia.com');

// ============================================
// TABLE PREFIX — CHANGE FROM DEFAULT 'wp_'
// (Do this BEFORE installation)
// ============================================
// $table_prefix = 'fa_'; // Example: 'fa_' instead of 'wp_'
