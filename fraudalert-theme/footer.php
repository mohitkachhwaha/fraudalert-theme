<!-- ==========================================
     FOOTER
     ========================================== -->
<footer class="site-footer" role="contentinfo">
  <div class="footer-inner">

    <div class="footer-top">

      <!-- Brand Column -->
      <div class="footer-brand">
        <?php fraudalert_logo(); ?>
        <p>India का सबसे comprehensive scam और fraud awareness platform। हमारा मकसद है हर भारतीय को cyber fraud से सुरक्षित रखना — free guides, real-time alerts, और expert advice के ज़रिए।</p>
        <div class="footer-social" aria-label="Social media links">
          <a href="#" class="fsoc" aria-label="Facebook" target="_blank" rel="noopener noreferrer">📘</a>
          <a href="#" class="fsoc" aria-label="Twitter / X" target="_blank" rel="noopener noreferrer">🐦</a>
          <a href="#" class="fsoc" aria-label="Instagram" target="_blank" rel="noopener noreferrer">📸</a>
          <a href="#" class="fsoc" aria-label="YouTube" target="_blank" rel="noopener noreferrer">▶️</a>
          <a href="#" class="fsoc" aria-label="WhatsApp Channel" target="_blank" rel="noopener noreferrer">💬</a>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="footer-col">
        <h4>Quick Links</h4>
        <?php
        wp_nav_menu([
            'theme_location' => 'footer',
            'container'      => false,
            'menu_class'     => '',
            'depth'          => 1,
            'fallback_cb'    => 'fraudalert_footer_fallback_nav',
        ]);
        ?>
      </div>

      <!-- Report & Help -->
      <div class="footer-col">
        <h4>Report &amp; Help</h4>
        <ul>
          <li><a href="tel:1930">1930 — Cyber Helpline</a></li>
          <li><a href="https://cybercrime.gov.in" target="_blank" rel="noopener noreferrer">cybercrime.gov.in</a></li>
          <li><a href="<?php echo esc_url(home_url('/bank-helplines')); ?>">Bank Helpline Numbers</a></li>
          <li><a href="<?php echo esc_url(home_url('/how-to-file-fir')); ?>">FIR कैसे दर्ज करें</a></li>
          <li><a href="<?php echo esc_url(home_url('/rbi-ombudsman')); ?>">RBI Ombudsman</a></li>
        </ul>
      </div>

      <!-- About -->
      <div class="footer-col">
        <h4>About Us</h4>
        <ul>
          <li><a href="<?php echo esc_url(home_url('/about')); ?>">हमारे बारे में</a></li>
          <li><a href="<?php echo esc_url(home_url('/editorial-policy')); ?>">Editorial Policy</a></li>
          <li><a href="<?php echo esc_url(home_url('/contact')); ?>">Contact Us</a></li>
          <li><a href="<?php echo esc_url(home_url('/privacy-policy')); ?>">Privacy Policy</a></li>
          <li><a href="<?php echo esc_url(home_url('/disclaimer')); ?>">Disclaimer</a></li>
        </ul>
      </div>

    </div><!-- /.footer-top -->

    <div class="footer-bottom">
      <div class="footer-copy">
        &copy; <?php echo date('Y'); ?> स्कैम से बचो. सभी अधिकार सुरक्षित।सुरक्षित।
        | Cyber Crime Helpline: <strong style="color:var(--saf2)">1930</strong>
      </div>
      <div class="footer-disclaimer">
        Disclaimer: यह एक awareness platform है। किसी भी fraud के लिए तुरंत
        official channels (1930, cybercrime.gov.in) से संपर्क करें।
      </div>
    </div>

  </div><!-- /.footer-inner -->
</footer>

<!-- ==========================================
     FLOATING BUTTONS
     ========================================== -->
<div class="float-helpline" aria-label="Quick action buttons">
  <a href="tel:1930" class="float-btn helpline" aria-label="Call 1930 Cyber Helpline">
    <span aria-hidden="true">📞</span> 1930 Helpline
  </a>
  <a href="<?php echo esc_url(home_url('/report-fraud')); ?>" class="float-btn" aria-label="Report a fraud">
    <span class="float-pulse" aria-hidden="true"></span> Fraud Report करें
  </a>
</div>

<!-- ==========================================
     INLINE JS — Mobile Menu Toggle (zero dependency)
     ========================================== -->
<script>
(function() {
  'use strict';
  var toggle = document.querySelector('.menu-toggle');
  var nav    = document.getElementById('mobile-nav');
  if (!toggle || !nav) return;

  toggle.addEventListener('click', function() {
    var isOpen = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', String(!isOpen));
    nav.hidden = isOpen;
    nav.classList.toggle('open', !isOpen);
    toggle.textContent = isOpen ? '☰' : '✕';
  });

  // Close on outside click
  document.addEventListener('click', function(e) {
    if (!toggle.contains(e.target) && !nav.contains(e.target)) {
      toggle.setAttribute('aria-expanded', 'false');
      nav.hidden = true;
      nav.classList.remove('open');
      toggle.textContent = '☰';
    }
  });
})();
</script>

<?php wp_footer(); ?>
</body>
</html>

<?php
/**
 * Footer fallback nav when no footer menu assigned
 */
function fraudalert_footer_fallback_nav(): void {
    echo '<ul>';
    echo '<li><a href="' . esc_url(home_url('/alerts')) . '">Latest Alerts</a></li>';
    echo '<li><a href="' . esc_url(home_url('/scam-types')) . '">Scam Types</a></li>';
    echo '<li><a href="' . esc_url(home_url('/guides')) . '">How-To Guides</a></li>';
    echo '<li><a href="' . esc_url(home_url('/faq')) . '">FAQ</a></li>';
    echo '</ul>';
}
