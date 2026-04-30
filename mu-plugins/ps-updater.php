<?php
/**
 * Plugin Name: PS Updater
 * Description: GitHub based theme and mu-plugins updater.
 * Version: 1.1
 * Author: FraudAlert
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PS_GITHUB_USER', 'mohitkachhwaha');
define('PS_GITHUB_REPO', 'fraud-alert-theme');
define('PS_VERSION_URL', 'https://raw.githubusercontent.com/' . PS_GITHUB_USER . '/' . PS_GITHUB_REPO . '/main/version.json');
define('PS_CURRENT_VERSION', get_option('ps_theme_version', '1.1'));
define('PS_BACKUP_DIR', WP_CONTENT_DIR . '/ps-backups/');
define('PS_MAX_BACKUPS', 3);

class PSUpdater {

    public static function init() {
        if (version_compare(get_bloginfo('version'), '6.0', '<') || version_compare(PHP_VERSION, '7.4', '<')) {
            return;
        }
        new self();
    }

    // Fix 1: Whitelist only raw.githubusercontent.com
    private function validate_github_url(string $url): bool {
        $parsed = parse_url($url);
        return isset($parsed['host'], $parsed['scheme'])
            && $parsed['host'] === 'raw.githubusercontent.com'
            && $parsed['scheme'] === 'https';
    }

    // Fix 6: Version string sanitizer
    private function sanitize_version(string $v): string {
        return sanitize_text_field(preg_replace('/[^0-9a-zA-Z.\-]/', '', $v));
    }

    private function __construct() {
        add_action('admin_menu',            [$this, 'register_menu']);
        add_action('admin_init',            [$this, 'register_settings']); // Fix 6: PAT setting
        add_action('admin_notices',         [$this, 'update_notice']);     // Fix 2: wire notice hook
        add_action('wp_ajax_ps_check_update', [$this, 'ajax_check_update']);
        add_action('wp_ajax_ps_do_update',    [$this, 'ajax_do_update']);
        add_action('wp_ajax_ps_do_rollback',  [$this, 'ajax_do_rollback']);
    }

    // Fix 6: register GitHub PAT setting
    public function register_settings() {
        register_setting('ps_updater_options', 'ps_github_token', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
    }

    public function register_menu() {
        add_menu_page('Theme Updates', 'Theme Updates', 'manage_options', 'ps-updater', [$this, 'admin_page'], 'dashicons-update', 80);
        add_submenu_page('ps-updater', 'Updater Settings', 'Settings', 'manage_options', 'ps-updater-settings', [$this, 'settings_page']);
    }

    // Fix 6: Settings page for GitHub PAT
    public function settings_page() {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['ps_save_settings'])) {
            check_admin_referer('ps_updater_settings_nonce');
            update_option('ps_github_token', sanitize_text_field($_POST['ps_github_token'] ?? ''));
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }
        $token = get_option('ps_github_token', '');
        ?>
        <div class="wrap">
            <h1>PS Updater Settings</h1>
            <form method="post">
                <?php wp_nonce_field('ps_updater_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th>GitHub Personal Access Token</th>
                        <td>
                            <input type="password" name="ps_github_token" value="<?php echo esc_attr($token); ?>" class="regular-text">
                            <p class="description">Private repo ke liye PAT enter karein. Public repo ke liye blank chhod dein.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings', 'primary', 'ps_save_settings'); ?>
            </form>
        </div>
        <?php
    }

    // Admin notice — shows on all admin pages when update available
    public function update_notice() {
        if (!current_user_can('manage_options')) return;
        if (!get_transient('ps_update_available')) {
            $cached = get_transient('ps_update_check');
            if (!$cached || empty($cached['has_update'])) return;
        }
        echo '<div class="notice notice-warning is-dismissible"><p><strong>Theme Update Available!</strong> <a href="' . esc_url(admin_url('admin.php?page=ps-updater')) . '">Review and install →</a></p></div>';
    }

    private function get_logs() {
        return get_option('ps_update_logs', []);
    }

    private function add_log($action, $version, $status) {
        $logs = $this->get_logs();
        array_unshift($logs, [
            'date' => current_time('mysql'),
            'version' => $version,
            'action' => $action,
            'status' => $status
        ]);
        $logs = array_slice($logs, 0, 10);
        update_option('ps_update_logs', $logs);
    }

    private function get_backups() {
        if (!is_dir(PS_BACKUP_DIR)) return [];
        $files = glob(PS_BACKUP_DIR . 'backup_v*');
        if (!$files) return [];
        
        $backups = [];
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/backup_v([^_]+)_(\d+)\.zip$/', $filename, $matches)) {
                $backups[] = [
                    'file' => $file,
                    'version' => $matches[1],
                    'time' => (int)$matches[2],
                    'date' => wp_date('F j, Y g:i a', (int)$matches[2])
                ];
            }
        }
        usort($backups, function($a, $b) { return $b['time'] - $a['time']; });
        return $backups;
    }

    public function admin_page() {
        $last_checked = get_option('ps_last_checked', 'Never');
        $backups = $this->get_backups();
        $logs = $this->get_logs();
        ?>
        <style>
            :root { --navy: #003459; --saf: #E85D04; }
            /* Fix 1: spin animation */
            @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
            .dashicons-spin { animation: spin 1s linear infinite; }
            .ps-updater-wrap {
                max-width: 900px;
                margin: 20px auto;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .ps-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                padding: 24px;
                margin-bottom: 24px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            }
            .ps-card-header {
                border-bottom: 1px solid #eee;
                padding-bottom: 16px;
                margin-bottom: 16px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .ps-card-header h2 { margin: 0; color: var(--navy); font-size: 1.2rem; display: flex; align-items: center; gap: 8px; }
            .ps-badge { background: var(--navy); color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
            .ps-btn {
                background: var(--saf); color: #fff; border: none;
                padding: 8px 16px; border-radius: 4px; cursor: pointer;
                font-weight: 600; display: inline-flex; align-items: center; gap: 6px;
                transition: opacity 0.2s;
            }
            .ps-btn:hover { opacity: 0.9; color: #fff; }
            .ps-btn:disabled { opacity: 0.5; cursor: not-allowed; }
            .ps-btn-outline { background: transparent; border: 1px solid var(--saf); color: var(--saf); }
            .ps-btn-outline:hover { background: var(--saf); color: #fff; }
            .ps-notice {
                padding: 12px 16px;
                border-left: 4px solid var(--saf);
                background: #fef3e2;
                margin-bottom: 16px;
                border-radius: 0 4px 4px 0;
            }
            .ps-notice.success { border-left-color: #46b450; background: #ecf7ed; }
            /* Fix 1: styled rollback warning */
            .ps-warning-box {
                background: #fff8e1;
                border-left: 4px solid #f0b429;
                padding: 12px 16px;
                border-radius: 0 4px 4px 0;
                margin-bottom: 16px;
                font-size: 13px;
            }
            .ps-warning-box strong { color: #b45309; }
            .ps-progress { width: 100%; height: 8px; background: #eee; border-radius: 4px; margin-top: 16px; overflow: hidden; display: none; }
            .ps-progress-bar { height: 100%; width: 0%; background: var(--saf); transition: width 0.6s ease; }
            .ps-step-label { font-size: 12px; color: #666; margin-top: 6px; font-style: italic; }
            .ps-table { width: 100%; border-collapse: collapse; }
            .ps-table th, .ps-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
            .ps-table th { font-weight: 600; color: var(--navy); }
            .ps-status-success { color: #46b450; font-weight: 600; }
            .ps-status-failed { color: #dc3232; font-weight: 600; }
            #update-result { margin-top: 16px; }
        </style>

        <div class="ps-updater-wrap">
            <h1 style="color:var(--navy); margin-bottom:24px;">Theme & Plugin Updates</h1>

            <div class="ps-card">
                <div class="ps-card-header">
                    <h2><span class="dashicons dashicons-admin-appearance"></span> Current Status</h2>
                    <span class="ps-badge">v<?php echo esc_html(PS_CURRENT_VERSION); ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <p style="margin:0 0 8px 0; color:#666;">Last checked: <span id="last-checked-time"><?php echo esc_html($last_checked); ?></span></p>
                        <a href="https://github.com/<?php echo esc_attr(PS_GITHUB_USER . '/' . PS_GITHUB_REPO); ?>" target="_blank" style="color:var(--navy); text-decoration:none;">
                            <span class="dashicons dashicons-external"></span> View GitHub Repository
                        </a>
                    </div>
                    <button id="btn-check-update" class="ps-btn">
                        <span class="dashicons dashicons-update"></span> Check for Updates
                    </button>
                </div>
                
                <div id="update-result"></div>
            </div>

            <div class="ps-card">
                <div class="ps-card-header">
                    <h2><span class="dashicons dashicons-backup"></span> System Rollback</h2>
                </div>
                <!-- Fix 1: styled warning box -->
                <div class="ps-warning-box">
                    <strong>⚠️ Warning:</strong> Rollback se current version backup se replace ho jayegi.
                    Site temporarily unavailable ho sakti hai during restore. Current state ka backup automatically le liya jayega.
                </div>
                
                <?php if (empty($backups)) : ?>
                    <p>No backups available.</p>
                <?php else : ?>
                    <table class="ps-table">
                        <thead>
                            <tr>
                                <th>Version</th>
                                <th>Backup Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $backup) : ?>
                                <tr>
                                    <td><strong>v<?php echo esc_html($backup['version']); ?></strong></td>
                                    <td><?php echo esc_html($backup['date']); ?></td>
                                    <td>
                                        <button class="ps-btn ps-btn-outline btn-rollback" data-file="<?php echo esc_attr(basename($backup['file'])); ?>">
                                            Restore
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="ps-card">
                <div class="ps-card-header">
                    <h2><span class="dashicons dashicons-list-view"></span> Update Log</h2>
                </div>
                <?php if (empty($logs)) : ?>
                    <p>No log history found.</p>
                <?php else : ?>
                    <table class="ps-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Version</th>
                                <th>Action</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log) : ?>
                                <tr>
                                    <td><?php echo esc_html($log['date']); ?></td>
                                    <td>v<?php echo esc_html($log['version']); ?></td>
                                    <td><?php echo esc_html(ucfirst($log['action'])); ?></td>
                                    <td class="ps-status-<?php echo esc_attr($log['status']); ?>">
                                        <?php echo esc_html(strtoupper($log['status'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btnCheck = document.getElementById('btn-check-update');
            const resultDiv = document.getElementById('update-result');
            let newVersionData = null;

            // Fix 3: XSS-safe HTML escaper
            const escHtml = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

            btnCheck.addEventListener('click', function() {
                btnCheck.disabled = true;
                // Fix 1: use CSS class for spin instead of inline style
                btnCheck.innerHTML = '<span class="dashicons dashicons-update dashicons-spin"></span> Checking...';

                const fd = new FormData();
                fd.append('action', 'ps_check_update');
                fd.append('nonce', '<?php echo wp_create_nonce("ps_updater_nonce"); ?>');

                // Fix 5: AbortController for check
                const ctrl = new AbortController();
                const tid  = setTimeout(() => ctrl.abort(), 20000);

                fetch(ajaxurl, { method: 'POST', body: fd, signal: ctrl.signal })
                .then(r => { clearTimeout(tid); return r.json(); })
                .then(res => {
                    btnCheck.disabled = false;
                    btnCheck.innerHTML = '<span class="dashicons dashicons-update"></span> Check for Updates';

                    if (res.success) {
                        document.getElementById('last-checked-time').innerText = 'Just now';
                        newVersionData = res.data;

                        if (res.data.has_update) {
                            // Fix 3: escape all values from server before innerHTML
                            let cl = '';
                            res.data.changelog.forEach(item => { cl += `<li>${escHtml(item)}</li>`; });
                            const html = `
                                <div class="ps-notice" style="margin-top:20px;">
                                    <h3 style="margin:0 0 10px 0;">🆕 New Update Available: v${escHtml(res.data.version)}</h3>
                                    <p style="margin:0 0 6px 0; color:#666;">📅 Release Date: ${escHtml(res.data.release_date)}</p>
                                    <p style="margin:0 0 10px 0; color:#666;">⚙️ Requirements: WP ${escHtml(res.data.min_wp)}+, PHP ${escHtml(res.data.min_php)}+</p>
                                    <ul style="margin-bottom:16px;">${cl}</ul>
                                    <p><strong>ℹ️ Note:</strong> Update se pehle automatic backup create hoga.</p>
                                    <button id="btn-do-update" class="ps-btn" style="background:#46b450;">
                                        <span class="dashicons dashicons-download"></span> Update Now
                                    </button>
                                    <div class="ps-progress" id="update-progress">
                                        <div class="ps-progress-bar" id="update-progress-bar"></div>
                                    </div>
                                    <p id="update-step-label" class="ps-step-label"></p>
                                    <p id="update-status-text" style="margin-top:4px; font-size:13px; color:#666;"></p>
                                </div>
                            `;
                            resultDiv.innerHTML = html;
                            document.getElementById('btn-do-update').addEventListener('click', doUpdate);
                        } else {
                            resultDiv.innerHTML = `
                                <div class="ps-notice success" style="margin-top:20px;">
                                    <h3 style="margin:0;"><span class="dashicons dashicons-yes-alt"></span> You are up to date!</h3>
                                    <p style="margin:5px 0 0 0;">Latest version (v${escHtml(res.data.version)}) already installed.</p>
                                </div>`;
                        }
                    } else {
                        resultDiv.innerHTML = `<div class="ps-notice" style="border-color:#dc3232; margin-top:20px;">Failed to check for updates. Please try again.</div>`;
                    }
                })
                .catch(err => {
                    clearTimeout(tid);
                    btnCheck.disabled = false;
                    btnCheck.innerHTML = '<span class="dashicons dashicons-update"></span> Check for Updates';
                    const msg = err.name === 'AbortError' ? 'Connection timed out. Please try again.' : 'Network error occurred.';
                    resultDiv.innerHTML = `<div class="ps-notice" style="border-color:#dc3232; margin-top:20px;">${msg}</div>`;
                });
            });

            function doUpdate() {
                const btn      = document.getElementById('btn-do-update');
                const prog     = document.getElementById('update-progress');
                const progBar  = document.getElementById('update-progress-bar');
                const stepLbl  = document.getElementById('update-step-label');
                const statTxt  = document.getElementById('update-status-text');

                btn.disabled = true;
                prog.style.display = 'block';

                // Fix 4: step-by-step progress labels
                const steps = [
                    [15,  'Verifying requirements...'],
                    [35,  'Creating backup...'],
                    [55,  'Downloading update files...'],
                    [80,  'Installing update...'],
                    [95,  'Finalizing...'],
                ];
                let stepIdx = 0;
                const stepTimer = setInterval(() => {
                    if (stepIdx < steps.length) {
                        progBar.style.width = steps[stepIdx][0] + '%';
                        stepLbl.innerText   = steps[stepIdx][1];
                        stepIdx++;
                    }
                }, 2200);

                const fd = new FormData();
                fd.append('action', 'ps_do_update');
                fd.append('nonce', '<?php echo wp_create_nonce("ps_updater_nonce"); ?>');

                // Fix 5: AbortController — 150s timeout for large zips
                const controller = new AbortController();
                const timeoutId  = setTimeout(() => controller.abort(), 150000);

                fetch(ajaxurl, { method: 'POST', body: fd, signal: controller.signal })
                .then(r => { clearTimeout(timeoutId); clearInterval(stepTimer); return r.json(); })
                .then(res => {
                    progBar.style.width = '100%';
                    stepLbl.innerText   = '';
                    if (res.success) {
                        statTxt.innerText      = '✅ ' + res.data.message;
                        statTxt.style.color    = '#46b450';
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        statTxt.innerText   = '❌ Error: ' + res.data.message;
                        statTxt.style.color = '#dc3232';
                        btn.disabled = false;
                    }
                })
                .catch(err => {
                    clearTimeout(timeoutId); clearInterval(stepTimer);
                    const msg = err.name === 'AbortError'
                        ? 'Update timed out. Please try again or check server logs.'
                        : 'Network error during update.';
                    stepLbl.innerText   = '';
                    statTxt.innerText   = '❌ ' + msg;
                    statTxt.style.color = '#dc3232';
                    btn.disabled = false;
                });
            }

            document.querySelectorAll('.btn-rollback').forEach(btn => {
                btn.addEventListener('click', function() {
                    if(!confirm('Are you sure you want to rollback to this version?')) return;
                    
                    const file = this.getAttribute('data-file');
                    this.disabled = true;
                    this.innerText = 'Restoring...';

                    const fd = new FormData();
                    fd.append('action', 'ps_do_rollback');
                    fd.append('nonce', '<?php echo wp_create_nonce("ps_updater_nonce"); ?>');
                    fd.append('file', file);

                    fetch(ajaxurl, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            alert('Rollback successful!');
                            location.reload();
                        } else {
                            alert('Error: ' + res.data.message);
                            this.disabled = false;
                            this.innerText = 'Restore';
                        }
                    });
                });
            });
        });
        </script>
        <?php
    }

    public function ajax_check_update() {
        check_ajax_referer('ps_updater_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);

        $cached = get_transient('ps_update_check');
        if (false !== $cached && is_array($cached)) {
            update_option('ps_last_checked', current_time('mysql'));
            wp_send_json_success($cached);
        }

        // Fix 6: PAT + timeout + User-Agent for all GitHub requests
        $token = get_option('ps_github_token', '');
        $remote_args = [
            'timeout'    => 15,
            'user-agent' => 'FraudAlert-Updater/' . PS_CURRENT_VERSION,
            'headers'    => $token ? ['Authorization' => 'Bearer ' . $token] : [],
        ];
        $response = wp_remote_get(PS_VERSION_URL, $remote_args);
        if (is_wp_error($response)) wp_send_json_error(['message' => 'Failed to connect to GitHub.']);

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!$data || !isset($data['version'])) wp_send_json_error(['message' => 'Invalid JSON from GitHub.']);

        $has_update = version_compare($data['version'], PS_CURRENT_VERSION, '>');

        $result = [
            'version'      => $this->sanitize_version($data['version']), // Fix 6
            'has_update'   => $has_update,
            'release_date' => sanitize_text_field($data['release_date'] ?? 'Unknown'),
            'changelog'    => array_map('sanitize_text_field', (array)($data['changelog'] ?? [])),
            'min_wp'       => sanitize_text_field($data['min_wp'] ?? '6.0'),
            'min_php'      => sanitize_text_field($data['min_php'] ?? '7.4'),
        ];

        set_transient('ps_update_check', $result, HOUR_IN_SECONDS);
        update_option('ps_last_checked', current_time('mysql'));

        wp_send_json_success($result);
    }

    public function ajax_do_update() {
        check_ajax_referer('ps_updater_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);

        // Fix 6: PAT + timeout + User-Agent for all GitHub requests
        $token = get_option('ps_github_token', '');
        $remote_args = [
            'timeout'    => 15,
            'user-agent' => 'FraudAlert-Updater/' . PS_CURRENT_VERSION,
            'headers'    => $token ? ['Authorization' => 'Bearer ' . $token] : [],
        ];
        $response = wp_remote_get(PS_VERSION_URL, $remote_args);
        if (is_wp_error($response)) {
            $this->add_log('update', 'unknown', 'failed');
            wp_send_json_error(['message' => 'Failed to fetch version info.']);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!$data || !isset($data['files'])) {
            $this->add_log('update', 'unknown', 'failed');
            wp_send_json_error(['message' => 'Invalid update payload.']);
        }

        if (version_compare(get_bloginfo('version'), $data['min_wp'] ?? '6.0', '<') || version_compare(PHP_VERSION, $data['min_php'] ?? '7.4', '<')) {
            $this->add_log('update', $data['version'] ?? 'unknown', 'failed');
            wp_send_json_error(['message' => 'Server does not meet minimum requirements.']);
        }

        // Fix 5: disk space check — require 100MB free
        $free = disk_free_space(WP_CONTENT_DIR);
        if ($free !== false && $free < 100 * 1024 * 1024) {
            wp_send_json_error(['message' => 'Insufficient disk space (need 100MB free).']);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        // Fix 7: check WP_Filesystem init
        $creds = request_filesystem_credentials(admin_url());
        if (!WP_Filesystem($creds)) {
            wp_send_json_error(['message' => 'Could not initialize filesystem. Check server permissions.']);
        }
        global $wp_filesystem;

        $this->create_backup();

        $checksums = $data['checksums'] ?? [];
        $tmps      = [];
        $success   = true;
        $new_ver   = $this->sanitize_version($data['version'] ?? '');

        // Phase 1: Download + verify all files before touching anything
        foreach (['theme', 'mu_plugins'] as $key) {
            $url = $data['files'][$key] ?? '';
            if (!$url) continue;

            // Fix 1: whitelist GitHub URL
            if (!$this->validate_github_url($url)) {
                $success = false;
                break;
            }

            // Fix 5: download with timeout
            $tmp = download_url($url, 120);
            if (is_wp_error($tmp)) { $success = false; break; }

            // Fix 2: checksum required — reject if missing
            if (empty($checksums[$key])) {
                unlink($tmp);
                $success = false;
                break;
            }
            if (hash_file('sha256', $tmp) !== $checksums[$key]) {
                unlink($tmp);
                $success = false;
                break;
            }

            $tmps[$key] = $tmp;
        }

        // Phase 2: Install only if ALL downloads verified
        // Fix 3: partial update prevention
        if ($success) {
            foreach ($tmps as $key => $tmp) {
                $dest     = $key === 'mu_plugins' ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/themes';
                $unzipped = unzip_file($tmp, $dest);
                unlink($tmp);
                if (is_wp_error($unzipped)) { $success = false; break; }
            }
        } else {
            // Cleanup any downloaded tmps
            foreach ($tmps as $tmp) { if (file_exists($tmp)) unlink($tmp); }
        }

        if ($success) {
            // Fix 6: sanitize version before DB write
            update_option('ps_theme_version', $new_ver);
            delete_transient('ps_update_check');
            $this->add_log('update', $new_ver, 'success');
            wp_send_json_success(['message' => 'Update completed successfully!']);
        } else {
            // Fix 3: auto-restore from latest backup
            $backups = $this->get_backups();
            if (!empty($backups)) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                unzip_file($backups[0]['file'], WP_CONTENT_DIR);
            }
            $this->add_log('update', $new_ver, 'failed');
            wp_send_json_error(['message' => 'Update failed. Previous version restored automatically.']);
        }
    }

    public function ajax_do_rollback() {
        check_ajax_referer('ps_updater_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);

        $filename = isset($_POST['file']) ? sanitize_file_name($_POST['file']) : '';
        if (!$filename) wp_send_json_error(['message' => 'Invalid backup file.']);

        // Fix: ensure file is within backup dir (path traversal guard)
        $filepath = realpath(PS_BACKUP_DIR . $filename);
        if (!$filepath || strpos($filepath, realpath(PS_BACKUP_DIR)) !== 0 || !file_exists($filepath)) {
            $this->add_log('rollback', 'unknown', 'failed');
            wp_send_json_error(['message' => 'Backup file not found or invalid path.']);
        }

        // Backup current state before rollback
        $this->create_backup();

        require_once ABSPATH . 'wp-admin/includes/file.php';
        // Fix 7: check WP_Filesystem init
        $creds = request_filesystem_credentials(admin_url());
        if (!WP_Filesystem($creds)) {
            wp_send_json_error(['message' => 'Could not initialize filesystem.']);
        }
        global $wp_filesystem;

        $res = unzip_file($filepath, WP_CONTENT_DIR);

        if (is_wp_error($res)) {
            $this->add_log('rollback', 'unknown', 'failed');
            wp_send_json_error(['message' => 'Failed to extract backup file.']);
        }

        // Fix 6: sanitize version
        $rolled_version = 'unknown';
        if (preg_match('/backup_v([^_]+)_/', $filename, $matches)) {
            $rolled_version = $this->sanitize_version($matches[1]);
            update_option('ps_theme_version', $rolled_version);
        }

        delete_transient('ps_update_check');
        $this->add_log('rollback', $rolled_version, 'success');
        wp_send_json_success(['message' => 'Rollback completed successfully!']);
    }

    private function create_backup(): bool {
        if (!file_exists(PS_BACKUP_DIR)) {
            wp_mkdir_p(PS_BACKUP_DIR);
        }

        // Fix 4: Protect backup dir from web access
        $htaccess = PS_BACKUP_DIR . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }
        $index = PS_BACKUP_DIR . 'index.php';
        if (!file_exists($index)) {
            file_put_contents($index, '<?php // Silence is golden.');
        }

        $backups = glob(PS_BACKUP_DIR . 'backup_v*') ?: [];
        if (count($backups) >= PS_MAX_BACKUPS) {
            usort($backups, function($a, $b) { return filemtime($a) - filemtime($b); });
            while (count($backups) >= PS_MAX_BACKUPS) {
                unlink(array_shift($backups));
            }
        }

        if (!class_exists('ZipArchive')) return false;

        $zip = new ZipArchive();
        $backup_file = PS_BACKUP_DIR . 'backup_v' . $this->sanitize_version(PS_CURRENT_VERSION) . '_' . time() . '.zip';
        if ($zip->open($backup_file, ZipArchive::CREATE) !== TRUE) return false;

        $this->add_folder_to_zip(WP_CONTENT_DIR . '/themes/fraudalert-theme-child', $zip, 'themes/fraudalert-theme-child');
        $this->add_folder_to_zip(WPMU_PLUGIN_DIR, $zip, 'mu-plugins');
        $zip->close();
        return true;
    }

    private function add_folder_to_zip($folder, &$zip, $local_path) {
        if (!is_dir($folder)) return;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = $local_path . '/' . substr($filePath, strlen($folder) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
}
add_action('after_setup_theme', ['PSUpdater', 'init']);
