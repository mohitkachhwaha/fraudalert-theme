/**
 * Ad Manager Frontend Logic
 * Theme: fraudalert-theme-child
 *
 * Fixes applied:
 *  - sessionStorage wrapped in try-catch (Private/Incognito safe)
 *  - Scroll listener throttled via requestAnimationFrame
 *  - IntersectionObserver.unobserve() after ad loads (memory leak fix)
 *  - setInterval cleared on visibilitychange (memory leak fix)
 *  - body.ps-sticky-active class for content push
 *  - Dead setInterval body removed
 */

'use strict';

(function () {

    /* ── sessionStorage helpers (Private/Incognito safe) ── */
    function ssGet(key) {
        try { return sessionStorage.getItem(key); } catch (e) { return null; }
    }
    function ssSet(key, val) {
        try { sessionStorage.setItem(key, val); } catch (e) { /* storage disabled */ }
    }

    var PSAds = {

        init: function () {
            try {
                this.setupStickyFooter();
                this.setupLazyLoading();
                this.setupAdRefresh();
            } catch (e) {
                if (window.location.hostname === 'localhost') {
                    console.error('PSAds Init Error:', e);
                }
            }
        },

        /* ═══════════════════════════════════════════════════════
           1. STICKY FOOTER
        ═══════════════════════════════════════════════════════ */
        setupStickyFooter: function () {
            try {
                var footer = document.querySelector('.ps-ad-sticky-footer');
                if (!footer) return;

                /* Already dismissed this session */
                if (ssGet('ps_ad_footer_hidden') === 'true') {
                    footer.style.display = 'none';
                    return;
                }

                /* Inject close button dynamically — absent when JS disabled */
                var closeBtn = document.createElement('button');
                closeBtn.className = 'ps-sticky-close';
                closeBtn.innerHTML = '&times;';
                closeBtn.setAttribute('aria-label', 'Close advertisement');
                closeBtn.setAttribute('type', 'button');
                footer.appendChild(closeBtn);

                closeBtn.addEventListener('click', function () {
                    footer.style.display = 'none';
                    document.body.classList.remove('ps-sticky-active');
                    ssSet('ps_ad_footer_hidden', 'true');
                    /* Remove listener — no more scroll needed after close */
                    window.removeEventListener('scroll', onScroll);
                });

                /* Throttled scroll handler via requestAnimationFrame */
                var ticking = false;
                function onScroll() {
                    if (!ticking) {
                        requestAnimationFrame(function () {
                            if (ssGet('ps_ad_footer_hidden') === 'true') {
                                ticking = false;
                                return;
                            }
                            var show = window.scrollY > 300;
                            footer.style.display = show ? 'block' : 'none';
                            /* Add body padding so sticky footer doesn't overlap content */
                            document.body.classList.toggle('ps-sticky-active', show);
                            ticking = false;
                        });
                        ticking = true;
                    }
                }

                window.addEventListener('scroll', onScroll, { passive: true });

            } catch (e) {
                if (window.location.hostname === 'localhost') {
                    console.error('PSAds Sticky Footer Error:', e);
                }
            }
        },

        /* ═══════════════════════════════════════════════════════
           2. LAZY AD LOADING
           Uses IntersectionObserver to trigger ps-ad-loaded class.
           Falls back gracefully — PHP already rendered ad content in DOM.
        ═══════════════════════════════════════════════════════ */
        setupLazyLoading: function () {
            try {
                if (!('IntersectionObserver' in window)) {
                    /* Fallback: mark all ads as loaded immediately */
                    document.querySelectorAll('.ps-ad-wrap').forEach(function (w) {
                        w.classList.add('ps-ad-loaded');
                    });
                    return;
                }

                var self = this;
                var adWrappers = document.querySelectorAll('.ps-ad-wrap');
                if (!adWrappers.length) return;

                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            var wrapper = entry.target;
                            if (!wrapper.classList.contains('ps-ad-loaded')) {
                                self.loadAd(wrapper);
                                observer.unobserve(wrapper); /* Free after load — no re-fire */
                            }
                        }
                    });
                }, {
                    rootMargin: '200px'
                });

                adWrappers.forEach(function (wrap) { observer.observe(wrap); });

            } catch (e) {
                if (window.location.hostname === 'localhost') {
                    console.error('PSAds Lazy Load Error:', e);
                }
            }
        },

        loadAd: function (wrapper) {
            try {
                wrapper.classList.add('ps-ad-loaded');

                /* If ad was deferred inside a <template> for true lazy loading */
                var template = wrapper.querySelector('template.ps-ad-template');
                if (template) {
                    wrapper.appendChild(template.content.cloneNode(true));
                }
            } catch (e) {
                if (window.location.hostname === 'localhost') {
                    console.error('PSAds Load Ad Error:', e);
                }
            }
        },

        /* ═══════════════════════════════════════════════════════
           3. AD REFRESH (GAM only — AdSense does not support it)
           Interval cleared on visibilitychange to prevent memory leaks.
           Refresh body is a hook — implement per ad network requirements.
        ═══════════════════════════════════════════════════════ */
        setupAdRefresh: function () {
            try {
                var refreshId = setInterval(function () {
                    /* Only process visible ads */
                    document.querySelectorAll('.ps-ad-wrap.ps-ad-loaded').forEach(function (ad) {
                        if (PSAds.isElementVisible(ad)) {
                            /* GAM refresh hook:
                               googletag.pubads().refresh([slot]);
                               Uncomment and configure per GAM slot setup. */
                        }
                    });
                }, 30000); /* 30 seconds */

                /* Clear interval when page is hidden — prevents background CPU usage */
                document.addEventListener('visibilitychange', function () {
                    if (document.hidden) {
                        clearInterval(refreshId);
                    }
                });

            } catch (e) {
                if (window.location.hostname === 'localhost') {
                    console.error('PSAds Refresh Error:', e);
                }
            }
        },

        /**
         * Partial-visibility check (more useful than fully-in-viewport for ads).
         */
        isElementVisible: function (el) {
            var rect = el.getBoundingClientRect();
            return rect.top < (window.innerHeight || document.documentElement.clientHeight) &&
                   rect.bottom > 0 &&
                   rect.left < (window.innerWidth  || document.documentElement.clientWidth) &&
                   rect.right > 0;
        }
    };

    document.addEventListener('DOMContentLoaded', function () { PSAds.init(); });

})();
