// This file is part of Moodle - http://moodle.org/

define(['local_flwmedia/lazyload'], function(Lazyload) {
    /**
     * Normalize dictation text.
     *
     * @param {String} text Text to normalize.
     * @return {String}
     */
    var normalize = function(text) {
        return (text || '')
            .toLowerCase()
            .trim()
            .replace(/[^\p{L}\p{N}\s]+/gu, '')
            .replace(/\s+/g, ' ')
            .trim();
    };

    /**
     * Score a dictation answer.
     *
     * @param {String} response Learner response.
     * @param {String} expected Expected text.
     * @return {Object}
     */
    var score = function(response, expected) {
        var normalizedResponse = normalize(response);
        var normalizedExpected = normalize(expected);

        if (!normalizedExpected) {
            return {score: 0, exact: false, normalizedMatch: false, wordOverlap: 0};
        }

        if (normalizedResponse === normalizedExpected) {
            return {
                score: 100,
                exact: response.trim() === expected.trim(),
                normalizedMatch: true,
                wordOverlap: 100
            };
        }

        var expectedWords = normalizedExpected.split(' ').filter(Boolean);
        var responseWords = normalizedResponse.split(' ').filter(Boolean);
        var matches = expectedWords.filter(function(word) {
            return responseWords.indexOf(word) !== -1;
        }).length;
        var overlap = expectedWords.length ? Math.round((matches / expectedWords.length) * 100) : 0;

        return {
            score: overlap,
            exact: false,
            normalizedMatch: false,
            wordOverlap: overlap
        };
    };

    /**
     * Attach dictation controls.
     *
     * @param {Element} root Root node.
     * @param {Function} saveAttempt Attempt callback.
     */
    var init = function(root, saveAttempt) {
        Array.prototype.forEach.call(root.querySelectorAll('.flwmedia-card-dictate'), function(card) {
            Lazyload.loadElement(card);

            var textarea = card.querySelector('.flwmedia-dictation-response');
            var check = card.querySelector('.flwmedia-dictation-check');
            var reset = card.querySelector('.flwmedia-dictation-reset');
            var status = card.querySelector('.flwmedia-status');
            var answer = card.querySelector('.flwmedia-answer');
            var expected = card.dataset.expected || '';

            check.addEventListener('click', function() {
                var result = score(textarea.value, expected);
                var message = result.normalizedMatch ? 'Correct.' : 'Score: ' + result.score + '% word overlap.';
                status.textContent = message;
                status.classList.toggle('is-complete', result.score >= 80);
                if (answer) {
                    answer.open = true;
                }

                saveAttempt(card.dataset.itemid, {
                    response: textarea.value,
                    transcript: '',
                    score: result.score,
                    feedback: JSON.stringify(result),
                    audiofileurl: '',
                    attemptjson: JSON.stringify(result)
                });
            });

            reset.addEventListener('click', function() {
                textarea.value = '';
                status.textContent = '';
                status.classList.remove('is-complete');
                if (answer) {
                    answer.open = false;
                }
            });
        });
    };

    return {
        init: init,
        normalize: normalize,
        score: score
    };
});
