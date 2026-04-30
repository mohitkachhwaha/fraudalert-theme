<?php
/**
 * FraudAlert India — Performance Optimizations
 *
 * @package FraudAlert
 */

defined('ABSPATH') || exit;


/* =============================================================
   1. LAZY LOAD ALL IMAGES BY DEFAULT
   WordPress 5.5+ adds loading="lazy" but we enforce it explicitly
   ============================================================= */

add_filter('wp_lazy_loading_enabled', '__return_true');

// Add width + height attributes to prevent CLS (Cumulative Layout Shift)
add_filter('wp_get_attachment_image_attributes', function(array $attr, WP_Post $attachment): array {
    if (!isset($attr['loading'])) {
        $attr['loading'] = 'lazy';
    }
    return $attr;
}, 10, 2);


/* =============================================================
   2. DISABLE LAZY LOAD ON FIRST/HERO IMAGE (LCP Optimization)
   The first image should load eagerly for best LCP score
   ============================================================= */

add_filter('post_thumbnail_html', function(string $html, int $post_id, int $post_thumbnail_id, $size): string {
    // Only on single posts, make the hero image load eagerly
    if (is_singular() && is_main_query()) {
        $html = str_replace('loading="lazy"', 'loading="eager"', $html);
        // Add fetchpriority hint for LCP
        if (strpos($html, 'fetchpriority') === false) {
            $html = str_replace('<img ', '<img fetchpriority="high" ', $html);
        }
    }
    return $html;
}, 10, 4);


/* =============================================================
   3. ADD WEBP MIME TYPE SUPPORT
   ============================================================= */

add_filter('file_is_displayable_image', function(bool $result, string $path): bool {
    if (pathinfo($path, PATHINFO_EXTENSION) === 'webp') {
        return true;
    }
    return $result;
}, 10, 2);


/* =============================================================
   4. DEFER NON-CRITICAL SCRIPTS
   ============================================================= */

add_filter('script_loader_tag', function(string $tag, string $handle, string $src): string {
    // Scripts to defer (add your own as needed)
    $defer_scripts = [
        'fraudalert-main',
    ];

    if (in_array($handle, $defer_scripts, true)) {
        // Don't defer if already has async/defer
        if (strpos($tag, 'defer') === false && strpos($tag, 'async') === false) {
            $tag = str_replace(' src=', ' defer src=', $tag);
        }
    }
    return $tag;
}, 10, 3);


/* =============================================================
   5. PRELOAD CRITICAL FONTS
   Inlined in <head> so fonts load before CSS renders
   ============================================================= */

add_action('wp_head', function(): void {
    // Preload the most critical font variants
    $fonts = [
        'https://fonts.gstatic.com/s/baloo2/v21/wXKrE3kTposypRyd11_WAewrhXY.woff2',
        'https://fonts.gstatic.com/s/mukta/v14/iJWHBXyXfDDVXbnErmCB.woff2',
    ];
    foreach ($fonts as $font) {
        echo '<link rel="preload" href="' . esc_url($font) . '" as="font" type="font/woff2" crossorigin>' . "\n";
    }
}, 2);


/* =============================================================
   6. OUTPUT BUFFER COMPRESSION (if not handled by server)
   ============================================================= */

add_action('init', function(): void {
    if (!is_admin() && extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
        if (!headers_sent()) {
            ob_start('ob_gzhandler');
        }
    }
});


/* =============================================================
   7. REMOVE QUERY STRINGS FROM STATIC ASSETS
   Helps with Cloudflare caching — cleaner URLs
   ============================================================= */

add_filter('style_loader_src', 'fraudalert_remove_query_strings', 15);
add_filter('script_loader_src', 'fraudalert_remove_query_strings', 15);

function fraudalert_remove_query_strings(string $src): string {
    // Only remove ver= from our own assets, keep external CDN URLs intact
    if (strpos($src, get_template_directory_uri()) !== false) {
        $src = remove_query_arg('ver', $src);
    }
    return $src;
}


/* =============================================================
   8. HEARTBEAT API — LIMIT TO ADMIN ONLY
   WordPress heartbeat pings every 15-60s — wastes server resources on frontend
   ============================================================= */

add_filter('heartbeat_settings', function(array $settings): array {
    $settings['interval'] = 60; // 60 seconds (max)
    return $settings;
});

add_action('init', function(): void {
    if (!is_admin()) {
        wp_deregister_script('heartbeat');
    }
});


/* =============================================================
   9. LIMIT CRON EVENTS (Reduce background DB load)
   ============================================================= */

// Make sure WP_CRON isn't running on every page load
// Add to wp-config.php: define('DISABLE_WP_CRON', true);
// Then set up a real server cron: */5 * * * * php /path/to/wp-cron.php


/* =============================================================
   10. TRANSIENT CACHE HELPERS
   Use these when building dynamic homepage sections
   ============================================================= */

function fraudalert_get_cached(string $key, callable $callback, int $expiry = HOUR_IN_SECONDS) {
    $cached = get_transient($key);
    if ($cached !== false) {
        return $cached;
    }
    $result = $callback();
    set_transient($key, $result, $expiry);
    return $result;
}

// Clear relevant transients when a post is published/updated
add_action('save_post', function(int $post_id): void {
    if (wp_is_post_revision($post_id)) return;
    delete_transient('fraudalert_ticker_items');
    delete_transient('fraudalert_homepage_posts');
    
    // Clear ISR Cache folder when new post is out
    $cache_dir = get_theme_file_path('/cache');
    if (is_dir($cache_dir)) {
        array_map('unlink', glob("$cache_dir/*.*"));
    }
});


/* =============================================================
   11. ISR / NATIVE STATIC HTML CACHING (Next.js Style)
   Delivers ~50ms page load speeds for guest users by bypassing PHP/DB
   ============================================================= */

function fraudalert_isr_get_cache_path() {
    $url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    return get_theme_file_path('/cache/' . md5($url) . '.html');
}

// 1. Intercept Request, Serve Cache if exists
add_action('template_redirect', function() {
    if (is_user_logged_in() || $_SERVER['REQUEST_METHOD'] !== 'GET' || is_search()) return;
    
    $cache_file = fraudalert_isr_get_cache_path();
    $cache_time = 3600; // 1 Hour ISR Revalidation Time
    
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
        $html = file_get_contents($cache_file);
        echo $html . "\n<!-- ⚡ SSG/ISR Cache Hit (Served natively via FraudAlert) -->";
        exit;
    }
    
    // Start output buffering to capture the generated HTML
    ob_start();
}, 0);

// 2. Capture Request, Save to Cache file
add_action('shutdown', function() {
    if (is_user_logged_in() || $_SERVER['REQUEST_METHOD'] !== 'GET' || is_search()) return;
    
    $html = ob_get_clean();
    if ($html) {
        $cache_dir = get_theme_file_path('/cache');
        if (!file_exists($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
        $cache_file = fraudalert_isr_get_cache_path();
        file_put_contents($cache_file, $html);
        echo $html . "\n<!-- ⚡ SSG/ISR Cache Miss (Generated and Saved dynamically) -->";
    }
}, 9999);

