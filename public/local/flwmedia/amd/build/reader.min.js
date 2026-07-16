// This file is part of Moodle - http://moodle.org/

define(['local_flwmedia/lazyload'], function(Lazyload) {
    /**
     * Attach reading completion controls.
     *
     * @param {Element} root Root node.
     * @param {Function} saveAttempt Attempt callback.
     */
    var init = function(root, saveAttempt) {
        Array.prototype.forEach.call(root.querySelectorAll('.flwmedia-card-read'), function(card) {
            Lazyload.loadElement(card);

            var button = card.querySelector('.flwmedia-mark-read');
            var response = card.querySelector('.flwmedia-read-response');
            var status = card.querySelector('.flwmedia-status');

            button.addEventListener('click', function() {
                button.disabled = true;
                saveAttempt(card.dataset.itemid, {
                    response: response ? response.value : '',
                    transcript: '',
                    score: 100,
                    feedback: 'Marked as read',
                    audiofileurl: '',
                    attemptjson: JSON.stringify({markedRead: true})
                }).then(function() {
                    status.textContent = 'Reading completed.';
                    status.classList.add('is-complete');
                }).catch(function() {
                    button.disabled = false;
                });
            });
        });
    };

    return {
        init: init
    };
});
