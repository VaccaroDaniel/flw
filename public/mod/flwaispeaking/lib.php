<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Supported feature list.
 *
 * @param string $feature Feature constant.
 * @return mixed
 */
function flwaispeaking_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return false;
        default:
            return null;
    }
}

/**
 * Add a new FLW AI Speaking activity.
 *
 * @param stdClass $data Form data.
 * @param mod_flwaispeaking_mod_form|null $mform Form.
 * @return int
 */
function flwaispeaking_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('flwaispeaking', $data);

    flwaispeaking_grade_item_update($data);

    return $data->id;
}

/**
 * Update an FLW AI Speaking activity.
 *
 * @param stdClass $data Form data.
 * @param mod_flwaispeaking_mod_form|null $mform Form.
 * @return bool
 */
function flwaispeaking_update_instance($data, $mform = null) {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();

    $DB->update_record('flwaispeaking', $data);
    flwaispeaking_grade_item_update($data);

    return true;
}

/**
 * Delete an FLW AI Speaking activity.
 *
 * @param int $id Instance id.
 * @return bool
 */
function flwaispeaking_delete_instance($id) {
    global $DB;

    if (!$flwaispeaking = $DB->get_record('flwaispeaking', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('flwaispeaking_submissions', ['flwaispeakingid' => $flwaispeaking->id]);
    $DB->delete_records('flwaispeaking', ['id' => $flwaispeaking->id]);
    flwaispeaking_grade_item_delete($flwaispeaking);

    return true;
}

/**
 * Return a user's best grade.
 *
 * @param stdClass $flwaispeaking Activity instance.
 * @param int $userid User id.
 * @return stdClass|null
 */
function flwaispeaking_get_user_grade($flwaispeaking, $userid) {
    global $DB;

    $record = $DB->get_record_sql(
        'SELECT MAX(totalscore) AS score
           FROM {flwaispeaking_submissions}
          WHERE flwaispeakingid = :flwaispeakingid
            AND userid = :userid
            AND status = :status',
        [
            'flwaispeakingid' => $flwaispeaking->id,
            'userid' => $userid,
            'status' => 'complete',
        ]
    );

    if (!$record || $record->score === null) {
        return null;
    }

    return (object) [
        'userid' => $userid,
        'rawgrade' => (float) $record->score,
    ];
}

/**
 * Update gradebook item or grades.
 *
 * @param stdClass $flwaispeaking Activity instance.
 * @param stdClass|array|null $grades Grade data.
 * @return int
 */
function flwaispeaking_grade_item_update($flwaispeaking, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $gradeitem = [
        'itemname' => $flwaispeaking->name,
        'gradetype' => GRADE_TYPE_VALUE,
        'grademax' => empty($flwaispeaking->grade) ? 100 : $flwaispeaking->grade,
        'grademin' => 0,
    ];

    return grade_update('mod/flwaispeaking', $flwaispeaking->course, 'mod', 'flwaispeaking', $flwaispeaking->id, 0, $grades, $gradeitem);
}

/**
 * Delete gradebook item.
 *
 * @param stdClass $flwaispeaking Activity instance.
 * @return int
 */
function flwaispeaking_grade_item_delete($flwaispeaking) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/flwaispeaking', $flwaispeaking->course, 'mod', 'flwaispeaking', $flwaispeaking->id, 0, null, ['deleted' => 1]);
}

/**
 * Count attempts for a user.
 *
 * @param int $flwaispeakingid Activity id.
 * @param int $userid User id.
 * @return int
 */
function flwaispeaking_count_user_attempts($flwaispeakingid, $userid): int {
    global $DB;

    return (int) $DB->count_records('flwaispeaking_submissions', [
        'flwaispeakingid' => $flwaispeakingid,
        'userid' => $userid,
    ]);
}

/**
 * Return the next attempt number for a user without reusing deleted attempts.
 *
 * @param int $flwaispeakingid Activity id.
 * @param int $userid User id.
 * @return int
 */
function flwaispeaking_get_next_attempt_number(int $flwaispeakingid, int $userid): int {
    global $DB;

    $record = $DB->get_record_sql(
        'SELECT MAX(attemptnumber) AS maxattempt
           FROM {flwaispeaking_submissions}
          WHERE flwaispeakingid = :flwaispeakingid
            AND userid = :userid',
        [
            'flwaispeakingid' => $flwaispeakingid,
            'userid' => $userid,
        ]
    );

    return ((int) ($record->maxattempt ?? 0)) + 1;
}

/**
 * Build the scoring prompt sent to the local AI assessment server.
 *
 * @param stdClass $flwaispeaking Activity instance.
 * @return string
 */
function flwaispeaking_build_ai_prompt($flwaispeaking): string {
    $tasktype = $flwaispeaking->tasktype ?? 'topic';
    $lines = [
        'FLW speaking task type: ' . ($tasktype === 'readaloud' ? 'read-aloud' : 'open-topic'),
        'Target CEFR level: ' . ($flwaispeaking->cefrlevel ?? ''),
        '',
        'Teacher prompt:',
        trim((string) ($flwaispeaking->prompttext ?? '')),
    ];

    if ($tasktype === 'readaloud') {
        $lines[] = '';
        $lines[] = 'Read-aloud target text:';
        $lines[] = trim((string) ($flwaispeaking->targettext ?? ''));
        $lines[] = '';
        $lines[] = 'Scoring focus: compare the learner transcript with the target text; score pronunciation from omissions, substitutions, unclear words, and rhythm/word-boundary evidence; also score grammar only where the learner output changes the target or adds language.';
    } else {
        $lines[] = '';
        $lines[] = 'Scoring focus: score semantic relevance to the topic, grammar accuracy, vocabulary, fluency, and pronunciation evidence from the transcript.';
    }

    if (!empty($flwaispeaking->kpcodes)) {
        $lines[] = '';
        $lines[] = 'FLW knowledge point codes:';
        $lines[] = trim((string) $flwaispeaking->kpcodes);
    }

    return trim(implode("\n", $lines));
}

/**
 * Delete one submission and its linked unconfirmed AI result.
 *
 * @param stdClass $flwaispeaking Activity instance.
 * @param int $submissionid Submission id.
 * @param int $userid User id.
 */
function flwaispeaking_delete_submission($flwaispeaking, int $submissionid, int $userid): void {
    global $DB;

    $submission = $DB->get_record('flwaispeaking_submissions', [
        'id' => $submissionid,
        'flwaispeakingid' => $flwaispeaking->id,
        'userid' => $userid,
    ], '*', MUST_EXIST);

    if (!empty($submission->assessmentid)) {
        $assessment = $DB->get_record('local_flwai_results', ['id' => $submission->assessmentid]);
        if ($assessment && !empty($assessment->teacherconfirmed)) {
            throw new moodle_exception('cannotdeleteconfirmed', 'flwaispeaking');
        }
        $DB->delete_records('local_flwai_results', ['id' => $submission->assessmentid]);
    }

    $DB->delete_records('flwaispeaking_submissions', ['id' => $submission->id]);
    flwaispeaking_update_user_grade($flwaispeaking, $userid);
}

/**
 * Create an AI assessment and local submission record.
 *
 * @param stdClass $flwaispeaking Activity instance.
 * @param cm_info|stdClass $cm Course module.
 * @param int $userid User id.
 * @param string $transcript Speaking transcript.
 * @param string $submissiontype Submission type.
 * @param array $audioinfo Optional audio metadata.
 * @return int Submission id.
 */
function flwaispeaking_submit_transcript($flwaispeaking, $cm, int $userid, string $transcript, string $submissiontype = 'transcript', array $audioinfo = []): int {
    global $DB;

    $attemptnumber = flwaispeaking_get_next_attempt_number((int) $flwaispeaking->id, $userid);
    $now = time();

    $assessmentid = \local_flwaiassessment\service\result_repository::create_pending([
        'userid' => $userid,
        'courseid' => (int) $flwaispeaking->course,
        'cmid' => (int) $cm->id,
        'activitytype' => 'flwaispeaking',
        'sourcecomponent' => 'mod_flwaispeaking',
        'submissionid' => 0,
        'skilltype' => 'speaking',
        'transcript' => $transcript,
        'prompttext' => flwaispeaking_build_ai_prompt($flwaispeaking),
    ]);

    $submissionid = $DB->insert_record('flwaispeaking_submissions', (object) [
        'flwaispeakingid' => $flwaispeaking->id,
        'userid' => $userid,
        'attemptnumber' => $attemptnumber,
        'submissiontype' => $submissiontype,
        'transcript' => $transcript,
        'audiofilename' => $audioinfo['filename'] ?? null,
        'audiomimetype' => $audioinfo['mimetype'] ?? null,
        'assessmentid' => $assessmentid,
        'status' => 'pending',
        'cefrlevel' => '',
        'totalscore' => 0,
        'rubricjson' => null,
        'weakkpjson' => null,
        'recommendjson' => null,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);

    $DB->set_field('local_flwai_results', 'submissionid', $submissionid, ['id' => $assessmentid]);

    if (!empty($flwaispeaking->instantprocess) && get_config('local_flwaiassessment', 'enableprocessing')) {
        $task = new \local_flwaiassessment\task\process_pending();
        $task->execute();
        flwaispeaking_sync_submission($submissionid);
        flwaispeaking_update_user_grade($flwaispeaking, $userid);
    }

    return $submissionid;
}

/**
 * Transcribe a browser-recorded audio data URL using the local FLW AI server.
 *
 * @param string $dataurl Audio data URL.
 * @return array Transcript and metadata.
 */
function flwaispeaking_transcribe_audio_dataurl(string $dataurl): array {
    global $CFG;

    require_once($CFG->libdir . '/filelib.php');

    if (!preg_match('/^data:(audio\/[a-z0-9.+-]+)(?:;[^,]+)*;base64,(.+)$/i', $dataurl, $matches)) {
        throw new moodle_exception('recordingnotready', 'flwaispeaking');
    }

    $mimetype = strtolower($matches[1]);
    $binary = base64_decode($matches[2], true);
    if ($binary === false || $binary === '') {
        throw new moodle_exception('recordingnotready', 'flwaispeaking');
    }

    $extension = 'webm';
    if (str_contains($mimetype, 'mpeg')) {
        $extension = 'mp3';
    } else if (str_contains($mimetype, 'wav')) {
        $extension = 'wav';
    } else if (str_contains($mimetype, 'ogg')) {
        $extension = 'ogg';
    }

    $tempdir = make_temp_directory('mod_flwaispeaking');
    $filename = 'speaking-' . time() . '-' . random_string(8) . '.' . $extension;
    $filepath = $tempdir . DIRECTORY_SEPARATOR . $filename;
    file_put_contents($filepath, $binary);

    $baseurl = rtrim((string) get_config('local_flwaiassessment', 'apiurl'), '/');
    $timeout = (int) get_config('local_flwaiassessment', 'requesttimeout');
    $timeout = $timeout > 0 ? max($timeout, 120) : 120;

    $curl = new \curl();
    $curl->setHeader(['Accept: application/json', 'Expect:']);
    $response = $curl->post($baseurl . '/transcribe/audio', [
        'file' => new \CURLFile($filepath, $mimetype, $filename),
    ], [
        'CURLOPT_TIMEOUT' => $timeout,
        'CURLOPT_CONNECTTIMEOUT' => 10,
    ]);

    @unlink($filepath);

    if ($curl->get_errno()) {
        throw new moodle_exception('transcriptionfailed', 'flwaispeaking', '', $curl->error);
    }

    $httpcode = (int) ($curl->info['http_code'] ?? 0);
    if ($httpcode >= 400) {
        throw new moodle_exception('transcriptionfailed', 'flwaispeaking', '', $response);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded) || trim((string) ($decoded['transcript'] ?? '')) === '') {
        throw new moodle_exception('transcriptionfailed', 'flwaispeaking', '', 'The local FLW AI server did not return a transcript.');
    }

    return [
        'transcript' => trim((string) $decoded['transcript']),
        'filename' => $filename,
        'mimetype' => $mimetype,
        'response' => $decoded,
    ];
}

/**
 * Sync one local submission from its linked AI assessment.
 *
 * @param int $submissionid Submission id.
 * @return stdClass
 */
function flwaispeaking_sync_submission(int $submissionid): stdClass {
    global $DB;

    $submission = $DB->get_record('flwaispeaking_submissions', ['id' => $submissionid], '*', MUST_EXIST);
    if (empty($submission->assessmentid)) {
        return $submission;
    }

    $assessment = $DB->get_record('local_flwai_results', ['id' => $submission->assessmentid]);
    if (!$assessment) {
        return $submission;
    }

    $submission->status = $assessment->status;
    $submission->cefrlevel = $assessment->cefrlevel;
    $submission->totalscore = $assessment->totalscore;
    $submission->rubricjson = $assessment->rubricjson;
    $submission->weakkpjson = $assessment->weakkpjson;
    $submission->recommendjson = $assessment->recommendjson;
    $submission->timemodified = time();
    $DB->update_record('flwaispeaking_submissions', $submission);

    return $submission;
}

/**
 * Sync all submissions for an activity.
 *
 * @param stdClass $flwaispeaking Activity instance.
 */
function flwaispeaking_sync_activity_submissions($flwaispeaking): void {
    global $DB;

    $submissions = $DB->get_records('flwaispeaking_submissions', ['flwaispeakingid' => $flwaispeaking->id]);
    foreach ($submissions as $submission) {
        $updated = flwaispeaking_sync_submission((int) $submission->id);
        if ($updated->status === 'complete') {
            flwaispeaking_update_user_grade($flwaispeaking, (int) $updated->userid);
        }
    }
}

/**
 * Update a user's grade from best completed submission.
 *
 * @param stdClass $flwaispeaking Activity instance.
 * @param int $userid User id.
 */
function flwaispeaking_update_user_grade($flwaispeaking, int $userid): void {
    $grade = flwaispeaking_get_user_grade($flwaispeaking, $userid);
    if (!$grade) {
        $grade = (object) [
            'userid' => $userid,
            'rawgrade' => null,
        ];
    }
    flwaispeaking_grade_item_update($flwaispeaking, $grade);
}
