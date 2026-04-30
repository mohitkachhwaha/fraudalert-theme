<?php
/**
 * Homepage Template
 * Displays: topbar, header, hero, ticker, scam types, latest alerts,
 * how-to steps, scam detector, stories, stats, FAQ, report CTA, resources, footer
 *
 * @package FraudAlert
 */

defined('ABSPATH') || exit;

get_header();
?>

<!-- ==========================================
     ALERT TICKER BAND
     ========================================== -->
<div class="alert-band" role="marquee" aria-label="Live fraud alerts ticker" aria-live="off">
  <div class="ticker-wrap">
    <div class="ticker-label" aria-hidden="true">🔴 Live Alerts</div>
    <div class="ticker-track">
      <div class="ticker-inner" aria-hidden="true">
        <?php echo fraudalert_get_ticker_items(8); ?>
      </div>
    </div>
  </div>
</div>

<!-- ==========================================
     AD ZONE — After Ticker (optional)
     ========================================== -->
<?php fraudalert_ad_zone('ad-after-ticker'); ?>

<!-- ==========================================
     HERO SECTION
     ========================================== -->
<section class="hero" aria-label="Hero — FraudAlert India">
  <div class="hero-grid" aria-hidden="true"></div>
  <div class="hero-inner">

    <!-- Hero Left -->
    <div class="fade-up">
      <div class="hero-eyebrow">
        <span class="hero-badge">🛡️ India's #1 Fraud Awareness Platform</span>
        <span class="bdg bdg-live" aria-label="Live">Live</span>
      </div>

      <h1>Scam से बचें,<br><em>सुरक्षित रहें</em> —<br>हर कदम पर।</h1>

      <p class="hero-sub">
        UPI Fraud से लेकर Investment Scam तक — हम आपको real-time alerts,
        step-by-step guides और expert tips देते हैं। India में हर रोज़
        ₹7,000+ करोड़ का cyber fraud होता है — आप तैयार रहें।
      </p>

      <div class="hero-actions">
        <a href="<?php echo esc_url(home_url('/report-fraud')); ?>" class="hero-cta-primary">
          🚨 Fraud Report करें <span aria-hidden="true">→</span>
        </a>
        <a href="<?php echo esc_url(home_url('/guides')); ?>" class="hero-cta-secondary">
          📖 Guide पढ़ें
        </a>
      </div>

      <div class="hero-stats" aria-label="Key statistics">
        <div class="hstat">
          <div class="hstat-num">₹7,488<span aria-hidden="true" style="font-size:18px">Cr</span></div>
          <div class="hstat-lbl">2023 में Cyber Fraud नुकसान</div>
        </div>
        <div class="hstat-div" aria-hidden="true"></div>
        <div class="hstat">
          <div class="hstat-num">15.6L+</div>
          <div class="hstat-lbl">Complaints दर्ज (2023)</div>
        </div>
        <div class="hstat-div" aria-hidden="true"></div>
        <div class="hstat">
          <div class="hstat-num">
            <?php echo fraudalert_get_total_posts() . '+'; ?>
          </div>
          <div class="hstat-lbl">Guides &amp; Alerts</div>
        </div>
      </div>
    </div>

    <!-- Hero Right Card -->
    <div class="hero-card fade-up delay-2" aria-label="Quick help card">
      <div class="hcard-title">⚡ तुरंत मदद चाहिए?</div>
      <div class="hcard-helpline">
        <div class="hw-icon" aria-hidden="true">📞</div>
        <div class="helpline-num">1930</div>
        <div class="helpline-label">National Cyber Crime Helpline</div>
        <div class="helpline-sub">24×7 उपलब्ध — free call</div>
      </div>
      <div class="hcard-links">
        <a href="https://cybercrime.gov.in" class="hcard-link" target="_blank" rel="noopener noreferrer">
          <span class="hcard-link-text">🌐 cybercrime.gov.in पर complaint</span>
          <span class="hcard-link-arr" aria-hidden="true">→</span>
        </a>
        <a href="<?php echo esc_url(home_url('/upi-fraud-guide')); ?>" class="hcard-link">
          <span class="hcard-link-text">💳 UPI Fraud — Bank dispute guide</span>
          <span class="hcard-link-arr" aria-hidden="true">→</span>
        </a>
        <a href="<?php echo esc_url(home_url('/fake-app-guide')); ?>" class="hcard-link">
          <span class="hcard-link-text">📱 Fake App की पहचान कैसे करें</span>
          <span class="hcard-link-arr" aria-hidden="true">→</span>
        </a>
        <a href="<?php echo esc_url(home_url('/scam-number-check')); ?>" class="hcard-link">
          <span class="hcard-link-text">🔍 Scam Number Check करें</span>
          <span class="hcard-link-arr" aria-hidden="true">→</span>
        </a>
      </div>
    </div>

  </div>
</section>


<!-- ==========================================
     AD ZONE — Homepage Mid
     ========================================== -->
<?php fraudalert_ad_zone('ad-home-mid', 'ad-zone-home-mid'); ?>

