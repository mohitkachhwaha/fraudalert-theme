<?php
@ini_set('upload_max_filesize', '128M');
@ini_set('post_max_size', '128M');
@ini_set('memory_limit', '1024M');
@ini_set('max_execution_time', '300');
@set_time_limit(300);
/**
 * FraudAlert India — functions.php
 * @package FraudAlert
 * @version 1.0.0
 */
defined('ABSPATH') || exit;

require_once get_template_directory() . '/inc/security.php';
require_once get_template_directory() . '/inc/performance.php';

/* === BIG IMAGE SCALING FIX === */
add_filter('big_image_size_threshold', '__return_false');
add_filter('wp_image_editors', function() { return ['WP_Image_Editor_GD', 'WP_Image_Editor_Imagick']; });

// Completely bypass broken XAMPP image processors by NOT generating sub-sizes
add_filter('intermediate_image_sizes_advanced', '__return_empty_array');

/* === 1. THEME SETUP === */
function fraudalert_setup(): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form','comment-form','comment-list','gallery','caption','style','script']);
    add_filter('use_block_editor_for_post',      '__return_false', 10);
    add_filter('use_block_editor_for_post_type', '__return_false', 10);
    add_filter('use_widgets_block_editor',        '__return_false');
    register_nav_menus(['primary' => 'Primary Navigation', 'footer' => 'Footer Links']);
    add_image_size('fraudalert-hero',    800, 450, true);
    add_image_size('fraudalert-card',    400, 250, true);
    add_image_size('fraudalert-thumb',   120,  90, true);
    add_image_size('fraudalert-archive', 320, 200, true);
    load_theme_textdomain('fraudalert', get_template_directory() . '/languages');
}
add_action('after_setup_theme', 'fraudalert_setup');

/* === 2. ENQUEUE === */
function fraudalert_enqueue_assets(): void {
    $ver = wp_get_theme()->get('Version');
    wp_enqueue_style('fraudalert-style', get_template_directory_uri() . '/style.css', [], $ver);
    if (file_exists(get_template_directory() . '/assets/js/main.js')) {
        wp_enqueue_script('fraudalert-main', get_template_directory_uri() . '/assets/js/main.js', [], $ver, true);
    }
}
add_action('wp_enqueue_scripts', 'fraudalert_enqueue_assets');

/* === 3. REMOVE BLOAT === */
function fraudalert_remove_bloat(): void {
    wp_deregister_script('jquery');
    wp_deregister_script('jquery-core');
    wp_deregister_script('jquery-migrate');
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('global-styles');
    wp_dequeue_style('classic-theme-styles');
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');
    remove_action('wp_head', 'rest_output_link_wp_head', 10);
    remove_action('wp_head', 'feed_links_extra', 3);
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_shortlink_wp_head', 10);
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wp_resource_hints', 2);
    // Extra performance: Dequeue non-essential core assets
    if (!is_admin() && !is_user_logged_in()) {
        wp_dequeue_style('dashicons');
        wp_deregister_script('wp-embed');
    }
}
add_action('wp_enqueue_scripts', 'fraudalert_remove_bloat');

/* === 4. RESOURCE HINTS === */
function fraudalert_resource_hints(): void {
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
}
add_action('wp_head', 'fraudalert_resource_hints', 1);

/* === 5. CACHE HEADERS === */
function fraudalert_cache_headers(): void {
    if (is_admin() || is_user_logged_in()) return;
    if (is_search() || is_preview() || is_404()) { header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); header('Pragma: no-cache'); return; }
    if (is_front_page() || is_home() || is_archive() || is_category()) { header('Cache-Control: public, max-age=7200, s-maxage=43200'); return; }
    if (is_singular()) { header('Cache-Control: public, max-age=14400, s-maxage=86400'); return; }
    header('Cache-Control: public, max-age=3600, s-maxage=21600');
}
add_action('send_headers', 'fraudalert_cache_headers');

