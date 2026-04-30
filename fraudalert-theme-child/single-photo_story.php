<?php
/**
 * Single Template for Photo Story
 * Theme: fraudalert-theme-child
 */

get_header();

$post_id     = get_the_ID();
$slides      = get_post_meta($post_id, PS_SLIDES, true);
$slides      = is_array($slides) ? $slides : [];
$total       = count($slides);
$intro       = get_post_meta($post_id, PS_INTRO, true);
$story_type  = get_post_meta($post_id, PS_TYPE, true);
$terms    = get_the_terms($post_id, 'photo_story_category');
$cat_name = 'Uncategorized';
$cat_id   = 0;
if ($terms && !is_wp_error($terms)) {
    $cat_name = $terms[0]->name;
    $cat_id   = $terms[0]->term_id;
}

// Author info — safe outside loop
$ps_author_id  = (int) get_post_field('post_author', $post_id);
$author_name   = get_the_author_meta('display_name', $ps_author_id);
$author_url    = get_author_posts_url($ps_author_id);
$author_bio    = get_the_author_meta('description', $ps_author_id);
?>


<main class="single-post-wrap" id="main-content">
    <div class="single-layout">
        <div class="ps-container" style="max-width:100%; margin:0;">
            <?php while (have_posts()) : the_post(); ?>
                <header class="ps-header">
                    <div class="ps-breadcrumb">
                        <a href="<?php echo esc_url(home_url('/')); ?>">Home</a> &gt; 
                        <?php if ($cat_id > 0) :
                            $term_url = get_term_link($cat_id, 'photo_story_category');
                            if (!is_wp_error($term_url)) : ?>
                                <a href="<?php echo esc_url($term_url); ?>"><?php echo esc_html($cat_name); ?></a> &gt; 
                            <?php else : ?>
                                <?php echo esc_html($cat_name); ?> &gt; 
                            <?php endif;
                        else : ?>
                            <?php echo esc_html($cat_name); ?> &gt; 
                        <?php endif; ?>
                        <span><?php echo esc_html(get_the_title()); ?></span>
                    </div>
                    
                    <div class="ps-badges">
                        <span class="bdg bdg-r"><?php echo esc_html($total); ?> Photos</span>
                    </div>
                    
                    <h1 class="ps-title"><?php echo esc_html(get_the_title()); ?></h1>
                    <?php 
                    $summary = get_the_excerpt();
                    if (!empty($summary)) : 
                    ?>
                        <div class="ps-excerpt" style="font-family:var(--fu, sans-serif); font-size:17px; color:var(--muted, #666); line-height:1.6; margin-top:0.5rem; margin-bottom:1.5rem;">
                            <?php echo $summary; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php fraudalert_meta_info_box(); ?>
                </header>

                <?php if (!empty($intro)) : ?>
                    <div class="ps-intro">
                        <?php echo wp_kses_post($intro); ?>
                    </div>
                <?php endif; ?>

                <div class="ps-slides-container">
                    <?php 
                    $slide_counter = 0;
                    foreach ($slides as $slide) : 
                        $slide_counter++;
                        $img_id    = absint($slide[PS_IMAGE] ?? 0);
                        $cap_title = $slide[PS_CAP_T] ?? '';
                        $cap_text  = $slide[PS_CAP_X] ?? '';
                        $is_first  = ($slide_counter === 1);
                        ?>
                        <article class="ps-slide" id="slide-<?php echo esc_attr($slide_counter); ?>">
                            <div class="ps-slide-media">
                                <?php
                                if ($img_id) :
                                    echo wp_get_attachment_image($img_id, 'photo-story-16x9', false, [
                                        'loading'       => $is_first ? 'eager' : 'lazy',
                                        'fetchpriority' => $is_first ? 'high'  : 'auto',
                                        'decoding'      => $is_first ? 'sync'  : 'async',
                                        'alt'           => $cap_title,
                                        'class'         => 'ps-slide-img',
                                    ]);
                                else :
                                    echo '<div class="ps-img-placeholder" style="width:100%;aspect-ratio:16/9;background:var(--light);display:flex;align-items:center;justify-content:center;color:var(--muted);font-family:var(--fu);">No Image</div>';
                                endif;
                                ?>
                                <div class="ps-slide-counter"><?php echo esc_html($slide_counter . '/' . $total); ?></div>
                            </div>
                            
                            <div class="ps-slide-content">
                                <?php if ($cap_title) : ?>
                                    <h2 class="ps-caption-title"><?php echo esc_html($cap_title); ?></h2>
                                <?php endif; ?>
                                
                                <?php if ($cap_text) : ?>
                                    <div class="ps-caption-text">
                                        <?php echo wp_kses_post($cap_text); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>

                        <?php 
                        // Also Read — shown ONCE only, at story midpoint.
                        $midpoint = max(2, (int) floor($total / 2));
                        if ($slide_counter === $midpoint && $cat_id > 0) : 
                            $cache_key  = 'ps_read_' . $post_id . '_' . $cat_id;
                            $also_posts = get_transient($cache_key);
                            
                            if (false === $also_posts) {
                                $also_query = new WP_Query([
                                    'post_type'              => 'photo_story',
                                    'posts_per_page'         => 1,
                                    'post__not_in'           => [$post_id],
                                    'tax_query'              => [[
                                        'taxonomy' => 'photo_story_category',
                                        'field'    => 'term_id',
                                        'terms'    => $cat_id,
                                    ]],
                                    'orderby'                => 'rand',
                                    'no_found_rows'          => true,
                                ]);
                                $also_posts = $also_query->posts;
                                set_transient($cache_key, $also_posts, 12 * HOUR_IN_SECONDS);
                            }
                            
                            if (!empty($also_posts)) :
                                $a_post = $also_posts[0];
                                ?>
                                <div class="ps-also-read">
                                    <h3>Also Read</h3>
                                    <a href="<?php echo esc_url(get_permalink($a_post)); ?>">
                                        <?php echo esc_html(get_the_title($a_post)); ?>
                                    </a>
                                </div>
                            <?php endif; 
                        endif;
                        ?>

                        <?php
                        // In-content Ads (every 2 slides)
                        if ($slide_counter % 2 === 0 && $slide_counter !== $total) {
                            do_action('ps_ad_render_slides', $ps_author_id);
                        }
                        ?>
                    <?php endforeach; ?>
                </div>

                <footer class="ps-footer">
                    <?php 
                    // Tags
                    $tags = get_the_tags();
                    if ($tags) {
                        echo '<div class="ps-tags" style="margin-bottom: 2rem;"><strong>Tags:</strong> ';
                        foreach ($tags as $tag) {
                            echo '<a href="' . esc_url(get_tag_link($tag->term_id)) . '" class="bdg" style="margin-right: 0.5rem;">' . esc_html($tag->name) . '</a>';
                        }
                        echo '</div>';
                    }
                    ?>

                    <?php fraudalert_emergency_cta_banner(); ?>

                    <?php // WhatsApp Channel Banner ?>
                    <?php fraudalert_whatsapp_channel_btn(); ?>

                    <?php // Share buttons (bottom only — same as single.php) ?>
                    <?php fraudalert_share_buttons($post_id); ?>

                    <?php // Author Card ?>
                    <div class="author-card" style="display:flex; gap:1rem; align-items:flex-start; background:#f9f9f9; padding:1.5rem; border-radius:12px; margin:2rem 0;">
                        <?php echo get_avatar($ps_author_id, 60, '', esc_attr($author_name), ['class' => 'author-avatar']); ?>
                        <div>
                            <div style="font-weight:bold; margin-bottom:4px;">
                                <a href="<?php echo esc_url($author_url); ?>" rel="author" style="color:var(--navy,#003459); text-decoration:none;">
                                    <?php echo esc_html($author_name); ?>
                                </a>
                            </div>
                            <?php if ($author_bio) : ?>
                                <div style="font-size:13px; color:#666; line-height:1.5;">
                                    <?php echo esc_html($author_bio); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </footer>

            <?php endwhile; ?>

            <?php 
            // Related Photo Stories — 2-col grid, cached, skipped when no category
            $rel_posts = [];
            if ($cat_id > 0) {
                $rel_key   = 'ps_related_' . $post_id . '_' . $cat_id;
                $rel_posts = get_transient($rel_key);
                if (false === $rel_posts) {
                    $related_query = new WP_Query([
                        'post_type'              => 'photo_story',
                        'posts_per_page'         => 4,
                        'post__not_in'           => [$post_id],
                        'tax_query'              => [[
                            'taxonomy' => 'photo_story_category',
                            'field'    => 'term_id',
                            'terms'    => $cat_id,
                        ]],
                        'no_found_rows'          => true,
                        'update_post_meta_cache' => false,
                        'update_post_term_cache' => false,
                    ]);
                    $rel_posts = $related_query->posts;
                    set_transient($rel_key, $rel_posts, 12 * HOUR_IN_SECONDS);
                }
            }
            
            if (!empty($rel_posts)) :
            ?>
                <section class="ps-related">
                    <h3>More Photo Stories</h3>
                    <div class="ps-related-grid">
                        <?php foreach ($rel_posts as $rel_post) : setup_postdata($rel_post); ?>
                            <a href="<?php echo esc_url(get_permalink($rel_post)); ?>" class="ps-rel-card">
                                <?php 
                                if (has_post_thumbnail($rel_post)) {
                                    echo get_the_post_thumbnail($rel_post, 'photo-story-16x9', ['loading' => 'lazy', 'alt' => esc_attr(get_the_title($rel_post))]);
                                } else {
                                    echo '<div style="width:100%; aspect-ratio:16/9; background:var(--light); display:flex; align-items:center; justify-content:center;">No Image</div>';
                                }
                                ?>
                                <div class="ps-rel-card-content">
                                    <h4 class="ps-rel-card-title"><?php echo esc_html(get_the_title($rel_post)); ?></h4>
                                </div>
                            </a>
                        <?php endforeach; wp_reset_postdata(); ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
        
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

<?php // SEO meta + JSON-LD: rendered by PhotoStoryCPT::render_seo_head() on wp_head (priority 1). ?>

<?php get_footer(); ?>
