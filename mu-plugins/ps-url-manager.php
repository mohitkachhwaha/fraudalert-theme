<?php
/**
 * PS URL Manager — Must Use Plugin
 *
 * Permanently removes duplicate/harmful WordPress pages for SEO.
 * Survives ALL WordPress core updates and theme changes.
 *
 * ✅ Allowed:  Posts, Photo Story, Pages, Categories, Tags, Author pages
 * ❌ Blocked:  Date archives, Attachments, Post formats, Comment feeds,
 *              Search pages (noindex), Author ID URLs, REST API crawling,
 *              RSS feeds (noindex), Numeric author query strings
 *
 * @package FraudAlert
 * @version 2.0
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════
// 1. REDIRECT UNWANTED PAGES → 301
//    All redirects happen before template loads (priority 1).
// ═══════════════════════════════════════════════════════════════
add_action( 'template_redirect', 'ps_redirect_unwanted_pages', 1 );

function ps_redirect_unwanted_pages(): void {

    // ── Date archives (year / month / day) ──────────────────
    if ( is_date() ) {
        wp_redirect( home_url( '/' ), 301 );
        exit;
    }

    // ── Attachment pages → parent post or home ──────────────
    if ( is_attachment() ) {
        $post_id    = get_the_ID();
        $parent_id  = $post_id ? wp_get_post_parent_id( $post_id ) : 0;
        $target_url = ( $parent_id > 0 )
            ? get_permalink( $parent_id )
            : home_url( '/' );

        wp_redirect( esc_url_raw( $target_url ), 301 );
        exit;
    }

    // ── Post format archives → home ──────────────────────────
    if ( is_tax( 'post_format' ) ) {
        wp_redirect( home_url( '/' ), 301 );
        exit;
    }

    // ── Comment feeds → home ─────────────────────────────────
    // Covers: /comments/feed/, /post-name/feed/, etc.
    if ( is_comment_feed() ) {
        wp_redirect( home_url( '/' ), 301 );
        exit;
    }

    // ── Author ID URL → Author slug URL ──────────────────────
    // Redirects /?author=1 → /author/username/ (prevents user ID leakage)
    if ( is_author() && isset( $_GET['author'] ) ) {
        $author = get_queried_object();
        if ( $author instanceof WP_User ) {
            wp_redirect( esc_url_raw( get_author_posts_url( $author->ID, $author->user_nicename ) ), 301 );
            exit;
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// 2. DISABLE ATTACHMENT PERMALINK PAGES (WordPress 6.4+)
// ═══════════════════════════════════════════════════════════════
add_filter( 'wp_attachment_pages_enabled', '__return_false' );

// ═══════════════════════════════════════════════════════════════
// 3. NOINDEX META — Search, Feeds, and any missed pages
//    Search pages are useful for visitors but bad for Google.
//    Feeds are kept functional but noindexed.
// ═══════════════════════════════════════════════════════════════
add_action( 'wp_head', 'ps_noindex_seo_harmful_pages', 1 );

function ps_noindex_seo_harmful_pages(): void {

    $noindex = (
        is_date()            ||   // Date archives (fallback)
        is_attachment()      ||   // Attachment pages (fallback)
        is_tax( 'post_format' ) || // Post format archives
        is_search()               // Search results — noindex, don't redirect
    );

    if ( $noindex ) {
        // noindex: don't index. follow: still crawl links on page.
        echo '<meta name="robots" content="noindex, follow">' . PHP_EOL;
    }
}

// ═══════════════════════════════════════════════════════════════
// 4. RSS/ATOM FEEDS — X-Robots-Tag: noindex
//    Feeds work normally (visitors/RSS readers can use them),
//    but Google won't index them as web pages.
// ═══════════════════════════════════════════════════════════════
add_action( 'template_redirect', function (): void {
    // Only apply to main feed and post feeds (not comment feeds — those are redirected above)
    if ( is_feed() && ! is_comment_feed() ) {
        header( 'X-Robots-Tag: noindex', true );
    }
}, 2 );

// ═══════════════════════════════════════════════════════════════
// 5. ROBOTS.TXT — Block crawlers from irrelevant paths
//    Saves crawl budget for important pages.
// ═══════════════════════════════════════════════════════════════
add_filter( 'robots_txt', 'ps_custom_robots_txt', 10, 2 );

function ps_custom_robots_txt( string $output, string $public ): string {

    $additions  = "\n# === FraudAlert SEO Rules ===\n";
    $additions .= "User-agent: *\n";

    // Block REST API — no SEO value, wastes crawl budget
    $additions .= "Disallow: /wp-json/\n";

    // Block search results — dynamic, thin content
    $additions .= "Disallow: /?s=\n";
    $additions .= "Disallow: /search/\n";

    // Block admin areas (usually already blocked, but explicit)
    $additions .= "Disallow: /wp-admin/\n";
    $additions .= "Disallow: /wp-login.php\n";

    // Block author ID query (numeric) — use /author/slug/ instead
    $additions .= "Disallow: /?author=\n";

    // Block attachment pages
    $additions .= "Disallow: /?attachment_id=\n";

    // Block date archives explicitly
    $additions .= "Disallow: /20";   // Blocks /2020/, /2021/, /2024/, etc.
    $additions .= "\n";

    // Block tracking/utility params
    $additions .= "Disallow: /*?replytocom=\n";
    $additions .= "Disallow: /*?preview=\n";

    // Allow important paths explicitly
    $additions .= "\nAllow: /wp-admin/admin-ajax.php\n";
    $additions .= "Allow: /wp-content/uploads/\n";

    return $output . $additions;
}

// ═══════════════════════════════════════════════════════════════
// 6. REMOVE DATE ARCHIVE REWRITE RULES
//    WordPress won't even generate /2024/ type URLs.
// ═══════════════════════════════════════════════════════════════
add_filter( 'date_rewrite_rules', '__return_empty_array' );

// ═══════════════════════════════════════════════════════════════
// 7. FIX DATE/ARCHIVE LINK FUNCTIONS
//    get_year_link(), get_month_link() etc. return home URL.
// ═══════════════════════════════════════════════════════════════
add_filter( 'year_link',  'ps_replace_date_link_with_home' );
add_filter( 'month_link', 'ps_replace_date_link_with_home' );
add_filter( 'day_link',   'ps_replace_date_link_with_home' );

function ps_replace_date_link_with_home(): string {
    return home_url( '/' );
}

// ═══════════════════════════════════════════════════════════════
// 8. BLOCK UNWANTED POST TYPE ARCHIVE LINKS
//    Only photo_story archive is allowed.
// ═══════════════════════════════════════════════════════════════
add_filter( 'post_type_archive_link', 'ps_maybe_block_archive_link', 10, 2 );

function ps_maybe_block_archive_link( string $link, string $post_type ): string {
    $allowed = [ 'photo_story' ];
    return in_array( $post_type, $allowed, true ) ? $link : home_url( '/' );
}

// ═══════════════════════════════════════════════════════════════
// 9. CANONICAL TAG FOR PAGINATED CONTENT
//    /page/2/ etc. point canonical to page 1.
//    Prevents pagination from being treated as duplicate.
// ═══════════════════════════════════════════════════════════════
add_action( 'wp_head', 'ps_canonical_for_paged', 2 );

function ps_canonical_for_paged(): void {
    global $wp_query;

    // Only for paginated archives/home (page 2+)
    if ( is_paged() && ( is_category() || is_tag() || is_author() || is_home() ) ) {
        // Get page 1 URL (remove /page/N/)
        $page1_url = '';

        if ( is_category() ) {
            $page1_url = get_category_link( get_queried_object_id() );
        } elseif ( is_tag() ) {
            $page1_url = get_tag_link( get_queried_object_id() );
        } elseif ( is_author() ) {
            $page1_url = get_author_posts_url( get_queried_object_id() );
        } elseif ( is_home() ) {
            $page1_url = home_url( '/' );
        }

        if ( $page1_url ) {
            printf(
                '<link rel="canonical" href="%s">' . PHP_EOL,
                esc_url( $page1_url )
            );
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// 10. DISABLE XMLRPC (Security + Crawl Budget)
//     XML-RPC is outdated and a common attack vector.
// ═══════════════════════════════════════════════════════════════
add_filter( 'xmlrpc_enabled', '__return_false' );

// Remove xmlrpc header advertisement
add_filter( 'wp_headers', function( array $headers ): array {
    unset( $headers['X-Pingback'] );
    return $headers;
} );

// Remove xmlrpc from head
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );

// ═══════════════════════════════════════════════════════════════
// 11. REMOVE GENERATOR TAG (Security)
//     Hides WordPress version from source code.
// ═══════════════════════════════════════════════════════════════
remove_action( 'wp_head', 'wp_generator' );

// ═══════════════════════════════════════════════════════════════
// 12. FLUSH REWRITE RULES ONCE ON FIRST ACTIVATION
// ═══════════════════════════════════════════════════════════════
add_action( 'init', 'ps_url_manager_flush_once' );

function ps_url_manager_flush_once(): void {
    $key = 'ps_url_manager_flushed_v2'; // bumped to v2 to re-flush

    if ( ! get_option( $key ) ) {
        flush_rewrite_rules( false );
        update_option( $key, true, false );
    }
}