/* === 6. SECURITY HEADERS === */
function fraudalert_security_headers(): void {
    if (is_admin()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    header_remove('X-Powered-By');
    header_remove('Server');
}
add_action('send_headers', 'fraudalert_security_headers');

/* === 7. DISABLE XML-RPC === */
add_filter('xmlrpc_enabled', '__return_false');
add_filter('wp_headers', function(array $h): array { unset($h['X-Pingback']); return $h; });

/* === 8. RESTRICT REST API === */
add_filter('rest_authentication_errors', function($result) {
    if (true === $result || is_wp_error($result)) return $result;
    if (!is_user_logged_in()) return new WP_Error('rest_not_logged_in', 'REST API access restricted.', ['status' => 401]);
    return $result;
});

/* === 9. HIDE WP VERSION === */
add_filter('the_generator', '__return_empty_string');
add_filter('wp_generator',  '__return_empty_string');
function fraudalert_remove_version_query(string $src): string {
    if (strpos($src, '?ver=')) $src = remove_query_arg('ver', $src);
    return $src;
}
add_filter('style_loader_src',  'fraudalert_remove_version_query');
add_filter('script_loader_src', 'fraudalert_remove_version_query');

/* === 10. WIDGET AREAS === */
function fraudalert_register_widgets(): void {
    $zones = [
        ['Ad — Post Top (728x90)',    'ad-post-top'],
        ['Ad — Post Bottom (728x90)', 'ad-post-bottom'],
        ['Ad — Sidebar (300x250)',    'ad-sidebar'],
        ['Ad — Homepage Mid',         'ad-home-mid'],
        ['Ad — After Ticker',         'ad-after-ticker'],
        ['Sidebar — Main',            'sidebar-main'],
    ];
    foreach ($zones as [$name, $id]) {
        register_sidebar([
            'name'          => $name,
            'id'            => $id,
            'before_widget' => '<div id="%1$s" class="widget ad-zone %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
        ]);
    }
}
add_action('widgets_init', 'fraudalert_register_widgets');

/* === 11. MISC === */
add_filter('excerpt_length', fn() => 22, 999);
add_filter('excerpt_more',   fn() => '&hellip;');
if (!isset($content_width)) $content_width = 800;

function fraudalert_remove_metaboxes(): void {
    remove_meta_box('postcustom',       'post', 'normal');
    remove_meta_box('trackbacksdiv',    'post', 'normal');
    remove_meta_box('commentstatusdiv', 'post', 'normal');
}
add_action('admin_menu', 'fraudalert_remove_metaboxes');
add_filter('show_admin_bar', fn() => current_user_can('administrator'));

/* === 12. TEMPLATE HELPERS === */
function fraudalert_ad_zone(string $zone_id, string $extra_class = ''): void {
    if (is_active_sidebar($zone_id)) {
        echo '<div class="ad-zone ad-zone-' . esc_attr($zone_id) . ' ' . esc_attr($extra_class) . '">';
        dynamic_sidebar($zone_id);
        echo '</div>';
    }
}

function fraudalert_breadcrumbs(): void {
    if (function_exists('rank_math_the_breadcrumbs')) {
        echo '<nav class="breadcrumbs" aria-label="Breadcrumb">';
        rank_math_the_breadcrumbs();
        echo '</nav>';
        return;
    }
    if (is_front_page()) return;
    echo '<nav class="breadcrumbs" aria-label="Breadcrumb"><ol class="breadcrumb-list">';
    echo '<li><a href="' . esc_url(home_url('/')) . '">होम</a></li>';
    if (is_category() || is_single()) { $cats = get_the_category(); if ($cats) echo '<li><a href="' . esc_url(get_category_link($cats[0]->term_id)) . '">' . esc_html($cats[0]->name) . '</a></li>'; }
    if (is_single() || is_page()) echo '<li>' . esc_html(get_the_title()) . '</li>';
    elseif (is_category()) echo '<li>' . esc_html(single_cat_title('', false)) . '</li>';
    echo '</ol></nav>';
}

function fraudalert_share_buttons(?int $post_id = null): void {
    $post_id = $post_id ?? get_the_ID();
    $url     = urlencode(get_permalink($post_id));
    $title   = urlencode(html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8'));
    $raw_url = esc_attr(get_permalink($post_id));

    // Inline SVG icons (16x16, no external deps)
    $icons = [
        'whatsapp'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>',
        'telegram'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012 0 12 12 0 0011.944 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 01.171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
        'facebook'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
        'twitter'   => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
        'linkedin'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
        'copy'      => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>',
    ];

    echo '<div class="post-share" role="group" aria-label="Share this post">';
    echo '<span class="share-label">Share:</span>';
    echo '<div class="share-btns">';
    echo '<a href="https://wa.me/?text=' . $title . '%20' . $url . '" class="share-btn share-wa" target="_blank" rel="noopener noreferrer" aria-label="Share on WhatsApp">' . $icons['whatsapp'] . '<span>WhatsApp</span></a>';
    echo '<a href="https://t.me/share/url?url=' . $url . '&text=' . $title . '" class="share-btn share-tg" target="_blank" rel="noopener noreferrer" aria-label="Share on Telegram">' . $icons['telegram'] . '<span>Telegram</span></a>';
    echo '<a href="https://www.facebook.com/sharer/sharer.php?u=' . $url . '" class="share-btn share-fb" target="_blank" rel="noopener noreferrer" aria-label="Share on Facebook">' . $icons['facebook'] . '<span>Facebook</span></a>';
    echo '<a href="https://twitter.com/intent/tweet?text=' . $title . '&url=' . $url . '" class="share-btn share-tw" target="_blank" rel="noopener noreferrer" aria-label="Share on X">' . $icons['twitter'] . '<span>X</span></a>';
    echo '<a href="https://www.linkedin.com/sharing/share-offsite/?url=' . $url . '" class="share-btn share-li" target="_blank" rel="noopener noreferrer" aria-label="Share on LinkedIn">' . $icons['linkedin'] . '<span>LinkedIn</span></a>';
    echo '<button class="share-btn share-copy" data-url="' . $raw_url . '" aria-label="Copy link">' . $icons['copy'] . '<span>Copy Link</span></button>';
    echo '</div>';
    echo '</div>';

    // Lazy-initialized copy JS (only fires on click, no render blocking)
    echo '<script>document.addEventListener("click",function(e){var b=e.target.closest(".share-copy");if(b){e.preventDefault();var u=b.getAttribute("data-url");if(navigator.clipboard){navigator.clipboard.writeText(u).then(function(){b.querySelector("span").textContent="Copied!";setTimeout(function(){b.querySelector("span").textContent="Copy Link"},2000)})}else{var t=document.createElement("textarea");t.value=u;document.body.appendChild(t);t.select();document.execCommand("copy");document.body.removeChild(t);b.querySelector("span").textContent="Copied!";setTimeout(function(){b.querySelector("span").textContent="Copy Link"},2000)}}});</script>';
}

/* === Professional Horizontal Logo (SVG + Typography) === */
function fraudalert_logo(): void {
    $home_url = esc_url(home_url('/'));
    $site_name = get_bloginfo('name');
    ?>
    <div class="ssb-logo-container">
        <a href="<?php echo $home_url; ?>" class="ssb-logo-link" aria-label="<?php echo esc_attr($site_name); ?> — Home">
            <div class="ssb-icon-wrap">
                <svg viewBox="0 0 120 100" class="ssb-svg" xmlns="http://www.w3.org/2000/svg">
                    <!-- Modern Minimalist Shield -->
                    <path d="M60 5 L15 25 V50 C15 75 60 95 60 95 C60 95 105 75 105 50 V25 L60 5 Z" fill="var(--navy)" />
                    <path d="M35 50 L52 65 L85 35" fill="none" stroke="#fff" stroke-width="10" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>
            <div class="ssb-text-wrap">
                <span class="ssb-t-main">स्कैम <span class="ssb-t-red">से बचो</span></span>
            </div>
        </a>
    </div>
    <?php
}

/* === Add Social Fields to User Profile === */
function fraudalert_add_user_social_fields($methods) {
    $methods['job_title']   = 'Designation (e.g. Cyber Expert)';
    $methods['experience']  = 'Years of Experience';
    $methods['expertise']   = 'Expertise Areas (Expert in...)';
    $methods['is_verified'] = 'Verified Author? (yes/no)';
    $methods['twitter']     = 'X (Twitter) URL';
    $methods['facebook']    = 'Facebook URL';
    $methods['linkedin']    = 'LinkedIn URL';
    $methods['instagram']   = 'Instagram URL';
    $methods['youtube']      = 'YouTube Channel URL';
    return $methods;
}
add_filter('user_contactmethods', 'fraudalert_add_user_social_fields');

/* === WhatsApp Channel Join Button === */
function fraudalert_whatsapp_channel_btn(): void {
    // Check both parent option and child Customizer option
    $channel_url = get_option('fraudalert_wa_channel_url', '');
    if (empty($channel_url)) {
        $channel_url = get_theme_mod('fraudalert_whatsapp_channel_url', '');
    }
    if (empty($channel_url)) return;

    $wa_svg = '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';

    echo '<a href="' . esc_url($channel_url) . '" class="wa-channel-btn" target="_blank" rel="noopener noreferrer">';
    echo '<span class="wa-channel-icon">' . $wa_svg . '</span>';
    echo '<span class="wa-channel-text"><strong>🚨 स्कैमर्स से एक कदम आगे रहें!</strong><span>नए फ्रॉड अलर्ट्स और बचने के तरीके सीधे अपने WhatsApp पर पाएं। अभी जुड़ें 🤝</span></span>';
    echo '<span class="wa-channel-arrow">→</span>';
    echo '</a>';
}

/* === ADMIN: Theme Settings Page (WhatsApp Channel URL) === */
if (!function_exists('fraudalert_register_settings')) {
    function fraudalert_register_settings(): void {
        add_theme_page(
            'Theme Settings',
            'Theme Settings',
            'manage_options',
            'fraudalert-settings',
            'fraudalert_settings_page'
        );
    }
    add_action('admin_menu', 'fraudalert_register_settings');

    function fraudalert_settings_init(): void {
        register_setting('fraudalert_settings', 'fraudalert_wa_channel_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ]);
    }
    add_action('admin_init', 'fraudalert_settings_init');

    function fraudalert_settings_page(): void {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap">
            <h1>FraudAlert Theme Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('fraudalert_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="fraudalert_wa_channel_url">WhatsApp Channel URL</label></th>
                        <td>
                            <input type="url" id="fraudalert_wa_channel_url" name="fraudalert_wa_channel_url"
                                   value="<?php echo esc_attr(get_option('fraudalert_wa_channel_url', '')); ?>"
                                   class="regular-text" placeholder="https://whatsapp.com/channel/...">
                            <p class="description">Enter your WhatsApp Channel URL. Leave empty to hide the join button.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }
}


function fraudalert_related_posts(int $limit = 3): void {
    $categories = get_the_category();
    if (!$categories) return;

    $args = array(
        'category__in'   => array($categories[0]->term_id),
        'post__not_in'   => array(get_the_ID()),
        'posts_per_page' => $limit,
        'orderby'        => 'date', // Changed from rand to date for extreme speed
        'order'          => 'DESC',
        'no_found_rows'  => true,
        'ignore_sticky_posts' => true,
    );

    $related = new WP_Query($args);

    if ($related->have_posts()) :
        echo '<section class="related-posts-section">';
        echo '<h3 class="related-heading">ये भी पढ़ें — स्कैम से जुड़ी अन्य खबरें</h3>';
        echo '<div class="related-grid">';
        while ($related->have_posts()) : $related->the_post();
            $thumb = has_post_thumbnail() ? get_the_post_thumbnail(null, 'fraudalert-card', ['loading' => 'lazy', 'alt' => esc_attr(get_the_title())]) : '<div class="no-thumb-placeholder">🛡️</div>';
            ?>
            <article class="related-card">
                <a href="<?php the_permalink(); ?>" class="related-thumb-link"><?php echo $thumb; ?></a>
                <div class="related-content">
                    <div class="related-meta">
                        <span>📅 <?php echo get_the_date(); ?></span>
                        <span>👤 <?php the_author(); ?></span>
                    </div>
                    <h4 class="related-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
                    <p class="related-excerpt"><?php echo wp_trim_words(get_the_excerpt(), 18, '...'); ?></p>
                </div>
            </article>
            <?php
        endwhile;
        echo '</div></section>';
        wp_reset_postdata();
    endif;
}

function fraudalert_get_ticker_items(int $limit = 8): string {
    $cached = get_transient('fraudalert_ticker_items');
    if ($cached !== false) return $cached;

    $posts = get_posts(['numberposts' => $limit,'post_status' => 'publish','post_type' => 'post','no_found_rows' => true]);
    if (empty($posts)) return '';
    $html = '';
    foreach ($posts as $post) {
        $html .= '<span class="ticker-item"><span class="ticker-dot"></span><a href="' . esc_url(get_permalink($post->ID)) . '" style="color:inherit;text-decoration:none;">' . esc_html(wp_trim_words($post->post_title, 12, '...')) . '</a></span>';
    }
    $final_html = $html . $html;
    set_transient('fraudalert_ticker_items', $final_html, HOUR_IN_SECONDS);
    return $final_html;
}

function fraudalert_get_today_count(): int {
    $cached = get_transient('fraudalert_today_count');
    if ($cached !== false) return (int) $cached;

    $query = new WP_Query([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'date_query'     => [['after' => '24 hours ago']],
        'no_found_rows'  => false,
    ]);
    $count = (int) $query->found_posts;
    set_transient('fraudalert_today_count', $count, HOUR_IN_SECONDS);
    return $count;
}

function fraudalert_get_total_posts(): int {
    $cached = get_transient('fraudalert_total_posts');
    if ($cached !== false) return (int) $cached;

    $count = (int) wp_count_posts('post')->publish;
    set_transient('fraudalert_total_posts', $count, 12 * HOUR_IN_SECONDS);
    return $count;
}

function fraudalert_og_fallback(): void {
    if (function_exists('rank_math')) return;
    if (is_singular()) {
        global $post;
        $image = has_post_thumbnail($post->ID) ? get_the_post_thumbnail_url($post->ID, 'fraudalert-hero') : get_template_directory_uri() . '/assets/og-default.jpg';
        echo '<meta property="og:title" content="' . esc_attr(get_the_title()) . '">' . "\n";
        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink()) . '">' . "\n";
        echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    }
}
add_action('wp_head', 'fraudalert_og_fallback', 5);

/**
 * Simple reading time calculator
 */
if (!function_exists('get_the_reading_time')) {
    function get_the_reading_time(): int {
        $content   = get_post_field('post_content', get_the_ID());
        $word_count = str_word_count(strip_tags($content));
        return max(1, (int) ceil($word_count / 200));
    }
}
