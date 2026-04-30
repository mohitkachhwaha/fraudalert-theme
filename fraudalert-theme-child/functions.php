<?php
defined('ABSPATH') || exit;

/*
 * ── EARLY CACHE SERVE ──────────────────────────────────────
 * Runs at theme-load time, before init / template hooks.
 * If a valid static HTML file exists for this request, serve it and exit.
 */
(function (): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') return;
    if (isset($_GET['s']) || isset($_GET['preview'])) return;
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    if (defined('DOING_CRON') && DOING_CRON) return;

    foreach (array_keys($_COOKIE) as $k) {
        if (str_starts_with($k, 'wordpress_logged_in_')) return;
    }

    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '';
    if (preg_match('#/wp-(admin|login|cron|json)(/|$)#', $path)) return;

    $rel = preg_replace('/[^a-zA-Z0-9\/_-]/', '', trim($path, '/'));
    if ($rel === '') $rel = '_home';

    $ua  = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    $dev = preg_match('/mobile|android(?!.*tablet)|iphone|ipod|blackberry|opera mini|iemobile/', $ua)
        ? 'mobile' : 'desktop';

    $file = WP_CONTENT_DIR . '/cache/fraudalert/' . $rel . '/' . $dev . '.html';
    if (!is_file($file)) return;

    $mt = filemtime($file);
    if ((time() - $mt) > 86400) return;

    $etag = '"fa-' . md5($file . $mt) . '"';

    if (
        (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) ||
        (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mt)
    ) {
        http_response_code(304);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: public, max-age=3600, s-maxage=86400, stale-while-revalidate=3600');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mt) . ' GMT');
    header('X-Cache: HIT');
    readfile($file);
    exit;
})();

/* ── ENQUEUE PARENT + CHILD STYLES ──────────────────────────── */

add_action('wp_enqueue_scripts', function (): void {
    $parent_handle = 'fraudalert-style';

    if (!wp_style_is($parent_handle, 'enqueued')) {
        wp_enqueue_style(
            $parent_handle,
            get_parent_theme_file_uri('/style.css'),
            [],
            wp_get_theme(get_template())->get('Version')
        );
    }

    wp_enqueue_style(
        'fraudalert-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        [$parent_handle],
        wp_get_theme()->get('Version')
    );
}, 20);

/* ── DISABLE GUTENBERG + STRIP BLOCK CSS ────────────────────── */

add_filter('use_block_editor_for_post', '__return_false', 999);
add_filter('use_block_editor_for_post_type', '__return_false', 999);
add_filter('use_widgets_block_editor', '__return_false', 999);

add_action('admin_init', function (): void {
    remove_action('try_gutenberg_panel', 'wp_try_gutenberg_panel');
}, 999);

add_action('wp_enqueue_scripts', function (): void {
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('global-styles');
    wp_dequeue_style('classic-theme-styles');
}, 999);

/* ── STATIC HTML CACHE ENGINE ───────────────────────────────── */

final class FraudAlert_HTML_Cache {

    private const DIR = WP_CONTENT_DIR . '/cache/fraudalert';
    private const TTL = 86400;

    public static function init(): void {
        add_action('template_redirect', [self::class, 'start_buffer'], 0);

        add_action('save_post',             [self::class, 'on_save_post'], 10, 2);
        add_action('wp_trash_post',         [self::class, 'on_delete_post']);
        add_action('delete_post',           [self::class, 'on_delete_post']);
        add_action('comment_post',          [self::class, 'on_new_comment'], 10, 2);
        add_action('wp_set_comment_status', [self::class, 'on_comment_status']);
        add_action('switch_theme',          [self::class, 'purge_all']);
        add_action('customize_save_after',  [self::class, 'purge_all']);
        add_action('wp_update_nav_menu',    [self::class, 'purge_all']);
        add_action('created_term',          [self::class, 'purge_all']);
        add_action('edited_term',           [self::class, 'purge_all']);
        add_action('delete_term',           [self::class, 'purge_all']);

        self::protect_dir();
    }

    /* ── Output buffering / generation ────────────────────── */

    public static function start_buffer(): void {
        if (
            is_user_logged_in() || is_search() || is_404() || is_feed() ||
            is_preview() || is_customize_preview() ||
            (defined('DOING_AJAX') && DOING_AJAX) ||
            (defined('REST_REQUEST') && REST_REQUEST) ||
            $_SERVER['REQUEST_METHOD'] !== 'GET'
        ) return;

        ob_start([self::class, 'capture']);
    }

    public static function capture(string $html): string {
        if (strlen($html) < 255 || http_response_code() !== 200) return $html;

        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $html = str_replace("\0", '', $html);
        $html = preg_replace('/<\?(php|=)/i', '&lt;?$1', $html);

        $file = self::cache_path();
        if (!$file) return $html;

        wp_mkdir_p(dirname($file));

        $tmp = $file . '.' . getmypid() . '.tmp';
        $stamped = $html . "\n<!-- cached " . gmdate('c') . " -->\n";

        if (file_put_contents($tmp, $stamped, LOCK_EX) !== false && rename($tmp, $file)) {
            $mt = filemtime($file);
            header('ETag: "fa-' . md5($file . $mt) . '"');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mt) . ' GMT');
        } else {
            @unlink($tmp);
        }

        header('Cache-Control: public, max-age=3600, s-maxage=86400, stale-while-revalidate=3600');
        header('X-Cache: MISS');

        return $html;
    }

    /* ── Path / device helpers ────────────────────────────── */

    private static function cache_path(): ?string {
        $p = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '', '/');
        $p = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $p);
        return self::DIR . '/' . ($p ?: '_home') . '/' . self::device() . '.html';
    }

    private static function device(): string {
        $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        return preg_match('/mobile|android(?!.*tablet)|iphone|ipod|blackberry|opera mini|iemobile/', $ua)
            ? 'mobile' : 'desktop';
    }

    /* ── Purge triggers ───────────────────────────────────── */

    public static function on_save_post(int $id, \WP_Post $post): void {
        if (wp_is_post_revision($id) || wp_is_post_autosave($id)) return;
        if (!in_array($post->post_status, ['publish', 'trash', 'private'], true)) return;
        self::flush_post($post);
    }

    public static function on_delete_post(int $id): void {
        $post = get_post($id);
        if ($post) self::flush_post($post);
    }

    public static function on_new_comment(int $id, $approved): void {
        if ($approved == 1) self::flush_comment($id);
    }

    public static function on_comment_status(int $id): void {
        self::flush_comment($id);
    }

    private static function flush_comment(int $cid): void {
        $c = get_comment($cid);
        if ($c && $c->comment_post_ID) {
            self::drop_url(get_permalink((int) $c->comment_post_ID));
        }
    }

    private static function flush_post(\WP_Post $post): void {
        self::drop_url(get_permalink($post));
        self::drop_url(home_url('/'));

        foreach (wp_get_post_categories($post->ID) as $cat) {
            self::drop_url(get_category_link($cat));
        }
        foreach (wp_get_post_tags($post->ID) ?: [] as $tag) {
            self::drop_url(get_tag_link($tag->term_id));
        }
        self::drop_url(get_author_posts_url($post->post_author));
    }

    private static function drop_url(string $url): void {
        $p   = trim(parse_url($url, PHP_URL_PATH) ?: '', '/');
        $p   = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $p);
        $dir = self::DIR . '/' . ($p ?: '_home');

        if (is_dir($dir)) {
            foreach (glob($dir . '/*.html') ?: [] as $f) @unlink($f);
            @rmdir($dir);
        }

        wp_remote_get($url, [
            'timeout'   => 0.01,
            'blocking'  => false,
            'sslverify' => false,
            'cookies'   => [],
        ]);
    }

    public static function purge_all(): void {
        self::rrmdir(self::DIR);
        self::protect_dir();
        wp_remote_get(home_url('/'), [
            'timeout'   => 0.01,
            'blocking'  => false,
            'sslverify' => false,
            'cookies'   => [],
        ]);
    }

    /* ── Cache directory security ─────────────────────────── */

    public static function protect_dir(): void {
        if (!is_dir(self::DIR)) wp_mkdir_p(self::DIR);

        if (!is_file(self::DIR . '/.htaccess')) {
            file_put_contents(self::DIR . '/.htaccess', <<<'HTACCESS'
Options -Indexes -ExecCGI
<IfModule mod_php.c>
  php_flag engine off
</IfModule>
<IfModule mod_php8.c>
  php_flag engine off
</IfModule>
<Files "*.php">
  Require all denied
</Files>
<FilesMatch "\.(html)$">
  Require all granted
</FilesMatch>
HTACCESS);
        }

        if (!is_file(self::DIR . '/index.php')) {
            file_put_contents(self::DIR . '/index.php', '<?php // Silence.');
        }
    }

    /* ── Utilities ────────────────────────────────────────── */

    private static function rrmdir(string $dir): void {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
    }
}

FraudAlert_HTML_Cache::init();

/* ── PERFORMANCE: CRITICAL CSS + DEFER JS ───────────────────── */

add_action('wp_head', function (): void {
    $f = get_stylesheet_directory() . '/critical.css';
    if (is_file($f) && filesize($f) < 50000) {
        echo '<style id="critical-css">' . file_get_contents($f) . "</style>\n";
    }
}, 1);

add_filter('style_loader_tag', function (string $tag, string $handle): string {
    if (is_admin()) return $tag;
    static $has_critical = null;
    $has_critical ??= is_file(get_stylesheet_directory() . '/critical.css');
    if (!$has_critical) return $tag;
    $deferred = str_replace("media='all'", "media='print' onload=\"this.media='all'\"", $tag);
    return $deferred . '<noscript>' . $tag . '</noscript>';
}, 10, 2);

add_filter('script_loader_tag', function (string $tag, string $handle): string {
    if (is_admin() || str_contains($tag, 'defer') || str_contains($tag, 'async')) return $tag;
    return str_replace(' src=', ' defer src=', $tag);
}, 10, 2);


/* ── IMAGE SIZES: GOOGLE DISCOVER + RESPONSIVE ─────────────── */

add_action('after_setup_theme', function (): void {
    add_theme_support('post-thumbnails');
    add_image_size('fraudalert-discover', 1280, 720, true);
    add_image_size('fraudalert-lg', 1024, 576, true);
    add_image_size('fraudalert-md', 768, 432, true);
    add_image_size('fraudalert-sm', 480, 270, true);
}, 20);

/* ── SOCIAL SETTINGS & UTILITIES ─────────────────────────── */

add_action('customize_register', function ($wp_customize) {
    $wp_customize->add_section('fraudalert_social_settings', [
        'title'    => 'Social & Messaging',
        'priority' => 30,
    ]);

    $wp_customize->add_setting('fraudalert_whatsapp_channel_url', [
        'default'           => '',
        'public'            => true,
        'sanitize_callback' => 'esc_url_raw',
    ]);

    $wp_customize->add_control('fraudalert_whatsapp_channel_url', [
        'label'    => 'WhatsApp Channel URL',
        'section'  => 'fraudalert_social_settings',
        'type'     => 'url',
        'description' => 'URL of your official WhatsApp Channel for the Join button in single posts.',
    ]);
});

add_action('wp_enqueue_scripts', function (): void {
    if (is_singular('post')) {
        wp_enqueue_script(
            'fraudalert-share-js',
            get_stylesheet_directory_uri() . '/assets/js/share.js',
            [],
            '1.0.0',
            true
        );
    }
}, 20);

/* Share buttons handled by parent theme's fraudalert_share_buttons() */

/* ── GOOGLE DISCOVER / SEO META TAGS ───────────────────────── */

add_action('after_setup_theme', function (): void {
    remove_action('wp_head', 'fraudalert_og_fallback', 5);
}, 99);

