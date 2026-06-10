/**
 * Product Badges admin JS
 * - Live preview of badge appearance when color inputs change
 * - Filter for the product multiselect
 */
(function ($) {
    'use strict';

    // ── Live badge preview ───────────────────────────────────────────────────

    function updatePreview() {
        var bg    = $('#bg_color').val() || '';
        var text  = $('#text_color').val() || '';
        var langId = window.id_lang_default || '';
        var label = $('#label_' + langId).val() || 'BADGE';

        var hexRe = /^#[0-9A-Fa-f]{6}$/;
        if (!hexRe.test(bg) || !hexRe.test(text)) {
            return;
        }

        $('#productbadges-live-preview')
            .text(label)
            .css({ 'background-color': bg, 'color': text });
    }

    // ── Product multiselect filter ───────────────────────────────────────────

    function initProductFilter() {
        var $filter   = $('#pb_product_filter');
        var $select   = $('#pb_product_multiselect');

        if (!$filter.length || !$select.length) {
            return;
        }

        // Cache all options so we can restore them on filter clear
        var allOptions = $select.find('option').clone();

        $filter.on('input', function () {
            var query = $(this).val().toLowerCase().trim();
            $select.empty();

            allOptions.each(function () {
                if (!query || $(this).text().toLowerCase().indexOf(query) !== -1) {
                    $select.append($(this).clone());
                }
            });
        });
    }

    // ── Init ─────────────────────────────────────────────────────────────────

    $(document).ready(function () {
        // Preview
        if ($('#bg_color').length) {
            var $preview = $('<span>', {
                id: 'productbadges-live-preview',
                'class': 'productbadges-preview'
            }).text('BADGE');

            $('#bg_color').closest('.form-group').after(
                $('<div class="form-group"><label class="control-label col-lg-3"></label>'
                    + '<div class="col-lg-9"></div></div>').find('div').append($preview).end()
            );

            updatePreview();
            $(document).on('input change', '#bg_color, #text_color, [id^="label_"]', updatePreview);
        }

        // Product filter
        initProductFilter();
    });
}(jQuery));
