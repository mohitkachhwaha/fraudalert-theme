<?php
/**
 * Custom Author Profile Template (E-E-A-T Optimized)
 * 
 * @package FraudAlert Child
 */

defined('ABSPATH') || exit;

$author = get_queried_object();
$aid    = $author->ID;

// Fetch UX/EEAT Meta
$job_title  = get_the_author_meta('job_title', $aid);
$experience = get_the_author_meta('experience', $aid);
$expertise  = get_the_author_meta('expertise', $aid);
$is_verified = strtolower(get_the_author_meta('is_verified', $aid)) === 'yes';
$socials = [
    'twitter'   => get_the_author_meta('twitter', $aid),
    'facebook'  => get_the_author_meta('facebook', $aid),
    'linkedin'  => get_the_author_meta('linkedin', $aid),
    'instagram' => get_the_author_meta('instagram', $aid),
    'youtube'   => get_the_author_meta('youtube', $aid),
    'url'       => get_the_author_meta('url', $aid),
];

// Calculate total posts
$post_count = count_user_posts($aid);

// Handle SEO: Noindex if 0 posts
if ($post_count === 0) {
    add_action('wp_head', function() { echo '<meta name="robots" content="noindex, follow">'; }, 1);
}

get_header(); ?>

<main class="author-master-wrap" style="background:#fff; min-height:80vh;">
    
    <!-- Premium Hero Section -->
    <header class="author-hero" style="background:linear-gradient(135deg, var(--navy) 0%, var(--nav2) 100%); padding: 4rem 1.5rem; color:#fff; text-align:center; position:relative; overflow:hidden;">
        <div class="author-hero-inner" style="max-width:900px; margin:0 auto; position:relative; z-index:2;">
            
            <div class="author-avatar-wrap" style="position:relative; display:inline-block; margin-bottom:1.5rem;">
                <div style="width:140px; height:140px; border-radius:50%; border:4px solid rgba(255,255,255,0.2); overflow:hidden; background:#eee; margin:0 auto;">
                    <?php echo get_avatar($aid, 140); ?>
                </div>
                <?php if ($is_verified) : ?>
                    <div class="verified-badge" title="Verified Author" style="position:absolute; bottom:5px; right:5px; background:#00A8FF; color:#fff; width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; border:3px solid var(--navy); font-size:14px;">✓</div>
                <?php endif; ?>
            </div>

            <?php if ($job_title) : ?>
                <div class="author-badge" style="display:inline-block; background:var(--saf); color:#fff; font-size:12px; font-weight:800; text-transform:uppercase; padding:4px 12px; border-radius:50px; margin-bottom:1rem; letter-spacing:1px;"><?php echo esc_html($job_title); ?></div>
            <?php endif; ?>
            
            <h1 style="font-family:var(--fh); font-size:clamp(32px, 8vw, 48px); margin:0 0 0.5rem 0; line-height:1.1;"><?php the_author(); ?></h1>
            
            <div class="author-bio" style="font-family:var(--fu); font-size:17px; opacity:0.9; line-height:1.6; max-width:700px; margin:1rem auto 2rem auto;">
                <?php echo get_the_author_meta('description', $aid) ?: 'स्कैम से बचो (FraudAlert) के आधिकारिक साइबर सुरक्षा विशेषज्ञ। सुरक्षित रहें, सतर्क रहें।'; ?>
            </div>

            <!-- Social Links -->
            <div class="author-social-icons" style="display:flex; justify-content:center; gap:20px; font-size:24px;">
                <?php foreach ($socials as $key => $url) : if ($url) : ?>
                    <a href="<?php echo esc_url($url); ?>" target="_blank" style="color:#fff; opacity:0.8; transition:0.3s; text-decoration:none;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.8">
                        <?php echo match($key) { 'twitter' => '𝕏', 'facebook' => '📘', 'linkedin' => '💼', 'instagram' => '📸', 'youtube' => '📺', default => '🌐' }; ?>
                    </a>
                <?php endif; endforeach; ?>
            </div>
        </div>
        
        <!-- Decoration bits -->
        <div style="position:absolute; top:-50px; left:-50px; width:200px; height:200px; background:rgba(255,b255,255,0.03); border-radius:50%;"></div>
    </header>

    <!-- Stats & EEAT Bar -->
    <div class="author-eeat-bar" style="background:var(--light); border-bottom:1px solid var(--border); padding:1.5rem 0;">
        <div class="container" style="max-width:1100px; margin:0 auto; padding:0 1.5rem; display:flex; flex-wrap:wrap; justify-content:center; gap:2rem 4rem;">
            
            <div class="stat-item" style="text-align:center;">
                <div style="font-size:24px; font-weight:900; color:var(--navy);"><?php echo $post_count; ?></div>
                <div style="font-size:11px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:1px;">Total Alerts</div>
            </div>

            <?php if ($experience) : ?>
            <div class="stat-item" style="text-align:center;">
                <div style="font-size:24px; font-weight:900; color:var(--navy);"><?php echo esc_html($experience); ?></div>
                <div style="font-size:11px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:1px;">Years Exp.</div>
            </div>
            <?php endif; ?>

            <?php if ($expertise) : ?>
            <div class="stat-item" style="text-align:center;">
                <div style="font-size:13px; font-weight:800; color:var(--navy); margin-bottom:2px;">Expert In:</div>
                <div style="font-size:13px; color:var(--nav2); font-weight:600;"><?php echo esc_html($expertise); ?></div>
            </div>
            <?php endif; ?>

            <div class="stat-item" style="text-align:center;">
                <div style="font-size:24px; color:var(--saf);">🏆</div>
                <div style="font-size:11px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:1px;">Verified Expert</div>
            </div>

        </div>
    </div>

    <!-- Disclaimers -->
    <div style="max-width:1100px; margin: 0 auto; padding: 0 1.5rem;">
        <?php if (function_exists('fraudalert_get_disclaimer_markup')) { echo fraudalert_get_disclaimer_markup('archive'); } ?>
    </div>

    <!-- Author Post Grid -->
    <section class="author-posts" style="padding: 4rem 1.5rem; max-width:1100px; margin:0 auto;">
        <div style="margin-bottom:2.5rem; border-bottom:2px solid var(--border); padding-bottom:0.75rem; display:flex; justify-content:space-between; align-items:flex-end;">
            <h2 style="font-family:var(--fh); font-size:24px; color:var(--navy); margin:0;">📝 Recent Contributions</h2>
            <div style="font-size:13px; color:var(--muted);">Archive for <?php the_author(); ?></div>
        </div>

        <?php if (have_posts()) : ?>
            <div class="author-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:2.5rem;">
                <?php while (have_posts()) : the_post(); ?>
                    <article class="post-card-premium" style="display:flex; flex-direction:column; gap:1.25rem;">
                        <a href="<?php the_permalink(); ?>" style="display:block; aspect-ratio:16/9; overflow:hidden; border-radius:15px; background:#eee;">
                            <?php if (has_post_thumbnail()) : the_post_thumbnail('fraudalert-md', ['style' => 'width:100%; height:100%; object-fit:cover; transition:0.4s ease;']); endif; ?>
                        </a>
                        <div class="pc-content">
                            <div style="font-size:12px; font-weight:800; color:var(--saf); text-transform:uppercase; margin-bottom:0.5rem;"><?php echo get_the_date(); ?></div>
                            <h3 style="font-family:var(--fh); font-size:20px; line-height:1.3; margin:0 0 0.75rem 0;">
                                <a href="<?php the_permalink(); ?>" style="text-decoration:none; color:var(--navy);"><?php the_title(); ?></a>
                            </h3>
                            <p style="font-family:var(--fu); font-size:14.5px; color:var(--muted); line-height:1.6; margin:0;">
                                <?php echo wp_trim_words(get_the_excerpt(), 20, '...'); ?>
                            </p>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>

            <!-- Custom Pagination -->
            <?php if (function_exists('fraudalert_numbered_pagination')) : 
                fraudalert_numbered_pagination(); 
            endif; ?>

        <?php else : ?>
            <div style="text-align:center; padding: 4rem 0;">
                <p style="font-size:18px; color:var(--muted);">This author hasn't published any alerts yet.</p>
                <a href="<?php echo home_url(); ?>" class="btn-primary" style="margin-top:1.5rem; display:inline-block;">Back to Homepage</a>
            </div>
        <?php endif; ?>
    </section>

</main>

<!-- Schema JSON-LD -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Person",
  "name": "<?php echo esc_attr(get_the_author()); ?>",
  "url": "<?php echo esc_url(get_author_posts_url($aid)); ?>",
  "description": "<?php echo esc_attr(get_the_author_meta('description', $aid)); ?>",
  "jobTitle": "<?php echo esc_attr($job_title); ?>",
  "knowsAbout": ["Online Fraud Detection", "Cybersecurity", "Fraud Awareness", "<?php echo esc_attr($expertise); ?>"],
  "sameAs": [
    <?php 
    $s_links = array_filter($socials);
    echo '"' . implode('","', array_map('esc_url', $s_links)) . '"';
    ?>
  ]
}
</script>

<?php get_footer(); ?>
