/**
 * Lightweight FLW video gallery pagination.
 *
 * @module     theme_flwclean/video_gallery
 * @copyright  2026 Foreign Language World
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    const SELECTOR = '.flw-video-gallery';

    const pauseVideo = function(card) {
        const video = card.querySelector('video');
        if (video && typeof video.pause === 'function') {
            video.pause();
        }
    };

    const setVideoLoading = function(card, isVisible) {
        const video = card.querySelector('video');
        if (!video) {
            return;
        }

        if (isVisible && !video.dataset.flwLoaded) {
            const sources = video.querySelectorAll('source[data-src]');
            sources.forEach(function(source) {
                source.src = source.dataset.src;
                source.removeAttribute('data-src');
            });
            if (video.dataset.src) {
                video.src = video.dataset.src;
                video.removeAttribute('data-src');
            }
            video.dataset.flwLoaded = '1';
            video.load();
        }
    };

    const setupGallery = function(gallery) {
        if (gallery.dataset.flwGalleryReady === '1') {
            return;
        }

        const cards = Array.from(gallery.querySelectorAll('.flw-video-card'));
        const pageSize = Math.max(parseInt(gallery.dataset.pageSize || '6', 10), 1);
        const pager = gallery.querySelector('.flw-video-gallery__pager');
        const prev = pager ? pager.querySelector('[data-flw-gallery-prev]') : null;
        const next = pager ? pager.querySelector('[data-flw-gallery-next]') : null;
        const status = pager ? pager.querySelector('[data-flw-gallery-status]') : null;
        const totalPages = Math.max(Math.ceil(cards.length / pageSize), 1);
        let page = 0;

        const render = function() {
            cards.forEach(function(card, index) {
                const visible = index >= page * pageSize && index < (page + 1) * pageSize;
                card.hidden = !visible;
                setVideoLoading(card, visible);
                if (!visible) {
                    pauseVideo(card);
                }
            });

            if (prev) {
                prev.disabled = page === 0;
            }
            if (next) {
                next.disabled = page >= totalPages - 1;
            }
            if (status) {
                status.textContent = (page + 1) + ' / ' + totalPages;
            }
            if (pager) {
                pager.hidden = totalPages <= 1;
            }
        };

        if (prev) {
            prev.addEventListener('click', function() {
                page = Math.max(page - 1, 0);
                render();
            });
        }
        if (next) {
            next.addEventListener('click', function() {
                page = Math.min(page + 1, totalPages - 1);
                render();
            });
        }

        gallery.dataset.flwGalleryReady = '1';
        render();
    };

    return {
        init: function() {
            document.querySelectorAll(SELECTOR).forEach(setupGallery);
        }
    };
});
