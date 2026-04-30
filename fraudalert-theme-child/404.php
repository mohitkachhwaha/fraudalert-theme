<?php
/**
 * 404 Page Template
 *
 * @package FraudAlert Child
 */

defined('ABSPATH') || exit;

// Ensure correct HTTP status code
status_header(404);

get_header();
?>

<main class="error-404-wrap" id="main-content" style="display:flex; justify-content:center; padding: 2rem 1rem;">
    <div class="single-layout" style="display:block; max-width:1100px; width:100%; margin:0 auto; grid-template-columns: 1fr !important;">

        <!-- Breadcrumbs -->
        <?php
        echo '<nav class="breadcrumbs" aria-label="Breadcrumb" style="margin-bottom:2.5rem; display:flex; justify-content:flex-start;">';
        echo '<ol style="list-style:none; display:flex; gap:0.5rem; padding:0; font-size:13px; color:var(--muted);">';
        echo '<li><a href="' . esc_url(home_url('/')) . '" style="text-decoration:none; color:var(--navy);">Home</a> <span style="margin:0 0.25rem;">&gt;</span></li>';
        echo '<li><span aria-current="page">404</span></li>';
        echo '</ol>';
        echo '</nav>';
        ?>

        <div class="error-404-content" style="text-align:center; padding: 2rem 0;">
            <h1 style="font-family:var(--fh); font-size:clamp(32px, 8vw, 48px); color:var(--navy); margin-bottom:1rem;">Page Not Found</h1>
            <p style="font-family:var(--fu); font-size:clamp(16px, 4vw, 18px); color:var(--muted); margin-bottom:2rem;">
                Sorry, the page you are looking for doesn\'t exist or has been moved.
            </p>

            <!-- Search Bar -->
            <div class="error-search-wrap" style="max-width:500px; margin: 0 auto 4rem auto;">
                <?php get_search_form(); ?>
            </div>

            <!-- Latest Alerts Section -->
            <section class="latest-alerts-404" style="text-align:center; margin-top: 5rem;">
                <div class="sec-header" style="margin-bottom: 2.5rem; border-bottom: 2px solid var(--border); padding-bottom: 0.75rem; display:inline-block; margin-left:auto; margin-right:auto;">
                    <h2 style="font-family:var(--fh); font-size:24px; color:var(--navy); margin:0;">🔥 Latest Alerts</h2>
                </div>

                <div class="alerts-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <?php
                    $alerts_query = new WP_Query([
                        'posts_per_page' => 3,
                        'post_status'    => 'publish',
                        'no_found_rows'  => true,
                        'ignore_sticky_posts' => true,
                    ]);

                    if ($alerts_query->have_posts()) :
                        while ($alerts_query->have_posts()) : $alerts_query->the_post(); ?>
                            <article class="alert-card-404" style="background:var(--white); border:1.5px solid var(--border); border-radius:14px; overflow:hidden; display:flex; flex-direction:column; box-shadow:var(--shadow); transition:transform 0.3s ease;">
                                <div class="alert-thumb" style="aspect-ratio:3/2; overflow:hidden;">
                                    <?php if (has_post_thumbnail()) : ?>
                                        <?php the_post_thumbnail('fraudalert-thumb', ['style' => 'width:100%; height:100%; object-fit:cover;']); ?>
                                    <?php else : ?>
                                        <div style="width:100%; height:100%; background:var(--navy); display:flex; align-items:center; justify-content:center; color:#fff;">🛡️ Alert</div>
                                    <?php endif; ?>
                                </div>
                                <div class="alert-body" style="padding:1.25rem; text-align:left;">
                                    <div class="alert-meta" style="display:flex; gap:12px; font-size:11px; color:var(--muted); text-transform:uppercase; font-weight:700; margin-bottom:0.75rem; font-family:var(--fu);">
                                        <span>📅 <?php echo get_the_date(); ?></span>
                                        <span>👤 <?php echo get_the_author(); ?></span>
                                    </div>
                                    <h3 style="font-family:var(--fh); font-size:18px; line-height:1.3; color:var(--navy); margin:0 0 0.75rem 0;">
                                        <a href="<?php the_permalink(); ?>" style="text-decoration:none; color:inherit;"><?php the_title(); ?></a>
                                    </h3>
                                    <div style="font-family:var(--fu); font-size:13.5px; color:var(--muted); line-height:1.6; margin-bottom:1rem;">
                                        <?php echo wp_trim_words(get_the_excerpt(), 18, '...'); ?>
                                    </div>
                                </div>
                            </article>
                        <?php endwhile;
                        wp_reset_postdata();
                    else :
                        echo '<p style="color:var(--muted); text-align:center; grid-column: 1 / -1;">No recent alerts found.</p>';
                    endif;
                    ?>
                </div>
            </section>
        </div>
    </div>
</main>
</main>

<?php get_footer(); ?>
