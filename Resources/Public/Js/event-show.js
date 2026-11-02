(function () {
    'use strict';

    function initializeEventShow() {
        function syncCarouselControls(carousel) {
            var image = carousel.querySelector('.carousel-item.active img');
            if (!image) {
                return;
            }

            var imageHeight = Math.ceil(image.getBoundingClientRect().height);
            if (imageHeight > 0) {
                carousel.style.setProperty('--rescue-carousel-image-height', imageHeight + 'px');
            }
        }

        document.querySelectorAll('.rescue-image-gallery.carousel').forEach(function (carousel) {
            syncCarouselControls(carousel);
            carousel.addEventListener('slid.bs.carousel', function () {
                syncCarouselControls(carousel);
            });

            var image = carousel.querySelector('.carousel-item.active img');
            if (image && !image.complete) {
                image.addEventListener('load', function () {
                    syncCarouselControls(carousel);
                }, { once: true });
            }
            window.addEventListener('resize', function () {
                syncCarouselControls(carousel);
            });
        });

        var lightbox = null;
        if (typeof window.GLightbox === 'function' && document.querySelector('.glightbox')) {
            lightbox = window.GLightbox({
                selector: '.glightbox',
                keyboardNavigation: true,
                touchNavigation: true,
                loop: true
            });
        }

        if (
            window.jQuery
            && typeof window.jQuery.fn.owlCarousel === 'function'
            && document.getElementById('einsatzslider')
        ) {
            window.jQuery('#einsatzslider').owlCarousel({
                singleItem: true,
                navigation: true,
                navigationText: ['zurück', 'weiter'],
                slideSpeed: 400,
                autoHeight: true,
                lazyLoad: true,
                lazyFollow: true,
                addClassActive: true
            });
        }

        document.addEventListener('keydown', function (event) {
            if (
                event.key === 'Escape'
                && lightbox
                && document.body.classList.contains('glightbox-open')
            ) {
                event.preventDefault();
                event.stopImmediatePropagation();
                lightbox.close();
                return;
            }

            if (
                event.defaultPrevented
                || event.altKey
                || event.ctrlKey
                || event.metaKey
                || document.body.classList.contains('glightbox-open')
            ) {
                return;
            }

            var gallery = event.target.closest('.rescue-image-gallery');
            if (!gallery || (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight')) {
                return;
            }

            var direction = event.key === 'ArrowLeft' ? 'prev' : 'next';
            if (gallery.classList.contains('carousel')) {
                var button = gallery.querySelector('.carousel-control-' + direction);
                if (button) {
                    event.preventDefault();
                    button.click();
                }
                return;
            }

            if (gallery.id === 'einsatzslider' && window.jQuery) {
                event.preventDefault();
                window.jQuery(gallery).trigger('owl.' + direction);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeEventShow, { once: true });
    } else {
        initializeEventShow();
    }
}());