<!-- ==========================================
     LATEST ALERTS
     ========================================== -->
<section class="latest-alerts" aria-labelledby="alerts-heading">
  <div class="section-inner">
    <div class="sec-header">
      <div>
        <div class="sec-title" id="alerts-heading">🚨 Latest <span>Alerts</span></div>
        <div class="sec-sub">आज के सबसे नए और ज़रूरी fraud warnings</div>
      </div>
      <a href="<?php echo esc_url(home_url('/alerts')); ?>" class="sec-viewall">सभी देखें →</a>
    </div>

    <div class="alerts-layout">

      <!-- Main Column -->
      <div class="alerts-main">

        <?php
        // Featured post
        $featured = new WP_Query([
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_key'       => '_featured',
            'meta_value'     => '1',
            'no_found_rows'  => true,
        ]);

        // Fallback to latest if no featured post set
        if (!$featured->have_posts()) {
            $featured = new WP_Query([
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'no_found_rows'  => true,
            ]);
        }

        if ($featured->have_posts()) :
            $featured->the_post();
        ?>
        <a href="<?php the_permalink(); ?>" class="alert-featured">
          <div class="afeat-img">
            <?php if (has_post_thumbnail()) : ?>
              <?php the_post_thumbnail('fraudalert-hero', [
                  'loading' => 'eager',
                  'alt'     => esc_attr(get_the_title()),
              ]); ?>
            <?php else : ?>
              <span class="afeat-img-icon" aria-hidden="true">🚨</span>
            <?php endif; ?>
            <?php
            $cats = get_the_category();
            if ($cats) : ?>
              <span class="afeat-urgency"><?php echo esc_html($cats[0]->name); ?></span>
            <?php endif; ?>
          </div>
          <div class="afeat-body">
            <h2 class="afeat-title"><?php the_title(); ?></h2>
            <div class="afeat-desc"><?php echo wp_trim_words(get_the_excerpt(), 20, '...'); ?></div>
            <div class="afeat-meta">
              <span><?php echo get_the_date(); ?></span>
              <span>·</span>
              <strong><?php echo esc_html(get_the_author()); ?></strong>
            </div>
          </div>
        </a>
        <?php
        wp_reset_postdata();
        endif;

        // Latest 5 posts (excluding the featured)
        $latest = new WP_Query([
            'posts_per_page' => 5,
            'post_status'    => 'publish',
            'offset'         => 1,
            'no_found_rows'  => true,
        ]);
        ?>

        <div class="alerts-list">
          <?php while ($latest->have_posts()) : $latest->the_post(); ?>
            <?php
            $cats = get_the_category();
            $cat_name = $cats ? $cats[0]->name : '';
            ?>
            <a href="<?php the_permalink(); ?>" class="alert-item">
              <div class="ai-icon">
                <?php if (has_post_thumbnail()) : ?>
                  <?php the_post_thumbnail('fraudalert-thumb', ['loading' => 'lazy', 'alt' => '']); ?>
                <?php else : ?>
                  <span aria-hidden="true">🚨</span>
                <?php endif; ?>
              </div>
              <div class="ai-content">
                <div class="ai-title"><?php the_title(); ?></div>
                <div class="ai-meta">
                  <?php if ($cat_name) : ?>
                    <span class="bdg bdg-r"><?php echo esc_html($cat_name); ?></span>
                  <?php endif; ?>
                  <span><?php echo get_the_date(); ?></span>
                </div>
              </div>
              <span class="ai-arrow" aria-hidden="true">›</span>
            </a>
          <?php endwhile; wp_reset_postdata(); ?>
        </div>

      </div><!-- /.alerts-main -->

      <!-- Sidebar -->
      <aside class="alerts-sidebar" aria-label="Sidebar">

        <!-- Helpline Widget -->
        <div class="helpline-widget" role="complementary" aria-label="Helpline info">
          <div class="hw-icon" aria-hidden="true">📞</div>
          <div class="hw-num">1930</div>
          <div class="hw-label">National Cyber Crime Helpline</div>
          <div class="hw-sub">24×7 उपलब्ध — free call</div>
          <a href="tel:1930" class="hw-btn">अभी Call करें</a>
          <div class="hw-divider"></div>
          <a href="https://cybercrime.gov.in" class="hw-link" target="_blank" rel="noopener noreferrer">
            <span class="hw-link-text">🌐 Complaint Online दर्ज करें</span>
            <span class="hw-link-arr" aria-hidden="true">→</span>
          </a>
          <a href="<?php echo esc_url(home_url('/bank-helplines')); ?>" class="hw-link">
            <span class="hw-link-text">🏦 Bank Helpline Numbers</span>
            <span class="hw-link-arr" aria-hidden="true">→</span>
          </a>
        </div>

        <!-- Safety Checklist Widget -->
        <div class="widget">
          <div class="widget-hd">
            <h3>✅ Safety Checklist</h3>
          </div>
          <div class="widget-body">
            <?php
            $checklist = [
                ['icon' => '✓', 'warn' => false, 'text' => 'UPI PIN कभी share न करें'],
                ['icon' => '✓', 'warn' => false, 'text' => 'अनजान links पर click न करें'],
                ['icon' => '✓', 'warn' => false, 'text' => 'Bank कभी OTP नहीं मांगता'],
                ['icon' => '!', 'warn' => true,  'text' => 'Screen share app install मत करो'],
                ['icon' => '!', 'warn' => true,  'text' => '"Free gift" calls = Scam'],
                ['icon' => '✓', 'warn' => false, 'text' => 'Fraud होने पर 1930 call करें'],
            ];
            foreach ($checklist as $item) :
            ?>
            <div class="check-item">
              <span class="check-icon <?php echo $item['warn'] ? 'warn' : ''; ?>" aria-hidden="true">
                <?php echo $item['icon']; ?>
              </span>
              <span class="check-text"><?php echo esc_html($item['text']); ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Ad Zone — Sidebar -->
        <?php fraudalert_ad_zone('ad-sidebar'); ?>

      </aside><!-- /.alerts-sidebar -->

    </div><!-- /.alerts-layout -->
  </div>