add_action('wp_head', function (): void {
    // Google Discover: max-image-preview:large for all pages
    echo '<meta name="robots" content="max-image-preview:large">' . "\n";

    // Stop if a known SEO plugin is active
    if (defined('WPSEO_VERSION') || function_exists('rank_math') || class_exists('AIOSEO_WP')) {
        return;
    }

    /* --- Canonical Tags --- */
    $canonical_url = '';
    if (!is_404() && !is_search()) {
        if (is_front_page() || is_home()) {
            $canonical_url = home_url('/');
        } elseif (is_singular()) {
            $canonical_url = get_permalink();
        } elseif (is_category()) {
            $canonical_url = get_category_link(get_queried_object_id());
        } elseif (is_tag()) {
            $canonical_url = get_tag_link(get_queried_object_id());
        } elseif (is_author()) {
            $canonical_url = get_author_posts_url(get_queried_object_id());
        } elseif (is_archive() && function_exists('get_post_type_archive_link')) {
            $canonical_url = get_post_type_archive_link(get_post_type());
        }
        
        if (is_paged()) {
            $canonical_url = get_pagenum_link(get_query_var('paged'));
        }
        
        if ($canonical_url) {
            echo '<link rel="canonical" href="' . esc_url($canonical_url) . '">' . "\n";
        }
    }

    /* --- Open Graph & Twitter Cards --- */
    global $post;
    
    $opts = get_option('fraudalert_settings', []);
    $site_name = !empty($opts['org_name']) ? $opts['org_name'] : get_bloginfo('name');
    
    $og_url   = $canonical_url ?: home_url($_SERVER['REQUEST_URI'] ?? '/');
    $og_type  = is_singular('post') ? 'article' : 'website';
    
    $og_title = '';
    $og_desc  = '';
    
    if (is_singular()) {
        $og_title = get_the_title();
        $og_desc  = has_excerpt() ? get_the_excerpt() : wp_trim_words(wp_strip_all_tags($post->post_content), 20);
    } elseif (is_category() || is_tag() || is_tax()) {
        $og_title = single_term_title('', false);
        $og_desc  = strip_tags(term_description());
    } elseif (is_author()) {
        $og_title = get_the_author_meta('display_name', get_queried_object_id());
        $og_desc  = get_the_author_meta('description', get_queried_object_id());
    } else {
        $og_title = get_bloginfo('name');
        $og_desc  = get_bloginfo('description');
    }
    
    if (!$og_title) $og_title = get_bloginfo('name');
    if (!$og_desc) $og_desc = get_bloginfo('description');
    
    echo '<meta name="description" content="' . esc_attr($og_desc) . '">' . "\n";
    
    $img_url = !empty($opts['default_og_image']) ? $opts['default_og_image'] : get_template_directory_uri() . '/assets/og-default.jpg';
    $img_w   = 1280;
    $img_h   = 720;
    
    if (is_singular() && has_post_thumbnail($post->ID)) {
        $src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'fraudalert-discover');
        if ($src && $src[1] >= 1200) {
            $img_url = $src[0];
            $img_w   = $src[1];
            $img_h   = $src[2];
        }
    }
    
    echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
    echo '<meta property="og:locale" content="' . esc_attr(get_locale()) . '">' . "\n";
    echo '<meta property="og:type" content="' . esc_attr($og_type) . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($og_title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($og_desc) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($og_url) . '">' . "\n";
    echo '<meta property="og:image" content="' . esc_url($img_url) . '">' . "\n";
    echo '<meta property="og:image:width" content="' . (int) $img_w . '">' . "\n";
    echo '<meta property="og:image:height" content="' . (int) $img_h . '">' . "\n";
    
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($og_title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($og_desc) . '">' . "\n";
    echo '<meta name="twitter:image" content="' . esc_url($img_url) . '">' . "\n";
    
    if (is_singular('post')) {
        echo '<meta property="article:published_time" content="' . esc_attr(get_the_date('c', $post)) . '">' . "\n";
        echo '<meta property="article:modified_time" content="' . esc_attr(get_the_modified_date('c', $post)) . '">' . "\n";
        
        $author = get_the_author_meta('display_name', $post->post_author);
        if ($author) {
            echo '<meta property="article:author" content="' . esc_attr($author) . '">' . "\n";
        }
        
        $cats = get_the_category($post->ID);
        if (!empty($cats)) {
            echo '<meta property="article:section" content="' . esc_attr($cats[0]->name) . '">' . "\n";
        }
    }
}, 5);

/* ── AUTO-FILL ALT TEXT ────────────────────────────────────── */

add_filter('wp_get_attachment_image_attributes', function (array $attr, \WP_Post $attachment): array {
    if (!empty($attr['alt'])) return $attr;
    $parent = $attachment->post_parent ? get_the_title($attachment->post_parent) : '';
    $attr['alt'] = $parent ?: $attachment->post_title;
    return $attr;
}, 10, 2);

/* ── WEBP AUTO-CONVERSION ON UPLOAD ────────────────────────── */

add_filter('wp_generate_attachment_metadata', function (array $metadata, int $id): array {
    $file = get_attached_file($id);
    if (!$file) return $metadata;

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) return $metadata;

    $use_imagick = extension_loaded('imagick') && class_exists('Imagick');
    $use_gd      = !$use_imagick && function_exists('imagewebp');
    if (!$use_imagick && !$use_gd) return $metadata;

    $dir = dirname($file);
    $convert = function (string $path) use ($use_imagick): void {
        if (!is_file($path)) return;
        $webp = $path . '.webp';
        if (is_file($webp)) return;

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($use_imagick) {
            try {
                $im = new \Imagick($path);
                $im->setImageFormat('webp');
                $im->setImageCompressionQuality(80);
                $im->writeImage($webp);
                $im->destroy();
            } catch (\Exception $e) {
                @unlink($webp);
            }
            return;
        }

        $img = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png'         => @imagecreatefrompng($path),
            default       => false,
        };
        if (!$img) return;

        if ($ext === 'png') {
            imagepalettetotruecolor($img);
            imagealphablending($img, true);
            imagesavealpha($img, true);
        }

        if (!@imagewebp($img, $webp, 80)) {
            @unlink($webp);
        }
        imagedestroy($img);
    };

    $convert($file);

    if (!empty($metadata['sizes'])) {
        foreach ($metadata['sizes'] as $size) {
            $convert($dir . '/' . $size['file']);
        }
    }

    return $metadata;
}, 10, 2);

/* ── WEBP .HTACCESS RULES ON THEME ACTIVATION ─────────────── */

add_action('after_switch_theme', function (): void {
    if (!function_exists('insert_with_markers')) {
        require_once ABSPATH . 'wp-admin/includes/misc.php';
    }

    $htaccess = ABSPATH . '.htaccess';
    if (!is_writable($htaccess)) return;

    $rules = [
        '<IfModule mod_rewrite.c>',
        '  RewriteEngine On',
        '  RewriteCond %{HTTP_ACCEPT} image/webp',
        '  RewriteCond %{REQUEST_FILENAME} \.(jpe?g|png)$',
        '  RewriteCond %{REQUEST_FILENAME}.webp -f',
        '  RewriteRule ^(.+)\.(jpe?g|png)$ $1.$2.webp [T=image/webp,L]',
        '</IfModule>',
        '<IfModule mod_headers.c>',
        '  <FilesMatch "\.(jpe?g|png)$">',
        '    Header append Vary Accept',
        '  </FilesMatch>',
        '</IfModule>',
    ];

    insert_with_markers($htaccess, 'FraudAlert WebP', $rules);
});

/* ── ADMIN: FEATURED IMAGE SIZE WARNING ────────────────────── */

add_action('admin_notices', function (): void {
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post') return;

    global $post;
    if (!$post || !has_post_thumbnail($post->ID)) return;

    $meta = wp_get_attachment_metadata(get_post_thumbnail_id($post->ID));
    if (!$meta || ($meta['width'] >= 1280 && $meta['height'] >= 720)) return;

    echo '<div class="notice notice-warning is-dismissible"><p>';
    echo '<strong>Featured image is too small.</strong> ';
    echo 'Google Discover requires at least <strong>1280&times;720px</strong>. ';
    echo 'Current size: ' . (int) $meta['width'] . '&times;' . (int) $meta['height'] . 'px.';
    echo '</p></div>';
});

/* ── STRUCTURED DATA: JSON-LD SCHEMA ───────────────────────── */

/* ── POST SUMMARY META BOX ────────────────────────────────── */

add_action('add_meta_boxes', function (): void {
    add_meta_box(
        'fraudalert_post_summary',
        'Post Summary',
        function (\WP_Post $post): void {
            wp_nonce_field('fraudalert_summary_save', 'fraudalert_summary_nonce');
            $value = get_post_meta($post->ID, '_fraudalert_summary', true);
            echo '<label class="screen-reader-text" for="fraudalert-summary">Post Summary</label>';
            echo '<textarea id="fraudalert-summary" name="fraudalert_summary" rows="4" style="width:100%;">'
                . esc_textarea($value) . '</textarea>';
            echo '<p class="description">Brief summary shown above the featured image and used in Article schema. Plain text only.</p>';
        },
        'post',
        'normal',
        'high'
    );
});

add_action('save_post', function (int $post_id): void {
    if (!isset($_POST['fraudalert_summary_nonce'])) return;
    if (!wp_verify_nonce($_POST['fraudalert_summary_nonce'], 'fraudalert_summary_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['fraudalert_summary'])) {
        update_post_meta($post_id, '_fraudalert_summary', sanitize_textarea_field($_POST['fraudalert_summary']));
    }
});

add_action('wp_head', function (): void {
    if (function_exists('rank_math') || defined('WPSEO_VERSION')) return;

    $site_name = get_bloginfo('name');
    $site_url  = home_url('/');

    $logo = null;
    $logo_id = get_theme_mod('custom_logo');
    if ($logo_id) {
        $src = wp_get_attachment_image_src($logo_id, 'full');
        if ($src) {
            $logo = ['@type' => 'ImageObject', 'url' => $src[0], 'width' => $src[1], 'height' => $src[2]];
        }
    }

    $publisher = ['@type' => 'Organization', 'name' => $site_name];
    if ($logo) $publisher['logo'] = $logo;

    $emit = function (array $data): void {
        $data = ['@context' => 'https://schema.org'] + $data;
        echo '<script type="application/ld+json">'
            . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . "</script>\n";
    };

    /* ── Organization (all pages) */
    $org = ['@type' => 'Organization', 'name' => $site_name, 'url' => $site_url];
    if ($logo) $org['logo'] = $logo;
    $org['contactPoint'] = [
        '@type'       => 'ContactPoint',
        'telephone'   => '1930',
        'contactType' => 'customer service',
    ];
    $social = apply_filters('fraudalert_schema_social_urls', []);
    if ($social) $org['sameAs'] = $social;
    $emit($org);

    /* ── WebSite (homepage) */
    if (is_front_page()) {
        $emit([
            '@type' => 'WebSite',
            'name'  => $site_name,
            'url'   => $site_url,
            'potentialAction' => [
                '@type'  => 'SearchAction',
                'target' => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $site_url . '?s={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ]);
    }

    /* ── BreadcrumbList (all non-front pages) */
    $crumbs = [['name' => $site_name, 'url' => $site_url]];

    if (is_singular()) {
        global $post;
        if ($post->post_type === 'post') {
            $cats = get_the_category($post->ID);
            if ($cats) {
                $primary = $cats[0];
                foreach (array_reverse(get_ancestors($primary->term_id, 'category')) as $anc_id) {
                    $anc = get_category($anc_id);
                    if ($anc) $crumbs[] = ['name' => $anc->name, 'url' => get_category_link($anc_id)];
                }
                $crumbs[] = ['name' => $primary->name, 'url' => get_category_link($primary->term_id)];
            }
        } elseif ($post->post_parent) {
            foreach (array_reverse(get_post_ancestors($post->ID)) as $anc_id) {
                $crumbs[] = ['name' => get_the_title($anc_id), 'url' => get_permalink($anc_id)];
            }
        }
        $crumbs[] = ['name' => get_the_title(), 'url' => get_permalink()];
    } elseif (is_category()) {
        $cat = get_queried_object();
        foreach (array_reverse(get_ancestors($cat->term_id, 'category')) as $anc_id) {
            $anc = get_category($anc_id);
            if ($anc) $crumbs[] = ['name' => $anc->name, 'url' => get_category_link($anc_id)];
        }
        $crumbs[] = ['name' => $cat->name, 'url' => get_category_link($cat->term_id)];
    } elseif (is_tag()) {
        $tag = get_queried_object();
        $crumbs[] = ['name' => $tag->name, 'url' => get_tag_link($tag->term_id)];
    } elseif (is_author()) {
        $crumbs[] = [
            'name' => get_the_author_meta('display_name', get_queried_object_id()),
            'url'  => get_author_posts_url(get_queried_object_id()),
        ];
    } elseif (is_year()) {
        $crumbs[] = ['name' => get_the_date('Y'), 'url' => get_year_link(get_query_var('year'))];
    } elseif (is_month()) {
        $y = get_query_var('year');
        $crumbs[] = ['name' => (string) $y, 'url' => get_year_link($y)];
        $crumbs[] = ['name' => get_the_date('F Y'), 'url' => get_month_link($y, get_query_var('monthnum'))];
    } elseif (is_day()) {
        $y = get_query_var('year');
        $m = get_query_var('monthnum');
        $crumbs[] = ['name' => (string) $y, 'url' => get_year_link($y)];
        $crumbs[] = ['name' => get_the_date('F Y'), 'url' => get_month_link($y, $m)];
        $crumbs[] = ['name' => get_the_date(), 'url' => get_day_link($y, $m, get_query_var('day'))];
    }

    if (count($crumbs) > 1) {
        $items = [];
        foreach ($crumbs as $i => $crumb) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $crumb['name'],
                'item'     => $crumb['url'],
            ];
        }
        $emit(['@type' => 'BreadcrumbList', 'itemListElement' => $items]);
    }

    /* ── Article (single posts) */
    if (is_singular('post')) {
        global $post;

        $desc = has_excerpt($post->ID)
            ? wp_strip_all_tags(get_the_excerpt($post->ID))
            : wp_trim_words(wp_strip_all_tags($post->post_content), 30);

        $article = [
            '@type'            => 'Article',
            'headline'         => get_the_title(),
            'datePublished'    => get_the_date('c', $post),
            'dateModified'     => get_the_modified_date('c', $post),
            'author'           => [
                '@type' => 'Person',
                'name'  => get_the_author_meta('display_name', $post->post_author),
                'url'   => get_author_posts_url($post->post_author),
            ],
            'publisher'        => $publisher,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => get_permalink()],
        ];

        if ($desc) $article['description'] = $desc;

        if (has_post_thumbnail($post->ID)) {
            $src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'fraudalert-discover');
            if ($src) {
                $article['image'] = ['@type' => 'ImageObject', 'url' => $src[0], 'width' => $src[1], 'height' => $src[2]];
            }
        }

        $emit($article);
    }

    /* ── Person / Author (author archive pages) */
    if (is_author()) {
        $aid = get_queried_object_id();

        $person = [
            '@type' => 'Person',
            'name'  => get_the_author_meta('display_name', $aid),
            'url'   => get_author_posts_url($aid),
        ];

        $bio = get_the_author_meta('description', $aid);
        if ($bio) $person['description'] = $bio;

        $job = get_the_author_meta('job_title', $aid);
        if ($job) $person['jobTitle'] = $job;

        $knows = apply_filters('fraudalert_schema_author_knows', [], $aid);
        if ($knows) $person['knowsAbout'] = $knows;

        $author_social = [];
        foreach (['twitter', 'facebook', 'instagram', 'linkedin', 'youtube'] as $net) {
            $url = get_the_author_meta($net, $aid);
            if ($url) $author_social[] = $url;
        }
        $author_social = apply_filters('fraudalert_schema_author_social', $author_social, $aid);
        if ($author_social) $person['sameAs'] = $author_social;

        $emit($person);
    }
}, 2);


