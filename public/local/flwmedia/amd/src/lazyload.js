// This file is part of Moodle - http://moodle.org/

define([], function() {
    /**
     * Load deferred media sources inside a node.
     *
     * @param {Element} root Root node.
     */
    var loadElement = function(root) {
        if (!root) {
            return;
        }

        var sources = root.matches && root.matches('[data-src]') ? [root] : root.querySelectorAll('[data-src]');
        Array.prototype.forEach.call(sources, function(source) {
            if (!source.getAttribute('src')) {
                source.setAttribute('src', source.getAttribute('data-src'));
                source.removeAttribute('data-src');
            }
            var media = source.closest('audio,video');
            if (media && typeof media.load === 'function') {
                media.load();
            }
        });
    };

    /**
     * Observe lazy media and load when cards enter the viewport.
     *
     * @param {Element} root Root node.
     */
    var init = function(root) {
        if (!root) {
            return;
        }

        if (!('IntersectionObserver' in window)) {
            root.addEventListener('play', function(event) {
                loadElement(event.target);
            }, true);
            return;
        }

        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    loadElement(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: '160px 0px'
        });

        Array.prototype.forEach.call(root.querySelectorAll('.flwmedia-card'), function(card) {
            observer.observe(card);
        });
    };

    return {
        init: init,
        loadElement: loadElement
    };
});
