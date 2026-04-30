<?php
/**
 * Photo Story CPT — Pure WordPress, No Plugins
 * File:  inc/photo-story-cpt.php
 * Theme: fraudalert-theme-child
 */
defined( 'ABSPATH' ) || exit;

if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error is-dismissible">
        <p>Photo Story CPT: WordPress 6.0+ required.</p>
        </div>';
    } );
    return;
}
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error is-dismissible">
        <p>Photo Story CPT: PHP 7.4+ required.</p>
        </div>';
    } );
    return;
}

defined( 'PS_SLIDES' )   || define( 'PS_SLIDES',   'ps_gallery_slides' );
defined( 'PS_IMAGE' )    || define( 'PS_IMAGE',    'slide_image_id' );
defined( 'PS_CAP_T' )    || define( 'PS_CAP_T',    'slide_caption_title' );
defined( 'PS_CAP_X' )    || define( 'PS_CAP_X',    'slide_caption_text' );
defined( 'PS_INTRO' )    || define( 'PS_INTRO',    'ps_story_intro' );
defined( 'PS_TYPE' )     || define( 'PS_TYPE',     'ps_story_type' );
defined( 'PS_FEATURED' ) || define( 'PS_FEATURED', 'ps_is_featured' );
defined( 'PS_CREDITS' )  || define( 'PS_CREDITS',  'ps_photo_credits' );

class PhotoStoryCPT {

    public static function init() { new self(); }

