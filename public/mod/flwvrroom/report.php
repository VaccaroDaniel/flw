<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$attemptid = optional_param('attemptid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('flwvrroom', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$flwvrroom = $DB->get_record('flwvrroom', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/flwvrroom:viewreports', $context);

$PAGE->set_url('/mod/flwvrroom/report.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($flwvrroom->name) . ': ' . get_string('report', 'flwvrroom'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/mod/flwvrroom/styles.css');

/**
 * Split stored KP code text into displayable code values.
 *
 * @param string|null $value
 * @return array
 */
function flwvrroom_report_split_codes($value) {
    $parts = preg_split('/[\r\n,]+/', (string) $value);
    return array_values(array_filter(array_map('trim', $parts), static function($code) {
        return $code !== '';
    }));
}

/**
 * Format a short text value for a report detail block.
 *
 * @param string|null $value
 * @return string
 */
function flwvrroom_report_text($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return html_writer::span(get_string('none'), 'text-muted');
    }
    return nl2br(s($value));
}

/**
 * Format structured attempt JSON for teacher report details.
 *
 * @param string|null $value
 * @param string $type
 * @return string
 */
function flwvrroom_report_structured_json($value, $type) {
    $data = json_decode((string) $value, true);
    if (!is_array($data) || empty($data)) {
        return html_writer::span(get_string('none'), 'text-muted');
    }

    $items = [];
    foreach ($data as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ($type === 'hotspot') {
            $label = $item['title'] ?? $item['id'] ?? '';
            $items[] = trim($label . ' - ' . ($item['score'] ?? 0));
        } else if ($type === 'role') {
            $prompt = $item['prompt'] ?? '';
            $response = $item['learnerresponse'] ?? '';
            $items[] = trim($prompt . ' -> ' . $response);
        } else {
            $prompt = $item['prompt'] ?? '';
            $response = $item['recognizedresponse'] ?? '';
            $score = isset($item['normalizedscore']) && $item['normalizedscore'] !== null ?
                round((float) $item['normalizedscore'] * 100, 1) . '%' : '';
            $items[] = trim($prompt . ' -> ' . $response . ($score !== '' ? ' (' . $score . ')' : ''));
        }
    }

    return !empty($items) ? nl2br(s(implode("\n", $items))) : html_writer::span(get_string('none'), 'text-muted');
}

/**
 * Render a compact chronological attempt timeline from structured evidence JSON.
 *
 * @param stdClass $attempt
 * @return string
 */
function flwvrroom_report_attempt_timeline(stdClass $attempt) {
    $rows = [];
    $hotspots = json_decode((string)($attempt->hotspotsjson ?? ''), true);
    if (is_array($hotspots)) {
        foreach ($hotspots as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rows[] = get_string('hotspotevidence', 'flwvrroom') . ': ' .
                ($item['title'] ?? $item['id'] ?? '') . ' (' . ($item['score'] ?? 0) . ')';
        }
    }

    $speaking = json_decode((string)($attempt->speakingjson ?? ''), true);
    if (is_array($speaking)) {
        foreach ($speaking as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rows[] = get_string('speakingevidence', 'flwvrroom') . ': ' .
                ($item['recognizedresponse'] ?? '') .
                (isset($item['normalizedscore']) && $item['normalizedscore'] !== null ?
                    ' (' . round((float)$item['normalizedscore'] * 100, 1) . '%)' : '');
        }
    }

    $roleturns = json_decode((string)($attempt->roleturnsjson ?? ''), true);
    if (is_array($roleturns)) {
        foreach ($roleturns as $item) {
            if (!is_array($item)) {
                continue;
            }
            $rows[] = get_string('roleturnevidence', 'flwvrroom') . ': ' .
                ($item['prompt'] ?? '') . ' -> ' . ($item['learnerresponse'] ?? '');
        }
    }

    if (empty($rows)) {
        return html_writer::span(get_string('none'), 'text-muted');
    }

    return html_writer::alist(array_map('s', $rows), ['class' => 'flwvrroom-report-timeline']);
}

/**
 * Render the C-UP-KP evidence records linked to this VR attempt.
 *
 * @param stdClass $attempt
 * @return string
 */
