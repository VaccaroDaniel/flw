// This file is part of Moodle - http://moodle.org/

define(['local_flwmedia/lazyload'], function(Lazyload) {
    /**
     * Attach browser MediaRecorder controls.
     *
     * @param {Element} root Root node.
     * @param {Function} saveAttempt Attempt callback.
     */
    var init = function(root, saveAttempt) {
        Array.prototype.forEach.call(root.querySelectorAll('.flwmedia-card-speak'), function(card) {
            var start = card.querySelector('.flwmedia-record-start');
            var stop = card.querySelector('.flwmedia-record-stop');
            var status = card.querySelector('.flwmedia-status');
            var recorder = null;
            var chunks = [];
            var startedAt = 0;

            Lazyload.loadElement(card);

            if (!navigator.mediaDevices || !window.MediaRecorder) {
                if (status) {
                    status.textContent = 'Recording is not supported in this browser.';
                }
                if (start) {
                    start.disabled = true;
                }
                return;
            }

            start.addEventListener('click', function() {
                navigator.mediaDevices.getUserMedia({audio: true}).then(function(stream) {
                    chunks = [];
                    recorder = new MediaRecorder(stream);
                    startedAt = Date.now();

                    recorder.addEventListener('dataavailable', function(event) {
                        if (event.data && event.data.size > 0) {
                            chunks.push(event.data);
                        }
                    });

                    recorder.addEventListener('stop', function() {
                        stream.getTracks().forEach(function(track) {
                            track.stop();
                        });
                        var blob = new Blob(chunks, {type: recorder.mimeType || 'audio/webm'});
                        var duration = Math.round((Date.now() - startedAt) / 1000);

                        // Future server upload belongs here; v1 stores metadata only.
                        saveAttempt(card.dataset.itemid, {
                            response: '',
                            transcript: '',
                            score: null,
                            feedback: '',
                            audiofileurl: '',
                            attemptjson: JSON.stringify({
                                recorded: true,
                                mimeType: blob.type,
                                bytes: blob.size,
                                duration: duration
                            })
                        }).then(function() {
                            status.textContent = 'Recording saved.';
                            status.classList.add('is-complete');
                        });
                    });

                    recorder.start();
                    start.disabled = true;
                    stop.disabled = false;
                    status.textContent = 'Recording...';
                }).catch(function(error) {
                    status.textContent = error.message || 'Microphone permission was not granted.';
                });
            });

            stop.addEventListener('click', function() {
                if (recorder && recorder.state !== 'inactive') {
                    recorder.stop();
                }
                start.disabled = false;
                stop.disabled = true;
            });
        });
    };

    return {
        init: init
    };
});
