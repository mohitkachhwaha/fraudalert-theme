# FraudAlert India — WordPress Theme
### Version 1.0.0 | Mobile-First | Zero Bloat | Cloudflare-Ready

---

## THEME FILES OVERVIEW

```
fraudalert-theme/
├── style.css                 ← Theme header + complete CSS (28 sections)
├── functions.php             ← All theme setup, helpers, hooks
├── header.php                ← Topbar, sticky header, nav, mobile menu
├── footer.php                ← Footer, social links, floating buttons, JS
├── index.php                 ← Full homepage template
├── single.php                ← Single post + sidebar + related posts
├── archive.php               ← Category, tag, date archive pages
├── page.php                  ← Static pages (About, Contact, etc.)
├── search.php                ← Search results page
├── 404.php                   ← Custom 404 error page
├── searchform.php            ← Custom search form HTML
├── .htaccess                 ← Apache security + performance rules
├── wp-config-additions.php   ← Copy these constants to wp-config.php
├── inc/
│   ├── security.php          ← Login protection, file upload security
│   └── performance.php       ← Lazy load, defer, WebP, caching
└── assets/
    └── js/
        └── main.js           ← Vanilla JS (menu, FAQ, scroll effects)
```

---

## INSTALLATION — STEP BY STEP

### Step 1 — Local Testing (Do this first)
1. Download LocalWP from localwp.com (free)
2. Create a new WordPress site
3. Go to: wp-content/themes/
4. Create folder: `fraudalert-theme`
5. Copy all theme files into this folder
6. WordPress Admin → Appearance → Themes → Activate "FraudAlert India"

### Step 2 — wp-config.php Setup
Open wp-config.php and add ABOVE "That's all, stop editing!":
```php
define('DISALLOW_FILE_EDIT', true);
define('FORCE_SSL_ADMIN', true);
define('WP_DEBUG', false);
define('WP_POST_REVISIONS', 3);
define('AUTOSAVE_INTERVAL', 300);
define('DISABLE_WP_CRON', true);
define('WP_MEMORY_LIMIT', '256M');

// Cloudflare fix
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
```

### Step 3 — .htaccess
Replace your existing .htaccess with the one provided.
Also create `/wp-content/uploads/.htaccess` with:
```apache
<Files *.php>
    deny from all
</Files>
```

### Step 4 — Install These 4 Plugins Only
| Plugin | Source |
|---|---|
| Rank Math SEO | wordpress.org/plugins/seo-by-rank-math |
| Cloudflare (Official) | wordpress.org/plugins/cloudflare |
| OneSignal | wordpress.org/plugins/onesignal-free-web-push-notifications |
| Brevo / MailPoet | wordpress.org/plugins/mailinglist-by-mailpoet |

### Step 5 — Navigation Menus
WordPress Admin → Appearance → Menus
- Create "Primary Menu" → assign to "Primary Navigation"
- Create "Footer Menu" → assign to "Footer Links"

Add these pages to Primary Menu:
- होम (Home)
- Latest Alerts
- Scam Types
- How-To Guides
- FAQ
- Resources

### Step 6 — Ad Zones Setup
WordPress Admin → Appearance → Widgets
You will see 6 ad zones:
- **Ad — Post Top**: Above single post content (728x90)
- **Ad — Post Bottom**: Below single post content (728x90)
- **Ad — Sidebar**: Sidebar sticky ad (300x250)
- **Ad — Homepage Mid**: Between homepage sections
- **Ad — After Ticker**: After alert ticker band

Paste your AdSense or direct ad code in the "Custom HTML" widget.

### Step 7 — Rank Math SEO Setup
1. Install Rank Math → Setup Wizard
2. Enable: Article Schema, FAQ Schema, Breadcrumbs, Sitemap
3. Connect Google Search Console
4. Submit sitemap: yourdomain.com/sitemap_index.xml

### Step 8 — Cloudflare Setup
1. Add domain to Cloudflare
2. DNS → Point to your hosting
3. SSL → Full (strict)
4. Speed → Auto Minify: HTML + CSS + JS
5. Speed → Brotli: On

**Cache Rules (3 rules — free plan):**
```
Rule 1: Cache Everything
  If: Cookie does NOT contain "wordpress_logged_in"
  Then: Cache Level = Cache Everything
  Edge TTL: 24 hours, Browser TTL: 4 hours

Rule 2: Bypass Cache — WP Admin
  If: URI Path contains /wp-admin
  Then: Cache Level = Bypass

Rule 3: Bypass Cache — Login
  If: URI Path contains /wp-login.php
  Then: Cache Level = Bypass
```

**WAF Rules (5 rules — free plan):**
```
Rule 1: Block xmlrpc.php
  URI Path = /xmlrpc.php → Block

Rule 2: Rate limit wp-login.php
  URI = /wp-login.php + Rate > 5/min → Block

Rule 3: Block bad bots
  User Agent contains "havij" OR "sqlmap" → Block
```

### Step 9 — Connect Cloudflare Plugin
WordPress Admin → Settings → Cloudflare
- Enter API key
- Enable "Automatic Cache Purge on Post Publish"

---

## HOW TO POST (Daily Workflow)

1. WordPress Admin → Posts → Add New
2. Write content in Classic Editor (no Gutenberg)
3. Add Featured Image (WebP format, 800x450px)
4. Set Category (UPI Fraud, WhatsApp Scam, etc.)
5. Add Tags
6. Rank Math will auto-generate meta — review and publish
7. Cloudflare cache auto-purges on publish

**Image preparation before upload:**
- Go to squoosh.app
- Upload your image → Convert to WebP
- Compress to under 100KB for card images, 150KB for hero
- File name: `upi-fraud-case-2024.webp` (descriptive, no spaces)

---

## EXPECTED PERFORMANCE

| Metric | Target |
|---|---|
| Lighthouse Mobile Score | 90-100 |
| Page Size | 50-90KB |
| TTFB (Cloudflare cache HIT) | 20-50ms |
| TTFB (cache MISS) | 300-600ms |
| HTTP Requests | 4-7 |
| Core Web Vitals | All Green |

---

## TROUBLESHOOTING

**Theme not showing up in WordPress:**
→ Check that `style.css` has correct Theme Name header at top

**Ticker not scrolling:**
→ Check that posts are published. Ticker pulls from latest posts.

**Cache not clearing after new post:**
→ Make sure Cloudflare plugin is connected with API key

**Admin bar showing on frontend:**
→ Only shows for Administrator role — this is correct

**Images not loading:**
→ Make sure WebP files are under 5MB
→ Check uploads folder .htaccess isn't blocking

---

## SECURITY CHECKLIST (Monthly)

- [ ] WordPress core updated
- [ ] All 4 plugins updated
- [ ] Admin password changed (every 3 months)
- [ ] UpdraftPlus backup verified
- [ ] Cloudflare WAF logs reviewed
- [ ] Check for any failed login attempts in security transients
