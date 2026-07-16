// This file is part of Moodle - http://moodle.org/

define(['local_flwmedia/lazyload'], function(Lazyload) {
    /**
     * Attach progress tracking to audio and video cards.
     *
     * @param {Element} root Root node.
     * @param {Function} saveProgress Progress callback.
     */
    var init = function(root, saveProgress) {
        Array.prototype.forEach.call(root.querySelectorAll('audio, video'), function(media) {
            var card = media.closest('.flwmedia-card');
            if (!card) {
                return;
            }

            var lastSavedPercent = -1;
            var lastSavedAt = 0;

            media.addEventListener('play', function() {
                Lazyload.loadElement(media);
            });

            var save = function(force) {
                var duration = media.duration || 0;
                var seconds = Math.floor(media.currentTime || 0);
                var percent = duration > 0 ? Math.min(100, Math.floor((seconds / duration) * 100)) : 0;
                var completed = percent >= 90 || media.ended;
                var now = Date.now();

                if (!force && !completed && (now - lastSavedAt < 10000) && Math.abs(percent - lastSavedPercent) < 10) {
                    return;
                }

                lastSavedAt = now;
                lastSavedPercent = percent;
                saveProgress(card.dataset.itemid, card.dataset.mode, percent, completed, null, JSON.stringify({
                    event: completed ? 'complete' : 'progress',
                    currentTime: seconds,
                    duration: Math.floor(duration)
                }));
            };

            media.addEventListener('timeupdate', function() {
                save(false);
            });
            media.addEventListener('ended', function() {
                save(true);
            });
        });
    };

    return {
        init: init
    };
});
