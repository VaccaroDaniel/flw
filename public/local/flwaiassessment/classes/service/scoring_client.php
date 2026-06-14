<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwaiassessment\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Client for the offline FLW scoring API.
 */
class scoring_client {
    /**
     * Estimate a writing submission.
     *
     * @param \stdClass $record Result record.
     * @return array
     */
    public function estimate_writing(\stdClass $record): array {
        return $this->post('/estimate/writing', [
            'userid' => (int) $record->userid,
            'courseid' => (int) $record->courseid,
            'cmid' => (int) $record->cmid,
            'submissionid' => (int) $record->submissionid,
            'model' => get_config('local_flwaiassessment', 'modelname'),
            'prompt' => $record->prompttext,
            'text' => $record->rawtext,
        ]);
    }

    /**
     * Estimate a speaking submission from a transcript.
     *
     * @param \stdClass $record Result record.
     * @return array
     */
    public function estimate_speaking(\stdClass $record): array {
        return $this->post('/estimate/speaking', [
            'userid' => (int) $record->userid,
            'courseid' => (int) $record->courseid,
            'cmid' => (int) $record->cmid,
            'submissionid' => (int) $record->submissionid,
            'model' => get_config('local_flwaiassessment', 'modelname'),
            'prompt' => $record->prompttext,
            'transcript' => $record->transcript,
            'audio_path' => $record->audiopath,
        ]);
    }

    /**
     * Send JSON to the local scoring API.
     *
     * @param string $path API path.
     * @param array $payload Request body.
     * @return array Decoded response.
     */
    private function post(string $path, array $payload): array {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        $baseurl = rtrim((string) get_config('local_flwaiassessment', 'apiurl'), '/');
        $timeout = (int) get_config('local_flwaiassessment', 'requesttimeout');
        $timeout = $timeout > 0 ? $timeout : 60;

        $curl = new \curl();
        $curl->setHeader([
            'Content-Type: application/json',
            'Accept: application/json',
            'Expect:',
        ]);

        $response = $curl->post(
            $baseurl . $path,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            [
                'CURLOPT_TIMEOUT' => $timeout,
                'CURLOPT_CONNECTTIMEOUT' => 10,
            ]
        );

        if ($curl->get_errno()) {
            throw new \moodle_exception('curlerror', 'error', '', null, $curl->error);
        }

        $httpcode = (int) ($curl->info['http_code'] ?? 0);
        if ($httpcode >= 400) {
            throw new \moodle_exception('curlerror', 'error', '', null, 'The local scoring API returned HTTP ' . $httpcode . ': ' . $response);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \moodle_exception('invalidresponse', 'error', '', null, 'The local scoring API did not return valid JSON.');
        }

        return $decoded;
    }
}
