function productBadgesInit() {
    var el   = document.getElementById('productbadges-data');
    var data = el ? JSON.parse(el.getAttribute('data-badges') || '{}') : {};
    var maxB = el ? parseInt(el.getAttribute('data-max') || '0', 10) : 0;

    // ── Listing pages: render from JSON data ─────────────────────────────
    document.querySelectorAll('[data-id-product]').forEach(function (article) {
        if (article.querySelector('.productbadges-wrapper')) { return; }

        var idProduct = parseInt(article.getAttribute('data-id-product'), 10);
        var badges    = data[idProduct];

        if (!badges || badges.length === 0) { return; }
        if (maxB > 0) { badges = badges.slice(0, maxB); }

        // Target the image container inside the article card
        var imgTarget = article.querySelector('.card-img-top')
                     || article;
        imgTarget.style.position = 'relative';
        imgTarget.appendChild(buildWrapper(badges));
    });

    // ── Product page: move existing wrapper (rendered by hook) ───────────
    document.querySelectorAll('.productbadges-wrapper').forEach(function (wrapper) {
        if (wrapper.closest && wrapper.closest('[data-id-product]')) { return; }
        var imgContainer = document.querySelector(
            '.images-container, .product-cover-container, .js-qv-product-cover'
        );
        if (imgContainer) {
            imgContainer.style.position = 'relative';
            imgContainer.prepend(wrapper);
        }
    });

    // ── Helper ───────────────────────────────────────────────────────────
    function buildWrapper(badges) {
        var wrapper  = document.createElement('div');
        wrapper.className = 'productbadges-wrapper';

        var colLeft  = document.createElement('div');
        colLeft.className = 'productbadges-col productbadges-col-left';

        var colRight = document.createElement('div');
        colRight.className = 'productbadges-col productbadges-col-right';

        badges.forEach(function (b) {
            var span = document.createElement('span');
            span.className = 'productbadges-badge';
            span.textContent = b.label;
            span.style.backgroundColor = b.bg;
            span.style.color           = b.text;
            if (b.position === 'top-right') {
                colRight.appendChild(span);
            } else {
                colLeft.appendChild(span);
            }
        });

        wrapper.appendChild(colLeft);
        wrapper.appendChild(colRight);
        return wrapper;
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', productBadgesInit);
} else {
    productBadgesInit();
}