function flwvrroom_report_evidence_debug(stdClass $attempt) {
    global $DB;

    $dbman = $DB->get_manager();
    $table = new xmldb_table('flwcupkp_evidence');
    if (!$dbman->table_exists($table)) {
        return html_writer::span(get_string('none'), 'text-muted');
    }

    $wantedfields = [
        'id',
        'evidencetype',
        'targettype',
        'targetid',
        'rawscore',
        'normalizedscore',
        'confidence',
        'evidencestrength',
        'sourceattempt',
        'sourceref',
        'timecreated',
    ];
    $fields = [];
    foreach ($wantedfields as $fieldname) {
        if ($dbman->field_exists($table, new xmldb_field($fieldname))) {
            $fields[] = $fieldname;
        }
    }
    if (!in_array('id', $fields, true) || !in_array('sourceref', $fields, true)) {
        return html_writer::span(get_string('none'), 'text-muted');
    }

    $sort = in_array('timecreated', $fields, true) ? 'timecreated ASC, id ASC' : 'id ASC';
    $records = $DB->get_records_sql(
        'SELECT ' . implode(', ', $fields) . '
           FROM {flwcupkp_evidence}
          WHERE sourceref = :sourceref
       ORDER BY ' . $sort,
        ['sourceref' => 'flwvrroom_attempt:' . (int)$attempt->id]
    );
    if (!$records) {
        return html_writer::span(get_string('none'), 'text-muted');
    }

    $debugtable = new html_table();
    $debugtable->attributes['class'] = 'generaltable flwvrroom-evidence-debug';
    $debugtable->head = [
        get_string('evidencetype', 'flwvrroom'),
        get_string('evidencetarget', 'flwvrroom'),
        get_string('normalizedscore', 'flwvrroom'),
        get_string('confidence', 'flwvrroom'),
        get_string('sourceattempt', 'flwvrroom'),
    ];

    foreach ($records as $record) {
        $target = trim((string)($record->targettype ?? '') . ':' . (string)($record->targetid ?? ''), ':');
        $score = isset($record->normalizedscore) ? format_float((float)$record->normalizedscore, 3) : '-';
        $confidence = isset($record->confidence) ? format_float((float)$record->confidence, 3) : '-';
        $debugtable->data[] = [
            s($record->evidencetype ?? '-'),
            s($target ?: '-'),
            s($score),
            s($confidence),
            s($record->sourceattempt ?? '-'),
        ];
    }

    return html_writer::table($debugtable);
}

/**
 * Render seconds as a compact duration string.
 *
 * @param int $seconds
 * @return string
 */
function flwvrroom_report_duration($seconds) {
    $seconds = max(0, (int) $seconds);
    $minutes = intdiv($seconds, 60);
    $remaining = $seconds % 60;
    return $minutes > 0 ? $minutes . 'm ' . $remaining . 's' : $remaining . 's';
}

/**
 * Format a Moodle timestamp, leaving unknown legacy values quiet.
 *
 * @param int $time
 * @return string
 */
function flwvrroom_report_time($time) {
    $time = (int) $time;
    if ($time <= 0) {
        return html_writer::span(get_string('none'), 'text-muted');
    }
    return userdate($time);
}

/**
 * Extract the role-play section from the saved transcript.
 *
 * @param string|null $value
 * @return string
 */
function flwvrroom_report_roleplay_dialogue($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $position = strpos($value, 'Role play (');
    if ($position === false) {
        return '';
    }

    return trim(substr($value, $position));
}

/**
 * Build a simple teacher-facing next practice note for one attempt.
 *
 * @param stdClass $attempt
 * @param int $passinggrade
 * @return string
 */
function flwvrroom_report_recommendation(stdClass $attempt, $passinggrade) {
    $codes = flwvrroom_report_split_codes($attempt->kpcodes ?? '');
    $completed = (string) ($attempt->completedobjects ?? '');
    $speakingtext = trim((string) ($attempt->speakingtext ?? ''));

    if ($speakingtext === '' && strpos($completed, 'rolecharacter') === false) {
        return get_string('recommendtryroleplay', 'flwvrroom');
    }

    if ((int) $attempt->score < (int) $passinggrade) {
        if (!empty($codes)) {
            return get_string('recommendrepeatkp', 'flwvrroom', implode(', ', $codes));
        }
        return get_string('recommendrepeatroom', 'flwvrroom');
    }

    return get_string('recommendnextscenario', 'flwvrroom');
}

$attempts = $DB->get_records_sql(
    "SELECT a.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
            u.middlename, u.alternatename, u.email, u.idnumber, u.picture, u.imagealt
       FROM {flwvrroom_attempts} a
       JOIN {user} u ON u.id = a.userid
      WHERE a.flwvrroomid = :flwvrroomid
   ORDER BY a.timecreated DESC, a.id DESC",
    ['flwvrroomid' => $flwvrroom->id]
);

