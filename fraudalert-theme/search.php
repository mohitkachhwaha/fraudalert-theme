<?php
/**
 * Search Results Template
 *
 * @package FraudAlert
 */

defined('ABSPATH') || exit;

get_header();
?>

<<main class="error-404-wrap" id="main-content" style="display:flex; justify-content:center; padding: 2rem 1rem;">
    <div class="single-layout" style="display:block; max-width:1100px; width:100%; margin:0 auto; grid-template-columns: 1fr !important;">

        <!-- Breadcrumbs -->
        <nav class="breadcrumbs" aria-label="Breadcrumb" style="margin-bottom:2.5rem; display:flex; justify-content:flex-start;">
            <ol style="list-style:none; display:flex; gap:0.5rem; padding:0; font-size:13px; color:var(--muted);">
                <li><a href="<?php echo esc_url(home_url('/')); ?>" style="text-decoration:none; color:var(--navy);">Home</a> <span style="margin:0 0.25rem;">&gt;</span></li>
                <li><span aria-current="page">Search</span></li>
            </ol>
        </nav>

        <div class="search-content" style="text-align:center; padding: 2rem 0;">
            <h1 style="font-family:var(--fh); font-size:clamp(32px, 8vw, 48px); color:var(--navy); margin-bottom:1rem;">
                <?php if (get_search_query()) : ?>
                    Results for: "<?php echo esc_html(get_search_query()); ?>"
                <?php else : ?>
                    Search Results
                <?php endif; ?>
            </h1>

            <!-- Search Bar -->
            <div class="error-search-wrap" style="max-width:500px; margin: 0 auto 4rem auto;">
                <?php get_search_form(); ?>
            </div>

            <?php if (have_posts()) : ?>
                <div class="search-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; text-align: left;">
                    <?php while (have_posts()) : the_post(); ?>
                        <article class="alert-card-404" style="background:var(--white); border:1.5px solid var(--border); border-radius:14px; overflow:hidden; display:flex; flex-direction:column; box-shadow:var(--shadow); transition:transform 0.3s ease;">
                            <div class="alert-thumb" style="aspect-ratio:3/2; overflow:hidden;">
                                <?php if (has_post_thumbnail()) : ?>
                                    <?php the_post_thumbnail('fraudalert-thumb', ['style' => 'width:100%; height:100%; object-fit:cover;']); ?>
                                <?php else : ?>
                                    <div style="width:100%; height:100%; background:var(--navy); display:flex; align-items:center; justify-content:center; color:#fff;">🛡️ Alert</div>
                                <?php endif; ?>
                            </div>
                            <div class="alert-body" style="padding:1.25rem;">
                                <div class="alert-meta" style="display:flex; gap:12px; font-size:11px; color:var(--muted); text-transform:uppercase; font-weight:700; margin-bottom:0.75rem;">
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
                    <?php endwhile; ?>
                </div>

                <div class="pagination" style="margin-top: 3rem;">
                    <?php echo paginate_links(['prev_text' => '‹ Prev', 'next_text' => 'Next ›', 'type' => 'plain']); ?>
                </div>

            <?php else : ?>
                <div class="no-results" style="padding: 2rem 0;">
                    <h2 style="font-family:var(--fh); font-size:28px; color:var(--navy); margin-bottom:1rem;">कोई result नहीं मिला</h2>
                    <p style="color:var(--muted); margin-bottom:4rem;">कृपया दूसरे keywords try करें या नीचे दिए गए Latest Alerts देखें।</p>
                </div>

                <!-- Latest Alerts for No Results -->
                <section class="latest-alerts-404" style="text-align:center; margin-top: 2rem;">
                    <div class="sec-header" style="margin-bottom: 2.5rem; border-bottom: 2px solid var(--border); padding-bottom: 0.75rem; display:inline-block; margin: 0 auto;">
                        <h2 style="font-family:var(--fh); font-size:24px; color:var(--navy); margin:0;">🔥 Latest Alerts</h2>
                    </div>
                    <div class="alerts-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; text-align: left;">
                        <?php
                        $fallback = new WP_Query(['posts_per_page' => 3, 'no_found_rows' => true]);
                        if ($fallback->have_posts()) :
                            while ($fallback->have_posts()) : $fallback->the_post(); ?>
                                <article class="alert-card-404" style="background:var(--white); border:1.5px solid var(--border); border-radius:14px; overflow:hidden; display:flex; flex-direction:column; box-shadow:var(--shadow);">
                                    <div class="alert-thumb" style="aspect-ratio:3/2; overflow:hidden;">
                                        <?php if (has_post_thumbnail()) : ?>
                                            <?php the_post_thumbnail('fraudalert-thumb', ['style' => 'width:100%; height:100%; object-fit:cover;']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="alert-body" style="padding:1.25rem;">
                                        <div style="font-size:11px; color:var(--muted); font-weight:700; margin-bottom:0.75rem;">📅 <?php echo get_the_date(); ?></div>
                                        <h4 style="font-family:var(--fh); font-size:17px; margin:0;"><a href="<?php the_permalink(); ?>" style="text-decoration:none; color:var(--navy);"><?php the_title(); ?></a></h4>
                                    </div>
                                </article>
                        <?php endwhile; wp_reset_postdata(); endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</main>


<?php get_footer(); ?>