</section>

<!-- ==========================================
     HOW TO PROTECT
     ========================================== -->
<section class="how-protect" aria-labelledby="protect-heading">
  <div class="section-inner">
    <div class="sec-header">
      <div>
        <div class="sec-title" id="protect-heading">🛡️ <span>खुद को बचाएं</span> — Step by Step</div>
        <div class="sec-sub">Fraud होने पर, या होने से पहले — जानें क्या करें</div>
      </div>
    </div>

    <?php
    $protection_data = [
        'upi' => [
            'id'    => 'steps-upi',
            'label' => 'UPI Fraud',
            'steps' => [
                ['num' => '01', 'icon' => '🚫', 'title' => 'Transaction रोकें',   'desc' => 'तुरंत अपने Bank को call करें और transaction block करवाएं। 24 घंटे में रिपोर्ट करें।', 'urgent' => true],
                ['num' => '02', 'icon' => '📞', 'title' => '1930 पर Call करें',  'desc' => 'National Cyber Crime Helpline 1930 पर call करें — free, 24×7 available।', 'urgent' => true],
                ['num' => '03', 'icon' => '💻', 'title' => 'Online Complaint',    'desc' => 'cybercrime.gov.in पर online complaint दर्ज करें। Evidence — screenshots और ID — save रखें।', 'urgent' => false],
                ['num' => '04', 'icon' => '🏛️', 'title' => 'FIR दर्ज करें',       'desc' => 'नजदीकी Police Station या Cyber Cell में FIR दर्ज करें। Complaint number note करें।', 'urgent' => false],
            ]
        ],
        'job' => [
            'id'    => 'steps-job',
            'label' => 'Fake Job',
            'steps' => [
                ['num' => '01', 'icon' => '🔍', 'title' => 'Identity Verify',   'desc' => 'Company की official email (@company.com) और official website check करें।', 'urgent' => true],
                ['num' => '02', 'icon' => '❌', 'title' => 'Payment न करें',   'desc' => 'Registration या interview के नाम पर पैसे की मांग = 100% Scam। कभी pay न करें।', 'urgent' => true],
                ['num' => '03', 'icon' => '📢', 'title' => 'Report करें',       'desc' => 'LinkedIn या Job Portal पर fake recruiter profile को तुरंत report करें।', 'urgent' => false],
                ['num' => '04', 'icon' => '📞', 'title' => '1930 Call करें',    'desc' => 'अगर पैसे कट गए हैं, तो तुरंत 1930 helpline पर report दर्ज करवाएं।', 'urgent' => false],
            ]
        ],
        'investment' => [
            'id'    => 'steps-investment',
            'label' => 'Investment Scam',
            'steps' => [
                ['num' => '01', 'icon' => '📈', 'title' => 'SEBI Check',       'desc' => 'क्या platform SEBI registered है? Official website पर license number verify करें।', 'urgent' => true],
                ['num' => '02', 'icon' => '🛡️', 'title' => 'Broker Verify',    'desc' => 'High returns का वादा करने वाले "Expert" या WhatsApp groups से सावधान रहें।', 'urgent' => true],
                ['num' => '03', 'icon' => '🛑', 'title' => 'Transfer रोकें',    'desc' => 'Profit निकालने के लिए और पैसे (Tax/Fee) मांगना scam का हिस्सा है। पैसे न भेजें।', 'urgent' => false],
                ['num' => '04', 'icon' => '💻', 'title' => 'Cyber Complaint',   'desc' => 'Investment fraud की details के साथ cybercrime.gov.in पर शिकायत दर्ज करें।', 'urgent' => false],
            ]
        ],
        'loan' => [
            'id'    => 'steps-loan',
            'label' => 'Loan App',
            'steps' => [
                ['num' => '01', 'icon' => '🏦', 'title' => 'RBI List Check',    'desc' => 'Loan app का NBFC partner RBI registered है या नहीं, यह RBI की list में देखें।', 'urgent' => true],
                ['num' => '02', 'icon' => '🔐', 'title' => 'Privacy Settings',  'desc' => 'App को Contacts और Gallery का access न दें। Settings में जाकर permissions बंद करें।', 'urgent' => true],
                ['num' => '03', 'icon' => '⚠️', 'title' => 'Harassment Help',   'desc' => 'अगर recovery agent परेशान करें, तो RBI Sachet portal पर शिकायत दर्ज करें।', 'urgent' => false],
                ['num' => '04', 'icon' => '🏛️', 'title' => 'Local Police',      'desc' => 'Blackmail या धमकी मिलने पर स्थानीय पुलिस और Cyber Cell को सूचित करें।', 'urgent' => false],
            ]
        ],
        'whatsapp' => [
            'id'    => 'steps-whatsapp',
            'label' => 'WhatsApp Scam',
            'steps' => [
                ['num' => '01', 'icon' => '📲', 'title' => '2-Step Verify',     'desc' => 'Settings > Account > Two-Step Verification enable करें। पिन किसी को न दें।', 'urgent' => true],
                ['num' => '02', 'icon' => '⛔', 'title' => 'Block & Report',    'desc' => 'अनजान international (+92, +84 etc) call आने पर तुरंत block और report करें।', 'urgent' => true],
                ['num' => '03', 'icon' => '👤', 'title' => 'Privacy Check',     'desc' => 'Profile photo और Status को "My Contacts" पर सेट करें ताकि अनजान न देख सकें।', 'urgent' => false],
                ['num' => '04', 'icon' => '🏠', 'title' => 'Family Alert',      'desc' => 'किसी को "Family Member" बनकर पैसे मांगते देख, call करके आवाज verify करें।', 'urgent' => false],
            ]
        ],
        'aadhaar' => [
            'id'    => 'steps-aadhaar',
            'label' => 'Aadhaar Fraud',
            'steps' => [
                ['num' => '01', 'icon' => '🔒', 'title' => 'Aadhaar Lock',     'desc' => 'm-Aadhaar app या UIDAI website से Biometrics lock करें। यह सबसे सुरक्षित तरीका है।', 'urgent' => true],
                ['num' => '02', 'icon' => '📱', 'title' => 'Official App',      'desc' => 'KYC update के लिए केवल bank के official app या branch का ही उपयोग करें।', 'urgent' => true],
                ['num' => '03', 'icon' => '🔗', 'title' => 'Link Caution',      'desc' => 'KYC suspended जैसे SMS में दिए गए किसी भी link पर click न करें।', 'urgent' => false],
                ['num' => '04', 'icon' => '🏢', 'title' => 'Physical Visit',    'desc' => 'संदेह होने पर पास की bank branch जाएं। Online KYC scam से बचें।', 'urgent' => false],
            ]
        ],
        'shopping' => [
            'id'    => 'steps-shopping',
            'label' => 'Shopping Scam',
            'steps' => [
                ['num' => '01', 'icon' => '🛒', 'title' => 'URL Verify',      'desc' => 'Website का URL check करें — https और correct domain name (flipkart.com, amazon.in) होना चाहिए।', 'urgent' => true],
                ['num' => '02', 'icon' => '💳', 'title' => 'COD चुनें',        'desc' => 'Unknown sites पर Cash on Delivery चुनें। Advance payment या direct bank transfer से बचें।', 'urgent' => true],
                ['num' => '03', 'icon' => '⭐', 'title' => 'Review Check',     'desc' => 'Product reviews और seller ratings ध्यान से पढ़ें। बहुत सस्ते deal = scam signal।', 'urgent' => false],
                ['num' => '04', 'icon' => '📦', 'title' => 'Fraud Report',     'desc' => 'Wrong product या no delivery मिलने पर Consumer Helpline 1800-11-4000 पर complaint करें।', 'urgent' => false],
            ]
        ],
        'crypto' => [
            'id'    => 'steps-crypto',
            'label' => 'Crypto Scam',
            'steps' => [
                ['num' => '01', 'icon' => '₿', 'title' => 'Exchange Verify',  'desc' => 'केवल RBI/FIU registered exchanges (WazirX, CoinDCX) ही use करें। Unknown apps से बचें।', 'urgent' => true],
                ['num' => '02', 'icon' => '🚫', 'title' => 'Guaranteed Return', 'desc' => 'Crypto में guaranteed returns असंभव है। "Daily 5% profit" का वादा = 100% scam।', 'urgent' => true],
                ['num' => '03', 'icon' => '🔑', 'title' => 'Private Key',     'desc' => 'अपनी Private Key या Seed Phrase कभी share न करें — यह आपके wallet की चाबी है।', 'urgent' => false],
                ['num' => '04', 'icon' => '📢', 'title' => 'Cyber Report',     'desc' => 'Crypto fraud होने पर तुरंत 1930 call करें और cybercrime.gov.in पर evidence के साथ complaint करें।', 'urgent' => false],
            ]
        ],
        'lottery' => [
            'id'    => 'steps-lottery',
            'label' => 'Lottery / KBC',
            'steps' => [
                ['num' => '01', 'icon' => '🎰', 'title' => 'Ignore करें',     'desc' => 'KBC, Jio, Amazon Lucky Draw जैसे messages 100% fake हैं। कोई भी company unsolicited prize नहीं देती।', 'urgent' => true],
                ['num' => '02', 'icon' => '💸', 'title' => 'Payment न करें',  'desc' => 'Prize claim के लिए Tax, GST, या Processing Fee मांगना = scam। एक रुपया भी न दें।', 'urgent' => true],
                ['num' => '03', 'icon' => '🔍', 'title' => 'Number Check',    'desc' => 'Unknown numbers से आने वाले calls को Truecaller पर check करें। +92, +44 codes से सावधान।', 'urgent' => false],
                ['num' => '04', 'icon' => '📱', 'title' => 'Block & Report',   'desc' => 'WhatsApp पर ऐसे messages आएं तो sender को Block करें और Report Spam पर click करें।', 'urgent' => false],
            ]
        ],
        'sim_swap' => [
            'id'    => 'steps-sim',
            'label' => 'SIM Swap',
            'steps' => [
                ['num' => '01', 'icon' => '📵', 'title' => 'Signal Check',    'desc' => 'अचानक mobile network बंद हो जाए तो तुरंत अपने telecom operator (Jio/Airtel/Vi) को call करें।', 'urgent' => true],
                ['num' => '02', 'icon' => '🏦', 'title' => 'Bank Alert',      'desc' => 'SIM बंद होते ही सभी linked bank accounts को तुरंत freeze करवाएं — nearest branch जाएं।', 'urgent' => true],
                ['num' => '03', 'icon' => '🔐', 'title' => 'SIM Lock',        'desc' => 'Operator से SIM port-out lock लगवाएं। Aadhaar-linked SIM change के लिए OTP आए तो share न करें।', 'urgent' => false],
                ['num' => '04', 'icon' => '📋', 'title' => 'FIR + Complaint', 'desc' => 'Police FIR दर्ज करें, 1930 call करें, और TRAI portal (1800-11-2000) पर भी complaint करें।', 'urgent' => false],
            ]
        ],
        'credit_card' => [
            'id'    => 'steps-cc',
            'label' => 'Credit Card',
            'steps' => [
                ['num' => '01', 'icon' => '🔒', 'title' => 'Card Block',      'desc' => 'Fraud transaction दिखे तो तुरंत bank app से card block करें या helpline call करें।', 'urgent' => true],
                ['num' => '02', 'icon' => '📲', 'title' => 'SMS Alerts',      'desc' => 'हर transaction की SMS/Email alert ON रखें। Unknown charge दिखे तो 24 घंटे में dispute करें।', 'urgent' => true],
                ['num' => '03', 'icon' => '🛡️', 'title' => 'CVV सुरक्षा',     'desc' => 'CVV, OTP, Card Number कभी phone या email पर share न करें। Bank कभी नहीं मांगता।', 'urgent' => false],
                ['num' => '04', 'icon' => '🌐', 'title' => 'Safe Shopping',   'desc' => 'Online payment सिर्फ https websites पर करें। Public WiFi पर card details enter न करें।', 'urgent' => false],
            ]
        ],
        'romance' => [
            'id'    => 'steps-romance',
            'label' => 'Romance Scam',
            'steps' => [
                ['num' => '01', 'icon' => '💔', 'title' => 'Identity Verify',  'desc' => 'Online मिले व्यक्ति की photo Google Reverse Image Search से check करें। Fake profile पहचानें।', 'urgent' => true],
                ['num' => '02', 'icon' => '💸', 'title' => 'Money न भेजें',   'desc' => 'कभी भी online friend/partner को पैसे न भेजें — Medical emergency या visa fee = scam tactics।', 'urgent' => true],
                ['num' => '03', 'icon' => '📹', 'title' => 'Video Call',       'desc' => 'Video call verify करें। अगर बार-बार बहाने बनाए तो scam है। अनजान से intimate chat avoid करें।', 'urgent' => false],
                ['num' => '04', 'icon' => '🚔', 'title' => 'Blackmail Help',   'desc' => 'अगर photos/videos से blackmail हो तो घबराएं नहीं — Cyber Cell में report करें, payment न करें।', 'urgent' => false],
            ]
        ],
    ];
    ?>

    <div class="protect-tabs" role="tablist" aria-label="Protection categories">
      <?php $first = true; foreach ($protection_data as $key => $data) : ?>
        <button
          class="ptab <?php echo $first ? 'active' : ''; ?>"
          role="tab"
          aria-selected="<?php echo $first ? 'true' : 'false'; ?>"
          aria-controls="<?php echo esc_attr($data['id']); ?>"
        >
          <?php echo esc_html($data['label']); ?>
        </button>
      <?php $first = false; endforeach; ?>
    </div>

    <?php $first = true; foreach ($protection_data as $key => $data) : ?>
      <div class="steps-grid <?php echo $first ? 'active' : ''; ?>" id="<?php echo esc_attr($data['id']); ?>" role="tabpanel">
        <?php foreach ($data['steps'] as $step) : ?>
          <div class="step-card">
            <div class="step-num" aria-hidden="true"><?php echo esc_html($step['num']); ?></div>
            <span class="step-icon" aria-hidden="true"><?php echo $step['icon']; ?></span>
            <div class="step-title"><?php echo esc_html($step['title']); ?></div>
            <div class="step-desc"><?php echo esc_html($step['desc']); ?></div>
            <?php if ($step['urgent']) : ?>
              <div class="step-urgency">
                <span>⚡ तुरंत करें</span>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php $first = false; endforeach; ?>

  </div>
