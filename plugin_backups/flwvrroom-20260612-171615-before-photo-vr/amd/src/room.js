define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    var started = Date.now();

    var clamp = function(value, min, max) {
        return Math.max(min, Math.min(max, value));
    };

    var updateScore = function(root, passinggrade, maxgrade) {
        var score = 0;
        var completed = [];

        root.querySelectorAll('[data-hotspot].is-complete').forEach(function(button) {
            score += parseInt(button.getAttribute('data-score'), 10) || 0;
            completed.push(button.getAttribute('data-hotspot'));
        });

        var answer = root.querySelector('input[type=radio]:checked');
        if (answer) {
            score += parseInt(answer.value, 10) || 0;
        }

        score = clamp(score, 0, maxgrade);
        root.querySelector('[data-region="score"]').textContent = score;
        root.classList.toggle('is-passed', score >= passinggrade);

        return {
            score: score,
            completed: completed
        };
    };

    var init = function(config) {
        var root = document.getElementById(config.rootid);
        if (!root) {
            return;
        }

        root.querySelectorAll('[data-hotspot]').forEach(function(button) {
            button.addEventListener('click', function() {
                button.classList.add('is-complete');
                button.setAttribute('aria-pressed', 'true');
                updateScore(root, config.passinggrade, config.maxgrade);
            });
        });

        root.querySelectorAll('input[type=radio]').forEach(function(input) {
            input.addEventListener('change', function() {
                updateScore(root, config.passinggrade, config.maxgrade);
            });
        });

        var save = root.querySelector('[data-action="save-attempt"]');
        var status = root.querySelector('[data-region="status"]');

        save.addEventListener('click', function() {
            var result = updateScore(root, config.passinggrade, config.maxgrade);
            save.disabled = true;
            status.textContent = 'Saving...';

            Ajax.call([{
                methodname: 'mod_flwvrroom_submit_attempt',
                args: {
                    cmid: config.cmid,
                    score: result.score,
                    completedobjects: result.completed.join(','),
                    taskcomplete: result.score >= config.passinggrade,
                    durationseconds: Math.round((Date.now() - started) / 1000)
                }
            }])[0].then(function(response) {
                var bestScore = root.querySelector('[data-region="best-score"]');
                bestScore.textContent = Math.max(parseInt(bestScore.textContent, 10) || 0, response.score);
                status.textContent = config.strings.saved + ' Score: ' + response.score + (response.passed ? ' / Passed' : ' / Try again');
                return response;
            }).catch(function(error) {
                status.textContent = config.strings.savefailed;
                Notification.exception(error);
            }).then(function() {
                save.disabled = false;
            });
        });
    };

    return {
        init: init
    };
});