$totalattempts = count($attempts);
$learnerids = [];
$scoresum = 0;
$passcount = 0;
$kpsummary = [];
$learners = [];

foreach ($attempts as $attempt) {
    $userid = (int) $attempt->userid;
    $learnerids[$userid] = true;
    $score = (int) $attempt->score;
    $scoresum += $score;
    $passed = $score >= (int) $flwvrroom->passinggrade;
    if ($passed) {
        $passcount++;
    }

    if (!isset($learners[$userid])) {
        $learners[$userid] = [
            'user' => $attempt,
            'attempts' => 0,
            'sum' => 0,
            'best' => 0,
            'passed' => 0,
            'lasttime' => 0,
            'kps' => [],
        ];
    }
    $learners[$userid]['attempts']++;
    $learners[$userid]['sum'] += $score;
    $learners[$userid]['best'] = max($learners[$userid]['best'], $score);
    $learners[$userid]['lasttime'] = max($learners[$userid]['lasttime'], (int) $attempt->timecreated);
    if ($passed) {
        $learners[$userid]['passed']++;
    }

    $codes = flwvrroom_report_split_codes($attempt->kpcodes ?? '');
    foreach ($codes as $code) {
        $learners[$userid]['kps'][$code] = true;
        if (!isset($kpsummary[$code])) {
            $kpsummary[$code] = [
                'attempts' => 0,
                'learners' => [],
                'sum' => 0,
                'passed' => 0,
                'lasttime' => 0,
            ];
        }
        $kpsummary[$code]['attempts']++;
        $kpsummary[$code]['learners'][$userid] = true;
        $kpsummary[$code]['sum'] += $score;
        $kpsummary[$code]['lasttime'] = max($kpsummary[$code]['lasttime'], (int) $attempt->timecreated);
        if ($passed) {
            $kpsummary[$code]['passed']++;
        }
    }
}

uasort($kpsummary, static function($a, $b) {
    return $b['attempts'] <=> $a['attempts'];
});
uasort($learners, static function($a, $b) {
    return $b['lasttime'] <=> $a['lasttime'];
});

$learnercount = count($learnerids);
$averagescore = $totalattempts ? round($scoresum / $totalattempts, 1) : 0;
$passrate = $totalattempts ? round(($passcount / $totalattempts) * 100, 1) : 0;

echo $OUTPUT->header();
echo html_writer::start_div('flwvrroom-report');
echo html_writer::div(
    html_writer::link(new moodle_url('/mod/flwvrroom/view.php', ['id' => $cm->id]), get_string('backtoroom', 'flwvrroom'), ['class' => 'btn btn-secondary']) .
    html_writer::tag('h2', format_string($flwvrroom->name) . ': ' . get_string('report', 'flwvrroom')),
    'flwvrroom-report-heading'
);