/* =============================================================
   FRAUDALERT THEME SETTINGS PAGE
   Location: Appearance > FraudAlert Theme Settings
   ============================================================= */

add_action('admin_menu', function() {
    add_theme_page(
        'FraudAlert Theme Settings',
        'FraudAlert Settings',
        'manage_options',
        'fraudalert-child-settings',    // unique slug — avoids collision with parent 'fraudalert-settings'
        'fraudalert_theme_settings_page'
    );
});

add_action('admin_init', function() {
    register_setting('fraudalert_settings_group', 'fraudalert_settings');

    // Section 1: General
    add_settings_section('fraudalert_general_section', 'General Settings', null, 'fraudalert-child-settings');
    add_settings_field('site_disclaimer', 'Site Disclaimer Text', 'fraudalert_field_editor_cb', 'fraudalert-child-settings', 'fraudalert_general_section', ['id' => 'site_disclaimer']);
    add_settings_field('footer_disclaimer', 'Footer Disclaimer', 'fraudalert_field_textarea_cb', 'fraudalert-child-settings', 'fraudalert_general_section', ['id' => 'footer_disclaimer']);

    // Section 2: Social & Contact
    add_settings_section('fraudalert_social_section', 'Social & Contact', null, 'fraudalert-child-settings');
    add_settings_field('whatsapp_url', 'WhatsApp Channel URL', 'fraudalert_field_text_cb', 'fraudalert-child-settings', 'fraudalert_social_section', ['id' => 'whatsapp_url']);
    add_settings_field('telegram_url', 'Telegram Channel URL', 'fraudalert_field_text_cb', 'fraudalert-child-settings', 'fraudalert_social_section', ['id' => 'telegram_url']);
    add_settings_field('fb_url', 'Facebook Profile URL', 'fraudalert_field_text_cb', 'fraudalert-child-settings', 'fraudalert_social_section', ['id' => 'fb_url']);
    add_settings_field('tw_url', 'X (Twitter) URL', 'fraudalert_field_text_cb', 'fraudalert-child-settings', 'fraudalert_social_section', ['id' => 'tw_url']);
    add_settings_field('ig_url', 'Instagram URL', 'fraudalert_field_text_cb', 'fraudalert-child-settings', 'fraudalert_social_section', ['id' => 'ig_url']);
    add_settings_field('yt_url', 'YouTube Channel URL', 'fraudalert_field_text_cb', 'fraudalert-child-settings', 'fraudalert_social_section', ['id' => 'yt_url']);

    // Section 3: Link Settings
    add_settings_section('fraudalert_links_section', 'Link & CTA Settings', null, 'fraudalert-child-settings');
    add_settings_field('report_fraud_url', 'Report Fraud Page URL', 'fraudalert_field_text_cb', 'fraudalert-child-settings', 'fraudalert_links_section', ['id' => 'report_fraud_url']);
    add_settings_field('verify_site_url', 'Verify Website Page URL', 'fraudalert_field_text_cb', 'fraudalert-child-settings', 'fraudalert_links_section', ['id' => 'verify_site_url']);
    add_settings_field('cta_label', 'CTA Button Label', 'fraudalert_field_text_cb', 'fraudalert-child-settings', 'fraudalert_links_section', ['id' => 'cta_label']);
    add_settings_field('cta_url', 'CTA Button URL', 'fraudalert_field_text_cb', 'fraudalert-child-settings', 'fraudalert_links_section', ['id' => 'cta_url']);

    // Section: Disclaimers
    add_settings_section('fa_section_disclaimers', 'Disclaimers Settings', null, 'fraudalert-child-settings');
    add_settings_field('disc_post_top', 'Show in Posts (Top)', 'fa_render_toggle', 'fraudalert-child-settings', 'fa_section_disclaimers', ['name' => 'disc_post_top']);
    add_settings_field('disc_post_bottom', 'Show in Posts (Bottom)', 'fa_render_toggle', 'fraudalert-child-settings', 'fa_section_disclaimers', ['name' => 'disc_post_bottom']);

    // Section 4: Google Discover
    add_settings_section('fraudalert_discover_section', 'Google Discover & SEO', null, 'fraudalert-child-settings');
    add_settings_field('publisher_logo', 'Publisher Logo URL (600x60px)', 'fraudalert_field_text_cb', 'fraudalert-child-settings', 'fraudalert_discover_section', ['id' => 'publisher_logo']);
    add_settings_field('org_name', 'Organization Name', 'fraudalert_field_text_cb', 'fraudalert-child-settings', 'fraudalert_discover_section', ['id' => 'org_name']);
    add_settings_field('default_og_image', 'Default OG Image URL', 'fraudalert_field_text_cb', 'fraudalert-child-settings', 'fraudalert_discover_section', ['id' => 'default_og_image']);

    // Section 5: Disclaimers & Compliance
    add_settings_section('fraudalert_disclaimers_section', 'Disclaimers & Compliance', null, 'fraudalert-child-settings');
    add_settings_field('disc_general', 'General Disclaimer', 'fraudalert_field_editor_cb', 'fraudalert-child-settings', 'fraudalert_disclaimers_section', ['id' => 'disc_general']);
    add_settings_field('disc_affiliate', 'Affiliate/Referral Disclaimer', 'fraudalert_field_editor_cb', 'fraudalert-child-settings', 'fraudalert_disclaimers_section', ['id' => 'disc_affiliate']);
    add_settings_field('disc_legal', 'Legal (IT Act 2000) Disclaimer', 'fraudalert_field_editor_cb', 'fraudalert-child-settings', 'fraudalert_disclaimers_section', ['id' => 'disc_legal']);
    add_settings_field('disc_report', 'Report Fraud Disclaimer', 'fraudalert_field_editor_cb', 'fraudalert-child-settings', 'fraudalert_disclaimers_section', ['id' => 'disc_report']);
    
    // Toggles
    add_settings_field('show_disc_posts', 'Show on Posts', 'fraudalert_field_checkbox_cb', 'fraudalert-child-settings', 'fraudalert_disclaimers_section', ['id' => 'show_disc_posts']);
    add_settings_field('show_disc_pages', 'Show on Pages', 'fraudalert_field_checkbox_cb', 'fraudalert-child-settings', 'fraudalert_disclaimers_section', ['id' => 'show_disc_pages']);
    add_settings_field('show_disc_archives', 'Show on Archives', 'fraudalert_field_checkbox_cb', 'fraudalert-child-settings', 'fraudalert_disclaimers_section', ['id' => 'show_disc_archives']);
    add_settings_field('show_disc_home', 'Show on Home/404 (Footer)', 'fraudalert_field_checkbox_cb', 'fraudalert-child-settings', 'fraudalert_disclaimers_section', ['id' => 'show_disc_home']);
});



// Helper to render toggles (checkboxes)
function fa_render_toggle($args) {
    $opts = get_option('fraudalert_settings', []);
    $checked = !empty($opts[$args['name']]) ? 'checked' : '';
    echo '<input type="checkbox" name="fraudalert_settings[' . esc_attr($args['name']) . ']" value="1" ' . $checked . '>';
}

function fraudalert_field_checkbox_cb($args) {
    $opts = get_option('fraudalert_settings');
    $val = isset($opts[$args['id']]) ? $opts[$args['id']] : '';
    echo '<input type="checkbox" name="fraudalert_settings[' . esc_attr($args['id']) . ']" value="1"' . checked(1, $val, false) . ' />';
}

/* --- Callback Functions for Fields --- */
function fraudalert_field_text_cb($args) {
    $opts = get_option('fraudalert_settings');
    $val = isset($opts[$args['id']]) ? $opts[$args['id']] : '';
    echo '<input type="text" name="fraudalert_settings[' . esc_attr($args['id']) . ']" value="' . esc_attr($val) . '" class="regular-text">';
}

function fraudalert_field_textarea_cb($args) {
    $opts = get_option('fraudalert_settings');
    $val = isset($opts[$args['id']]) ? $opts[$args['id']] : '';
    echo '<textarea name="fraudalert_settings[' . esc_attr($args['id']) . ']" rows="3" class="large-text">' . esc_textarea($val) . '</textarea>';
}

function fraudalert_field_editor_cb($args) {
    $opts = get_option('fraudalert_settings');
    $content = isset($opts[$args['id']]) ? $opts[$args['id']] : '';
    wp_editor($content, 'fraudalert_settings_' . $args['id'], [
        'textarea_name' => 'fraudalert_settings[' . esc_attr($args['id']) . ']',
        'media_buttons' => true,
        'textarea_rows' => 10,
        'teeny'         => false
    ]);
}