</section>

<!-- ==========================================
     SCAM DETECTOR TOOL
     ========================================== -->
<section class="scam-detector" aria-labelledby="detector-heading">
  <div class="detector-inner">
    <div class="detector-badge">🔍 Free Tool</div>
    <h2 class="detector-title" id="detector-heading">
      Scam <em>Detector</em> Tool
    </h2>
    <p class="detector-sub">Number, link, या message paste करें — हम बताएंगे यह safe है या scam</p>

    <div class="detector-input-wrap" role="search">
      <label for="scam-input" class="sr-only">Number, link या message paste करें</label>
      <input
        type="text"
        id="scam-input"
        class="detector-input"
        placeholder="Number, link, या message paste करें..."
        aria-label="Enter number, link or message to check"
      >
      <button class="detector-btn" type="button" aria-label="Check for scam">Check करें 🔍</button>
    </div>

    <div class="detector-options" role="group" aria-label="Quick check options">
      <button class="d-opt" type="button">📱 Phone Number</button>
      <button class="d-opt" type="button">🔗 Suspicious Link</button>
      <button class="d-opt" type="button">💬 WhatsApp Message</button>
      <button class="d-opt" type="button">📧 Email Scam</button>
      <button class="d-opt" type="button">🏦 UPI ID Check</button>
    </div>
  </div>