if ($totalattempts === 0) {
    echo $OUTPUT->notification(get_string('noreportattempts', 'flwvrroom'), 'info');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

if ($attemptid > 0) {
    $attempt = $attempts[$attemptid] ?? null;
    if (!$attempt) {
        echo $OUTPUT->notification(get_string('invalidattemptid', 'flwvrroom'), 'warning');
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        exit;
    }

    $passed = !empty($attempt->taskcomplete);
    echo html_writer::div(
        html_writer::link(new moodle_url('/mod/flwvrroom/report.php', ['id' => $cm->id]),
            get_string('backtoreport', 'flwvrroom'), ['class' => 'btn btn-secondary']),
        'flwvrroom-report-actions'
    );
    echo $OUTPUT->heading(fullname($attempt) . ' - ' . get_string('attemptdetail', 'flwvrroom'), 3);
    echo html_writer::start_div('flwvrroom-attempt-detail');
    echo html_writer::div(
        html_writer::span(get_string('scorelabel', 'flwvrroom')) .
        html_writer::tag('strong', (int)$attempt->score) .
        html_writer::span($passed ? get_string('passed', 'flwvrroom') : get_string('notpassed', 'flwvrroom')),
        'flwvrroom-report-card'
    );
    echo html_writer::div(
        html_writer::span(get_string('timelabel', 'flwvrroom')) .
        html_writer::tag('strong', flwvrroom_report_time($attempt->timecreated)) .
        html_writer::span(flwvrroom_report_duration($attempt->durationseconds)),
        'flwvrroom-report-card'
    );
    echo html_writer::end_div();

    echo $OUTPUT->heading(get_string('attempttimeline', 'flwvrroom'), 4);
    echo html_writer::div(flwvrroom_report_attempt_timeline($attempt), 'flwvrroom-report-text');
    echo $OUTPUT->heading(get_string('structuredevidence', 'flwvrroom'), 4);
    echo html_writer::div(
        html_writer::tag('em', get_string('hotspotevidence', 'flwvrroom')) . html_writer::empty_tag('br') .
        flwvrroom_report_structured_json($attempt->hotspotsjson ?? '', 'hotspot') . html_writer::empty_tag('br') .
        html_writer::tag('em', get_string('roleturnevidence', 'flwvrroom')) . html_writer::empty_tag('br') .
        flwvrroom_report_structured_json($attempt->roleturnsjson ?? '', 'role') . html_writer::empty_tag('br') .
        html_writer::tag('em', get_string('speakingevidence', 'flwvrroom')) . html_writer::empty_tag('br') .
        flwvrroom_report_structured_json($attempt->speakingjson ?? '', 'speaking'),
        'flwvrroom-report-text'
    );
    echo $OUTPUT->heading(get_string('transcript', 'flwvrroom'), 4);
    echo html_writer::div(flwvrroom_report_text($attempt->speakingtext ?? ''), 'flwvrroom-report-text');
    echo $OUTPUT->heading(get_string('aifeedback', 'flwvrroom'), 4);
    echo html_writer::div(flwvrroom_report_text($attempt->aifeedback ?? ''), 'flwvrroom-report-text');
    echo $OUTPUT->heading(get_string('evidencedebug', 'flwvrroom'), 4);
    echo html_writer::div(flwvrroom_report_evidence_debug($attempt), 'flwvrroom-report-text');
    echo $OUTPUT->heading(get_string('recommendedpractice', 'flwvrroom'), 4);
    echo html_writer::div(s(flwvrroom_report_recommendation($attempt, (int)$flwvrroom->passinggrade)),
        'flwvrroom-report-recommendation');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_div('flwvrroom-report-cards');
$cards = [
    get_string('reportattempts', 'flwvrroom') => $totalattempts,
    get_string('reportlearners', 'flwvrroom') => $learnercount,
    get_string('reportaveragescore', 'flwvrroom') => $averagescore,
    get_string('reportpassrate', 'flwvrroom') => $passrate . '%',
];
foreach ($cards as $label => $value) {
    echo html_writer::div(
        html_writer::span($label) . html_writer::tag('strong', s($value)),
        'flwvrroom-report-card'
    );
}
echo html_writer::end_div();

echo $OUTPUT->heading(get_string('kpreport', 'flwvrroom'), 3);
if (empty($kpsummary)) {
    echo $OUTPUT->notification(get_string('nokpreportdata', 'flwvrroom'), 'info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable flwvrroom-report-table';
    $table->head = [
        get_string('knowledgepoint', 'flwvrroom'),
        get_string('reportattempts', 'flwvrroom'),
        get_string('reportlearners', 'flwvrroom'),
        get_string('reportaveragescore', 'flwvrroom'),
        get_string('reportpassrate', 'flwvrroom'),
        get_string('lastattempt', 'flwvrroom'),
    ];
    foreach ($kpsummary as $code => $item) {
        $attemptcount = max(1, $item['attempts']);
        $table->data[] = [
            s($code),
            $item['attempts'],
            count($item['learners']),
            round($item['sum'] / $attemptcount, 1),
            round(($item['passed'] / $attemptcount) * 100, 1) . '%',
            flwvrroom_report_time($item['lasttime']),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->heading(get_string('learnerreport', 'flwvrroom'), 3);
$table = new html_table();
$table->attributes['class'] = 'generaltable flwvrroom-report-table';
$table->head = [
    get_string('fullname'),
    get_string('reportattempts', 'flwvrroom'),
    get_string('bestscore', 'flwvrroom'),
    get_string('reportaveragescore', 'flwvrroom'),
    get_string('reportpassedattempts', 'flwvrroom'),
    get_string('knowledgepoints', 'flwvrroom'),
    get_string('lastattempt', 'flwvrroom'),
];
foreach ($learners as $item) {
    $kps = array_keys($item['kps']);
    sort($kps);
    $table->data[] = [
        fullname($item['user']),
        $item['attempts'],
        $item['best'],
        round($item['sum'] / max(1, $item['attempts']), 1),
        $item['passed'],
        !empty($kps) ? s(implode(', ', $kps)) : html_writer::span(get_string('none'), 'text-muted'),
        flwvrroom_report_time($item['lasttime']),
    ];
}
echo html_writer::table($table);

echo $OUTPUT->heading(get_string('recentattempts', 'flwvrroom'), 3);
$table = new html_table();
$table->attributes['class'] = 'generaltable flwvrroom-report-table flwvrroom-attempt-table';
$table->head = [
    get_string('fullname'),
    get_string('scorelabel', 'flwvrroom'),
    get_string('statuslabel', 'flwvrroom'),
    get_string('knowledgepoints', 'flwvrroom'),
    get_string('duration', 'flwvrroom'),
    get_string('timelabel', 'flwvrroom'),
    get_string('speakingfeedback', 'flwvrroom'),
];

$shown = 0;
foreach ($attempts as $attempt) {
    if ($shown >= 50) {
        break;
    }
    $shown++;

    $passed = !empty($attempt->taskcomplete);
    $details = html_writer::tag('summary', get_string('viewdetails', 'flwvrroom'));
    $roleplaydialogue = flwvrroom_report_roleplay_dialogue($attempt->speakingtext ?? '');
    $details .= html_writer::tag('div',
        html_writer::div(html_writer::link(new moodle_url('/mod/flwvrroom/report.php',
            ['id' => $cm->id, 'attemptid' => $attempt->id]), get_string('fullattemptdetail', 'flwvrroom')),
            'flwvrroom-report-detail-link') .
        html_writer::tag('strong', get_string('transcript', 'flwvrroom')) .
        html_writer::div(flwvrroom_report_text($attempt->speakingtext ?? ''), 'flwvrroom-report-text') .
        ($roleplaydialogue !== '' ?
            html_writer::tag('strong', get_string('roleplaydialogue', 'flwvrroom')) .
            html_writer::div(flwvrroom_report_text($roleplaydialogue), 'flwvrroom-report-text') :
            '') .
        html_writer::tag('strong', get_string('aifeedback', 'flwvrroom')) .
        html_writer::div(flwvrroom_report_text($attempt->aifeedback ?? ''), 'flwvrroom-report-text') .
        html_writer::tag('strong', get_string('completedobjects', 'flwvrroom')) .
        html_writer::div(flwvrroom_report_text($attempt->completedobjects ?? ''), 'flwvrroom-report-text') .
        html_writer::tag('strong', get_string('attempttimeline', 'flwvrroom')) .
        html_writer::div(flwvrroom_report_attempt_timeline($attempt), 'flwvrroom-report-text') .
        html_writer::tag('strong', get_string('structuredevidence', 'flwvrroom')) .
        html_writer::div(
            html_writer::tag('em', get_string('hotspotevidence', 'flwvrroom')) . html_writer::empty_tag('br') .
            flwvrroom_report_structured_json($attempt->hotspotsjson ?? '', 'hotspot') . html_writer::empty_tag('br') .
            html_writer::tag('em', get_string('roleturnevidence', 'flwvrroom')) . html_writer::empty_tag('br') .
            flwvrroom_report_structured_json($attempt->roleturnsjson ?? '', 'role') . html_writer::empty_tag('br') .
            html_writer::tag('em', get_string('speakingevidence', 'flwvrroom')) . html_writer::empty_tag('br') .
            flwvrroom_report_structured_json($attempt->speakingjson ?? '', 'speaking'),
            'flwvrroom-report-text'
        ) .
        html_writer::tag('strong', get_string('evidencedebug', 'flwvrroom')) .
        html_writer::div(flwvrroom_report_evidence_debug($attempt), 'flwvrroom-report-text') .
        html_writer::tag('strong', get_string('recommendedpractice', 'flwvrroom')) .
        html_writer::div(s(flwvrroom_report_recommendation($attempt, (int) $flwvrroom->passinggrade)), 'flwvrroom-report-recommendation')
    );

    $table->data[] = [
        fullname($attempt),
        (int) $attempt->score,
        html_writer::span(
            $passed ? get_string('passed', 'flwvrroom') : get_string('notpassed', 'flwvrroom'),
            $passed ? 'badge badge-success' : 'badge badge-secondary'
        ),
        s(implode(', ', flwvrroom_report_split_codes($attempt->kpcodes ?? ''))),
        flwvrroom_report_duration($attempt->durationseconds),
        flwvrroom_report_time($attempt->timecreated),
        html_writer::tag('details', $details),
    ];
}
echo html_writer::table($table);

echo html_writer::end_div();
echo $OUTPUT->footer();
