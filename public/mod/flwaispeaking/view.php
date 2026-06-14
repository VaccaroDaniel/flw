<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('flwaispeaking', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$flwaispeaking = $DB->get_record('flwaispeaking', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/flwaispeaking:view', $context);

$PAGE->set_url('/mod/flwaispeaking/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($flwaispeaking->name));
$PAGE->set_heading(format_string($course->fullname));

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

if (optional_param('refresh', 0, PARAM_BOOL)) {
    require_capability('mod/flwaispeaking:viewreports', $context);
    require_sesskey();
    flwaispeaking_sync_activity_submissions($flwaispeaking);
    redirect($PAGE->url, get_string('resultsrefreshed', 'flwaispeaking'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$attemptcount = flwaispeaking_count_user_attempts((int) $flwaispeaking->id, (int) $USER->id);
$attemptsremaining = empty($flwaispeaking->maxattempts) ? null : max(0, (int) $flwaispeaking->maxattempts - $attemptcount);

if (data_submitted() && confirm_sesskey()) {
    require_capability('mod/flwaispeaking:submit', $context);

    if ($attemptsremaining !== null && $attemptsremaining <= 0) {
        throw new moodle_exception('nomoreattempts', 'flwaispeaking');
    }

    $transcript = optional_param('transcript', '', PARAM_RAW_TRIMMED);
    $audiodata = optional_param('audiodata', '', PARAM_RAW);
    $submissiontype = 'transcript';
    $audioinfo = [];

    if (trim($audiodata) !== '') {
        $transcription = flwaispeaking_transcribe_audio_dataurl($audiodata);
        $transcript = $transcription['transcript'];
        $submissiontype = 'audio';
        $audioinfo = [
            'filename' => $transcription['filename'],
            'mimetype' => $transcription['mimetype'],
        ];
    }

    if (trim($transcript) === '') {
        throw new moodle_exception('audioortranscriptrequired', 'flwaispeaking');
    }

    $submissionid = flwaispeaking_submit_transcript($flwaispeaking, $cm, (int) $USER->id, $transcript, $submissiontype, $audioinfo);
    $submission = $DB->get_record('flwaispeaking_submissions', ['id' => $submissionid], '*', MUST_EXIST);

    redirect(
        new moodle_url('/mod/flwaispeaking/view.php', ['id' => $cm->id]),
        get_string($submission->status === 'complete' ? 'submissionprocessed' : 'submissionqueued', 'flwaispeaking'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$output = $PAGE->get_renderer('core');
echo $output->header();

echo $output->heading(format_string($flwaispeaking->name), 2);

if (trim((string) $flwaispeaking->intro) !== '') {
    echo format_module_intro('flwaispeaking', $flwaispeaking, $cm->id);
}

echo html_writer::tag('div', format_text($flwaispeaking->prompttext, FORMAT_PLAIN), ['class' => 'alert alert-info']);

if (has_capability('mod/flwaispeaking:viewreports', $context)) {
    echo html_writer::div(
        html_writer::link(new moodle_url('/mod/flwaispeaking/report.php', ['id' => $cm->id]), get_string('report', 'flwaispeaking'), ['class' => 'btn btn-secondary']) . ' ' .
        html_writer::link(new moodle_url('/mod/flwaispeaking/view.php', ['id' => $cm->id, 'refresh' => 1, 'sesskey' => sesskey()]), get_string('refreshresults', 'flwaispeaking'), ['class' => 'btn btn-secondary']),
        'mb-3'
    );
}

if (has_capability('mod/flwaispeaking:submit', $context)) {
    if ($attemptsremaining === null) {
        echo html_writer::div(get_string('unlimitedattempts', 'flwaispeaking'), 'text-muted mb-2');
    } else {
        echo html_writer::div(get_string('attemptsremaining', 'flwaispeaking', $attemptsremaining), 'text-muted mb-2');
    }

    if ($attemptsremaining === null || $attemptsremaining > 0) {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'audiodata', 'id' => 'flwaispeaking-audiodata', 'value' => '']);

        $mode = $flwaispeaking->submissionmode ?? 'transcript';
        if ($mode === 'transcript' || $mode === 'both') {
            echo html_writer::start_div('mb-3');
            echo html_writer::label(get_string('transcript', 'flwaispeaking'), 'transcript');
            echo html_writer::tag('textarea', '', [
                'name' => 'transcript',
                'id' => 'transcript',
                'class' => 'form-control',
                'rows' => 8,
                'required' => $mode === 'transcript' ? 'required' : null,
                'placeholder' => get_string('transcript_help', 'flwaispeaking'),
            ]);
            echo html_writer::end_div();
        }

        if ($mode === 'audio' || $mode === 'both') {
            echo html_writer::start_div('mb-3');
            echo html_writer::tag('div', get_string('audioonly_help', 'flwaispeaking'), ['class' => 'form-text mb-2']);
            echo html_writer::tag('button', get_string('recordaudio', 'flwaispeaking'), [
                'type' => 'button',
                'id' => 'flwaispeaking-record',
                'class' => 'btn btn-secondary',
            ]);
            echo ' ';
            echo html_writer::tag('button', get_string('stoprecording', 'flwaispeaking'), [
                'type' => 'button',
                'id' => 'flwaispeaking-stop',
                'class' => 'btn btn-secondary',
                'disabled' => 'disabled',
            ]);
            echo html_writer::tag('div', '', ['id' => 'flwaispeaking-recording-status', 'class' => 'form-text mt-2']);
            echo html_writer::tag('audio', '', ['id' => 'flwaispeaking-playback', 'class' => 'mt-2', 'controls' => 'controls', 'style' => 'display:none;']);
            echo html_writer::end_div();
            echo flwaispeaking_recording_script();
        }

        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('submitspeaking', 'flwaispeaking'),
            'class' => 'btn btn-primary',
        ]);
        echo html_writer::end_tag('form');
    } else {
        echo $output->notification(get_string('nomoreattempts', 'flwaispeaking'), 'info');
    }
}

$submissions = $DB->get_records('flwaispeaking_submissions', [
    'flwaispeakingid' => $flwaispeaking->id,
    'userid' => $USER->id,
], 'timecreated DESC');

echo $output->heading(get_string('yoursubmissions', 'flwaispeaking'), 3);
flwaispeaking_print_submissions_table($submissions);

echo $output->footer();

/**
 * Print a submissions table.
 *
 * @param array $submissions Submission records.
 */
function flwaispeaking_print_submissions_table(array $submissions): void {
    global $PAGE;

    if (!$submissions) {
        echo html_writer::div(get_string('nosubmissions', 'flwaispeaking'), 'alert alert-info');
        return;
    }

    $table = new html_table();
    $table->head = [
        get_string('attempt', 'flwaispeaking'),
        get_string('submissiontype', 'flwaispeaking'),
        get_string('status', 'flwaispeaking'),
        get_string('cefr', 'flwaispeaking'),
        get_string('score', 'flwaispeaking'),
        get_string('submitted', 'flwaispeaking'),
        '',
    ];

    foreach ($submissions as $submission) {
        $coursecontext = context_course::instance($PAGE->course->id);
        $link = $submission->assessmentid && has_capability('local/flwaiassessment:view', $coursecontext)
            ? html_writer::link(new moodle_url('/local/flwaiassessment/view.php', ['id' => $submission->assessmentid]), get_string('viewairesult', 'flwaispeaking'))
            : get_string('notavailable', 'flwaispeaking');

        $table->data[] = [
            (int) $submission->attemptnumber,
            s($submission->submissiontype ?? 'transcript'),
            s($submission->status),
            s($submission->cefrlevel),
            format_float($submission->totalscore, 2),
            userdate($submission->timecreated),
            $link,
        ];
    }

    echo html_writer::table($table);
}

/**
 * Return browser recording script.
 *
 * @return string
 */
function flwaispeaking_recording_script(): string {
    $recordingready = json_encode(get_string('recordingready', 'flwaispeaking'));
    $recordingnotready = json_encode(get_string('recordingnotready', 'flwaispeaking'));

    return html_writer::script("
(function() {
    var recordButton = document.getElementById('flwaispeaking-record');
    var stopButton = document.getElementById('flwaispeaking-stop');
    var status = document.getElementById('flwaispeaking-recording-status');
    var audioData = document.getElementById('flwaispeaking-audiodata');
    var playback = document.getElementById('flwaispeaking-playback');
    if (!recordButton || !stopButton || !status || !audioData || !playback) {
        return;
    }

    var recorder = null;
    var chunks = [];

    recordButton.addEventListener('click', function() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            status.textContent = 'Audio recording is not available in this browser.';
            return;
        }

        navigator.mediaDevices.getUserMedia({audio: true}).then(function(stream) {
            chunks = [];
            audioData.value = '';
            recorder = new MediaRecorder(stream);
            recorder.addEventListener('dataavailable', function(event) {
                if (event.data && event.data.size > 0) {
                    chunks.push(event.data);
                }
            });
            recorder.addEventListener('stop', function() {
                var blob = new Blob(chunks, {type: recorder.mimeType || 'audio/webm'});
                stream.getTracks().forEach(function(track) {
                    track.stop();
                });
                playback.src = URL.createObjectURL(blob);
                playback.style.display = 'block';
                var reader = new FileReader();
                reader.onloadend = function() {
                    audioData.value = reader.result || '';
                    status.textContent = " . $recordingready . ";
                };
                reader.readAsDataURL(blob);
            });
            recorder.start();
            recordButton.disabled = true;
            stopButton.disabled = false;
            status.textContent = 'Recording...';
        }).catch(function(error) {
            status.textContent = error && error.message ? error.message : " . $recordingnotready . ";
        });
    });

    stopButton.addEventListener('click', function() {
        if (recorder && recorder.state !== 'inactive') {
            recorder.stop();
        }
        recordButton.disabled = false;
        stopButton.disabled = true;
    });
}());
");
}
