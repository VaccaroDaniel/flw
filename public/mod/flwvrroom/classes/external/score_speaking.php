<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_flwvrroom\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/flwvrroom/lib.php');

/**
 * External service for sending recorded speech to the local FLW scoring server.
 */
class score_speaking extends \external_api {
    /**
     * Parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters() {
        return new \external_function_parameters([
            'cmid' => new \external_value(PARAM_INT, 'Course module id'),
            'audio' => new \external_value(PARAM_RAW, 'Base64 encoded audio payload'),
            'mimetype' => new \external_value(PARAM_TEXT, 'Browser audio MIME type', VALUE_DEFAULT, 'audio/webm'),
            'prompt' => new \external_value(PARAM_TEXT, 'Speaking prompt', VALUE_DEFAULT, ''),
            'targetanswer' => new \external_value(PARAM_TEXT, 'Target answer text', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Score a speaking sample using the configured local service.
     *
     * @param int $cmid
     * @param string $audio
     * @param string $mimetype
     * @param string $prompt
     * @param string $targetanswer
     * @return array
     */
    public static function execute($cmid, $audio, $mimetype = 'audio/webm', $prompt = '', $targetanswer = '') {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'audio' => $audio,
            'mimetype' => $mimetype,
            'prompt' => $prompt,
            'targetanswer' => $targetanswer,
        ]);

        $cm = get_coursemodule_from_id('flwvrroom', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $flwvrroom = $DB->get_record('flwvrroom', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/flwvrroom:submit', $context);

        $baseurl = trim((string) ($flwvrroom->speakingscoringurl ?? ''));
        if ($baseurl === '') {
            $baseurl = 'http://127.0.0.1:8000';
        }
        $baseurl = rtrim($baseurl, '/');

        $audio = preg_replace('/^data:[^;]+;base64,/', '', $params['audio']);
        $bytes = base64_decode($audio, true);
        if ($bytes === false || $bytes === '') {
            throw new \moodle_exception('invalidrecording', 'flwvrroom');
        }

        $extension = self::extension_from_mimetype($params['mimetype']);
        make_temp_directory('mod_flwvrroom');
        $tmpfile = $CFG->tempdir . '/mod_flwvrroom/speaking_' . $USER->id . '_' . time() . '_' . random_string(8) . $extension;
        file_put_contents($tmpfile, $bytes);

        try {
            $transcription = self::post_audio($baseurl . '/transcribe/audio', $tmpfile, $params['mimetype']);
            if (!empty($transcription['_flwvrroom_nospeech'])) {
                return self::no_speech_response();
            }

            $transcript = trim((string) ($transcription['transcript'] ?? $transcription['text'] ?? ''));
            if ($transcript === '') {
                return self::no_speech_response();
            }

            $estimate = self::post_json($baseurl . '/estimate/speaking', [
                'userid' => (int) $USER->id,
                'courseid' => (int) $course->id,
                'cmid' => (int) $cm->id,
                'submissionid' => 0,
                'prompt' => trim($params['prompt'] . "\nTarget answer: " . $params['targetanswer']),
                'transcript' => $transcript,
            ]);
        } finally {
            if (file_exists($tmpfile)) {
                @unlink($tmpfile);
            }
        }

        $feedbackparts = [];
        if (!empty($estimate['cefr_level'])) {
            $feedbackparts[] = 'CEFR: ' . $estimate['cefr_level'];
        }
        if (isset($estimate['total_score'])) {
            $feedbackparts[] = 'Score: ' . $estimate['total_score'];
        }
        if (!empty($estimate['teacher_note'])) {
            $feedbackparts[] = $estimate['teacher_note'];
        }
        if (!empty($estimate['weak_kps']) && is_array($estimate['weak_kps'])) {
            $feedbackparts[] = 'Weak KP: ' . implode(', ', $estimate['weak_kps']);
        }

        return [
            'status' => true,
            'transcript' => $transcript,
            'feedback' => implode(' / ', $feedbackparts),
            'cefrlevel' => (string) ($estimate['cefr_level'] ?? ''),
            'totalscore' => (float) ($estimate['total_score'] ?? 0),
            'rawjson' => json_encode($estimate),
        ];
    }

    /**
     * Return structure.
     *
     * @return \external_single_structure
     */
    public static function execute_returns() {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_BOOL, 'Score status'),
            'transcript' => new \external_value(PARAM_TEXT, 'Transcript'),
            'feedback' => new \external_value(PARAM_TEXT, 'Feedback summary'),
            'cefrlevel' => new \external_value(PARAM_TEXT, 'Estimated CEFR level'),
            'totalscore' => new \external_value(PARAM_FLOAT, 'Speaking score'),
            'rawjson' => new \external_value(PARAM_RAW, 'Raw JSON response'),
        ]);
    }

    /**
     * Post an audio file to the local transcription endpoint.
     *
     * @param string $url
     * @param string $path
     * @param string $mimetype
     * @return array
     */
    private static function post_audio($url, $path, $mimetype) {
        $curlfile = new \CURLFile($path, self::clean_mimetype($mimetype), basename($path));
        return self::curl_json($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['file' => $curlfile],
        ]);
    }

    /**
     * Post JSON to the local scoring endpoint.
     *
     * @param string $url
     * @param array $payload
     * @return array
     */
    private static function post_json($url, array $payload) {
        return self::curl_json($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
    }

    /**
     * Execute a cURL request and decode JSON.
     *
     * @param string $url
     * @param array $options
     * @return array
     */
    private static function curl_json($url, array $options) {
        $ch = curl_init($url);
        curl_setopt_array($ch, $options + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 180,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpcode >= 400) {
            $message = self::service_error_message($response, $error, $httpcode);
            if (self::is_no_speech_message($message)) {
                return ['_flwvrroom_nospeech' => true];
            }
            throw new \moodle_exception('scoringservicefailed', 'flwvrroom', '', $message);
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \moodle_exception('scoringservicefailed', 'flwvrroom', '', 'Invalid JSON response');
        }

        return $data;
    }

    /**
     * Response used when the learner recording does not contain usable speech.
     *
     * @return array
     */
    private static function no_speech_response() {
        return [
            'status' => false,
            'transcript' => '',
            'feedback' => get_string('nospeechdetected', 'flwvrroom'),
            'cefrlevel' => '',
            'totalscore' => 0,
            'rawjson' => '',
        ];
    }

    /**
     * Extract a readable service error from plain text or JSON.
     *
     * @param string|false $response
     * @param string $error
     * @param int $httpcode
     * @return string
     */
    private static function service_error_message($response, $error, $httpcode) {
        if ($response !== false && $response !== '') {
            $data = json_decode($response, true);
            if (is_array($data) && isset($data['detail'])) {
                return is_scalar($data['detail']) ? (string) $data['detail'] : json_encode($data['detail']);
            }
            return 'HTTP ' . $httpcode . ': ' . $response;
        }

        return $error ?: ('HTTP ' . $httpcode);
    }

    /**
     * Detect expected no-speech responses from local transcription services.
     *
     * @param string $message
     * @return bool
     */
    private static function is_no_speech_message($message) {
        return preg_match('/no\s+speech|speech\s+was\s+not\s+detected|empty\s+speech|silent\s+audio/i', $message) === 1;
    }

    /**
     * Choose a service-friendly extension from a MediaRecorder MIME type.
     *
     * @param string $mimetype
     * @return string
     */
    private static function extension_from_mimetype($mimetype) {
        if (stripos($mimetype, 'ogg') !== false) {
            return '.ogg';
        }
        if (stripos($mimetype, 'wav') !== false) {
            return '.wav';
        }
        if (stripos($mimetype, 'mp4') !== false || stripos($mimetype, 'm4a') !== false) {
            return '.m4a';
        }
        return '.webm';
    }

    /**
     * Strip browser codec hints before handing the file to cURL.
     *
     * @param string $mimetype
     * @return string
     */
    private static function clean_mimetype($mimetype) {
        $parts = explode(';', (string) $mimetype);
        $clean = trim($parts[0] ?? '');
        return $clean !== '' ? $clean : 'audio/webm';
    }
}