/* --- Render Settings Page --- */
function fraudalert_theme_settings_page() {
    if (!current_user_can('manage_options')) return;
    ?>
    <div class="wrap">
        <h1>🛡️ FraudAlert Theme Settings</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('fraudalert_settings_group');
            do_settings_sections('fraudalert-child-settings');
            submit_button('Save All Settings');
            ?>
        </form>
    </div>
    <style>
        .wrap h1 { font-family: 'Baloo 2', cursive; font-weight: 800; color: #003459; margin-bottom: 2rem; }
        .form-table th { font-weight: 700; color: #1a1a1a; width: 250px; }
        .settings-section-title { border-bottom: 2px solid #e8ddd0; padding-bottom: 10px; margin-top: 30px; }
    </style>
    <?php
}

/* =============================================================
   DISCLAIMER ENGINE & COMPLIANCE
   ============================================================= */

// 1. Post Meta Box for Affiliate Toggle
add_action('add_meta_boxes', function () {
    add_meta_box('fraudalert_affiliate_meta', 'Affiliate / Referral Status', function($post) {
        wp_nonce_field('fraudalert_affiliate_nonce_save', 'fraudalert_affiliate_nonce');
        $is_affiliate = get_post_meta($post->ID, '_is_affiliate_post', true);
        echo '<label><input type="checkbox" name="is_affiliate_post" value="1" ' . checked('1', $is_affiliate, false) . '> This post contains affiliate or referral links (Enables Affiliate Disclaimer).</label>';
    }, 'post', 'side', 'default');
});

add_action('save_post', function ($post_id) {
    if (!isset($_POST['fraudalert_affiliate_nonce']) || !wp_verify_nonce($_POST['fraudalert_affiliate_nonce'], 'fraudalert_affiliate_nonce_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    
    if (isset($_POST['is_affiliate_post'])) {
        update_post_meta($post_id, '_is_affiliate_post', '1');
    } else {
        delete_post_meta($post_id, '_is_affiliate_post');
    }
});

// 2. Core Rendering Function
function fraudalert_get_disclaimer_markup($context = 'general', $post_id = null) {
    $opts = get_option('fraudalert_settings', []);
    $html = '';

    // Check display rules
    if ($context === 'single' && empty($opts['show_disc_posts'])) return '';
    if ($context === 'page' && empty($opts['show_disc_pages'])) return '';
    if ($context === 'archive' && empty($opts['show_disc_archives'])) return '';
    if (($context === 'home' || $context === '404') && empty($opts['show_disc_home'])) return '';

    $disc_gen = !empty($opts['disc_general']) ? wp_kses_post($opts['disc_general']) : '';
    $disc_leg = !empty($opts['disc_legal']) ? wp_kses_post($opts['disc_legal']) : '';
    $disc_rep = !empty($opts['disc_report']) ? wp_kses_post($opts['disc_report']) : '';
    $disc_aff = !empty($opts['disc_affiliate']) ? wp_kses_post($opts['disc_affiliate']) : '';

    if ($disc_gen || $disc_leg || $disc_rep || $disc_aff) {
        $html .= '<div class="fraudalert-disclaimer-box" style="background:#fef3e2; border-left:4px solid var(--saf); padding:1rem 1.5rem; margin:2rem 0; font-family:var(--fu); font-size:13px; color:var(--muted); line-height:1.6; border-radius:4px;">';
        $html .= '<div style="font-family:var(--fh); font-weight:800; color:var(--navy); font-size:15px; margin-bottom:0.5rem; text-transform:uppercase;">⚠️ Important Disclosure</div>';
        
        if ($disc_gen) $html .= '<div class="disc-section" style="margin-bottom:0.75rem;">' . $disc_gen . '</div>';
        
        // Append Affiliate if tagged
        if ($post_id && get_post_meta($post_id, '_is_affiliate_post', true) && $disc_aff) {
            $html .= '<div class="disc-section" style="margin-bottom:0.75rem; border-top:1px dashed var(--border); padding-top:0.75rem;">' . $disc_aff . '</div>';
        }
        
        if ($disc_leg) $html .= '<div class="disc-section" style="margin-bottom:0.75rem; border-top:1px dashed var(--border); padding-top:0.75rem;">' . $disc_leg . '</div>';
        if ($disc_rep) $html .= '<div class="disc-section" style="margin-bottom:0; border-top:1px dashed var(--border); padding-top:0.75rem;">' . $disc_rep . '</div>';
        
        $html .= '</div>';
    }
    return $html;
}

// 3. Frontend Hooks
add_filter('the_content', function ($content) {
    if (is_page()) {
        $content .= fraudalert_get_disclaimer_markup('page');
    }
    return $content;
});

add_action('loop_start', function ($query) {
    if ($query->is_main_query() && (is_archive() || is_search()) && !is_author()) {
        echo fraudalert_get_disclaimer_markup('archive');
    }
});

add_action('get_footer', function () {
    if (is_front_page() || is_home()) {
        echo '<div style="max-width:1160px; margin:0 auto; padding: 0 1.5rem;">' . fraudalert_get_disclaimer_markup('home') . '</div>';
    } elseif (is_404()) {
        echo '<div style="max-width:1100px; margin:0 auto; padding: 0 1rem;">' . fraudalert_get_disclaimer_markup('404') . '</div>';
    }
});

/* =============================================================
   ARCHIVE REDIRECTS & SEO CONTROL
   ============================================================= */

add_action('template_redirect', function () {
    // Disable Date, Time, and specific empty archives via 301
    if (is_date() || is_time() || (is_author() && !have_posts()) || (is_tag() && !have_posts())) {
        wp_redirect(home_url('/'), 301);
        exit;
    }
});

add_action('wp_head', function () {
    // Add noindex for search pages, 404s, or optionally empty archives to prevent thin content indexing 
    if (is_search() || is_404() || (is_archive() && !have_posts())) {
        // Only output if SEO plugins are absent
        if (!defined('WPSEO_VERSION') && !function_exists('rank_math') && !class_exists('AIOSEO_WP')) {
            echo '<meta name="robots" content="noindex, follow">' . "\n";
        }
    }
}, 4);

/* =============================================================
   CUSTOM NUMERIC PAGINATION
   Replaces default pagination loops with numbered prev/next
   Format: « 1 2 3 ... 10 »
   ============================================================= */

function fraudalert_numbered_pagination() {
    global $wp_query;
    $total_pages = isset($wp_query->max_num_pages) ? $wp_query->max_num_pages : 1;
    
    if ($total_pages > 1) {
        $current_page = max(1, get_query_var('paged'));
        echo '<nav class="pagination" role="navigation" aria-label="Pagination" style="margin-top:3rem; text-align:center;">';
        
        echo paginate_links([
            'base'      => get_pagenum_link(1) . '%_%',
            'format'    => 'page/%#%/',
            'current'   => $current_page,
            'total'     => $total_pages,
            'prev_text' => '«',
            'next_text' => '»',
            'mid_size'  => 2,
            'end_size'  => 1,
            'type'      => 'plain',
        ]);
        
        echo '</nav>';
    }
}

/* =============================================================
   WORDPRESS HARDENING & SPEED OPTIMIZATION
   ============================================================= */

// 1. Remove WP Version & Meta Generators
remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_false');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rsd_link');

// 2. Disable XML-RPC
add_filter('xmlrpc_enabled', '__return_false');
add_filter('xmlrpc_methods', '__return_empty_array');

// 3. Disable User Enumeration (?author=1 redirect to home)
add_action('template_redirect', function () {
    if (is_author() && isset($_GET['author']) && preg_match('/^\d+$/', $_GET['author'])) {
        wp_redirect(home_url('/'), 301);
        exit;
    }
});

// 4. Remove Query Strings from Static Assets
add_filter('style_loader_src', 'fraudalert_child_remove_query_strings', 10, 2);
add_filter('script_loader_src', 'fraudalert_child_remove_query_strings', 10, 2);
function fraudalert_child_remove_query_strings($src) {
    if (!is_admin() && strpos($src, '?ver=')) {
        $src = remove_query_arg('ver', $src);
    }
    return $src;
}

// 5. DNS Prefetch & Preconnect for Performance
add_filter('wp_resource_hints', function ($urls, $relation_type) {
    if ($relation_type === 'preconnect') {
        $urls[] = ['href' => 'https://fonts.googleapis.com', 'crossorigin' => ''];
        $urls[] = ['href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous'];
    }
    if ($relation_type === 'dns-prefetch') {
        $urls[] = 'https://fonts.googleapis.com';
    }
    return $urls;
}, 10, 2);

/* =============================================================
   PIB FOOTER TRUST BADGE
   ============================================================= */
add_action('wp_footer', function() {
    if (is_admin()) return;
    echo '<div style="text-align:center; padding:1.5rem; background:#f8f9fa; border-top:1px solid #eee; font-family:var(--fu); font-size:12px; color:var(--muted);">';
    echo 'स्कैम से बचो (FraudAlert) supports the <a href="https://factcheck.pib.gov.in" target="_blank" rel="nofollow" style="color:var(--nav2); font-weight:700;">PIB Fact Check</a> initiative by the Govt. of India. 🛡️';
    echo '</div>';
}, 20);

/* =============================================================
   CUSTOM LOCAL AVATAR SYSTEM (Performance & Privacy)
   ============================================================= */

// 1. Add Custom Field to User Profile
add_action('show_user_profile', 'fa_add_custom_avatar_field');
add_action('edit_user_profile', 'fa_add_custom_avatar_field');

function fa_add_custom_avatar_field($user) {
    $custom_avatar = get_user_meta($user->ID, 'fa_custom_avatar', true);
    ?>
    <h3>Profile Photo (Local)</h3>
    <table class="form-table">
        <tr>
            <th><label for="fa_custom_avatar">Upload Image</label></th>
            <td>
                <div class="fa-avatar-preview" style="margin-bottom:10px;">
                    <?php if ($custom_avatar) : ?>
                        <img src="<?php echo esc_url($custom_avatar); ?>" style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:2px solid #ccc;">
                    <?php endif; ?>
                </div>
                <input type="text" name="fa_custom_avatar" id="fa_custom_avatar" value="<?php echo esc_attr($custom_avatar); ?>" class="regular-text" style="display:none;">
                <button type="button" class="button button-primary" id="fa_avatar_upload_btn">Choose from Media Library</button>
                <button type="button" class="button" id="fa_avatar_remove_btn" style="<?php echo (!$custom_avatar) ? 'display:none;' : ''; ?>">Remove</button>
                <p class="description">This image will replace Gravatar across the entire site. Use a square 512x512px image for best results.</p>
            </td>
        </tr>
    </table>
    <script>
        jQuery(document).ready(function($){
            var mediaUploader;
            $('#fa_avatar_upload_btn').click(function(e) {
                e.preventDefault();
                if (mediaUploader) { mediaUploader.open(); return; }
                mediaUploader = wp.media.frames.file_frame = wp.media({
                    title: 'Choose Profile Picture',
                    button: { text: 'Use as Profile Picture' },
                    multiple: false
                });
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#fa_custom_avatar').val(attachment.url);
                    $('.fa-avatar-preview').html('<img src="'+attachment.url+'" style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:2px solid #ccc;">');
                    $('#fa_avatar_remove_btn').show();
                });
                mediaUploader.open();
            });
            $('#fa_avatar_remove_btn').click(function(){
                $('#fa_custom_avatar').val('');
                $('.fa-avatar-preview').empty();
                $(this).hide();
            });
        });
    </script>
    <?php
}

// 2. Enqueue Media Scripts in Admin
add_action('admin_enqueue_scripts', function($hook) {
    if ('profile.php' !== $hook && 'user-edit.php' !== $hook) return;
    wp_enqueue_media();
});

// 3. Save Custom Avatar Field
add_action('personal_options_update', 'fa_save_custom_avatar');
add_action('edit_user_profile_update', 'fa_save_custom_avatar');

function fa_save_custom_avatar($user_id) {
    if (isset($_POST['fa_custom_avatar'])) {
        update_user_meta($user_id, 'fa_custom_avatar', esc_url_raw($_POST['fa_custom_avatar']));
    }
}

// 4. Hook into get_avatar to override Gravatar
add_filter('get_avatar', 'fa_custom_avatar_filter', 10, 5);

function fa_custom_avatar_filter($avatar, $id_or_email, $size, $default, $alt) {
    $user_id = 0;
    if (is_numeric($id_or_email)) {
        $user_id = (int)$id_or_email;
    } elseif (is_object($id_or_email) && !empty($id_or_email->user_id)) {
        $user_id = (int)$id_or_email->user_id;
    } elseif (is_string($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
        $user_id = $user ? $user->ID : 0;
    }

    if ($user_id) {
        $custom_avatar = get_user_meta($user_id, 'fa_custom_avatar', true);
        if ($custom_avatar) {
            $avatar = "<img alt='" . esc_attr($alt) . "' src='" . esc_url($custom_avatar) . "' class='avatar avatar-" . esc_attr($size) . " photo' height='" . esc_attr($size) . "' width='" . esc_attr($size) . "' style='border-radius:50%; object-fit:cover;' />";
        }
    }
    return $avatar;
}

// Register Custom Widget Area for Sticky Ads
add_action('widgets_init', function() {
    register_sidebar([
        'name'          => 'Sticky Sidebar Ad Zone',
        'id'            => 'ad-sidebar-sticky',
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title" style="display:none;">',
        'after_title'   => '</h3>',
    ]);
});

require_once get_stylesheet_directory() . '/inc/photo-story-cpt.php';

/* =============================================================
   SITE-WIDE AD MANAGER SYSTEM
   ============================================================= */

if (version_compare(get_bloginfo('version'), '6.0', '>=') && version_compare(PHP_VERSION, '7.4', '>=')) {
    if (true) { // Version guards passed — class loads

        class PSAdManager {
            public static function init() { new self(); }

            private function __construct() {
                add_action('admin_menu', [$this, 'register_menu']);
                add_action('admin_init', [$this, 'register_settings']);
                add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
                add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
                // NOTE: save_post hook removed — sanitize_settings() clears cache on actual settings change
            }

            public function register_menu() {
                add_menu_page('Ad Settings', 'Ad Manager', 'manage_options', 'ps_ad_settings', [$this, 'page_settings'], 'dashicons-megaphone', 30);
                add_submenu_page('ps_ad_settings', 'Ad Settings', 'Ad Settings', 'manage_options', 'ps_ad_settings', [$this, 'page_settings']);
                add_submenu_page('ps_ad_settings', 'Ad Placements', 'Ad Placements', 'manage_options', 'ps_ad_placements', [$this, 'page_placements']);
                add_submenu_page('ps_ad_settings', 'Author Ads', 'Author Ads', 'manage_options', 'ps_ad_author', [$this, 'page_author']);
                add_submenu_page('ps_ad_settings', 'Custom Image Ads', 'Custom Image Ads', 'manage_options', 'ps_ad_custom_image', [$this, 'page_custom_image']);
                add_submenu_page('ps_ad_settings', 'Ad Preview', 'Ad Preview', 'manage_options', 'ps_ad_preview', [$this, 'page_preview']);
            }

            public function register_settings() {
                register_setting('ps_ad_options', 'ps_ad_settings', [$this, 'sanitize_settings']);
            }

            public function sanitize_settings($input) {
                delete_transient('ps_ad_settings_cache');
                if (!is_array($input)) return [];
                
                $clean = [];
                $tabs = ['global', 'single_post', 'single_page', 'category_tag', 'photo_story'];

                // Whitelist: condition rule types allowed
                $allowed_rule_types = ['author', 'category', 'tag', 'user_role', 'device', 'login_status', 'post_format', 'post_age'];

                // Whitelist: allowed placement keys per tab
                $allowed_placements = [
                    'global'       => ['header_below', 'footer_sticky', 'sidebar', 'before_footer'],
                    'single_post'  => ['content_top', 'content_middle', 'content_bottom', 'content_between'],
                    'single_page'  => ['content_top', 'content_bottom'],
                    'category_tag' => ['archive_header', 'archive_loop', 'archive_footer'],
                    'photo_story'  => ['header', 'slides', 'footer'], // ps_header/ps_slides/ps_end removed — dead keys
                ];
                
                foreach ($tabs as $tab) {
                    if (isset($input[$tab]) && is_array($input[$tab])) {
                        foreach ($input[$tab] as $place => $place_data) {
                            // Validate placement key
                            $place = sanitize_key($place);
                            if (!empty($allowed_placements[$tab]) && !in_array($place, $allowed_placements[$tab])) {
                                continue; // Reject unknown placement keys
                            }

                            $clean[$tab][$place] = ['default' => [], 'conditions' => []];
                            
                            // Sanitize Default Ad
                            if (isset($place_data['default']) && is_array($place_data['default'])) {
                                $clean[$tab][$place]['default'] = $this->sanitize_ad_array($place_data['default']);
                            }
                            
                            // Sanitize Conditions
                            if (isset($place_data['conditions']) && is_array($place_data['conditions'])) {
                                $conds = [];
                                foreach ($place_data['conditions'] as $c_data) {
                                    if (!is_array($c_data)) continue;
                                    
                                    $logic = sanitize_text_field($c_data['logic'] ?? 'AND');
                                    if (!in_array($logic, ['AND', 'OR'], true)) $logic = 'AND';
                                    
                                    $rules = [];
                                    if (isset($c_data['rules']) && is_array($c_data['rules'])) {
                                        foreach ($c_data['rules'] as $rule) {
                                            if (!isset($rule['type'], $rule['value'])) continue;
                                            $r_type = sanitize_key($rule['type']);
                                            if (!in_array($r_type, $allowed_rule_types, true)) continue; // Whitelist check
                                            $rules[] = [
                                                'type'  => $r_type,
                                                'value' => sanitize_text_field($rule['value'])
                                            ];
                                        }
                                    }
                                    
                                    $ad = isset($c_data['ad']) && is_array($c_data['ad'])
                                        ? $this->sanitize_ad_array($c_data['ad'])
                                        : [];
                                    
                                    if (!empty($rules)) {
                                        $conds[] = ['logic' => $logic, 'rules' => $rules, 'ad' => $ad];
                                    }
                                }
                                $clean[$tab][$place]['conditions'] = $conds;
                            }
                        }
                    }
                }
                
                return $clean;
            }

            private function sanitize_ad_array($data) {
                // Whitelist: ad code KSES — allow <script> and <ins> for AdSense/GAM
                $ad_kses = [
                    'script' => ['src'=>true,'async'=>true,'defer'=>true,'type'=>true,'id'=>true,
                                 'data-ad-client'=>true,'data-ad-slot'=>true,'crossorigin'=>true,
                                 'data-ad-format'=>true,'data-full-width-responsive'=>true],
                    'ins'    => ['class'=>true,'style'=>true,'data-ad-client'=>true,'data-ad-slot'=>true,
                                 'data-ad-format'=>true,'data-full-width-responsive'=>true],
                ];
                return [
                    'enabled'  => !empty($data['enabled']) ? 1 : 0,
                    // Whitelist type/device/size — sanitize_text_field alone is insufficient
                    'type'     => in_array($data['type'] ?? '', ['adsense','gam','custom_image','responsive'], true)
                                    ? $data['type'] : 'adsense',
                    'device'   => in_array($data['device'] ?? '', ['all','mobile','desktop'], true)
                                    ? $data['device'] : 'all',
                    'size'     => in_array($data['size'] ?? '', ['responsive','728x90','300x250','320x50'], true)
                                    ? $data['size'] : 'responsive',
                    // Allow <script>/<ins> for ad code; unfiltered_html users bypass KSES entirely
                    'code'     => current_user_can('unfiltered_html')
                                    ? ($data['code'] ?? '')
                                    : wp_kses($data['code'] ?? '', $ad_kses),
                    'image_id' => absint($data['image_id'] ?? 0),
                    'link'     => esc_url_raw($data['link'] ?? ''),
                    'alt'      => sanitize_text_field($data['alt'] ?? ''),
                    'new_tab'  => !empty($data['new_tab']) ? 1 : 0,
                    'nofollow' => !empty($data['nofollow']) ? 1 : 0,
                ];
            }

            public function enqueue_admin_assets($hook) {
                if (strpos($hook, 'ps_ad_') === false) return;

                wp_enqueue_media();

                $css_path = get_stylesheet_directory() . '/css/ps-ad-admin.css';
                $js_path  = get_stylesheet_directory() . '/js/ps-ad-admin.js';

                wp_enqueue_style(
                    'ps-ad-admin-style',
                    get_stylesheet_directory_uri() . '/css/ps-ad-admin.css',
                    [],
                    is_file($css_path) ? (string) filemtime($css_path) : '1.0.0'
                );

                wp_enqueue_script(
                    'ps-ad-admin-script',
                    get_stylesheet_directory_uri() . '/js/ps-ad-admin.js',
                    ['wp-util'],
                    is_file($js_path) ? (string) filemtime($js_path) : '1.0.0',
                    true
                );
            }

            public function enqueue_frontend_assets() {
                $css_path = get_stylesheet_directory() . '/css/ps-ads.css';
                $js_path  = get_stylesheet_directory() . '/js/ps-ads.js';

                wp_enqueue_style(
                    'ps-ads-style', 
                    get_stylesheet_directory_uri() . '/css/ps-ads.css', 
                    [], 
                    is_file($css_path) ? filemtime($css_path) : '1.0.0'
                );

                wp_enqueue_script(
                    'ps-ads-script', 
                    get_stylesheet_directory_uri() . '/js/ps-ads.js', 
                    [], 
                    is_file($js_path) ? filemtime($js_path) : '1.0.0', 
                    true
                );
            }

            public function clear_ad_cache($post_id = null, $post = null) {
                if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
                delete_transient('ps_ad_settings_cache');
            }

            public function page_settings() { $this->render_page('Ad Settings'); }
            public function page_placements() { $this->render_page('Ad Placements'); }
            public function page_author() { $this->render_page('Author Ads'); }
            public function page_custom_image() { $this->render_page('Custom Image Ads'); }
            public function page_preview() { $this->render_page('Ad Preview'); }

            private function render_page($title) {
                if (!current_user_can('manage_options')) return;
                $settings = get_option('ps_ad_settings', []);

                $tabs = [
                    'global'       => 'Global Ads',
                    'single_post'  => 'Single Post Ads',
                    'single_page'  => 'Single Page Ads',
                    'category_tag' => 'Category/Tag Ads',
                    'photo_story'  => 'Photo Story Ads',
                    'preview'      => 'Ad Preview'
                ];

                // Validate tab against whitelist — prevents blank-page on arbitrary ?tab= values
                $raw_tab    = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'global';
                $active_tab = array_key_exists($raw_tab, $tabs) ? $raw_tab : 'global';
                ?>
                <div class="wrap ps-ad-wrap">
                    <div class="ps-ad-header">
                        <h1><?php echo esc_html($title); ?></h1>
                        <button type="submit" form="ps-ad-form" class="button button-primary button-large">Save All Ads</button>
                    </div>
                    <div class="ps-ad-tabs" role="tablist" aria-label="Ad Manager Tabs">
                        <?php foreach($tabs as $key => $name): ?>
                            <a href="#"
                               class="ps-ad-tab <?php echo $active_tab === $key ? 'active' : ''; ?>"
                               role="tab"
                               id="tab-<?php echo esc_attr($key); ?>"
                               aria-selected="<?php echo $active_tab === $key ? 'true' : 'false'; ?>"
                               aria-controls="panel-<?php echo esc_attr($key); ?>"
                               data-target="<?php echo esc_attr($key); ?>"><?php echo esc_html($name); ?></a>
                        <?php endforeach; ?>
                    </div>
                    
                    <form method="post" action="options.php" id="ps-ad-form">
                        <?php settings_fields('ps_ad_options'); ?>
                        <!-- Preserve active tab across save/redirect -->
                        <input type="hidden" id="ps-active-tab-field" name="_ps_ad_active_tab" value="<?php echo esc_attr($active_tab); ?>">
                        <div class="ps-ad-content">
                            <?php foreach($tabs as $tab_key => $tab_name): ?>
                                <div class="ps-ad-panel <?php echo $active_tab === $tab_key ? 'active' : ''; ?>"
                                     id="panel-<?php echo esc_attr($tab_key); ?>"
                                     role="tabpanel"
                                     aria-labelledby="tab-<?php echo esc_attr($tab_key); ?>"
                                     tabindex="0">
                                    <h2><?php echo esc_html($tab_name); ?></h2>
                                    <?php 
                                    if ($tab_key === 'preview') {
                                        $this->render_preview_panel($settings);
                                    } else {
                                        $placements = $this->get_placements_for_tab($tab_key);
                                        foreach($placements as $place_key => $place_name) {
                                            $this->render_ad_block_ui($tab_key, $place_key, $place_name, $settings);
                                        }
                                    }
                                    ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
                <div style="display:none;" id="ps-cond-group-template">
                    <?php $this->render_condition_group('__TAB__', '__PLACE__', '__IDX__', []); ?>
                </div>
                <div style="display:none;" id="ps-cond-rule-template">
                    <?php $this->render_condition_rule('__BASE__', '__RIDX__', []); ?>
                </div>
                <?php
            }

            /**
             * Ad Preview Tab — shows saved ad configuration per placement.
             * Device filter buttons (All/Desktop/Mobile) are handled by ps-ad-admin.js.
             */
            private function render_preview_panel($settings) {
                $preview_tabs = [
                    'global'       => 'Global',
                    'single_post'  => 'Single Post',
                    'single_page'  => 'Single Page',
                    'category_tag' => 'Category / Tag',
                    'photo_story'  => 'Photo Story',
                ];
                ?>
                <p class="description" style="margin-bottom:16px;">
                    Preview of <strong>currently saved</strong> ad configurations.
                    Save settings first to see updates here.
                </p>

                <div class="ps-preview-filter">
                    <strong>Filter by Device:</strong>
                    <button type="button" class="button ps-dev-filter active" data-device="all">All Devices</button>
                    <button type="button" class="button ps-dev-filter" data-device="desktop">Desktop Only</button>
                    <button type="button" class="button ps-dev-filter" data-device="mobile">Mobile Only</button>
                </div>

                <?php foreach ($preview_tabs as $tab_key => $tab_label):
                    $placements = $this->get_placements_for_tab($tab_key);
                    if (empty($placements)) continue;
                ?>
                <div class="ps-preview-section">
                    <h3><?php echo esc_html($tab_label); ?></h3>

                    <?php foreach ($placements as $place_key => $place_label):
                        $pdata   = $settings[$tab_key][$place_key] ?? [];
                        $default = $pdata['default'] ?? [];
                        $conds   = $pdata['conditions'] ?? [];
                        $enabled = !empty($default['enabled']);
                        $type    = sanitize_key($default['type']   ?? 'adsense');
                        $device  = sanitize_key($default['device'] ?? 'all');
                    ?>
                    <div class="ps-preview-placement" data-device="<?php echo esc_attr($device); ?>">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
                            <strong><?php echo esc_html($place_label); ?></strong>
                            <span class="ps-preview-badges">
                                <span class="ps-preview-badge <?php echo $enabled ? 'ps-badge-enabled' : 'ps-badge-disabled'; ?>">
                                    <?php echo $enabled ? 'Enabled' : 'Disabled'; ?>
                                </span>
                                <span class="ps-preview-badge ps-badge-type"><?php echo esc_html(strtoupper($type)); ?></span>
                                <span class="ps-preview-badge ps-badge-device"><?php echo esc_html($device); ?></span>
                            </span>
                        </div>

                        <?php if (!$enabled): ?>
                            <p style="color:#999;font-style:italic;margin:0;">No active default ad configured.</p>

                        <?php elseif ($type === 'custom_image'):
                            $img_id  = absint($default['image_id'] ?? 0);
                            $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : '';
                            $link    = esc_url($default['link'] ?? '');
                            $alt     = esc_attr($default['alt'] ?? 'Ad Preview');
                        ?>
                            <?php if ($img_url): ?>
                                <div style="text-align:center;">
                                    <?php if ($link): ?>
                                        <a href="<?php echo $link; ?>" target="_blank" rel="noopener noreferrer">
                                            <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo $alt; ?>"
                                                 style="max-width:100%;max-height:200px;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,.1);">
                                        </a>
                                    <?php else: ?>
                                        <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo $alt; ?>"
                                             style="max-width:100%;max-height:200px;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,.1);">
                                    <?php endif; ?>
                                    <?php if ($link): ?>
                                        <p style="font-size:11px;color:#888;margin:4px 0 0;">Links to: <?php echo esc_html($default['link'] ?? ''); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <p style="color:#999;font-style:italic;margin:0;">Image not found (ID: <?php echo esc_html($img_id); ?>).</p>
                            <?php endif; ?>

                        <?php elseif ($type === 'adsense' || $type === 'gam'):
                            $code = $default['code'] ?? '';
                        ?>
                            <?php if ($code): ?>
                                <details>
                                    <summary style="cursor:pointer;color:#0073aa;font-weight:600;">View Ad Code (<?php echo esc_html(strtoupper($type)); ?>)</summary>
                                    <div class="ps-preview-code-wrap"><?php echo esc_html(mb_substr($code, 0, 400) . (mb_strlen($code) > 400 ? '\n...' : '')); ?></div>
                                </details>
                            <?php else: ?>
                                <p style="color:#999;font-style:italic;margin:0;">No ad code saved yet.</p>
                            <?php endif; ?>

                        <?php elseif ($type === 'responsive'): ?>
                            <div class="ps-preview-responsive-mock">&#9616; Responsive Ad Slot &#9612;</div>

                        <?php endif; ?>

                        <?php if (!empty($conds)): ?>
                            <p style="margin:10px 0 0;font-size:12px;color:#888;">
                                + <?php echo count($conds); ?> condition group(s) may override default ad.
                            </p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach;
            }

            private function get_placements_for_tab($tab) {
                switch($tab) {
                    case 'global': return ['header_below' => 'Below Header', 'footer_sticky' => 'Sticky Footer', 'sidebar' => 'Main Sidebar', 'before_footer' => 'Before Footer'];
                    case 'single_post':  return ['content_top' => 'Content Top', 'content_middle' => 'Content Middle', 'content_between' => 'Between Paragraphs', 'content_bottom' => 'Content Bottom'];
                    case 'single_page': return ['content_top' => 'Content Top', 'content_bottom' => 'Content Bottom'];
                    case 'category_tag': return ['archive_header' => 'Archive Header', 'archive_loop' => 'Inside Loop', 'archive_footer' => 'Archive Footer'];
                    case 'photo_story': return ['header' => 'Story Header', 'slides' => 'Between Slides', 'footer' => 'Story Footer'];
                    default: return [];
                }
            }

            private function render_ad_block_ui($tab, $place_key, $place_name, $settings) {
                $base_name = "ps_ad_settings[{$tab}][{$place_key}]";
                $data = $settings[$tab][$place_key] ?? ['default' => [], 'conditions' => []];
                
                echo '<div class="ps-ad-placement-wrap" style="border: 2px solid var(--navy, #003459); border-radius: 8px; margin-bottom: 30px; padding: 20px; background: #fff;">';
                echo '<h3 style="margin-top: 0; color: var(--navy, #003459); border-bottom: 1px solid #eee; padding-bottom: 10px;">' . esc_html($place_name) . '</h3>';
                
                // 1. Default Ad Section
                echo '<div class="ps-ad-default-wrap" style="background: #f9f9f9; padding: 15px; border-radius: 6px; border: 1px dashed #ccc; margin-bottom: 20px;">';
                echo '<h4 style="margin-top:0;">Default Ad Section (Fallback)</h4>';
                $this->render_ad_fields($base_name . '[default]', $data['default'] ?? []);
                echo '</div>';

                // 2. Conditions Section
                echo '<div class="ps-ad-conditions-wrap" id="conds_' . esc_attr("{$tab}_{$place_key}") . '">';
                $conditions = $data['conditions'] ?? [];
                foreach ($conditions as $idx => $cond) {
                    $this->render_condition_group($tab, $place_key, $idx, $cond);
                }
                echo '</div>';

                // Add Condition Group Button
                echo '<button type="button" class="button button-secondary ps-add-cond-group" data-tab="' . esc_attr($tab) . '" data-place="' . esc_attr($place_key) . '">+ Add Condition Group</button>';
                
                echo '</div>'; // End placement wrap
            }

            private function render_condition_group($tab, $place_key, $idx, $data = []) {
                $base_name = "ps_ad_settings[{$tab}][{$place_key}][conditions][{$idx}]";
                $logic = $data['logic'] ?? 'AND';
                $rules = $data['rules'] ?? [['type' => '', 'value' => '']];
                $ad = $data['ad'] ?? [];
                
                ?>
                <div class="ps-cond-group" style="border: 1px solid var(--saf, #e07a5f); border-radius: 6px; margin-bottom: 15px; background: #fff; overflow:hidden;">
                    <div style="background: #fff3f0; padding: 10px 15px; border-bottom: 1px solid #fbdad0; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <strong>Condition Group (Priority: <span class="ps-cond-priority"></span>)</strong>
                            <select name="<?php echo $base_name; ?>[logic]" style="margin-left: 15px; font-weight:bold;">
                                <option value="AND" <?php selected($logic, 'AND'); ?>>Match ALL Rules (AND)</option>
                                <option value="OR" <?php selected($logic, 'OR'); ?>>Match ANY Rule (OR)</option>
                            </select>
                        </div>
                        <button type="button" class="button ps-remove-cond-group">Remove Group</button>
                    </div>
                    
                    <div style="padding: 15px;">
                        <h4 style="margin-top:0;">IF</h4>
                        <div class="ps-cond-rules-wrap">
                            <?php foreach ($rules as $r_idx => $rule) {
                                $this->render_condition_rule($base_name, $r_idx, $rule);
                            } ?>
                        </div>
                        <button type="button" class="button button-small ps-add-rule" style="margin-bottom: 15px;" data-base="<?php echo esc_attr($base_name); ?>">+ Add Rule</button>
                        
                        <h4 style="margin-top:10px; border-top:1px solid #eee; padding-top:10px;">THEN SHOW AD</h4>
                        <?php $this->render_ad_fields($base_name . '[ad]', $ad, true); ?>
                    </div>
                </div>
                <?php
            }

            private function render_condition_rule($base_name, $r_idx, $rule) {
                $opts = $this->get_condition_options();
                $type = $rule['type'] ?? '';
                $val  = $rule['value'] ?? '';
                $rule_name = "{$base_name}[rules][{$r_idx}]";
                ?>
                <div class="ps-rule-row" style="display:flex; gap:10px; margin-bottom:10px; align-items:center;">
                    <select class="ps-rule-type" name="<?php echo $rule_name; ?>[type]">
                        <option value="">-- Select Condition --</option>
                        <option value="author" <?php selected($type, 'author'); ?>>Author</option>
                        <option value="category" <?php selected($type, 'category'); ?>>Category</option>
                        <option value="tag" <?php selected($type, 'tag'); ?>>Tag</option>
                        <option value="user_role" <?php selected($type, 'user_role'); ?>>User Role</option>
                        <option value="device" <?php selected($type, 'device'); ?>>Device</option>
                        <option value="login_status" <?php selected($type, 'login_status'); ?>>Login Status</option>
                    </select>
                    
                    <span class="ps-rule-is"> IS </span>
                    
                    <input type="text" class="ps-rule-value" name="<?php echo $rule_name; ?>[value]" value="<?php echo esc_attr($val); ?>" placeholder="Value" style="<?php if(in_array($type, ['author', 'category', 'tag', 'user_role', 'device', 'login_status'])) echo 'display:none;'; ?>">
                    
                    <select class="ps-rule-val-select ps-val-author" <?php if($type!=='author') echo 'style="display:none;" disabled'; else echo 'name="'.$rule_name.'[value]"'; ?>>
                        <option value="">- Select Author -</option>
                        <?php foreach($opts['author'] as $id => $name) echo '<option value="'.esc_attr($id).'" '.selected($val, $id, false).'>'.esc_html($name).'</option>'; ?>
                    </select>
                    
                    <select class="ps-rule-val-select ps-val-category" <?php if($type!=='category') echo 'style="display:none;" disabled'; else echo 'name="'.$rule_name.'[value]"'; ?>>
                        <option value="">- Select Category -</option>
                        <?php foreach($opts['category'] as $id => $name) echo '<option value="'.esc_attr($id).'" '.selected($val, $id, false).'>'.esc_html($name).'</option>'; ?>
                    </select>

                    <select class="ps-rule-val-select ps-val-tag" <?php if($type!=='tag') echo 'style="display:none;" disabled'; else echo 'name="'.$rule_name.'[value]"'; ?>>
                        <option value="">- Select Tag -</option>
                        <?php foreach($opts['tag'] as $id => $name) echo '<option value="'.esc_attr($id).'" '.selected($val, $id, false).'>'.esc_html($name).'</option>'; ?>
                    </select>

                    <select class="ps-rule-val-select ps-val-user_role" <?php if($type!=='user_role') echo 'style="display:none;" disabled'; else echo 'name="'.$rule_name.'[value]"'; ?>>
                        <option value="">- Select Role -</option>
                        <?php foreach($opts['user_role'] as $id => $name) echo '<option value="'.esc_attr($id).'" '.selected($val, $id, false).'>'.esc_html($name).'</option>'; ?>
                    </select>

                    <select class="ps-rule-val-select ps-val-device" <?php if($type!=='device') echo 'style="display:none;" disabled'; else echo 'name="'.$rule_name.'[value]"'; ?>>
                        <option value="">- Select Device -</option>
                        <option value="mobile" <?php selected($val, 'mobile'); ?>>Mobile</option>
                        <option value="desktop" <?php selected($val, 'desktop'); ?>>Desktop</option>
                    </select>

                    <select class="ps-rule-val-select ps-val-login_status" <?php if($type!=='login_status') echo 'style="display:none;" disabled'; else echo 'name="'.$rule_name.'[value]"'; ?>>
                        <option value="">- Select Status -</option>
                        <option value="logged_in" <?php selected($val, 'logged_in'); ?>>Logged In</option>
                        <option value="logged_out" <?php selected($val, 'logged_out'); ?>>Logged Out</option>
                    </select>

                    <button type="button" class="button ps-remove-rule" style="color:#d63638;">&times;</button>
                </div>
                <?php
            }

            private function render_ad_fields($base_name, $data, $is_condition = false) {
                $enabled = !isset($data['enabled']) || !empty($data['enabled']); 
                $type = $data['type'] ?? 'adsense';
                $device = $data['device'] ?? 'all';
                $size = $data['size'] ?? 'responsive';
                $code = $data['code'] ?? '';
                $image_id = absint($data['image_id'] ?? 0);
                $link = $data['link'] ?? '';
                $alt = $data['alt'] ?? '';
                $new_tab = !empty($data['new_tab']);
                $nofollow = !empty($data['nofollow']);

                $img_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
                $uid = wp_unique_id('ps_img_'); // wp_unique_id is WP-idiomatic vs uniqid()
                ?>
                <div class="ps-ad-row" style="margin-bottom: 0;">
                    <div class="ps-ad-col">
                        <label class="ps-switch" style="vertical-align: middle;">
                            <input type="checkbox" name="<?php echo $base_name; ?>[enabled]" value="1" <?php checked($enabled); ?>>
                            <span class="ps-slider"></span>
                        </label>
                        <span style="margin-left: 10px; vertical-align: middle;">Enable <?php echo $is_condition ? 'This Ad' : 'Default Ad'; ?></span>
                        
                        <label style="margin-top: 15px;">Ad Type</label>
                        <select name="<?php echo $base_name; ?>[type]" class="ps-ad-type-select">
                            <option value="adsense" <?php selected($type, 'adsense'); ?>>AdSense</option>
                            <option value="gam" <?php selected($type, 'gam'); ?>>GAM</option>
                            <option value="custom_image" <?php selected($type, 'custom_image'); ?>>Custom Image</option>
                            <option value="responsive" <?php selected($type, 'responsive'); ?>>Responsive (Auto)</option>
                        </select>

                        <label style="margin-top: 15px;">Device</label>
                        <select name="<?php echo $base_name; ?>[device]">
                            <option value="all" <?php selected($device, 'all'); ?>>All Devices</option>
                            <option value="mobile" <?php selected($device, 'mobile'); ?>>Mobile Only</option>
                            <option value="desktop" <?php selected($device, 'desktop'); ?>>Desktop Only</option>
                        </select>

                        <label style="margin-top: 15px;">Ad Size</label>
                        <select name="<?php echo $base_name; ?>[size]">
                            <option value="responsive" <?php selected($size, 'responsive'); ?>>Responsive</option>
                            <option value="728x90" <?php selected($size, '728x90'); ?>>728x90 Leaderboard</option>
                            <option value="300x250" <?php selected($size, '300x250'); ?>>300x250 Rectangle</option>
                            <option value="320x50" <?php selected($size, '320x50'); ?>>320x50 Mobile Banner</option>
                        </select>
                    </div>

                    <div class="ps-ad-col ps-ad-code-fields" style="<?php if($type==='custom_image') echo 'display:none;'; ?>">
                        <label>Ad Code</label>
                        <textarea name="<?php echo $base_name; ?>[code]"><?php echo esc_textarea($code); ?></textarea>
                    </div>

                    <div class="ps-ad-col ps-ad-image-fields" style="<?php if($type!=='custom_image') echo 'display:none;'; ?>">
                        <label>Custom Image</label>
                        <?php $img_field_id = "img_{$uid}"; ?>
                        <input type="hidden" name="<?php echo $base_name; ?>[image_id]" id="<?php echo $img_field_id; ?>" value="<?php echo $image_id; ?>">
                        <button type="button" class="button ps-ad-upload-btn" data-target="<?php echo $img_field_id; ?>">Select Image</button>
                        <div id="<?php echo $img_field_id; ?>_preview" class="ps-media-preview">
                            <?php if ($img_url) echo '<img src="' . esc_url($img_url) . '">'; ?>
                        </div>

                        <label style="margin-top: 15px;">Link URL</label>
                        <input type="text" name="<?php echo $base_name; ?>[link]" value="<?php echo esc_attr($link); ?>">

                        <label style="margin-top: 15px;">Alt Text</label>
                        <input type="text" name="<?php echo $base_name; ?>[alt]" value="<?php echo esc_attr($alt); ?>">

                        <div style="margin-top: 15px;">
                            <label><input type="checkbox" name="<?php echo $base_name; ?>[new_tab]" value="1" <?php checked($new_tab); ?>> Open in new tab</label><br>
                            <label><input type="checkbox" name="<?php echo $base_name; ?>[nofollow]" value="1" <?php checked($nofollow); ?>> rel="nofollow sponsored"</label>
                        </div>
                    </div>
                </div>
                <?php
            }

            private function get_condition_options() {
                static $options = null;
                if ($options !== null) return $options;

                $options = [
                    'author' => [],
                    'category' => [],
                    'tag' => [],
                    'user_role' => wp_roles()->get_names(),
                ];
                
                foreach (get_users(['fields' => ['ID', 'display_name']]) as $u) {
                    $options['author'][$u->ID] = $u->display_name;
                }
                foreach (get_categories(['hide_empty' => false]) as $c) {
                    $options['category'][$c->term_id] = $c->name;
                }
                foreach (get_tags(['hide_empty' => false]) as $t) {
                    $options['tag'][$t->term_id] = $t->name;
                }
                return $options;
            }

            public static function get_ads() {
                $ads = get_transient('ps_ad_settings_cache');
                if ($ads === false) {
                    $ads = get_option('ps_ad_settings', []);
                    set_transient('ps_ad_settings_cache', $ads, DAY_IN_SECONDS);
                }
                return is_array($ads) ? $ads : [];
            }
        }

        add_action('after_setup_theme', ['PSAdManager', 'init']);
    }
} else {
    // Version requirement not met — show admin notice so site owner knows why Ad Manager is absent
    add_action('admin_notices', function() {
        if (!current_user_can('manage_options')) return;
        printf(
            '<div class="notice notice-error"><p><strong>Ad Manager:</strong> Requires WordPress 6.0+ and PHP 7.4+. Current: WP %s, PHP %s.</p></div>',
            esc_html(get_bloginfo('version')),
            esc_html(PHP_VERSION)
        );
    });
}

/* =============================================================
   AD RENDER SYSTEM
   ============================================================= */

function ps_get_device() {
    static $ps_device = null;
    if ( $ps_device !== null ) return $ps_device;
    $ps_device = wp_is_mobile() ? 'mobile' : 'desktop';
    return $ps_device;
}

/**
 * Map WordPress post_type + context to the author_specific page_type key.
 * Centralised so both PSAdRender and hooks use the same mapping.
 */
function ps_resolve_page_type( $post_type = '' ) {
    switch ( $post_type ) {
        case 'post':        return 'single_post';
        case 'page':        return 'single_page';
        case 'photo_story': return 'photo_story';
        case 'author':      return 'author_archive';
        default:            return $post_type;
    }
}

class PSAdRender {

    /**
     * Main entry point.
     *
     * @param string $placement  Placement key, e.g. 'content_top'.
     * @param string $post_type  WordPress post_type (or 'author' for archives).
     * @param int    $author_id  Author user ID (0 = none).
     * @param bool   $return     True → return HTML, false → echo.
     * @return string
     */
    public static function show( $placement, $post_type = '', $author_id = 0, $return = false ) {
        $settings  = PSAdManager::get_ads();
        if ( empty( $settings ) ) return '';

        $device    = ps_get_device();
        $author_id = absint( $author_id );
        $tab       = ps_resolve_page_type( $post_type );
        
        // Find placement tab context
        if ( ! isset( $settings[ $tab ][ $placement ] ) ) {
            if ( isset( $settings['global'][ $placement ] ) ) {
                $tab = 'global';
            } else {
                return '';
            }
        }

        $placement_data = $settings[ $tab ][ $placement ];
        $output = '';
        $matched_ad = null;

        // 1st Priority: First matching condition group (top to bottom)
        if ( ! empty( $placement_data['conditions'] ) && is_array( $placement_data['conditions'] ) ) {
            foreach ( $placement_data['conditions'] as $group ) {
                if ( self::evaluate_group( $group, $device, $author_id ) ) {
                    $matched_ad = $group['ad'] ?? [];
                    break;
                }
            }
        }

        // 2nd Priority: Default ad (no condition match)
        if ( ! $matched_ad && ! empty( $placement_data['default'] ) ) {
            $matched_ad = $placement_data['default'];
        }

        // Output check: enabled + device targeting
        if ( $matched_ad && ! empty( $matched_ad['enabled'] ) ) {
            if ( $matched_ad['device'] === 'all' || $matched_ad['device'] === $device ) {
                $output = self::render_ad_html( $matched_ad, $placement, $device );
            }
        }

        if ( $output ) {
            $size = $matched_ad['size'] ?? 'responsive';
            $size_map = [ '728x90' => 'ps-ad-728', '300x250' => 'ps-ad-300', '320x50' => 'ps-ad-320', 'responsive' => 'ps-ad-responsive' ];
            $size_class = $size_map[ $size ] ?? 'ps-ad-responsive';

            $final_html  = '<div class="ps-ad-wrap ps-ad-lazy ' . esc_attr( $size_class ) . ' ps-ad-' . esc_attr( $placement ) . ' ps-ad-' . esc_attr( $device ) . '"';
            $final_html .= ' data-ad="' . esc_attr( $placement ) . '">';
            $final_html .= '<span class="ps-ad-label">' . esc_html__( 'Advertisement', 'fraudalert' ) . '</span>';
            $final_html .= $output;
            $final_html .= '</div>';

            if ( $return ) return $final_html;
            echo $final_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        return '';
    }

    private static function evaluate_group( $group, $device, $author_id ) {
        $logic = $group['logic'] ?? 'AND';
        $rules = $group['rules'] ?? [];
        if ( empty( $rules ) ) return false;

        $post_id   = get_the_ID();
        $is_logged = is_user_logged_in();                            // cached once
        $cur_user  = $is_logged ? wp_get_current_user() : null;     // cached once — avoids per-rule repeat

        foreach ( $rules as $rule ) {
            $type  = $rule['type'] ?? '';
            $val   = $rule['value'] ?? '';
            $match = false;

            switch ( $type ) {
                case 'author':
                    $match = ( (string) $author_id === (string) $val );
                    break;
                case 'category':
                    $match = has_category( $val, $post_id ) || is_category( $val );
                    break;
                case 'tag':
                    $match = has_tag( $val, $post_id ) || is_tag( $val );
                    break;
                case 'post_format':
                    $match = ( get_post_format( $post_id ) === $val );
                    break;
                case 'user_role':
                    $match = $cur_user && in_array( $val, (array) $cur_user->roles, true );
                    break;
                case 'device':
                    $match = ( $device === $val );
                    break;
                case 'login_status':
                    $match = ( $val === 'logged_in' && $is_logged ) || ( $val === 'logged_out' && ! $is_logged );
                    break;
                case 'post_age':
                    // val format: "before:2024-01-01" or "after:2024-01-01"
                    if ( $post_id && strpos( $val, ':' ) !== false ) {
                        [ $op, $date_str ] = explode( ':', $val, 2 );
                        $pub_ts = (int) get_post_time( 'U', true, $post_id );
                        $cmp_ts = (int) strtotime( sanitize_text_field( $date_str ) );
                        if ( $op === 'before' ) $match = $pub_ts < $cmp_ts;
                        if ( $op === 'after' )  $match = $pub_ts > $cmp_ts;
                    }
                    break;
            }

            if ( $logic === 'AND' && ! $match ) return false;
            if ( $logic === 'OR'  && $match )  return true;
        }

        return $logic === 'AND';
    }

    /**
     * Build the inner ad HTML for a single ad config array.
     */
    private static function render_ad_html( $ad, $placement, $device ) {
        $type = sanitize_key( $ad['type'] ?? 'adsense' );
        $html = '';

        if ( $type === 'custom_image' ) {
            $image_id = absint( $ad['image_id'] ?? 0 );
            $img_url  = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
            if ( $img_url ) {
                $link   = esc_url( $ad['link'] ?? '' );
                $alt    = esc_attr( $ad['alt'] ?: __( 'Advertisement', 'fraudalert' ) );
                if ( $link ) {
                    $target = ! empty( $ad['new_tab'] ) ? ' target="_blank"' : '';
                    $rel    = 'nofollow sponsored noopener';
                    if ( $target ) $rel .= ' noreferrer';
                    $html = sprintf(
                        '<a href="%s"%s rel="%s" class="ps-ad-img-wrap"><img src="%s" alt="%s" loading="lazy" decoding="async" class="ps-ad-img"></a>',
                        $link, $target, esc_attr( $rel ), esc_url( $img_url ), $alt
                    );
                } else {
                    // No link — render plain image without anchor (avoids <a href=""> pointing to current page)
                    $html = sprintf(
                        '<img src="%s" alt="%s" loading="lazy" decoding="async" class="ps-ad-img" style="display:block;width:100%;">',
                        esc_url( $img_url ), $alt
                    );
                }
            }

        } elseif ( $type === 'adsense' || $type === 'gam' ) {
            // Code was sanitized on save (wp_kses_post or unfiltered_html)
            $html = $ad['code'] ?? '';

        } elseif ( $type === 'responsive' ) {
            // Placeholder — theme can override via filter
            $html = apply_filters( 'ps_ad_responsive_html', '', $placement, $device );
        }

        return $html;
    }
}

// ── INTEGRATION HOOKS SYSTEM ──────────────────────────────────

// 1. HEADER AD — sab pages
add_action( 'ps_ad_after_header', function () {
    // Skip on photo_story — template fires its own ps_ad_render_header hook
    if ( is_singular( 'photo_story' ) ) return;
    $author_id = is_singular() ? (int) get_post_field( 'post_author', get_the_ID() ) : 0;
    PSAdRender::show( 'header_below', get_post_type(), $author_id );
} );

// 2. CONTENT AD — regular posts & pages (photo_story excluded)
add_filter( 'the_content', function ( $content ) {
    if ( ! is_singular() ) return $content;
    $post_type = get_post_type();
    if ( $post_type === 'photo_story' ) return $content;           // handled by template hooks
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return $content; // never inject ads into REST API

    $author_id = (int) get_post_field( 'post_author', get_the_ID() );

    $ad_top    = PSAdRender::show( 'content_top',    $post_type, $author_id, true );
    $ad_bottom = PSAdRender::show( 'content_bottom', $post_type, $author_id, true );

    // Split on </p> — keep the closing tag with each paragraph
    $paragraphs  = explode( '</p>', $content );
    $total_paras = count( $paragraphs );
    $ad_interval = 3;
    $result      = '';
    $real_count  = 0; // count only non-empty paragraphs

    foreach ( $paragraphs as $i => $para ) {
        $trimmed = trim( $para );
        if ( $trimmed === '' ) continue; // skip empty segments (last item after final </p>)

        $real_count++;
        $result .= $para . '</p>';

        // Inject between-paragraph ad — not after the very last real paragraph
        if ( $real_count % $ad_interval === 0 && $i < ( $total_paras - 1 ) ) {
            $result .= PSAdRender::show( 'content_between', $post_type, $author_id, true );
        }
    }

    return $ad_top . $result . $ad_bottom;
}, 10 );

// 3. SIDEBAR AD
add_action( 'ps_ad_sidebar', function () {
    $author_id = is_singular() ? (int) get_post_field( 'post_author', get_the_ID() ) : 0;
    PSAdRender::show( 'sidebar', get_post_type(), $author_id );
} );

// 4. FOOTER STICKY — no post_type / author dependency
add_action( 'wp_footer', function () {
    $ad_html = PSAdRender::show( 'footer_sticky', 'global', 0, true );
    if ( $ad_html ) {
        echo '<div class="ps-ad-sticky-footer" role="complementary" aria-label="' . esc_attr__( 'Sticky advertisement', 'fraudalert' ) . '">';
        echo $ad_html; // phpcs:ignore -- escaped inside render_ad_html
        echo '</div>';
    }
}, 99 );

// 5. ARCHIVE PAGES
add_action( 'ps_ad_archive_header', function () {
    if ( is_category() || is_tag() ) {
        PSAdRender::show( 'archive_header', 'category_tag', 0 );
    } elseif ( is_author() ) {
        // For author archives, author_id = queried user
        PSAdRender::show( 'archive_header', 'author', get_queried_object_id() );
    } else {
        PSAdRender::show( 'archive_header', 'global', 0 );
    }
} );

// 6. PHOTO STORY AD HOOKS — triggered inside single-photo_story.php
// author_id passed explicitly from template via get_post_field('post_author')
// Keys MUST match get_placements_for_tab('photo_story'): 'header','slides','footer'
add_action( 'ps_ad_render_header', function ( $author_id ) {
    PSAdRender::show( 'header', 'photo_story', absint( $author_id ) );
} );
add_action( 'ps_ad_render_slides', function ( $author_id ) {
    PSAdRender::show( 'slides', 'photo_story', absint( $author_id ) );
} );
add_action( 'ps_ad_render_end', function ( $author_id ) {
    PSAdRender::show( 'footer', 'photo_story', absint( $author_id ) );
} );

/* =============================================================
   EMERGENCY CTA BANNER (MOBILE ONLY)
   ============================================================= */
function fraudalert_emergency_cta_banner() {
    ?>
    <style>
        .fa-emergency-cta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #e63946;
            color: #fff;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border-radius: 8px;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .fa-ecta-content {
            flex: 1 1 100%;
        }
        .fa-ecta-title {
            margin: 0 0 0.5rem;
            font-size: 1.25rem;
            font-weight: 800;
            line-height: 1.3;
            color: #fff;
            font-family: var(--fh, 'Baloo 2', cursive);
        }
        .fa-ecta-desc {
            margin: 0;
            font-size: 0.85rem;
            opacity: 0.95;
            font-family: var(--fb, 'Inter', sans-serif);
        }
        .fa-ecta-actions {
            display: flex;
            gap: 0.75rem;
            flex: 1 1 100%;
        }
        .fa-ecta-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.6rem 1rem;
            border-radius: 6px;
            font-weight: 700;
            text-decoration: none;
            font-size: 0.9rem;
            flex: 1;
            transition: all 0.2s ease;
            font-family: var(--fu, 'Inter', sans-serif);
        }
        .fa-ecta-call {
            background-color: #fff;
            color: #e63946;
        }
        .fa-ecta-call:hover {
            background-color: #f8f9fa;
        }
        .fa-ecta-web {
            background-color: transparent;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.4);
        }
        .fa-ecta-web:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        @media (min-width: 768px) {
            .fa-emergency-cta {
                display: none !important;
            }
        }
    </style>
    <div class="fa-emergency-cta">
        <div class="fa-ecta-content">
            <h2 class="fa-ecta-title">फ्रॉड या धोखाधड़ी होने पर तुरंत रिपोर्ट करें!</h2>
            <p class="fa-ecta-desc">हर मिनट कीमती है। 72 घंटे के अंदर report करने पर refund की संभावना सबसे ज़्यादा रहती है।</p>
        </div>
        <div class="fa-ecta-actions">
            <a href="tel:1930" class="fa-ecta-btn fa-ecta-call">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-right:6px;"><path fill-rule="evenodd" d="M1.885.511a1.745 1.745 0 0 1 2.61.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.68.68 0 0 0 .178.643l2.457 2.457a.68.68 0 0 0 .644.178l2.189-.547a1.75 1.75 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.363-1.03-.038-2.136.702-2.877z"/></svg>
                1930 Call करें
            </a>
            <a href="https://cybercrime.gov.in" target="_blank" rel="noopener" class="fa-ecta-btn fa-ecta-web">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="margin-right:6px;"><path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m7.5-6.923c-.67.204-1.335.82-1.887 1.855A8 8 0 0 0 5.145 4H7.5zM4.09 4a9.3 9.3 0 0 1 .64-1.539 7 7 0 0 1 .597-.933A7.03 7.03 0 0 0 2.256 4zm-.582 3.5c.03-.877.138-1.718.312-2.5H1.674a7 7 0 0 0-.656 2.5zM4.847 5a12.5 12.5 0 0 0-.338 2.5H7.5V5zM8.5 5v2.5h2.99a12.5 12.5 0 0 0-.337-2.5zM4.51 8.5a12.5 12.5 0 0 0 .337 2.5H7.5V8.5zm3.99 0V11h2.653c.187-.765.306-1.608.338-2.5zM5.145 12c.138.386.295.744.468 1.068.552 1.035 1.218 1.65 1.887 1.855V12zm.182 2.472a7 7 0 0 1-.597-.933A9.3 9.3 0 0 1 4.09 12H2.255a7 7 0 0 0 3.072 2.472M3.82 11a13.7 13.7 0 0 1-.312-2.5h-2.49c.062.89.291 1.733.656 2.5zm6.853 3.472A7 7 0 0 0 13.745 12H11.91a9.3 9.3 0 0 1-.64 1.539 7 7 0 0 1-.597.933M8.5 12v2.923c.67-.204 1.335-.82 1.887-1.855q.26-.436.468-1.068zm3.68-1h2.146c.365-.767.594-1.61.656-2.5h-2.49a13.7 13.7 0 0 1-.312 2.5m2.802-3.5a7 7 0 0 0-.656-2.5H12.18c.174.782.282 1.623.312 2.5zM11.27 2.461c.247.464.462.98.64 1.539h1.835a7 7 0 0 0-3.072-2.472c.218.284.418.598.597.933M10.855 4a8 8 0 0 0-.468-1.068C9.835 1.897 9.17 1.282 8.5 1.077V4z"/></svg>
                Online Complaint
            </a>
        </div>
    </div>
    <?php
}

/* =============================================================
   SIDEBAR HELPLINE WIDGET
   ============================================================= */
function fraudalert_sidebar_helpline_widget() {
    ?>
    <style>
        .sidebar-helpline {
            background-color: #162f44;
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            font-family: var(--fu, 'Inter', sans-serif);
        }
        .sh-title {
            color: #fca311;
            font-weight: 700;
            margin-bottom: 16px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .sh-main {
            background-color: #4a4a4a;
            border-radius: 12px;
            padding: 24px 20px;
            text-align: center;
            margin-bottom: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .sh-icon {
            font-size: 28px;
            margin-bottom: 8px;
            line-height: 1;
            display: inline-block;
            background: linear-gradient(135deg, #ff4d4d, #c50e7b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0px 2px 4px rgba(0,0,0,0.2);
        }
        .sh-num {
            color: #ffb703;
            font-size: 48px;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 12px;
            font-family: var(--fh, 'Baloo 2', cursive);
            text-shadow: 0px 2px 4px rgba(0,0,0,0.3);
        }
        .sh-sub {
            color: #e0e0e0;
            font-size: 14px;
            margin-bottom: 6px;
            font-weight: 500;
        }
        .sh-micro {
            color: #a0a0a0;
            font-size: 12px;
        }
        .sh-link {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #213c54;
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 12px;
            color: #e0e0e0;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .sh-link:last-child {
            margin-bottom: 0;
        }
        .sh-link:hover {
            background-color: #2b4d6b;
            color: #fff;
            transform: translateX(4px);
        }
        .sh-link-arrow {
            color: #fca311;
            font-weight: bold;
        }
    </style>
    <div class="sidebar-helpline">
        <div class="sh-title">
            <svg width="12" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M11.251.068a.5.5 0 0 1 .227.58L9.677 6.5H13a.5.5 0 0 1 .364.843l-8 8.5a.5.5 0 0 1-.842-.49L6.323 9.5H3a.5.5 0 0 1-.364-.843l8-8.5a.5.5 0 0 1 .615-.09z"/></svg>
            तुरंत मदद चाहिए?
        </div>
        <div class="sh-main">
            <div class="sh-icon">📞</div>
            <div class="sh-num">1930</div>
            <div class="sh-sub">National Cyber Crime Helpline</div>
            <div class="sh-micro">24×7 उपलब्ध — free call</div>
        </div>
        <a href="https://cybercrime.gov.in" target="_blank" rel="noopener" class="sh-link">
            <span class="sh-link-left">🌐 cybercrime.gov.in पर complaint</span>
            <span class="sh-link-arrow">→</span>
        </a>
        <a href="#" class="sh-link">
            <span class="sh-link-left">💳 UPI Fraud — Bank dispute guide</span>
            <span class="sh-link-arrow">→</span>
        </a>
        <a href="#" class="sh-link">
            <span class="sh-link-left">📱 Fake App की पहचान कैसे करें</span>
            <span class="sh-link-arrow">→</span>
        </a>
        <a href="#" class="sh-link">
            <span class="sh-link-left">🔍 Scam Number Check करें</span>
            <span class="sh-link-arrow">→</span>
        </a>
    </div>
    <?php
}

/* =============================================================
   META INFO BOX (Byline, Date, Share)
   ============================================================= */
function fraudalert_meta_info_box() {
    $author_id = get_the_author_meta('ID');
    $author_name = get_the_author();
    $author_url = get_author_posts_url($author_id);
    $updated_date = get_the_modified_date('F j, Y g:i A');
    
    $post_type = get_post_type();
    $cat_name = '';
    $cat_link = '';
    if ($post_type === 'photo_story') {
        $terms = get_the_terms(get_the_ID(), 'photo_story_category');
        if ($terms && !is_wp_error($terms)) {
            $cat_name = $terms[0]->name;
            $cat_link = get_term_link($terms[0]->term_id);
        }
    } elseif ($post_type === 'post') {
        $cats = get_the_category();
        if (!empty($cats)) {
            $cat_name = $cats[0]->name;
            $cat_link = get_category_link($cats[0]->term_id);
        }
    }
    ?>
    <style>
        .fa-meta-box {
            background-color: var(--light, #f9f9f9);
            border: 1px solid var(--border, #eaeaea);
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 24px;
            font-family: var(--fu, 'Inter', sans-serif);
            font-size: 15px;
            color: var(--text, #444);
        }
        .fa-meta-row {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            gap: 10px;
            flex-wrap: wrap;
            line-height: 1.4;
        }
        .fa-meta-row:last-child {
            margin-bottom: 0;
        }
        .fa-meta-icon-svg {
            width: 18px;
            height: 18px;
            fill: var(--muted, #888);
        }
        .fa-meta-box a {
            color: var(--nav3, #0077b6);
            text-decoration: none;
            font-weight: 500;
        }
        .fa-meta-box a:hover {
            text-decoration: underline;
        }
    </style>
    <div class="fa-meta-box">
        <div class="fa-meta-row">
            <svg class="fa-meta-icon-svg" viewBox="0 0 24 24"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20a2 2 0 002 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/></svg>
            <span>Last updated on - <?php echo esc_html($updated_date); ?> IST</span>
        </div>
        <div class="fa-meta-row">
            <svg class="fa-meta-icon-svg" viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
            <span>By <a href="<?php echo esc_url($author_url); ?>"><?php echo esc_html($author_name); ?></a></span>
            
            <?php if ($cat_name) : ?>
                <span style="color: var(--muted, #888); opacity: 0.5;">|</span>
                <svg class="fa-meta-icon-svg" viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                <span><a href="<?php echo esc_url($cat_link); ?>" style="color:var(--saf, #E85D04);"><?php echo esc_html($cat_name); ?></a></span>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