    private function __construct() {
        add_action( 'init',                  [ $this, 'register_cpt' ],      0 );
        add_action( 'init',                  [ $this, 'register_taxonomy' ], 0 );
        add_action( 'after_switch_theme',    [ $this, 'flush_rules_once' ] );
        add_image_size( 'photo-story-16x9', 1280, 720, true );
        add_filter( 'image_size_names_choose',              [ $this, 'add_image_size' ] );
        add_action( 'wp_enqueue_scripts',                   [ $this, 'enqueue_assets' ], 10 );
        add_action( 'admin_enqueue_scripts',                [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_head',                              [ $this, 'render_seo_head' ], 1 );
        add_action( 'add_meta_boxes',                       [ $this, 'register_meta_boxes' ] );
        add_action( 'save_post',                            [ $this, 'save_meta' ],   10, 2 );
        add_action( 'save_post',                            [ $this, 'clear_cache' ], 20, 2 );
        add_filter( 'manage_photo_story_posts_columns',     [ $this, 'custom_columns' ] );
        add_action( 'manage_photo_story_posts_custom_column', [ $this, 'custom_column_data' ], 10, 2 );
    }

    public function register_taxonomy(): void {
        $labels = [
            'name'              => _x( 'Story Categories', 'taxonomy general name', 'fraudalert' ),
            'singular_name'     => _x( 'Story Category', 'taxonomy singular name', 'fraudalert' ),
            'search_items'      => __( 'Search Categories', 'fraudalert' ),
            'all_items'         => __( 'All Categories', 'fraudalert' ),
            'parent_item'       => __( 'Parent Category', 'fraudalert' ),
            'parent_item_colon' => __( 'Parent Category:', 'fraudalert' ),
            'edit_item'         => __( 'Edit Category', 'fraudalert' ),
            'update_item'       => __( 'Update Category', 'fraudalert' ),
            'add_new_item'      => __( 'Add New Category', 'fraudalert' ),
            'new_item_name'     => __( 'New Category Name', 'fraudalert' ),
            'menu_name'         => __( 'Categories', 'fraudalert' ),
        ];
        register_taxonomy( 'photo_story_category', [ 'photo_story' ], [
            'hierarchical' => true, 'labels' => $labels,
            'show_ui' => true, 'show_admin_column' => true,
            'query_var' => true, 'show_in_rest' => true,
            'rewrite' => [ 'slug' => 'photo-story-category', 'with_front' => false ],
        ] );
    }

    public function register_cpt(): void {
        $labels = [
            'name'               => _x( 'Photo Stories', 'post type general name', 'fraudalert' ),
            'singular_name'      => _x( 'Photo Story', 'post type singular name', 'fraudalert' ),
            'menu_name'          => _x( 'Photo Stories', 'admin menu', 'fraudalert' ),
            'name_admin_bar'     => _x( 'Photo Story', 'add new on admin bar', 'fraudalert' ),
            'add_new'            => __( 'Add New Photo Story', 'fraudalert' ),
            'add_new_item'       => __( 'Add New Photo Story', 'fraudalert' ),
            'new_item'           => __( 'New Story', 'fraudalert' ),
            'edit_item'          => __( 'Edit Story', 'fraudalert' ),
            'view_item'          => __( 'View Story', 'fraudalert' ),
            'all_items'          => __( 'All Photo Stories', 'fraudalert' ),
            'search_items'       => __( 'Search Stories', 'fraudalert' ),
            'not_found'          => __( 'No stories found.', 'fraudalert' ),
            'not_found_in_trash' => __( 'No stories found in trash.', 'fraudalert' ),
            'featured_image'     => __( 'Story Cover Image', 'fraudalert' ),
            'set_featured_image' => __( 'Set Cover Image', 'fraudalert' ),
            'remove_featured_image' => __( 'Remove Cover Image', 'fraudalert' ),
            'use_featured_image' => __( 'Use as Cover Image', 'fraudalert' ),
            'archives'           => __( 'Story Archives', 'fraudalert' ),
        ];
        register_post_type( 'photo_story', [
            'labels' => $labels, 'public' => true, 'publicly_queryable' => true,
            'show_ui' => true, 'show_in_menu' => true, 'show_in_nav_menus' => true,
            'show_in_admin_bar' => true, 'show_in_rest' => true, 'query_var' => true,
            'capability_type' => 'post', 'has_archive' => true, 'hierarchical' => false,
            'menu_position' => 5, 'menu_icon' => 'dashicons-format-gallery',
            'can_export' => true, 'delete_with_user' => false,
            'rewrite' => [ 'slug' => 'photo-story', 'with_front' => false, 'feeds' => true, 'pages' => false ],
            'supports' => [ 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'comments' ],
            'taxonomies' => [ 'photo_story_category' ],
        ] );
    }

    public function flush_rules_once(): void {
        flush_rewrite_rules( false );
    }

    public function add_image_size( array $sizes ): array {
        $sizes['photo-story-16x9'] = __( 'Photo Story 16:9 (1280x720)', 'fraudalert' );
        return $sizes;
    }

    public function enqueue_assets(): void {
        if ( ! is_singular( 'photo_story' ) ) return;
        $css = get_stylesheet_directory() . '/css/photo-story.css';
        $js  = get_stylesheet_directory() . '/js/photo-story.js';
        if ( is_file( $css ) ) {
            wp_enqueue_style( 'photo-story-css',
                get_stylesheet_directory_uri() . '/css/photo-story.css',
                [], (string) filemtime( $css )
            );
        }
        if ( is_file( $js ) ) {
            wp_enqueue_script( 'photo-story-js',
                get_stylesheet_directory_uri() . '/js/photo-story.js',
                [], (string) filemtime( $js ), true
            );
            // wp_localize_script added here only when AJAX handlers are implemented.
        }
    }

    public function enqueue_admin_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'photo_story' ) return;

        // Media uploader dependency
        wp_enqueue_media();

        $css = get_stylesheet_directory() . '/css/photo-story.css';
        if ( is_file( $css ) ) {
            wp_enqueue_style(
                'photo-story-admin-css',
                get_stylesheet_directory_uri() . '/css/photo-story.css',
                [ 'wp-admin' ],
                (string) filemtime( $css )
            );
        }

        $js = get_stylesheet_directory() . '/js/photo-story-admin.js';
        if ( is_file( $js ) ) {
            wp_enqueue_script(
                'photo-story-admin-js',
                get_stylesheet_directory_uri() . '/js/photo-story-admin.js',
                [ 'wp-util' ], // wp-util ensures wp object is available for wp.media()
                (string) filemtime( $js ),
                true
            );
        }
    }

    public function register_meta_boxes(): void {
        add_meta_box( 'ps_slides_box', __( 'Gallery Slides', 'fraudalert' ),
            [ $this, 'render_slides_box' ], 'photo_story', 'normal', 'high' );
        add_meta_box( 'ps_details_box', __( 'Story Details', 'fraudalert' ),
            [ $this, 'render_details_box' ], 'photo_story', 'side', 'default' );
    }

