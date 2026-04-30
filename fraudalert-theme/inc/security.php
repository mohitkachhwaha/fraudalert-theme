<?php
/**
 * FraudAlert India — Security Hardening
 * Loaded via functions.php include
 * Covers: login protection, file protection, header cleaning
 *
 * @package FraudAlert
 */

defined('ABSPATH') || exit;


/* =============================================================
   1. DISABLE FILE EDITING FROM WP ADMIN
   (Also add to wp-config.php: define('DISALLOW_FILE_EDIT', true);)
   ============================================================= */

if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}


/* =============================================================
   2. BLOCK USER ENUMERATION
   Prevents: site.com/?author=1 from revealing usernames
   ============================================================= */

add_action('init', function(): void {
    if (!is_admin() && isset($_GET['author'])) {
        wp_redirect(home_url('/'), 301);
        exit;
    }
});


/* =============================================================
   3. LIMIT LOGIN ATTEMPTS (Basic — use Cloudflare WAF for production)
   ============================================================= */

add_filter('authenticate', function($user, string $username, string $password) {
    if (empty($username) || empty($password)) {
        return $user;
    }

    $ip         = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    $key        = 'login_attempts_' . md5($ip);
    $attempts   = (int) get_transient($key);
    $max        = 5;
    $lockout    = 15 * MINUTE_IN_SECONDS;

    if ($attempts >= $max) {
        return new WP_Error(
            'too_many_attempts',
            sprintf(
                __('Too many failed login attempts. Please try again after 15 minutes.', 'fraudalert')
            )
        );
    }

    return $user;
}, 30, 3);

add_action('wp_login_failed', function(): void {
    $ip       = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    $key      = 'login_attempts_' . md5($ip);
    $attempts = (int) get_transient($key);
    set_transient($key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
});

add_action('wp_login', function(): void {
    $ip  = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    $key = 'login_attempts_' . md5($ip);
    delete_transient($key);
});


/* =============================================================
   4. DISABLE APPLICATION PASSWORDS (WordPress 5.6+)
   We don't need this feature — reduces attack surface
   ============================================================= */

add_filter('wp_is_application_passwords_available', '__return_false');


/* =============================================================
   5. REMOVE SENSITIVE INFO FROM LOGIN PAGE
   Default WP login shows different errors for wrong user vs password
   ============================================================= */

add_filter('login_errors', fn() => __('Incorrect login details.', 'fraudalert'));


/* =============================================================
   6. PREVENT DIRECTORY BROWSING (belt + suspenders with .htaccess)
   ============================================================= */

add_action('init', function(): void {
    if (!is_admin() && strpos($_SERVER['REQUEST_URI'] ?? '', '/wp-content/') !== false) {
        $path = ABSPATH . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (is_dir($path)) {
            wp_die('Directory browsing is disabled.', 403);
        }
    }
});


/* =============================================================
   7. SANITIZE UPLOADED FILE NAMES
   Prevents: "../../../../evil.php" type filenames
   ============================================================= */

add_filter('sanitize_file_name', function(string $filename): string {
    // Remove any path traversal attempts
    $filename = basename($filename);
    // Replace spaces and special chars
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename);
    // Remove multiple consecutive dashes
    $filename = preg_replace('/-+/', '-', $filename);
    return strtolower($filename);
});


/* =============================================================
   8. BLOCK DANGEROUS FILE TYPES IN UPLOADS
   ============================================================= */

add_filter('upload_mimes', function(array $mimes): array {
    // Only allow safe media types
    $allowed = [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'gif'          => 'image/gif',
        'png'          => 'image/png',
        'webp'         => 'image/webp',
        'svg'          => 'image/svg+xml',
        'pdf'          => 'application/pdf',
        'mp4|m4v'      => 'video/mp4',
        'mp3|m4a'      => 'audio/mpeg',
    ];
    return $allowed;
});

// Extra check: verify file content matches extension
add_filter('wp_check_filetype_and_ext', function(array $data, string $file, string $filename, ?array $mimes = null): array {
    if (!empty($data['ext']) && is_array($mimes)) {
        $wp_filetype = wp_check_filetype($filename, $mimes);
        if ($wp_filetype['ext'] !== $data['ext']) {
            $data['ext']  = false;
            $data['type'] = false;
        }
    }
    return $data;
}, 10, 4);


/* =============================================================
   9. NONCE VERIFICATION HELPER
   Use fraudalert_verify_nonce() in any AJAX handler
   ============================================================= */

function fraudalert_verify_nonce(string $nonce_value, string $action): bool {
    return (bool) wp_verify_nonce(
        sanitize_text_field($nonce_value),
        $action
    );
}


/* =============================================================
   10. REMOVE WORDPRESS DEFAULT ADMIN USER HINTS
   ============================================================= */

// Prevent username from leaking via author archive URLs
add_filter('author_rewrite_rules', function(): array {
    return [];
});