</section>

<!-- ==========================================
     STATS BAND
     ========================================== -->
<div class="stats-band" aria-labelledby="stats-heading">
  <div class="section-inner">
    <div class="stats-grid" role="list">
      <div class="stat-item" role="listitem">
        <div class="stat-num danger">₹7,488<sup>Cr</sup></div>
        <div class="stat-lbl">Cyber Fraud Loss (2023)</div>
        <div class="stat-change up">32% increase</div>
      </div>
      <div class="stat-div" aria-hidden="true"></div>
      <div class="stat-item" role="listitem">
        <div class="stat-num">15.6L+</div>
        <div class="stat-lbl">Complaints Filed (2023)</div>
        <div class="stat-change up">45% increase</div>
      </div>
      <div class="stat-div" aria-hidden="true"></div>
      <div class="stat-item" role="listitem">
        <div class="stat-num">1930</div>
        <div class="stat-lbl">National Helpline Number</div>
        <div class="stat-change" style="color:var(--green)">✓ 24×7 Active</div>
      </div>
      <div class="stat-div" aria-hidden="true"></div>
      <div class="stat-item" role="listitem">
        <div class="stat-num">₹1000<sup>Cr+</sup></div>
        <div class="stat-lbl">Recovered via 1930 (2023)</div>
        <div class="stat-change" style="color:var(--green)">✓ Growing</div>
      </div>
    </div>
  </div>
