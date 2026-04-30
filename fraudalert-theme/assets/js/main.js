/**
 * FraudAlert India — main.js
 * Vanilla JS only. Zero dependencies. Deferred loading.
 * All critical inline JS is in footer.php and index.php
 */

(function () {
  'use strict';

  /* ==============================================
     1. SMOOTH SCROLL FOR ANCHOR LINKS
     ============================================== */
  document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
    anchor.addEventListener('click', function (e) {
      var target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });


  /* ==============================================
     2. DETECTOR TOOL — Quick option buttons
     ============================================== */
  var detectorInput = document.getElementById('scam-input');
  var detectorBtn   = document.querySelector('.detector-btn');

  document.querySelectorAll('.d-opt').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (detectorInput) {
        detectorInput.placeholder = 'Enter ' + this.textContent.trim() + '...';
        detectorInput.focus();
      }
    });
  });

  if (detectorBtn && detectorInput) {
    detectorBtn.addEventListener('click', function () {
      var val = detectorInput.value.trim();
      if (!val) {
        detectorInput.focus();
        return;
      }
      // Future: Integrate with scam check API
      // For now: redirect to search
      window.location.href = '/?s=' + encodeURIComponent(val);
    });

    // Allow Enter key to submit
    detectorInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') detectorBtn.click();
    });
  }


  /* ==============================================
     3. STICKY HEADER — Add shadow on scroll
     ============================================== */
  var header = document.querySelector('.site-header');
  if (header) {
    var lastScroll = 0;
    window.addEventListener('scroll', function () {
      var current = window.scrollY;
      if (current > 10) {
        header.style.boxShadow = '0 4px 24px rgba(0,52,89,.14)';
      } else {
        header.style.boxShadow = '0 2px 16px rgba(0,52,89,.08)';
      }
      lastScroll = current;
    }, { passive: true });
  }


  /* ==============================================
     4. FLOAT BUTTONS — Hide on scroll down, show on scroll up
     ============================================== */
  var floatBtns = document.querySelector('.float-helpline');
  if (floatBtns) {
    var prevY    = 0;
    var ticking  = false;

    window.addEventListener('scroll', function () {
      if (!ticking) {
        requestAnimationFrame(function () {
          var currentY = window.scrollY;
          if (currentY > prevY && currentY > 300) {
            floatBtns.style.transform = 'translateY(120px)';
            floatBtns.style.opacity   = '0';
          } else {
            floatBtns.style.transform = 'translateY(0)';
            floatBtns.style.opacity   = '1';
          }
          floatBtns.style.transition = 'all .3s ease';
          prevY   = currentY;
          ticking = false;
        });
        ticking = true;
      }
    }, { passive: true });
  }


  /* ==============================================
     5. IMAGE ERROR FALLBACK
     Replace broken images with placeholder emoji
     ============================================== */
  document.querySelectorAll('img').forEach(function (img) {
    img.addEventListener('error', function () {
      this.style.display = 'none';
      var parent = this.closest('.ai-icon, .related-card-img, .post-card-thumb, .afeat-img');
      if (parent) {
        parent.innerHTML = '<span style="font-size:32px;display:flex;align-items:center;justify-content:center;height:100%;opacity:.5">🚨</span>';
      }
    });
  });


  /* ==============================================
     6. PROTECT TABS — handled by inline JS in index.php
     No duplicate handler needed here.
     ============================================== */

})();
