<?php
/**
 * Plugin Name: Scam Se Bacho — Security & Power Panel (System Level)
 * Description: MU-Plugin version. Hides Admin URL, Customizes UI, and adds One-Click Secure Post Duplication.
 * Version: 1.4.0
 * Author: Scam Se Bacho Team
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class ScamSeBacho_System_MU {

    private $slug_option = 'fa_admin_slug';
    private $default_slug = 'secure-portal';

    public function __construct() {
        // Core Security Logic
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('init', [$this, 'intercept_login_request'], 1);
        add_filter('site_url', [$this, 'filter_login_url'], 10, 4);
        add_filter('network_site_url', [$this, 'filter_login_url'], 10, 4);
        add_filter('wp_redirect', [$this, 'filter_redirect'], 10, 2);
        add_action('wp_logout', [$this, 'handle_logout']);

        // UI Customization
        add_action('login_enqueue_scripts', [$this, 'custom_login_styles']);
        add_filter('login_headerurl', fn() => home_url());
        add_filter('login_headertext', fn() => 'स्कैम से बचो — Security Portal');
        add_action('admin_enqueue_scripts', [$this, 'custom_admin_styles']);
        add_filter('admin_footer_text', [$this, 'custom_admin_footer']);
        add_action('wp_before_admin_bar_render', [$this, 'custom_admin_bar_logo'], 0);

        // Power Features: Duplication
        add_filter('post_row_actions', [$this, 'add_duplicate_link'], 10, 2);
        add_filter('page_row_actions', [$this, 'add_duplicate_link'], 10, 2);
        add_action('admin_action_ssb_duplicate_post', [$this, 'handle_post_duplication']);
    }

    public function add_menu() {
        add_options_page('Security Portal', 'Security Portal', 'manage_options', 'fa-login-hider', [$this, 'settings_page']);
    }

    public function register_settings() {
        register_setting('fa_hider_group', $this->slug_option, ['sanitize_callback' => [$this, 'sanitize_slug'], 'default' => $this->default_slug]);
    }

    public function sanitize_slug($slug) {
        $slug = sanitize_title($slug);
        return (empty($slug) || in_array($slug, ['admin', 'login', 'wp-admin'])) ? $this->default_slug : $slug;
    }

    public function settings_page() {
        $current_slug = get_option($this->slug_option, $this->default_slug);
        ?>
        <div class="wrap">
            <h1>🛡️ स्कैम से बचो — Security Portal Settings</h1>
            <p>System-level security, Branding & Power tools enabled.</p>
            <form method="post" action="options.php">
                <?php settings_fields('fa_hider_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Custom Security URL Slug</th>
                        <td>
                            <code><?php echo home_url('/'); ?></code>
                            <input type="text" name="<?php echo $this->slug_option; ?>" value="<?php echo esc_attr($current_slug); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button('Update Security Matrix'); ?>
            </form>
        </div>
        <?php
    }

    /* === LOGIN UI === */
    public function custom_login_styles() {
        ?>
        <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@800&family=Mukta:wght@400;600;700&display=swap" rel="stylesheet">
        <style type="text/css">
            body.login { background: #FFF8F0 !important; font-family: 'Mukta', sans-serif !important; }
            #login { width: 380px !important; }
            .login h1 a { background-image: url('https://img.icons8.com/color/96/data-protection.png') !important; background-size: 80px !important; width: 80px !important; height: 80px !important; }
            .login h1::after { content: "स्कैम से बचो"; display: block; color: #003459; font-family: 'Baloo 2', cursive; font-size: 38px; font-weight: 800; margin-top: 10px; text-align: center; }
            .login form { background: #FFFFFF !important; border: 1.5px solid #E8DDD0 !important; border-radius: 16px !important; box-shadow: 0 4px 24px rgba(0, 52, 89, 0.10) !important; padding: 35px !important; }
            .wp-core-ui .button-primary { background: #C1121F !important; border: none !important; border-radius: 10px !important; height: 48px !important; font-family: 'Baloo 2', cursive !important; font-size: 18px !important; }
            .language-switcher, .privacy-policy-page-link { display: none !important; }
        </style>
        <?php
    }

    /* === ADMIN DASHBOARD UI === */
    public function custom_admin_styles() {
        ?>
        <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@800&family=Mukta:wght@400;600;700&display=swap" rel="stylesheet">
        <style type="text/css">
            #wpadminbar { background: #003459 !important; border-bottom: 2px solid #C1121F !important; }
            #adminmenu, #adminmenu .wp-submenu, #adminmenuback, #adminmenuwrap { background-color: #002D4C !important; }
            #adminmenu li.wp-has-current-submenu a.wp-has-current-submenu, #adminmenu li.current a.menu-top, .wp-ui-primary { background: #C1121F !important; color: #fff !important; }
            #adminmenu li.menu-top:hover { background-color: #C1121F !important; color: #fff !important; }
            .wp-core-ui .button-primary { background: #C1121F !important; border-color: #C1121F !important; border-radius: 6px !important; text-shadow: none !important; }
            body, #wpadminbar *, #adminmenu * { font-family: 'Mukta', sans-serif !important; }
            /* Exclude TinyMCE icon font — .mce-ico uses the 'tinymce' icon font for toolbar symbols */
            .wp-core-ui :not(.mce-ico):not([class*="dashicons"]) { font-family: 'Mukta', sans-serif; }
            .ssb-duplicate-link { color: #C1121F !important; font-weight: bold !important; }
        </style>
        <?php
    }

    public function custom_admin_footer() {
        return '<span id="footer-thankyou">Powered by <strong>स्कैम से बचो — Security Framework</strong>. Stay Safe! 🛡️</span>';
    }

    public function custom_admin_bar_logo() {
        echo '<style>#wp-admin-bar-wp-logo > .ab-item .ab-icon:before { content: "🛡️" !important; font-size: 18px !important; }</style>';
    }

    /* === POWER FEATURE: DUPLICATION === */

    public function add_duplicate_link($actions, $post) {
        if (current_user_can('edit_posts')) {
            $actions['ssb_duplicate'] = '<a href="' . wp_nonce_url('admin.php?action=ssb_duplicate_post&post=' . $post->ID, 'ssb_duplication_' . $post->ID) . '" title="Duplicate this item" class="ssb-duplicate-link">Duplicate</a>';
        }
        return $actions;
    }

    public function handle_post_duplication() {
        if (!isset($_GET['post'])) {
            wp_die('No post to duplicate!');
        }

        $post_id = (isset($_GET['post']) ? absint($_GET['post']) : '');
        check_admin_referer('ssb_duplication_' . $post_id);

        $post = get_post($post_id);
        $current_user = wp_get_current_user();
        $new_post_author = $current_user->ID;

        if (isset($post) && $post != null) {
            $args = array(
                'comment_status' => $post->comment_status,
                'ping_status'    => $post->ping_status,
                'post_author'    => $new_post_author,
                'post_content'   => $post->post_content,
                'post_excerpt'   => $post->post_excerpt,
                'post_name'      => $post->post_name,
                'post_parent'    => $post->post_parent,
                'post_password'  => $post->post_password,
                'post_status'    => 'draft',
                'post_title'     => $post->post_title . ' (Copy)',
                'post_type'      => $post->post_type,
                'to_ping'        => $post->to_ping,
                'menu_order'     => $post->menu_order
            );

            $new_post_id = wp_insert_post($args);

            // Copy taxonomies
            $taxonomies = get_object_taxonomies($post->post_type);
            foreach ($taxonomies as $taxonomy) {
                $post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
                wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
            }

            // Copy meta data
            $post_meta_infos = get_post_custom($post_id);
            if (count($post_meta_infos) != 0) {
                foreach ($post_meta_infos as $key => $values) {
                    foreach ($values as $value) {
                        add_post_meta($new_post_id, $key, $value);
                    }
                }
            }

            wp_safe_redirect(admin_url('edit.php?post_type=' . $post->post_type));
            exit;
        } else {
            wp_die('Post creation failed, could not find original post: ' . $post_id);
        }
    }

    /* === CORE REDIRECTION === */
    public function intercept_login_request() {
        if (is_admin() && !is_user_logged_in() && !defined('DOING_AJAX')) {
            wp_safe_redirect(home_url('/'));
            exit;
        }
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $custom_slug = get_option($this->slug_option, $this->default_slug);
        $target_url  = home_url('/' . $custom_slug);
        if (rtrim($current_url, '/') === rtrim($target_url, '/')) {
            global $error, $interim_login, $action, $user_login, $user_pass, $redirect_to;
            @include ABSPATH . 'wp-login.php';
            exit;
        }
        if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false && !isset($_GET['action']) && !isset($_GET['key']) && !is_user_logged_in()) {
            wp_safe_redirect(home_url('/'));
            exit;
        }
    }

    public function filter_login_url($url, $path, $scheme, $blog_id) {
        if ($path === 'wp-login.php' || strpos($url, 'wp-login.php') !== false) {
            if (strpos($url, 'action=logout') !== false) return $url;
            return home_url('/' . get_option($this->slug_option, $this->default_slug));
        }
        return $url;
    }

    public function filter_redirect($location, $status) {
        if (strpos($location, 'wp-login.php') !== false) {
            return $this->filter_login_url($location, 'wp-login.php', null, null);
        }
        return $location;
    }

    public function handle_logout() {
        wp_safe_redirect(home_url('/'));
        exit;
    }
}

new ScamSeBacho_System_MU();