</div>

<!-- ==========================================
     FAQ STRIP
     ========================================== -->
<section class="faq-strip" aria-labelledby="faq-heading">
  <div class="section-inner">
    <div class="sec-header">
      <div>
        <div class="sec-title" id="faq-heading">❓ Frequently Asked <span>Questions</span></div>
      </div>
      <a href="<?php echo esc_url(home_url('/faq')); ?>" class="sec-viewall">सभी FAQ →</a>
    </div>

    <div class="faq-cols">
      <div>
        <div class="faq-col-title">UPI &amp; Banking Fraud</div>
        <?php
        $faqs_col1 = [
            ['q' => 'UPI fraud हो गया — पैसा वापस मिलेगा?',          'a' => '72 घंटे में 1930 call और bank dispute file करें। 70% cases में refund possible है।'],
            ['q' => 'Fraud के बाद सबसे पहले क्या करें?',              'a' => 'Bank को call करके card/UPI block करें, फिर 1930 dial करें।'],
            ['q' => 'OTP share करने से क्या होता है?',               'a' => 'OTP share करते ही account access और transactions possible हो जाते हैं।'],
            ['q' => 'Fake bank call कैसे पहचानें?',                  'a' => 'Banks कभी OTP, CVV, या full card number नहीं मांगते। ऐसी call = scam।'],
        ];
        foreach ($faqs_col1 as $faq) :
        ?>
        <div class="faq-item">
          <button class="faq-q" aria-expanded="false">
            <span class="faq-q-icon" aria-hidden="true">▸</span>
            <?php echo esc_html($faq['q']); ?>
          </button>
          <div class="faq-a-preview" hidden><?php echo esc_html($faq['a']); ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div>
        <div class="faq-col-title">Online Scams &amp; Safety</div>
        <?php
        $faqs_col2 = [
            ['q' => 'WhatsApp पर आया lottery message real है?',       'a' => 'नहीं। कोई भी unsolicited lottery, KBC, या prize message 100% scam है।'],
            ['q' => 'Fake job offer कैसे पहचानें?',                  'a' => 'Advance payment मांगना, too-good salary, WhatsApp interview = red flags।'],
            ['q' => 'Aadhaar से fraud कैसे होता है?',                'a' => 'SIM swap, fake KYC, या identity theft। कभी किसी को Aadhaar OTP न दें।'],
            ['q' => 'Cyber complaint कहाँ दर्ज करें?',               'a' => 'cybercrime.gov.in या 1930 helpline। 72 hours के अंदर report करें।'],
        ];
        foreach ($faqs_col2 as $faq) :
        ?>
        <div class="faq-item">
          <button class="faq-q" aria-expanded="false">
            <span class="faq-q-icon" aria-hidden="true">▸</span>
            <?php echo esc_html($faq['q']); ?>
          </button>
          <div class="faq-a-preview" hidden><?php echo esc_html($faq['a']); ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</section>