    public function render_slides_box( \WP_Post $post ): void {
        wp_nonce_field( 'ps_save_meta_action', 'ps_meta_nonce' );
        $slides = get_post_meta( $post->ID, PS_SLIDES, true );
        if ( ! is_array( $slides ) ) $slides = [];
        ?>
        <style>
            .ps-row{background:#f9f9f9;border:1px solid #ddd;border-radius:6px;padding:12px;margin-bottom:10px;transition:opacity .2s}
            .ps-row .ps-hd{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid #eee}
            .ps-row .ps-hd strong{font-size:14px;color:#003459}
            .ps-bd{display:flex;gap:16px}
            .ps-ic{width:220px;flex-shrink:0}
            .ps-tc{flex:1}
            .ps-pv img{max-width:200px;height:auto;border-radius:4px;border:1px solid #ddd;margin-bottom:8px}
            .ps-ph{width:200px;height:112px;background:#e8ddd0;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#999;font-size:13px;margin-bottom:8px}
            .ps-drag{cursor:grab;font-size:18px;color:#999;margin-right:8px;user-select:none}
            .ps-drag:active{cursor:grabbing}
            .ps-row.ps-dragging{opacity:.4;border-style:dashed}
            .ps-row.ps-over{border-top:3px solid #003459}
        </style>
        <div id="ps-slides-wrap">
        <?php foreach ( $slides as $i => $slide ) :
            $img_id  = absint( $slide[ PS_IMAGE ] ?? 0 );
            $cap_t   = $slide[ PS_CAP_T ] ?? '';
            $cap_x   = $slide[ PS_CAP_X ] ?? '';
            $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
        ?>
            <div class="ps-row" draggable="true" data-index="<?php echo esc_attr( $i ); ?>">
                <div class="ps-hd">
                    <span class="ps-drag">&#9776;</span>
                    <strong>Slide <span class="ps-num"><?php echo (int) $i + 1; ?></span></strong>
                    <button type="button" class="button ps-rm">Remove</button>
                </div>
                <div class="ps-bd">
                    <div class="ps-ic">
                        <input type="hidden" name="ps_slides[<?php echo (int) $i; ?>][slide_image_id]" value="<?php echo esc_attr( $img_id ); ?>" class="ps-img-id">
                        <div class="ps-pv">
                            <?php if ( $img_url ) : ?>
                                <img src="<?php echo esc_url( $img_url ); ?>">
                            <?php else : ?>
                                <div class="ps-ph">No image</div>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button ps-up">Choose Image</button>
                    </div>
                    <div class="ps-tc">
                        <p>
                            <label><strong>Caption Title</strong> <small>(max 120)</small></label><br>
                            <input type="text" name="ps_slides[<?php echo (int) $i; ?>][slide_caption_title]" value="<?php echo esc_attr( $cap_t ); ?>" class="widefat" maxlength="120">
                        </p>
                        <p>
                            <label><strong>Caption Text</strong> <small>(max 300)</small></label><br>
                            <textarea name="ps_slides[<?php echo (int) $i; ?>][slide_caption_text]" class="widefat" rows="3" maxlength="300"><?php echo esc_textarea( $cap_x ); ?></textarea>
                        </p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <p style="margin-top:12px"><button type="button" class="button button-primary" id="ps-add-slide">+ Add Slide</button></p>
        <?php
    }

    public function render_details_box( \WP_Post $post ): void {
        $intro    = get_post_meta( $post->ID, PS_INTRO, true );
        $type     = get_post_meta( $post->ID, PS_TYPE, true );
        $featured = get_post_meta( $post->ID, PS_FEATURED, true );
        $credits  = get_post_meta( $post->ID, PS_CREDITS, true );
        $types = [
            '' => '-- Select --', 'celebrity' => 'Celebrity',
            'movies' => 'Movies', 'sports' => 'Sports',
            'fashion' => 'Fashion', 'travel' => 'Travel',
        ];
        ?>
        <p>
            <label for="ps-intro"><strong>Story Intro</strong></label><br>
            <textarea id="ps-intro" name="<?php echo esc_attr( PS_INTRO ); ?>" rows="4" class="widefat" placeholder="Short intro text..."><?php echo esc_textarea( $intro ); ?></textarea>
        </p>
        <p>
            <label for="ps-type"><strong>Story Type</strong></label><br>
            <select id="ps-type" name="<?php echo esc_attr( PS_TYPE ); ?>" class="widefat">
                <?php foreach ( $types as $val => $lbl ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $type, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label>
                <input type="checkbox" name="<?php echo esc_attr( PS_FEATURED ); ?>" value="1" <?php checked( $featured, '1' ); ?>>
                <strong>Featured Story</strong>
            </label>
        </p>
        <p>
            <label for="ps-credits"><strong>Photo Credits</strong></label><br>
            <input type="text" id="ps-credits" name="<?php echo esc_attr( PS_CREDITS ); ?>" value="<?php echo esc_attr( $credits ); ?>" class="widefat" placeholder="Photographer / Source">
        </p>
        <?php
    }

    public function save_meta( int $post_id, \WP_Post $post ): void {
        if ( $post->post_type !== 'photo_story' ) return;
        if ( ! isset( $_POST['ps_meta_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['ps_meta_nonce'], 'ps_save_meta_action' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $raw = isset( $_POST['ps_slides'] ) && is_array( $_POST['ps_slides'] ) ? $_POST['ps_slides'] : [];
        $clean = [];
        foreach ( $raw as $slide ) {
            $img = absint( $slide['slide_image_id'] ?? 0 );
            $ct  = sanitize_text_field( $slide['slide_caption_title'] ?? '' );
            $cx  = wp_kses_post( $slide['slide_caption_text'] ?? '' );
            if ( mb_strlen( $ct ) > 120 ) $ct = mb_substr( $ct, 0, 120 );
            if ( mb_strlen( $cx ) > 300 ) $cx = mb_substr( $cx, 0, 300 );
            $clean[] = [ PS_IMAGE => $img, PS_CAP_T => $ct, PS_CAP_X => $cx ];
        }
        update_post_meta( $post_id, PS_SLIDES, $clean );

        if ( isset( $_POST[ PS_INTRO ] ) )
            update_post_meta( $post_id, PS_INTRO, sanitize_textarea_field( $_POST[ PS_INTRO ] ) );
        $allowed_types = [ '', 'celebrity', 'movies', 'sports', 'fashion', 'travel' ];
        if ( isset( $_POST[ PS_TYPE ] ) ) {
            $type_val = sanitize_text_field( $_POST[ PS_TYPE ] );
            if ( in_array( $type_val, $allowed_types, true ) ) {
                update_post_meta( $post_id, PS_TYPE, $type_val );
            }
        }
        if ( ! empty( $_POST[ PS_FEATURED ] ) ) {
            update_post_meta( $post_id, PS_FEATURED, '1' );
        } else {
            delete_post_meta( $post_id, PS_FEATURED );
        }
        if ( isset( $_POST[ PS_CREDITS ] ) )
            update_post_meta( $post_id, PS_CREDITS, sanitize_text_field( $_POST[ PS_CREDITS ] ) );
    }

    public function clear_cache( int $post_id, \WP_Post $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( $post->post_type !== 'photo_story' ) return;

        if ( class_exists( 'FraudAlert_HTML_Cache' ) ) FraudAlert_HTML_Cache::purge_all();

        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s
             OR option_name LIKE %s OR option_name LIKE %s",
            '_transient_ps_read_'    . $post_id . '_%',
            '_transient_timeout_ps_read_' . $post_id . '_%',
            '_transient_ps_related_' . $post_id . '_%',
            '_transient_timeout_ps_related_' . $post_id . '_%'
        ) );
    }

    public function custom_columns( array $columns ): array {
        $new = [];
        foreach ( $columns as $key => $label ) {
            if ( $key === 'title' ) $new['ps_thumb'] = __( 'Thumb', 'fraudalert' );
            $new[ $key ] = $label;
            if ( $key === 'title' ) $new['ps_slides'] = __( 'Slides', 'fraudalert' );
        }
        return $new;
    }

    public function custom_column_data( string $column, int $post_id ): void {
        if ( $column === 'ps_thumb' ) {
            $thumb = get_the_post_thumbnail( $post_id, [ 60, 40 ] );
            if ( $thumb ) {
                echo '<div style="width:60px;height:40px;overflow:hidden;border-radius:4px">' . $thumb . '</div>';
            } else {
                echo '<span style="color:#999">—</span>';
            }
        }
        if ( $column === 'ps_slides' ) {
            $slides = get_post_meta( $post_id, PS_SLIDES, true );
            $count  = is_array( $slides ) ? count( $slides ) : 0;
            echo '<strong>' . esc_html( (string) $count ) . '</strong>';
        }
    }

    /**
     * SEO: meta description + Canonical + Open Graph + Twitter Card + JSON-LD
     * Hooked to wp_head at priority 1 (runs before theme/plugin meta).
     * Skipped entirely if Rank Math, Yoast, SEOPress, or The SEO Framework is active.
     */
    public function render_seo_head(): void {
        if ( ! is_singular( 'photo_story' ) ) return;

        // ── 1. CONFLICT CHECK ──────────────────────────────────────
        if (
            defined( 'RANK_MATH_VERSION' )         ||
            defined( 'WPSEO_VERSION' )             ||
            defined( 'SEOPRESS_VERSION' )          ||
            defined( 'THE_SEO_FRAMEWORK_VERSION' )
        ) return;

        $post_id = get_the_ID();
        if ( ! $post_id ) return; // guard: not in a valid query context

        $slides = get_post_meta( $post_id, PS_SLIDES, true );
        $slides = is_array( $slides ) ? $slides : [];
        $intro  = (string) get_post_meta( $post_id, PS_INTRO, true );

        // ── 2. FIRST IMAGE — slide 1, fallback to featured image ───
        $first_img = '';
        if ( ! empty( $slides[0][ PS_IMAGE ] ) ) {
            $first_img = (string) wp_get_attachment_image_url(
                absint( $slides[0][ PS_IMAGE ] ), 'large'
            );
        }
        if ( ! $first_img ) {
            // Fallback: use the post's featured image when no slide image exists
            $first_img = (string) get_the_post_thumbnail_url( $post_id, 'large' );
        }

        // ── TITLE & DESCRIPTION ────────────────────────────────────
        $title    = get_the_title( $post_id );
        $raw_desc = $intro ? $intro : get_the_excerpt( $post_id );
        $description = mb_substr( wp_strip_all_tags( $raw_desc ), 0, 150 );
        if ( '' === $description ) {
            $description = mb_substr( $title, 0, 150 ); // ultimate fallback
        }
        $url = (string) get_permalink( $post_id );

        // ── 3. META TAGS ───────────────────────────────────────────

        // Standard HTML description — used by Google for SERPs
        echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";

        // Canonical
        echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";

        // Open Graph
        echo '<meta property="og:type"        content="article">' . "\n";
        echo '<meta property="og:url"         content="' . esc_url( $url ) . '">' . "\n";
        echo '<meta property="og:title"       content="' . esc_attr( $title ) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
        echo '<meta property="og:site_name"   content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
        if ( $first_img ) {
            echo '<meta property="og:image"        content="' . esc_url( $first_img ) . '">' . "\n";
            // Known hard-crop dimensions: photo-story-16x9 = 1280 × 720
            echo '<meta property="og:image:width"  content="1280">' . "\n";
            echo '<meta property="og:image:height" content="720">' . "\n";
            echo '<meta property="og:image:type"   content="image/jpeg">' . "\n";
        }

        // Twitter Card
        echo '<meta name="twitter:card"        content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title"       content="' . esc_attr( $title ) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
        if ( $first_img ) {
            echo '<meta name="twitter:image" content="' . esc_url( $first_img ) . '">' . "\n";
        }

        // ── 4. JSON-LD ─────────────────────────────────────────────
        $imgs = [];
        foreach ( array_slice( $slides, 0, 10 ) as $s ) {
            if ( ! empty( $s[ PS_IMAGE ] ) ) {
                $img_url = wp_get_attachment_image_url( absint( $s[ PS_IMAGE ] ), 'large' );
                if ( $img_url ) {
                    $imgs[] = esc_url_raw( $img_url ); // esc_url_raw for non-HTML JSON context
                }
            }
        }

        $logo_url  = get_site_icon_url( 112 );
        $author_id = (int) get_post_field( 'post_author', $post_id );

        $data = [
            '@context'      => 'https://schema.org',
            '@type'         => 'ImageGallery',
            'name'          => $title,
            'description'   => $description,
            'url'           => $url,
            'author'        => [
                '@type' => 'Person',
                'name'  => get_the_author_meta( 'display_name', $author_id ),
            ],
            'datePublished' => get_the_date( 'c', $post_id ),
            'dateModified'  => get_the_modified_date( 'c', $post_id ),
            'publisher'     => [
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
            ],
        ];

        // Only include 'image' key when we have actual slide images
        if ( ! empty( $imgs ) ) {
            $data['image'] = $imgs;
        }

        if ( $logo_url ) {
            $data['publisher']['logo'] = [
                '@type' => 'ImageObject',
                'url'   => esc_url_raw( $logo_url ),
            ];
        }

        /*
         * JSON_HEX_TAG converts < > to \u003C \u003E inside JSON strings.
         * This prevents </script> in a post title from breaking the ld+json block (XSS).
         * JSON_UNESCAPED_UNICODE preserves Hindi/Devanagari characters as-is.
         * JSON_UNESCAPED_SLASHES keeps URLs readable.
         */
        $json = wp_json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
        );

        if ( $json ) {
            echo '<script type="application/ld+json">' . "\n";
            echo $json; // phpcs:ignore WordPress.Security.EscapeOutput -- safe: json_encode + JSON_HEX_TAG
            echo "\n" . '</script>' . "\n";
        }
    }
}

add_action( 'after_setup_theme', [ 'PhotoStoryCPT', 'init' ] );