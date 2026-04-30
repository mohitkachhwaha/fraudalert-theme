/**
 * Ad Manager — Admin JavaScript
 * File: js/ps-ad-admin.js
 * Extracted from inline <script> in PSAdManager::render_page()
 * Enqueued via PSAdManager::enqueue_admin_assets() on ps_ad_* pages only.
 *
 * Also includes Ad Preview tab device filter logic.
 */

/* global wp */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        /* ══════════════════════════════════════════════════════
           TABS
        ══════════════════════════════════════════════════════ */
        var tabs   = document.querySelectorAll('.ps-ad-tab');
        var panels = document.querySelectorAll('.ps-ad-panel');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                tabs.forEach(function (t) {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
                panels.forEach(function (p) { p.classList.remove('active'); });

                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');
                document.getElementById('panel-' + tab.dataset.target).classList.add('active');

                /* Update hidden field — preserves tab across form save */
                var tabField = document.getElementById('ps-active-tab-field');
                if (tabField) tabField.value = tab.dataset.target;

                /* Update URL without page reload */
                var params = new URLSearchParams(window.location.search);
                params.set('tab', tab.dataset.target);
                history.replaceState(null, '', window.location.pathname + '?' + params.toString());
            });
        });

        /* ══════════════════════════════════════════════════════
           AD BLOCK INITIALIZER
           Initializes type toggles, media upload, and rule selectors.
           Called for initial DOM and for dynamically added elements.
        ══════════════════════════════════════════════════════ */
        function initAdBlocks(container) {

            /* Ad type → show/hide code vs image fields */
            container.querySelectorAll('.ps-ad-type-select').forEach(function (select) {
                select.addEventListener('change', function () {
                    var row        = this.closest('.ps-ad-row');
                    var codeFields = row.querySelectorAll('.ps-ad-code-fields');
                    var imgFields  = row.querySelectorAll('.ps-ad-image-fields');
                    if (this.value === 'custom_image') {
                        codeFields.forEach(function (f) { f.style.display = 'none'; });
                        imgFields.forEach(function (f)  { f.style.display = 'block'; });
                    } else {
                        codeFields.forEach(function (f) { f.style.display = 'block'; });
                        imgFields.forEach(function (f)  { f.style.display = 'none'; });
                    }
                });
                select.dispatchEvent(new Event('change'));
            });

            /* wp.media — cached frames per field ID */
            var mediaFrames = {};
            container.querySelectorAll('.ps-ad-upload-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var targetId = this.dataset.target;
                    var input    = document.getElementById(targetId);
                    var preview  = document.getElementById(targetId + '_preview');
                    if (mediaFrames[targetId]) {
                        mediaFrames[targetId].open();
                        return;
                    }
                    mediaFrames[targetId] = wp.media({
                        title:    'Select Ad Image',
                        button:   { text: 'Use Image' },
                        multiple: false
                    });
                    mediaFrames[targetId].on('select', function () {
                        var attachment = mediaFrames[targetId].state().get('selection').first().toJSON();
                        input.value = attachment.id;
                        preview.innerHTML = '<img src="' + attachment.url + '">';
                    });
                    mediaFrames[targetId].open();
                });
            });

            /* Condition rule type → show correct value selector */
            container.querySelectorAll('.ps-rule-type').forEach(function (sel) {
                sel.addEventListener('change', function () {
                    var row      = this.closest('.ps-rule-row');
                    var valInput = row.querySelector('.ps-rule-value');
                    var type     = this.value;
                    var nameAttr = valInput.name;

                    row.querySelectorAll('.ps-rule-val-select').forEach(function (s) {
                        s.style.display = 'none';
                        s.disabled = true;
                        s.removeAttribute('name');
                    });

                    var predefined = ['author', 'category', 'tag', 'user_role', 'device', 'login_status'];
                    if (predefined.indexOf(type) !== -1) {
                        valInput.style.display = 'none';
                        valInput.disabled = true;
                        valInput.removeAttribute('name');

                        var activeSel = row.querySelector('.ps-val-' + type);
                        if (activeSel) {
                            activeSel.style.display = 'inline-block';
                            activeSel.disabled = false;
                            activeSel.setAttribute('name', nameAttr);
                        }
                    } else {
                        valInput.style.display = 'inline-block';
                        valInput.disabled = false;
                        valInput.setAttribute('name', nameAttr);
                    }
                });
            });
        }

        initAdBlocks(document);

        /* ══════════════════════════════════════════════════════
           CONDITION GROUP — ADD
        ══════════════════════════════════════════════════════ */
        document.body.addEventListener('click', function (e) {
            if (!e.target.classList.contains('ps-add-cond-group')) return;
            e.preventDefault();

            var btn   = e.target;
            var tab   = btn.dataset.tab;
            var place = btn.dataset.place;
            var wrap  = document.getElementById('conds_' + tab + '_' + place);
            var idx   = Date.now();

            var tpl = document.getElementById('ps-cond-group-template').innerHTML;
            tpl = tpl.replace(/__TAB__/g, tab).replace(/__PLACE__/g, place).replace(/__IDX__/g, idx);

            var div = document.createElement('div');
            div.innerHTML = tpl;
            var newGroup = div.firstElementChild;
            wrap.appendChild(newGroup);

            initAdBlocks(newGroup);
            updatePriorities(wrap);
        });

        /* ══════════════════════════════════════════════════════
           CONDITION GROUP — REMOVE
        ══════════════════════════════════════════════════════ */
        document.body.addEventListener('click', function (e) {
            if (!e.target.classList.contains('ps-remove-cond-group')) return;
            e.preventDefault();
            if (confirm('Remove this condition group?')) {
                var wrap = e.target.closest('.ps-ad-conditions-wrap');
                e.target.closest('.ps-cond-group').remove();
                updatePriorities(wrap);
            }
        });

        /* ══════════════════════════════════════════════════════
           CONDITION RULE — ADD
        ══════════════════════════════════════════════════════ */
        document.body.addEventListener('click', function (e) {
            if (!e.target.classList.contains('ps-add-rule')) return;
            e.preventDefault();

            var btn  = e.target;
            var base = btn.dataset.base;
            var wrap = btn.closest('.ps-cond-group').querySelector('.ps-cond-rules-wrap');
            var ridx = Date.now();

            var tpl = document.getElementById('ps-cond-rule-template').innerHTML;
            tpl = tpl.replace(/__BASE__/g, base).replace(/__RIDX__/g, ridx);

            var div = document.createElement('div');
            div.innerHTML = tpl;
            var newRule = div.firstElementChild;
            wrap.appendChild(newRule);

            initAdBlocks(newRule);
        });

        /* ══════════════════════════════════════════════════════
           CONDITION RULE — REMOVE
        ══════════════════════════════════════════════════════ */
        document.body.addEventListener('click', function (e) {
            if (!e.target.classList.contains('ps-remove-rule')) return;
            e.preventDefault();
            var wrap = e.target.closest('.ps-cond-rules-wrap');
            if (wrap.querySelectorAll('.ps-rule-row').length > 1) {
                e.target.closest('.ps-rule-row').remove();
            } else {
                alert('A condition group must have at least one rule.');
            }
        });

        /* ══════════════════════════════════════════════════════
           UPDATE CONDITION GROUP PRIORITIES
        ══════════════════════════════════════════════════════ */
        function updatePriorities(wrap) {
            wrap.querySelectorAll('.ps-cond-group').forEach(function (g, i) {
                var p = g.querySelector('.ps-cond-priority');
                if (p) p.textContent = i + 1;
            });
        }

        document.querySelectorAll('.ps-ad-conditions-wrap').forEach(updatePriorities);

        /* ══════════════════════════════════════════════════════
           AD PREVIEW TAB — DEVICE FILTER
        ══════════════════════════════════════════════════════ */
        document.querySelectorAll('.ps-dev-filter').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.ps-dev-filter').forEach(function (b) {
                    b.classList.remove('active');
                });
                btn.classList.add('active');

                var device = btn.dataset.device;
                document.querySelectorAll('.ps-preview-placement').forEach(function (el) {
                    var elDevice = el.dataset.device || 'all';
                    var show = (device === 'all' || elDevice === 'all' || elDevice === device);
                    el.hidden = !show;
                });
            });
        });

    }); // DOMContentLoaded

})();
