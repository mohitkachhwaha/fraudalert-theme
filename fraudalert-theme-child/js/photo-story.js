/**
 * Photo Story — Frontend JavaScript
 * File:  js/photo-story.js
 * Theme: fraudalert-theme-child
 *
 * No jQuery. Vanilla ES5-compatible JS with 'use strict'.
 * Enqueued only on is_singular('photo_story') via PhotoStoryCPT::enqueue_assets().
 * Admin code lives in photo-story-admin.js — guarded by Feature 6 below.
 *
 * Features:
 *  1. Copy Link  (.ss-cp)          — clipboard API + execCommand fallback
 *  2. Social Share (.ss-fb/tw/wa/tg) — window.open with encoded URL/title
 *  3. Slide Hash                   — IntersectionObserver threshold:0.5
 *  4. Read Time  (.ps-readtime)    — word-count / 200, Math.ceil
 *  5. Image Fade (.ps-img-wrap)    — IntersectionObserver threshold:0.1
 *  6. Admin Guard                  — bail if document.body has .wp-admin
 *  7. Error Handling               — every feature wrapped in try-catch
 */

(function () {
    'use strict';

    /* ═══════════════════════════════════════════════════════════════
       FEATURE 6 — ADMIN GUARD
       Admin functionality is handled exclusively by photo-story-admin.js.
       If this script is ever accidentally loaded in the admin context,
       bail immediately so frontend observers don't pollute the editor.
    ═══════════════════════════════════════════════════════════════ */
    if (
        typeof document.body !== 'undefined' &&
        document.body.classList.contains('wp-admin')
    ) {
        return;
    }

    /* ═══════════════════════════════════════════════════════════════
       SHARE URL BUILDERS
       Each function receives a pre-encoded URL string and raw title;
       encodeURIComponent is applied here so callers don't double-encode.
    ═══════════════════════════════════════════════════════════════ */
    var SHARE_BUILDERS = {
        /**
         * Facebook — only accepts the URL (title ignored by FB Open Graph).
         */
        'ss-fb': function (url, title) {
            return (
                'https://www.facebook.com/sharer/sharer.php' +
                '?u=' + encodeURIComponent(url)
            );
        },

        /**
         * X (Twitter/X) — url + text.
         */
        'ss-tw': function (url, title) {
            return (
                'https://twitter.com/intent/tweet' +
                '?url='  + encodeURIComponent(url) +
                '&text=' + encodeURIComponent(title)
            );
        },

        /**
         * WhatsApp — combines title + url in a single text param.
         */
        'ss-wa': function (url, title) {
            return (
                'https://wa.me/' +
                '?text=' + encodeURIComponent(title + '\n' + url)
            );
        },

        /**
         * Telegram — url + text params.
         */
        'ss-tg': function (url, title) {
            return (
                'https://t.me/share/url' +
                '?url='  + encodeURIComponent(url) +
                '&text=' + encodeURIComponent(title)
            );
        }
    };

    /* ═══════════════════════════════════════════════════════════════
       HELPER: Get data-url and data-title from nearest ancestor
       with those attributes (the .ps-share-bar / .ps-slide-share div).
    ═══════════════════════════════════════════════════════════════ */
    function getShareData(btn) {
        var bar = btn.closest('[data-url]');
        return {
            url:   (bar && bar.dataset.url)   ? bar.dataset.url   : window.location.href,
            title: (bar && bar.dataset.title) ? bar.dataset.title : document.title
        };
    }

    /* ═══════════════════════════════════════════════════════════════
       FEATURE 1 — COPY LINK (.ss-cp)
       Priority:   navigator.clipboard.writeText() (async, secure context)
       Fallback:   document.execCommand('copy')    (sync, legacy browsers)
       Feedback:   button text + aria-label update for 2 s, then reset
    ═══════════════════════════════════════════════════════════════ */
    function initCopyLink() {
        try {
            document.querySelectorAll('.ss-cp').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    try {
                        var data          = getShareData(btn);
                        var url           = data.url;
                        var origText      = btn.textContent;
                        var origLabel     = btn.getAttribute('aria-label') || 'Copy link';

                        /* ── Feedback: update button text + aria-label ── */
                        function showCopiedFeedback() {
                            btn.textContent = 'Copied! ✓';
                            btn.setAttribute('aria-label', 'Link copied to clipboard');
                            btn.classList.add('ps-copied');
                            setTimeout(function () {
                                btn.textContent = origText;
                                btn.setAttribute('aria-label', origLabel);
                                btn.classList.remove('ps-copied');
                            }, 2000);
                        }

                        /* ── Fallback: execCommand (non-secure context / old Safari) ── */
                        function execCommandFallback(text) {
                            try {
                                var ta = document.createElement('textarea');
                                ta.value = text;
                                /* Keep off-screen; avoid scroll jump */
                                ta.style.cssText =
                                    'position:fixed;top:-9999px;left:-9999px;' +
                                    'width:1px;height:1px;opacity:0;pointer-events:none;';
                                document.body.appendChild(ta);
                                ta.focus();
                                ta.select();
                                var success = document.execCommand('copy');
                                document.body.removeChild(ta);
                                if (success) {
                                    showCopiedFeedback();
                                } else {
                                    console.warn('Photo Story: execCommand copy returned false.');
                                }
                            } catch (ex) {
                                console.warn('Photo Story: execCommand fallback failed.', ex);
                            }
                        }

                        /* ── Primary: Clipboard API ── */
                        if (
                            navigator.clipboard &&
                            typeof navigator.clipboard.writeText === 'function'
                        ) {
                            navigator.clipboard.writeText(url)
                                .then(showCopiedFeedback)
                                .catch(function (err) {
                                    /* Clipboard API rejected (permissions) — try fallback */
                                    console.warn('Photo Story: Clipboard API failed, using fallback.', err);
                                    execCommandFallback(url);
                                });
                        } else {
                            execCommandFallback(url);
                        }
                    } catch (innerErr) {
                        console.error('Photo Story: Copy click handler error.', innerErr);
                    }
                });
            });
        } catch (e) {
            console.error('Photo Story: initCopyLink error.', e);
        }
    }

    /* ═══════════════════════════════════════════════════════════════
       FEATURE 2 — SOCIAL SHARE (.ss-fb / .ss-tw / .ss-wa / .ss-tg)
       Opens a share popup: 600×500, rel=noopener,noreferrer equivalent.
       URL and title sourced from the nearest [data-url][data-title] ancestor
       (.ps-share-bar for header, .ps-slide-share for per-slide buttons).
    ═══════════════════════════════════════════════════════════════ */
    function initSocialShare() {
        try {
            Object.keys(SHARE_BUILDERS).forEach(function (cls) {
                document.querySelectorAll('.' + cls).forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        try {
                            var data     = getShareData(btn);
                            var shareUrl = SHARE_BUILDERS[cls](data.url, data.title);

                            /*
                             * 'noopener,noreferrer' as window.open features:
                             * Supported in Chrome 88+, Firefox 79+, Safari 12.1+.
                             * Nullifying opener manually as belt-and-suspenders.
                             */
                            var popup = window.open(
                                shareUrl,
                                '_blank',
                                'width=600,height=500,noopener,noreferrer'
                            );
                            /* Belt-and-suspenders: null the opener if browser didn't honour noopener */
                            if (popup && popup.opener) {
                                popup.opener = null;
                            }
                        } catch (innerErr) {
                            console.error('Photo Story: Social share click error.', innerErr);
                        }
                    });
                });
            });
        } catch (e) {
            console.error('Photo Story: initSocialShare error.', e);
        }
    }

    /* ═══════════════════════════════════════════════════════════════
       FEATURE 3 — SLIDE HASH + SCROLL REVEAL (.ps-slide)
       IntersectionObserver  threshold: 0.5
       On intersect:
         • history.replaceState → #slide-N  (no scroll jump)
         • classList.add('ps-visible')      → CSS: opacity 0→1, translateY→0
       data-slide-index is set dynamically (1-based) from DOM order.
    ═══════════════════════════════════════════════════════════════ */
    function initSlideHashAndReveal() {
        try {
            var slides = document.querySelectorAll('.ps-slide');
            if (!slides.length) return;

            /* Reduced-motion: reveal all immediately, no observer needed */
            var prefersReduced = (
                typeof window.matchMedia === 'function' &&
                window.matchMedia('(prefers-reduced-motion: reduce)').matches
            );
            if (prefersReduced) {
                slides.forEach(function (slide, i) {
                    slide.setAttribute('data-slide-index', i + 1);
                    slide.classList.add('ps-visible');
                });
                return;
            }

            /* IntersectionObserver not supported — reveal all */
            if (!('IntersectionObserver' in window)) {
                slides.forEach(function (slide, i) {
                    slide.setAttribute('data-slide-index', i + 1);
                    slide.classList.add('ps-visible');
                });
                return;
            }

            var slideObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    var idx = entry.target.getAttribute('data-slide-index');

                    /* Scroll-reveal */
                    entry.target.classList.add('ps-visible');

                    /* URL hash — only when index is known */
                    if (idx) {
                        try {
                            history.replaceState(null, '', '#slide-' + idx);
                        } catch (histErr) {
                            /* history.replaceState may throw in cross-origin iframes */
                            console.warn('Photo Story: history.replaceState failed.', histErr);
                        }
                    }

                    /*
                     * Unobserve once revealed — the slide won't go back to opacity:0,
                     * so no need to re-fire. Frees observer resources.
                     */
                    slideObserver.unobserve(entry.target);
                });
            }, {
                threshold: 0.5
            });

            slides.forEach(function (slide, i) {
                slide.setAttribute('data-slide-index', i + 1);
                slideObserver.observe(slide);
            });

        } catch (e) {
            console.error('Photo Story: initSlideHashAndReveal error.', e);
        }
    }

    /* ═══════════════════════════════════════════════════════════════
       FEATURE 4 — READ TIME (.ps-readtime)
       Sources: all .ps-caption-title + .ps-caption-text + .ps-intro
       Formula: Math.ceil(wordCount / 200) + " min read"
       Minimum: 1 min read (avoids "0 min read" for very short stories)
       The span is hidden by CSS (display:none via ps-readtime initial state),
       shown once populated to prevent orphan " | " pipe on no-JS.
    ═══════════════════════════════════════════════════════════════ */
    function initReadTime() {
        try {
            var rtEl = document.querySelector('.ps-readtime');
            if (!rtEl) return;

            var fullText = '';
            var selectors = ['.ps-caption-title', '.ps-caption-text', '.ps-intro'];
            selectors.forEach(function (sel) {
                document.querySelectorAll(sel).forEach(function (el) {
                    fullText += ' ' + (el.innerText || el.textContent || '');
                });
            });

            /* Count non-empty word tokens */
            var words = fullText
                .trim()
                .split(/\s+/)
                .filter(function (w) { return w.length > 0; })
                .length;

            var minutes = Math.max(1, Math.ceil(words / 200));

            rtEl.textContent = minutes + ' min read';
            rtEl.style.display = 'inline';   /* Reveal span — pipe separator now visible */

        } catch (e) {
            console.error('Photo Story: initReadTime error.', e);
        }
    }

    /* ═══════════════════════════════════════════════════════════════
       FEATURE 5 — IMAGE FADE (.ps-img-wrap)
       IntersectionObserver  threshold: 0.1   rootMargin: '50px'
       On intersect: classList.add('ps-visible') → CSS opacity:0→1
       Fires once per image (unobserve after reveal to free memory).
       Fallback: no IO support → add ps-visible immediately (no delay).
    ═══════════════════════════════════════════════════════════════ */
    function initImageFade() {
        try {
            var wraps = document.querySelectorAll('.ps-img-wrap');
            if (!wraps.length) return;

            /* No IO support: reveal all images immediately */
            if (!('IntersectionObserver' in window)) {
                wraps.forEach(function (w) { w.classList.add('ps-visible'); });
                return;
            }

            var imgObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('ps-visible');
                    imgObserver.unobserve(entry.target); /* Fire once */
                });
            }, {
                threshold:  0.1,
                rootMargin: '50px'
            });

            wraps.forEach(function (w) { imgObserver.observe(w); });

        } catch (e) {
            console.error('Photo Story: initImageFade error.', e);
        }
    }

    /* ═══════════════════════════════════════════════════════════════
       INIT — DOMContentLoaded
    ═══════════════════════════════════════════════════════════════ */
    document.addEventListener('DOMContentLoaded', function () {
        try {
            initCopyLink();
            initSocialShare();
            initSlideHashAndReveal();  /* Features 3 (hash) + scroll-reveal combined */
            initReadTime();
            initImageFade();
        } catch (e) {
            console.error('Photo Story: DOMContentLoaded init error.', e);
        }
    });

})();
