/**
 * Photo Story — Admin Meta Box JavaScript
 * File:  js/photo-story-admin.js
 * Theme: fraudalert-theme-child
 *
 * Extracted from the inline <script> in render_slides_box().
 * Enqueued only on photo_story post.php / post-new.php screens.
 *
 * Dependencies: wp-media (loaded via wp_enqueue_media in enqueue_admin_assets)
 *
 * Responsibilities:
 *  1. Drag-and-drop slide reordering (HTML5 DnD)
 *  2. Add new slide row dynamically
 *  3. Remove slide row (min 1 kept)
 *  4. WP Media Library picker per slide
 *  5. Input name index renumbering after every mutation
 */
(function () {
    'use strict';

    var wrap   = null; // #ps-slides-wrap
    var addBtn = null; // #ps-add-slide
    var dragEl = null; // currently dragged row

    /* ─────────────────────────────────────────
       HELPER: Renumber all row indices & names
    ───────────────────────────────────────── */
    function renum() {
        wrap.querySelectorAll('.ps-row').forEach(function (row, i) {
            row.setAttribute('data-index', i);
            var numEl = row.querySelector('.ps-num');
            if (numEl) numEl.textContent = i + 1;

            row.querySelectorAll('input, textarea').forEach(function (field) {
                var name = field.getAttribute('name');
                if (name) {
                    field.setAttribute(
                        'name',
                        name.replace(/ps_slides\[\d+\]/, 'ps_slides[' + i + ']')
                    );
                }
            });
        });
    }

    /* ─────────────────────────────────────────
       HELPER: Build new slide row HTML
    ───────────────────────────────────────── */
    function buildRowHTML(i) {
        return (
            '<div class="ps-row" draggable="true" data-index="' + i + '">' +
                '<div class="ps-hd">' +
                    '<span class="ps-drag">&#9776;</span>' +
                    '<strong>Slide <span class="ps-num">' + (i + 1) + '</span></strong>' +
                    '<button type="button" class="button ps-rm">Remove</button>' +
                '</div>' +
                '<div class="ps-bd">' +
                    '<div class="ps-ic">' +
                        '<input type="hidden" name="ps_slides[' + i + '][slide_image_id]" value="0" class="ps-img-id">' +
                        '<div class="ps-pv"><div class="ps-ph">No image</div></div>' +
                        '<button type="button" class="button ps-up">Choose Image</button>' +
                    '</div>' +
                    '<div class="ps-tc">' +
                        '<p>' +
                            '<label><strong>Caption Title</strong> <small>(max 120)</small></label><br>' +
                            '<input type="text" name="ps_slides[' + i + '][slide_caption_title]" value="" class="widefat" maxlength="120">' +
                        '</p>' +
                        '<p>' +
                            '<label><strong>Caption Text</strong> <small>(max 300)</small></label><br>' +
                            '<textarea name="ps_slides[' + i + '][slide_caption_text]" class="widefat" rows="3" maxlength="300"></textarea>' +
                        '</p>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );
    }

    /* ─────────────────────────────────────────
       DRAG & DROP
    ───────────────────────────────────────── */
    function bindDrag(row) {
        row.addEventListener('dragstart', function (e) {
            dragEl = row;
            row.classList.add('ps-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', row.getAttribute('data-index'));
        });

        row.addEventListener('dragend', function () {
            dragEl = null;
            row.classList.remove('ps-dragging');
            wrap.querySelectorAll('.ps-row').forEach(function (r) {
                r.classList.remove('ps-over');
            });
        });

        row.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (row !== dragEl) row.classList.add('ps-over');
        });

        row.addEventListener('dragleave', function () {
            row.classList.remove('ps-over');
        });

        row.addEventListener('drop', function (e) {
            e.preventDefault();
            row.classList.remove('ps-over');
            if (!dragEl || dragEl === row) return;

            // Determine direction to handle "insert after last" edge case
            var allRows = Array.from(wrap.querySelectorAll('.ps-row'));
            var dragIdx = allRows.indexOf(dragEl);
            var dropIdx = allRows.indexOf(row);

            if (dragIdx < dropIdx) {
                // Dragging down: insert after target
                wrap.insertBefore(dragEl, row.nextSibling);
            } else {
                // Dragging up: insert before target
                wrap.insertBefore(dragEl, row);
            }
            renum();
        });
    }

    /* ─────────────────────────────────────────
       MEDIA UPLOADER
    ───────────────────────────────────────── */
    function bindMediaUpload(row) {
        var upBtn  = row.querySelector('.ps-up');
        var imgId  = row.querySelector('.ps-img-id');
        var pvWrap = row.querySelector('.ps-pv');
        var frame  = null; // cached — reuse on subsequent clicks (prevents duplicate select events)

        if (!upBtn) return;

        upBtn.addEventListener('click', function (e) {
            e.preventDefault();

            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert('WordPress Media Library is not available. Please refresh and try again.');
                return;
            }

            if (frame) { frame.open(); return; } // reuse existing frame

            frame = wp.media({
                title    : 'Select Slide Image',
                multiple : false,
                library  : { type: 'image' },
                button   : { text: 'Use this image' },
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                var thumb      = (attachment.sizes && attachment.sizes.thumbnail)
                    ? attachment.sizes.thumbnail.url
                    : attachment.url;

                imgId.value      = attachment.id;
                pvWrap.innerHTML = '<img src="' + thumb + '" alt="Slide preview" style="max-width:200px;height:auto;border-radius:4px;border:1px solid #ddd;">';
            });

            frame.open();
        });
    }

    /* ─────────────────────────────────────────
       REMOVE SLIDE
    ───────────────────────────────────────── */
    function bindRemove(row) {
        var rmBtn = row.querySelector('.ps-rm');
        if (!rmBtn) return;

        rmBtn.addEventListener('click', function () {
            var rows = wrap.querySelectorAll('.ps-row');
            if (rows.length <= 1) {
                // Keep minimum 1 slide — just clear its fields
                var idField = row.querySelector('.ps-img-id');
                var pvWrap  = row.querySelector('.ps-pv');
                var inputs  = row.querySelectorAll('input[type="text"], textarea');
                if (idField) idField.value = '0';
                if (pvWrap) pvWrap.innerHTML = '<div class="ps-ph">No image</div>';
                inputs.forEach(function (f) { f.value = ''; });
                return;
            }
            row.remove();
            renum();
        });
    }

    /* ─────────────────────────────────────────
       BIND ALL EVENTS TO A ROW
    ───────────────────────────────────────── */
    function bindRow(row) {
        bindDrag(row);
        bindMediaUpload(row);
        bindRemove(row);
    }

    /* ─────────────────────────────────────────
       ADD SLIDE
    ───────────────────────────────────────── */
    function bindAddSlide() {
        addBtn.addEventListener('click', function () {
            var currentCount = wrap.querySelectorAll('.ps-row').length;
            if (currentCount >= 20) {
                alert('Maximum 20 slides allowed per story.');
                return;
            }
            wrap.insertAdjacentHTML('beforeend', buildRowHTML(currentCount));
            var newRow = wrap.lastElementChild;
            bindRow(newRow);
            renum(); // guarantee sequential indices after any prior delete+reorder
            newRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }

    /* ─────────────────────────────────────────
       INIT
    ───────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        wrap   = document.getElementById('ps-slides-wrap');
        addBtn = document.getElementById('ps-add-slide');

        if (!wrap || !addBtn) return; // Not on a photo_story edit screen

        wrap.querySelectorAll('.ps-row').forEach(bindRow);
        bindAddSlide();
    });

})();