<!-- ==========================================
     REPORT CTA
     ========================================== -->
<section class="report-cta" aria-label="Report fraud call to action">
  <div class="report-inner">
    <div class="report-text">
      <h2>फ्रॉड या धोखाधड़ी होने पर तुरंत रिपोर्ट करें!</h2>
      <p>हर मिनट कीमती है। 72 घंटे के अंदर report करने पर refund की संभावना सबसे ज़्यादा रहती है।</p>
    </div>
    <div class="report-btns">
      <a href="tel:1930" class="btn-white">📞 1930 Call करें</a>
      <a href="https://cybercrime.gov.in" class="btn-outline-white" target="_blank" rel="noopener noreferrer">🌐 Online Complaint</a>
    </div>
  </div>
</section>

<!-- ==========================================
     RESOURCES GRID
     ========================================== -->
<section class="resources" aria-labelledby="resources-heading">
  <div class="section-inner">
    <div class="sec-header">
      <div>
        <div class="sec-title" id="resources-heading">📚 Useful <span>Resources</span></div>
        <div class="sec-sub">Guides, tools, और official links — एक जगह पर</div>
      </div>
    </div>

    <div class="resources-grid">
      <?php
      $resources = [
          ['icon' => '🛡️', 'cat' => 'Official Portal',  'title' => 'Cyber Crime Reporting Portal',           'desc' => 'cybercrime.gov.in — Government of India का official fraud reporting portal।',           'link' => 'https://cybercrime.gov.in',               'cta' => 'Visit Portal →',  'ext' => true],
          ['icon' => '📋', 'cat' => 'Complete Guide',   'title' => 'Fraud के बाद — Complete Action Plan',   'desc' => 'Step-by-step guide: fraud के बाद पहले 24 घंटे में क्या करें।',                         'link' => home_url('/fraud-action-plan'),            'cta' => 'Guide पढ़ें →',  'ext' => false],
          ['icon' => '🏦', 'cat' => 'Bank Helplines',   'title' => 'सभी Banks के Fraud Helpline Numbers',   'desc' => 'SBI, HDFC, ICICI, Axis समेत सभी major banks के 24×7 fraud helpline numbers।',          'link' => home_url('/bank-helplines'),               'cta' => 'Numbers देखें →', 'ext' => false],
          ['icon' => '📱', 'cat' => 'App Safety',       'title' => 'Safe vs Unsafe App — कैसे पहचानें?',   'desc' => 'RBI registered loan apps की list, fake app के लक्षण, और safe app ढूंढने का तरीका।',     'link' => home_url('/app-safety-guide'),             'cta' => 'Check करें →',   'ext' => false],
          ['icon' => '📊', 'cat' => 'Awareness',        'title' => 'India Cyber Crime Report 2023',          'desc' => 'Latest statistics, most common scams, state-wise data — NCRB official data।',           'link' => home_url('/cyber-crime-report-2023'),     'cta' => 'Report देखें →', 'ext' => false],
          ['icon' => '🎯', 'cat' => 'Quiz',             'title' => 'Scam Awareness Quiz — Test Yourself',   'desc' => '10 सवालों का quiz — जांचें कि आप कितने scams पहचान सकते हैं।',                       'link' => home_url('/scam-quiz'),                   'cta' => 'Quiz लें →',     'ext' => false],
      ];
      foreach ($resources as $res) :
      ?>
      <a href="<?php echo esc_url($res['link']); ?>"
         class="res-card"
         <?php echo $res['ext'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
        <div class="res-icon" aria-hidden="true"><?php echo $res['icon']; ?></div>
        <div class="res-category"><?php echo esc_html($res['cat']); ?></div>
        <div class="res-title"><?php echo esc_html($res['title']); ?></div>
        <div class="res-desc"><?php echo esc_html($res['desc']); ?></div>
        <div class="res-arrow"><?php echo esc_html($res['cta']); ?></div>
      </a>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<!-- ==========================================
     SCAM TYPES GRID
     ========================================== -->
<div class="scam-types">
  <div class="section-inner">
    <div class="sec-header">
      <div>
        <div class="sec-title">सभी <span>Scam Types</span> — एक नज़र में</div>
        <div class="sec-sub">किस तरह का fraud हुआ? Category चुनें और complete guide पाएं।</div>
      </div>
      <a href="<?php echo esc_url(home_url('/scam-types')); ?>" class="sec-viewall">सभी देखें →</a>
    </div>

    <?php
    // Dynamic: show categories as scam type cards
    $scam_cats = get_categories([
        'hide_empty' => false,
        'number'     => 12,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ]);

    // Static fallback data
    $fallback_types = [
        ['icon' => '💸', 'name' => 'UPI / Banking Fraud',     'count' => '4,200+', 'hot' => 'सबसे ज़्यादा', 'danger' => true,  'slug' => 'upi-fraud'],
        ['icon' => '📱', 'name' => 'WhatsApp Scam',            'count' => '3,100+', 'hot' => 'Trending',    'danger' => true,  'slug' => 'whatsapp-scam'],
        ['icon' => '📈', 'name' => 'Investment / Stock Fraud', 'count' => '2,800+', 'hot' => '',            'danger' => false, 'slug' => 'investment-fraud'],
        ['icon' => '💼', 'name' => 'Fake Job Offer',           'count' => '1,900+', 'hot' => '',            'danger' => false, 'slug' => 'fake-job'],
        ['icon' => '🏦', 'name' => 'Loan App Fraud',           'count' => '1,600+', 'hot' => '',            'danger' => false, 'slug' => 'loan-fraud'],
        ['icon' => '🛒', 'name' => 'Online Shopping Scam',     'count' => '1,400+', 'hot' => '',            'danger' => false, 'slug' => 'shopping-scam'],
        ['icon' => '₿',  'name' => 'Crypto Scam',              'count' => '1,200+', 'hot' => '',            'danger' => false, 'slug' => 'crypto-scam'],
        ['icon' => '🏠', 'name' => 'Real Estate Fraud',        'count' => '900+',   'hot' => '',            'danger' => false, 'slug' => 'real-estate'],
        ['icon' => '🎰', 'name' => 'Lottery / KBC Scam',       'count' => '850+',   'hot' => '',            'danger' => false, 'slug' => 'lottery-scam'],
        ['icon' => '👴', 'name' => 'Senior Citizen Scam',      'count' => '700+',   'hot' => '',            'danger' => false, 'slug' => 'senior-scam'],
        ['icon' => '📋', 'name' => 'Aadhaar / KYC Fraud',      'count' => '650+',   'hot' => '',            'danger' => false, 'slug' => 'aadhaar-fraud'],
        ['icon' => '🎓', 'name' => 'Education Scam',           'count' => '580+',   'hot' => '',            'danger' => false, 'slug' => 'education-scam'],
    ];
    ?>

    <div class="types-grid">
      <?php foreach ($fallback_types as $type) : ?>
        <a href="<?php echo esc_url(home_url('/category/' . $type['slug'])); ?>"
           class="type-card<?php echo $type['danger'] ? ' danger' : ''; ?>">
          <span class="type-icon" aria-hidden="true"><?php echo $type['icon']; ?></span>
          <div class="type-name"><?php echo esc_html($type['name']); ?></div>
          <div class="type-count"><?php echo esc_html($type['count']); ?> cases</div>
          <?php if ($type['hot']) : ?>
            <div class="type-hot"><?php echo esc_html($type['hot']); ?></div>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<!-- ==========================================
     INLINE JS — FAQ accordion + Tab switcher
     (Zero dependencies, ~30 lines vanilla JS)
     ========================================== -->
<script>
(function() {
  'use strict';

  // FAQ Accordion
  document.querySelectorAll('.faq-q').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var answer  = this.nextElementSibling;
      var isOpen  = this.getAttribute('aria-expanded') === 'true';
      var icon    = this.querySelector('.faq-q-icon');
      this.setAttribute('aria-expanded', String(!isOpen));
      answer.hidden = isOpen;
      if (icon) icon.textContent = isOpen ? '▸' : '▾';
    });
  });

  // Protect Tabs (Performance focused)
  var tabs   = document.querySelectorAll('.ptab');
  var grids  = document.querySelectorAll('.steps-grid');

  tabs.forEach(function(tab) {
    tab.addEventListener('click', function() {
      var targetId = this.getAttribute('aria-controls');

      // Update tabs state
      tabs.forEach(function(t) {
        var isActive = (t === tab);
        t.classList.toggle('active', isActive);
        t.setAttribute('aria-selected', String(isActive));
      });

      // Update grids state
      grids.forEach(function(g) {
        g.classList.toggle('active', g.id === targetId);
      });
    });
  });

})();
</script>

<?php get_footer(); ?>
