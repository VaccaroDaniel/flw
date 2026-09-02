<?php
// Role-based home page for local_flwcupkp.

require_once(__DIR__ . '/../../config.php');

require_login();

$systemcontext = context_system::instance();
$PAGE->set_url(new moodle_url('/local/flwcupkp/index.php'));
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('cupkphome', 'local_flwcupkp'));
$PAGE->set_heading(get_string('cupkphome', 'local_flwcupkp'));
$PAGE->requires->css('/local/flwcupkp/styles.css');

$units = local_flwcupkp_home_units();
$adminaccess = is_siteadmin() || has_capability('local/flwcupkp:import', $systemcontext) ||
    has_capability('local/flwcupkp:viewreports', $systemcontext);
$teacherunits = array_values(array_filter($units, static function(array $unit): bool {
    return !empty($unit['canreport']);
}));
$studentunits = array_values(array_filter($units, static function(array $unit): bool {
    return !empty($unit['canview']);
}));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('cupkphome', 'local_flwcupkp'));

if (!$adminaccess && !$teacherunits && !$studentunits) {
    echo $OUTPUT->notification(get_string('cupkpnoaccess', 'local_flwcupkp'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_tag('div', ['class' => 'local-flwcupkp-home']);
echo html_writer::tag('p', get_string('cupkphomeintro', 'local_flwcupkp'), [
    'class' => 'local-flwcupkp-muted local-flwcupkp-home-intro',
]);

if ($adminaccess) {
    echo local_flwcupkp_home_section(get_string('adminworkspace', 'local_flwcupkp'),
        local_flwcupkp_home_admin_cards());
}

if ($teacherunits) {
    echo local_flwcupkp_home_section(get_string('teacherworkspace', 'local_flwcupkp'),
        local_flwcupkp_home_teacher_cards($teacherunits));
}

if ($studentunits) {
    echo local_flwcupkp_home_section(get_string('studentworkspace', 'local_flwcupkp'),
        local_flwcupkp_home_student_cards($studentunits));
}

if ($adminaccess || $teacherunits) {
    echo local_flwcupkp_home_section(get_string('operationreports', 'local_flwcupkp'),
        local_flwcupkp_home_report_cards($units, $adminaccess));
}

echo html_writer::end_tag('div');
echo $OUTPUT->footer();

/**
 * Mapped units available to the current user.
 *
 * @return array
 */
function local_flwcupkp_home_units(): array {
    global $DB;

    $recordset = $DB->get_recordset_sql(
        "SELECT DISTINCT o.courseid, o.unitcode, c.fullname, c.shortname
           FROM {flwcupkp_object} o
           JOIN {course} c ON c.id = o.courseid
          WHERE o.courseid IS NOT NULL
            AND o.courseid > 0
            AND o.unitcode IS NOT NULL
            AND o.unitcode <> ''
       ORDER BY c.fullname ASC, o.unitcode ASC"
    );

    $units = [];
    foreach ($recordset as $record) {
        $courseid = (int)$record->courseid;
        $context = context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            continue;
        }
        $canview = has_capability('local/flwcupkp:viewlearnerpath', $context);
        $canreport = has_capability('local/flwcupkp:viewreports', $context);
        $canoverride = has_capability('local/flwcupkp:override', $context);
        if (!$canview && !$canreport) {
            continue;
        }
        $units[] = [
            'courseid' => $courseid,
            'unitcode' => (string)$record->unitcode,
            'coursefullname' => format_string($record->fullname),
            'courseshortname' => format_string($record->shortname),
            'canview' => $canview,
            'canreport' => $canreport,
            'canoverride' => $canoverride,
        ];
    }
    $recordset->close();

    return $units;
}

/**
 * Render a card section.
 *
 * @param string $heading
 * @param array $cards
 * @return string
 */
function local_flwcupkp_home_section(string $heading, array $cards): string {
    if (!$cards) {
        return '';
    }

    $html = html_writer::start_tag('section', ['class' => 'local-flwcupkp-home-section']);
    $html .= html_writer::tag('h3', s($heading));
    $html .= html_writer::start_tag('div', ['class' => 'local-flwcupkp-home-grid']);
    foreach ($cards as $card) {
        $html .= local_flwcupkp_home_card($card);
    }
    $html .= html_writer::end_tag('div');
    $html .= html_writer::end_tag('section');

    return $html;
}

/**
 * Admin cards.
 *
 * @return array
 */
function local_flwcupkp_home_admin_cards(): array {
    $cards = [
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('unitsetupwizard', 'local_flwcupkp'),
            'detail' => get_string('unitsetupwizardhome', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/setup.php'),
            'button' => get_string('openunitsetup', 'local_flwcupkp'),
            'primary' => true,
        ],
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('curriculummanager', 'local_flwcupkp'),
            'detail' => get_string('curriculumhome', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/curriculum.php'),
            'button' => get_string('opencurriculum', 'local_flwcupkp'),
        ],
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('cm3governance', 'local_flwcupkp'),
            'detail' => get_string('cm3home', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/governance.php'),
            'button' => get_string('opencm3governance', 'local_flwcupkp'),
        ],
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('cm4management', 'local_flwcupkp'),
            'detail' => get_string('cm4home', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/management.php'),
            'button' => get_string('opencm4management', 'local_flwcupkp'),
        ],
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('historyevidenceadapter', 'local_flwcupkp'),
            'detail' => get_string('historyevidencehome', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/history_evidence.php'),
            'button' => get_string('openhistoryevidence', 'local_flwcupkp'),
        ],
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('masterystatee2', 'local_flwcupkp'),
            'detail' => get_string('masterystatehome', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/mastery_state.php'),
            'button' => get_string('openmasterystate', 'local_flwcupkp'),
        ],
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('retentionreviewe3', 'local_flwcupkp'),
            'detail' => get_string('retentionreviewhome', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/retention_review.php'),
            'button' => get_string('openretentionreview', 'local_flwcupkp'),
        ],
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('learninggoala1', 'local_flwcupkp'),
            'detail' => get_string('learninggoalhome', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/learning_goal.php'),
            'button' => get_string('openlearninggoal', 'local_flwcupkp'),
        ],
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('placementdiagnostica2', 'local_flwcupkp'),
            'detail' => get_string('placementdiagnostichome', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/placement_diagnostic.php'),
            'button' => get_string('openplacementdiagnostic', 'local_flwcupkp'),
        ],
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('adaptivedecisiona3', 'local_flwcupkp'),
            'detail' => get_string('adaptivedecisionhome', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/adaptive_decision.php'),
            'button' => get_string('openadaptivedecision', 'local_flwcupkp'),
        ],
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('initialpatha4', 'local_flwcupkp'),
            'detail' => get_string('initialpathhome', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/initial_path.php'),
            'button' => get_string('openinitialpath', 'local_flwcupkp'),
        ],
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('activityresolutiona4b', 'local_flwcupkp'),
            'detail' => get_string('activityresolutionhome', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/activity_resolution.php'),
            'button' => get_string('openactivityresolution', 'local_flwcupkp'),
        ],
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('adaptivepatha5', 'local_flwcupkp'),
            'detail' => get_string('adaptivepathhome', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/adaptive_path.php'),
            'button' => get_string('openadaptivepath', 'local_flwcupkp'),
        ],
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('trajectorysimulationa5b', 'local_flwcupkp'),
            'detail' => get_string('trajectorysimulationhome', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/trajectory_simulation.php'),
            'button' => get_string('opentrajectorysimulation', 'local_flwcupkp'),
        ],
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('progressreadinessa5c', 'local_flwcupkp'),
            'detail' => get_string('progressreadinesshome', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/progress_readiness.php'),
            'button' => get_string('openprogressreadiness', 'local_flwcupkp'),
        ],
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('foundationinspector', 'local_flwcupkp'),
            'detail' => get_string('foundationhome', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/foundation.php'),
            'button' => get_string('openfoundation', 'local_flwcupkp'),
        ],
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('calibrationreport', 'local_flwcupkp'),
            'detail' => get_string('calibrationhome', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/calibration.php'),
            'button' => get_string('opencalibration', 'local_flwcupkp'),
        ],
        [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('healthsync', 'local_flwcupkp'),
            'detail' => get_string('healthsynchome', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/sync.php'),
            'button' => get_string('openhealthsync', 'local_flwcupkp'),
        ],
    ];

    if (has_capability('local/flwcupkp:synccompetencies', context_system::instance())) {
        $cards[] = [
            'label' => get_string('adminworkspace', 'local_flwcupkp'),
            'title' => get_string('evidencesynchealth', 'local_flwcupkp'),
            'detail' => get_string('evidencesynchealthhome', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/evidence_sync.php'),
            'button' => get_string('openevidencesynchealth', 'local_flwcupkp'),
        ];
    }

    return $cards;
}

/**
 * Teacher cards for mapped units.
 *
 * @param array $units
 * @return array
 */
function local_flwcupkp_home_teacher_cards(array $units): array {
    $cards = [];
    foreach ($units as $unit) {
        $summary = local_flwcupkp_home_teacher_summary($unit);
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('teacherunitcard', 'local_flwcupkp', $unit['unitcode']),
            'detail' => $summary['detail'],
            'metric' => get_string('teacherunitmetric', 'local_flwcupkp', (object)[
                'review' => $summary['review'],
                'parent' => $summary['parent'],
            ]),
            'url' => local_flwcupkp_home_teacher_url($unit),
            'button' => get_string('openteacherreview', 'local_flwcupkp'),
            'secondaryurl' => local_flwcupkp_home_performance_url($unit),
            'secondarybutton' => get_string('openspeakingwriting', 'local_flwcupkp'),
            'primary' => $summary['review'] > 0 || $summary['parent'] > 0,
        ];
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('teacherlearninggoalcard', 'local_flwcupkp', $unit['unitcode']),
            'detail' => get_string('teacherlearninggoaldetail', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/learning_goal.php', [
                'courseid' => $unit['courseid'],
                'unitcode' => $unit['unitcode'],
            ]),
            'button' => get_string('openlearninggoal', 'local_flwcupkp'),
        ];
        $placement = \local_flwcupkp\local\placement_diagnostic_service::class_summary(
            (int)$unit['courseid'],
            (string)$unit['unitcode'],
            0,
            100
        );
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('teacherplacementdiagnosticcard', 'local_flwcupkp',
                (int)($placement['summary']['records'] ?? 0)),
            'detail' => get_string('teacherplacementdiagnosticdetail', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/placement_diagnostic.php', [
                'courseid' => $unit['courseid'],
                'unitcode' => $unit['unitcode'],
            ]),
            'button' => get_string('openplacementdiagnostic', 'local_flwcupkp'),
            'primary' => (int)($placement['summary']['not_taken_or_unknown'] ?? 0) > 0 ||
                (int)(($placement['summary']['states']['STALE'] ?? 0) +
                    ($placement['summary']['states']['LOW_CONFIDENCE'] ?? 0) +
                    ($placement['summary']['states']['INCOMPLETE'] ?? 0)) > 0,
        ];
        $adaptive = local_flwcupkp_home_adaptive_summary($unit);
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('teacheradaptivedecisioncard', 'local_flwcupkp', $adaptive['attention']),
            'detail' => get_string('teacheradaptivedecisiondetail', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/adaptive_decision.php', [
                'courseid' => $unit['courseid'],
                'unitcode' => $unit['unitcode'],
            ]),
            'button' => get_string('openadaptivedecision', 'local_flwcupkp'),
            'primary' => $adaptive['attention'] > 0,
        ];
        $initial = local_flwcupkp_home_initial_path_summary($unit);
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('teacherinitialpathcard', 'local_flwcupkp', $initial['items']),
            'detail' => get_string('teacherinitialpathdetail', 'local_flwcupkp'),
            'url' => local_flwcupkp_home_initial_path_url($unit),
            'button' => get_string('openinitialpath', 'local_flwcupkp'),
            'primary' => $initial['items'] > 0,
        ];
        $activityresolution = local_flwcupkp_home_activity_resolution_summary($unit);
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('teacheractivityresolutioncard', 'local_flwcupkp',
                $activityresolution['next']),
            'detail' => get_string('teacheractivityresolutiondetail', 'local_flwcupkp', (object)[
                'diagnostic' => $activityresolution['diagnostic'],
                'fallback' => $activityresolution['fallback'],
            ]),
            'url' => local_flwcupkp_home_activity_resolution_url($unit),
            'button' => get_string('openactivityresolution', 'local_flwcupkp'),
            'primary' => $activityresolution['diagnostic'] > 0 || $activityresolution['fallback'] > 0,
        ];
        $adaptivepath = local_flwcupkp_home_adaptive_path_summary($unit);
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('teacheradaptivepathcard', 'local_flwcupkp',
                $adaptivepath['ready'] + $adaptivepath['refresh']),
            'detail' => get_string('teacheradaptivepathdetail', 'local_flwcupkp', (object)$adaptivepath),
            'url' => local_flwcupkp_home_adaptive_path_url($unit),
            'button' => get_string('openadaptivepath', 'local_flwcupkp'),
            'primary' => $adaptivepath['ready'] > 0 || $adaptivepath['refresh'] > 0 ||
                $adaptivepath['diagnostic'] > 0,
        ];
        $trajectory = local_flwcupkp_home_trajectory_summary($unit);
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('teachertrajectorycard', 'local_flwcupkp', $trajectory['passed']),
            'detail' => get_string('teachertrajectorydetail', 'local_flwcupkp', (object)$trajectory),
            'url' => local_flwcupkp_home_trajectory_url($unit),
            'button' => get_string('opentrajectorysimulation', 'local_flwcupkp'),
            'primary' => $trajectory['status'] !== 'ready' || !$trajectory['deterministicpass'],
        ];
        $readiness = local_flwcupkp_home_progress_readiness_summary($unit);
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('teacherprogressreadinesscard', 'local_flwcupkp', $readiness['achieved']),
            'detail' => get_string('teacherprogressreadinessdetail', 'local_flwcupkp', (object)$readiness),
            'url' => local_flwcupkp_home_progress_readiness_url($unit),
            'button' => get_string('openprogressreadiness', 'local_flwcupkp'),
            'primary' => $readiness['qualitative'] > 0 || $readiness['failed'] > 0,
        ];
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('teacherlearningtimelinecard', 'local_flwcupkp'),
            'detail' => get_string('teacherlearningtimelinedetail', 'local_flwcupkp'),
            'url' => local_flwcupkp_home_learning_timeline_url($unit),
            'button' => get_string('openlearningtimeline', 'local_flwcupkp'),
        ];
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('staffintelligencecard', 'local_flwcupkp'),
            'detail' => get_string('staffintelligencecarddetail', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/staff_intelligence.php', [
                'courseid' => $unit['courseid'],
                'unitcode' => $unit['unitcode'],
            ]),
            'button' => get_string('openstaffintelligence', 'local_flwcupkp'),
        ];
    }
    return $cards;
}

/**
 * Student cards for mapped units.
 *
 * @param array $units
 * @return array
 */
function local_flwcupkp_home_student_cards(array $units): array {
    global $USER;

    $cards = [];
    foreach ($units as $unit) {
        $progress = local_flwcupkp_home_student_progress($unit, (int)$USER->id);
        $next = $progress['next'] !== '' ? $progress['next'] : get_string('courseallmasteredunit', 'local_flwcupkp',
            $unit['unitcode']);
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('studentunitcard', 'local_flwcupkp', $unit['unitcode']),
            'detail' => $next,
            'metric' => get_string('studentunitmetric', 'local_flwcupkp', (object)[
                'percent' => $progress['percent'],
                'gaps' => $progress['gaps'],
            ]),
            'url' => local_flwcupkp_home_student_url($unit),
            'button' => get_string('openmyprogress', 'local_flwcupkp'),
            'secondaryurl' => local_flwcupkp_home_evaluation_url($unit),
            'secondarybutton' => get_string('openmylearningpath', 'local_flwcupkp'),
            'primary' => $progress['gaps'] > 0,
        ];
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('studentlearninggoalcard', 'local_flwcupkp', $unit['unitcode']),
            'detail' => get_string('studentlearninggoaldetail', 'local_flwcupkp'),
            'url' => new moodle_url('/local/flwcupkp/learning_goal.php', [
                'courseid' => $unit['courseid'],
                'unitcode' => $unit['unitcode'],
                'userid' => (int)$USER->id,
            ]),
            'button' => get_string('openlearninggoal', 'local_flwcupkp'),
        ];
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('studentinitialpathcard', 'local_flwcupkp', $unit['unitcode']),
            'detail' => get_string('studentinitialpathdetail', 'local_flwcupkp'),
            'url' => local_flwcupkp_home_initial_path_url($unit, (int)$USER->id),
            'button' => get_string('openinitialpath', 'local_flwcupkp'),
            'primary' => $progress['gaps'] > 0,
        ];
        $activityresolution = local_flwcupkp_home_student_activity_resolution($unit, (int)$USER->id);
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('studentactivityresolutioncard', 'local_flwcupkp', $unit['unitcode']),
            'detail' => $activityresolution['detail'],
            'url' => local_flwcupkp_home_activity_resolution_url($unit, (int)$USER->id),
            'button' => get_string('openactivityresolution', 'local_flwcupkp'),
            'primary' => $activityresolution['ready'],
        ];
        $adaptivepath = local_flwcupkp_home_student_adaptive_path($unit, (int)$USER->id);
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('studentadaptivepathcard', 'local_flwcupkp', $unit['unitcode']),
            'detail' => $adaptivepath['detail'],
            'url' => local_flwcupkp_home_adaptive_path_url($unit, (int)$USER->id),
            'button' => get_string('openadaptivepath', 'local_flwcupkp'),
            'primary' => $adaptivepath['ready'],
        ];
        $readiness = local_flwcupkp_home_student_progress_readiness($unit, (int)$USER->id);
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('studentprogressreadinesscard', 'local_flwcupkp'),
            'detail' => $readiness['detail'],
            'url' => local_flwcupkp_home_progress_readiness_url($unit, (int)$USER->id),
            'button' => get_string('openprogressreadiness', 'local_flwcupkp'),
            'primary' => !$readiness['achieved'],
        ];
        $cards[] = [
            'label' => $unit['coursefullname'],
            'title' => get_string('studentlearningtimelinecard', 'local_flwcupkp'),
            'detail' => get_string('studentlearningtimelinedetail', 'local_flwcupkp'),
            'url' => local_flwcupkp_home_learning_timeline_url($unit, (int)$USER->id),
            'button' => get_string('openlearningtimeline', 'local_flwcupkp'),
            'primary' => true,
        ];
    }
    return $cards;
}

/**
 * Operational report cards.
 *
 * @param array $units
 * @param bool $adminaccess
 * @return array
 */
function local_flwcupkp_home_report_cards(array $units, bool $adminaccess): array {
    global $DB;

    $activeunits = 0;
    $unitcodes = [];
    $objects = 0;
    $linkedobjects = 0;
    $evidence = 0;
    $sync = [
        'readyforwrites' => false,
        'linkedframeworks' => 0,
        'frameworks' => 0,
        'linkedcompetencies' => 0,
        'competencies' => 0,
    ];
    if ($adminaccess) {
        $unitcodes = \local_flwcupkp\local\curriculum_manager::unit_options();
        foreach (array_keys($unitcodes) as $unitcode) {
            try {
                $status = \local_flwcupkp\local\unit_setup_service::status($unitcode);
                if (!empty($status['activation']['ready'])) {
                    $activeunits++;
                }
            } catch (Throwable $e) {
                // Keep the home page available even if one imported unit is incomplete.
            }
        }
        $objects = (int)$DB->count_records_select('flwcupkp_object',
            "unitcode IS NOT NULL AND unitcode <> ''");
        $linkedobjects = (int)$DB->count_records_select('flwcupkp_object',
            "unitcode IS NOT NULL AND unitcode <> '' AND cmid IS NOT NULL AND cmid > 0");
        $evidence = (int)$DB->count_records('flwcupkp_evidence');
        $sync = \local_flwcupkp\local\curriculum_manager::sync_readiness();
    }

    $review = 0;
    $parent = 0;
    foreach ($units as $unit) {
        if (empty($unit['canreport'])) {
            continue;
        }
        $summary = local_flwcupkp_home_teacher_summary($unit);
        $review += $summary['review'];
        $parent += $summary['parent'];
    }

    $studentgaps = local_flwcupkp_home_gap_count($units);

    $cards = [];
    if ($adminaccess) {
        $cards[] = [
            'label' => get_string('operationreports', 'local_flwcupkp'),
            'title' => get_string('unitreadinessreport', 'local_flwcupkp'),
            'detail' => get_string('unitreadinessdetail', 'local_flwcupkp', (object)[
                'active' => $activeunits,
                'total' => count($unitcodes),
            ]),
            'url' => new moodle_url('/local/flwcupkp/setup.php'),
            'button' => get_string('openunitsetup', 'local_flwcupkp'),
        ];
    }

    if ($adminaccess || $review > 0 || $parent > 0) {
        $cards[] = [
            'label' => get_string('operationreports', 'local_flwcupkp'),
            'title' => get_string('reviewworkloadreport', 'local_flwcupkp'),
            'detail' => get_string('reviewworkloaddetail', 'local_flwcupkp', (object)[
                'review' => $review,
                'parent' => $parent,
            ]),
            'url' => local_flwcupkp_home_first_teacher_review_url($units),
            'button' => get_string('openreviewqueue', 'local_flwcupkp'),
            'primary' => $review > 0 || $parent > 0,
        ];
    }

    if ($adminaccess) {
        $cards[] = [
            'label' => get_string('operationreports', 'local_flwcupkp'),
            'title' => get_string('studentgapreport', 'local_flwcupkp'),
            'detail' => get_string('studentgapdetail', 'local_flwcupkp', $studentgaps),
            'url' => local_flwcupkp_home_first_teacher_review_url($units),
            'button' => get_string('openreviewqueue', 'local_flwcupkp'),
            'primary' => $studentgaps > 0,
        ];
        $cards[] = [
            'label' => get_string('operationreports', 'local_flwcupkp'),
            'title' => get_string('evidencecoveragereport', 'local_flwcupkp'),
            'detail' => get_string('evidencecoveragedetail', 'local_flwcupkp', (object)[
                'linked' => $linkedobjects,
                'objects' => $objects,
                'evidence' => $evidence,
            ]),
            'url' => new moodle_url('/local/flwcupkp/trace.php'),
            'button' => get_string('opentraceability', 'local_flwcupkp'),
        ];
        $cards[] = [
            'label' => get_string('operationreports', 'local_flwcupkp'),
            'title' => get_string('syncstatusreport', 'local_flwcupkp'),
            'detail' => get_string($sync['readyforwrites'] ? 'syncstatusreadydetail' : 'syncstatusblockeddetail',
                'local_flwcupkp', (object)[
                    'frameworks' => (int)$sync['linkedframeworks'] . '/' . (int)$sync['frameworks'],
                    'competencies' => (int)$sync['linkedcompetencies'] . '/' . (int)$sync['competencies'],
                ]),
            'url' => new moodle_url('/local/flwcupkp/sync.php'),
            'button' => get_string('opensyncstatus', 'local_flwcupkp'),
            'primary' => !$sync['readyforwrites'] && $adminaccess,
        ];
    }

    return $cards;
}

/**
 * Render one home card.
 *
 * @param array $card
 * @return string
 */
function local_flwcupkp_home_card(array $card): string {
    $classes = 'local-flwcupkp-home-card';
    if (!empty($card['primary'])) {
        $classes .= ' local-flwcupkp-home-card-primary';
    }

    $html = html_writer::start_tag('article', ['class' => $classes]);
    $html .= html_writer::tag('div', s((string)($card['label'] ?? '')), [
        'class' => 'local-flwcupkp-course-next-label',
    ]);
    $html .= html_writer::tag('h4', s((string)($card['title'] ?? '')));
    if (!empty($card['metric'])) {
        $html .= html_writer::tag('strong', s((string)$card['metric']), ['class' => 'local-flwcupkp-home-metric']);
    }
    if (!empty($card['detail'])) {
        $html .= html_writer::tag('p', s((string)$card['detail']));
    }
    $actions = '';
    if (!empty($card['url']) && $card['url'] instanceof moodle_url) {
        $actions .= html_writer::link($card['url'], s((string)($card['button'] ?? get_string('open'))), [
            'class' => 'btn ' . (!empty($card['primary']) ? 'btn-primary' : 'btn-secondary') . ' btn-sm',
        ]);
    }
    if (!empty($card['secondaryurl']) && $card['secondaryurl'] instanceof moodle_url) {
        $actions .= html_writer::link($card['secondaryurl'], s((string)($card['secondarybutton'] ?? get_string('open'))), [
            'class' => 'btn btn-secondary btn-sm',
        ]);
    }
    if ($actions !== '') {
        $html .= html_writer::tag('div', $actions, ['class' => 'local-flwcupkp-formactions']);
    }
    $html .= html_writer::end_tag('article');

    return $html;
}

/**
 * Teacher summary counts for one unit.
 *
 * @param array $unit
 * @return array
 */
function local_flwcupkp_home_teacher_summary(array $unit): array {
    try {
        if ($unit['unitcode'] === 'U038') {
            $report = \local_flwcupkp\local\teacher_report::u038_report((int)$unit['courseid'], ['evidence' => 'review']);
            $competency = \local_flwcupkp\local\teacher_report::u038_mastery_overview((int)$unit['courseid'], [
                'targettype' => 'competency',
                'stategroup' => 'notachieved',
                'parentreview' => 'review',
            ]);
            $up = \local_flwcupkp\local\teacher_report::u038_mastery_overview((int)$unit['courseid'], [
                'targettype' => 'up',
                'stategroup' => 'notdemonstrated',
                'parentreview' => 'review',
            ]);
        } else {
            $report = \local_flwcupkp\local\unit_report::kp_report((int)$unit['courseid'], $unit['unitcode'],
                ['evidence' => 'review']);
            $competency = \local_flwcupkp\local\unit_report::mastery_overview((int)$unit['courseid'], $unit['unitcode'], [
                'targettype' => 'competency',
                'stategroup' => 'notachieved',
                'parentreview' => 'review',
            ]);
            $up = \local_flwcupkp\local\unit_report::mastery_overview((int)$unit['courseid'], $unit['unitcode'], [
                'targettype' => 'up',
                'stategroup' => 'notdemonstrated',
                'parentreview' => 'review',
            ]);
        }
        $review = count($report['rows']);
        $parent = count($competency['rows']) + count($up['rows']);
        $detail = $review > 0 || $parent > 0 ?
            get_string('teacherunitdetailreview', 'local_flwcupkp') :
            get_string('teacherunitdetailclear', 'local_flwcupkp');
        return ['review' => $review, 'parent' => $parent, 'detail' => $detail];
    } catch (Throwable $e) {
        return [
            'review' => 0,
            'parent' => 0,
            'detail' => get_string('teacherunitdetailunavailable', 'local_flwcupkp'),
        ];
    }
}

/**
 * Adaptive decision attention count for one unit.
 *
 * @param array $unit
 * @return array
 */
function local_flwcupkp_home_adaptive_summary(array $unit): array {
    try {
        $summary = \local_flwcupkp\local\adaptive_decision_policy_service::class_summary(
            (int)$unit['courseid'],
            (string)$unit['unitcode'],
            0,
            40
        );
        $urgency = $summary['summary']['urgency'] ?? [];
        $attention = (int)($urgency['urgent'] ?? 0) + (int)($urgency['attention'] ?? 0);
        return [
            'attention' => $attention,
            'urgent' => (int)($urgency['urgent'] ?? 0),
        ];
    } catch (Throwable $e) {
        return [
            'attention' => 0,
            'urgent' => 0,
        ];
    }
}

/**
 * Initial path workload count for one unit.
 *
 * @param array $unit
 * @return array
 */
function local_flwcupkp_home_initial_path_summary(array $unit): array {
    try {
        $summary = \local_flwcupkp\local\goal_gap_path_service::class_summary(
            (int)$unit['courseid'],
            (string)$unit['unitcode'],
            0,
            40
        );
        $data = $summary['summary'] ?? [];
        $items = (int)($data['ready_to_work'] ?? 0) + (int)($data['blocked_by_prerequisite'] ?? 0) +
            (int)($data['needs_goal'] ?? 0) + (int)($data['needs_setup'] ?? 0);
        return ['items' => $items];
    } catch (Throwable $e) {
        return ['items' => 0];
    }
}

/**
 * Activity resolution workload count for one unit.
 *
 * @param array $unit
 * @return array
 */
function local_flwcupkp_home_activity_resolution_summary(array $unit): array {
    try {
        $summary = \local_flwcupkp\local\candidate_activity_resolution_service::class_summary(
            (int)$unit['courseid'],
            (string)$unit['unitcode'],
            0,
            40
        );
        $data = $summary['summary'] ?? [];
        return [
            'next' => (int)($data['next_activity_ready'] ?? 0),
            'diagnostic' => (int)($data['diagnostic_required'] ?? 0),
            'fallback' => (int)($data['fallback_used'] ?? 0),
        ];
    } catch (Throwable $e) {
        return [
            'next' => 0,
            'diagnostic' => 0,
            'fallback' => 0,
        ];
    }
}

/**
 * Student activity-resolution summary for one unit.
 *
 * @param array $unit
 * @param int $userid
 * @return array
 */
function local_flwcupkp_home_student_activity_resolution(array $unit, int $userid): array {
    try {
        $resolution = \local_flwcupkp\local\candidate_activity_resolution_service::learner_resolution(
            $userid,
            (int)$unit['courseid'],
            (string)$unit['unitcode'],
            0,
            40
        );
        $next = $resolution['next_activity'] ?? null;
        if ($next) {
            return [
                'ready' => true,
                'detail' => get_string('studentactivityresolutiondetailready', 'local_flwcupkp',
                    (string)($next['title'] ?? '')),
            ];
        }
        return [
            'ready' => false,
            'detail' => get_string('studentactivityresolutiondetailnotready', 'local_flwcupkp',
                (string)($resolution['diagnostic']['code'] ?? '')),
        ];
    } catch (Throwable $e) {
        return [
            'ready' => false,
            'detail' => get_string('studentunitdetailunavailable', 'local_flwcupkp'),
        ];
    }
}

/**
 * Teacher A5 recommendation refresh metrics for one unit.
 *
 * @param array $unit
 * @return array
 */
function local_flwcupkp_home_adaptive_path_summary(array $unit): array {
    try {
        $summary = \local_flwcupkp\local\adaptive_path_engine_service::class_summary(
            (int)$unit['courseid'],
            (string)$unit['unitcode'],
            0,
            40
        );
        $data = $summary['summary'] ?? [];
        return [
            'current' => (int)($data['current'] ?? 0),
            'ready' => (int)($data['ready_to_apply'] ?? 0),
            'refresh' => (int)($data['refresh_required'] ?? 0),
            'diagnostic' => (int)($data['diagnostic_required'] ?? 0),
        ];
    } catch (Throwable $e) {
        return ['current' => 0, 'ready' => 0, 'refresh' => 0, 'diagnostic' => 0];
    }
}

/**
 * Student A5 current next step for one unit.
 *
 * @param array $unit
 * @param int $userid
 * @return array
 */
function local_flwcupkp_home_student_adaptive_path(array $unit, int $userid): array {
    try {
        $path = \local_flwcupkp\local\adaptive_path_engine_service::learner_path(
            $userid,
            (int)$unit['courseid'],
            (string)$unit['unitcode'],
            0,
            40
        );
        $recommendation = $path['recommendation'] ?? [];
        $activity = $recommendation['selected_activity'] ?? null;
        if ($activity) {
            return [
                'ready' => true,
                'detail' => get_string('studentadaptivepathdetailready', 'local_flwcupkp', (object)[
                    'action' => (string)($recommendation['action'] ?? ''),
                    'activity' => (string)($activity['title'] ?? ''),
                ]),
            ];
        }
        return [
            'ready' => false,
            'detail' => get_string('studentadaptivepathdetaildiagnostic', 'local_flwcupkp',
                (string)($recommendation['action'] ?? 'REPRIORITIZE')),
        ];
    } catch (Throwable $e) {
        return [
            'ready' => false,
            'detail' => get_string('studentunitdetailunavailable', 'local_flwcupkp'),
        ];
    }
}

/**
 * Teacher A5B invariant health for one unit.
 *
 * @param array $unit
 * @return array
 */
function local_flwcupkp_home_trajectory_summary(array $unit): array {
    try {
        $status = \local_flwcupkp\local\trajectory_invariant_service::status(
            (int)$unit['courseid'],
            (string)$unit['unitcode'],
            0
        );
        return [
            'status' => (string)($status['status'] ?? 'blocked'),
            'passed' => (int)($status['detector_self_test']['passed'] ?? 0),
            'deterministic' => !empty($status['determinism_smoke']['pass']) ? get_string('yes') : get_string('no'),
            'deterministicpass' => !empty($status['determinism_smoke']['pass']),
            'next' => (string)($status['next_allowed_gate'] ?? 'A5C'),
        ];
    } catch (Throwable $e) {
        return [
            'status' => 'blocked',
            'passed' => 0,
            'deterministic' => get_string('no'),
            'deterministicpass' => false,
            'next' => 'A5C',
        ];
    }
}

/**
 * Teacher A5C class metric summary for one unit.
 *
 * @param array $unit
 * @return array
 */
function local_flwcupkp_home_progress_readiness_summary(array $unit): array {
    try {
        $result = \local_flwcupkp\local\progress_goal_readiness_service::class_summary(
            (int)$unit['courseid'],
            (string)$unit['unitcode'],
            0,
            40
        );
        $summary = $result['summary'] ?? [];
        return [
            'available' => (int)($summary['goal_readiness_percentage_available'] ?? 0),
            'qualitative' => (int)($summary['qualitative_only'] ?? 0),
            'achieved' => (int)($summary['goal_achieved'] ?? 0),
            'failed' => (int)($summary['failed'] ?? 0),
        ];
    } catch (Throwable $e) {
        return ['available' => 0, 'qualitative' => 0, 'achieved' => 0, 'failed' => 1];
    }
}

/**
 * Student A5C preferred metric for one unit.
 *
 * @param array $unit
 * @param int $userid
 * @return array
 */
function local_flwcupkp_home_student_progress_readiness(array $unit, int $userid): array {
    try {
        $result = \local_flwcupkp\local\progress_goal_readiness_service::learner_progress(
            $userid,
            (int)$unit['courseid'],
            (string)$unit['unitcode'],
            0,
            100
        );
        $preferred = $result['progress']['preferred_learner_metric'];
        $achievement = $result['progress']['goal_achievement'];
        if ($preferred['percentage'] !== null) {
            $detail = get_string('studentprogressreadinessdetailpercentage', 'local_flwcupkp', (object)[
                'percentage' => format_float((float)$preferred['percentage'], 1),
                'milestone' => (string)$preferred['milestone'],
            ]);
        } else {
            $detail = get_string('studentprogressreadinessdetailqualitative', 'local_flwcupkp',
                (string)$preferred['milestone']);
        }
        return ['detail' => $detail, 'achieved' => !empty($achievement['achieved'])];
    } catch (Throwable $e) {
        return ['detail' => get_string('studentunitdetailunavailable', 'local_flwcupkp'), 'achieved' => false];
    }
}

/**
 * Student progress summary for one unit.
 *
 * @param array $unit
 * @param int $userid
 * @return array
 */
function local_flwcupkp_home_student_progress(array $unit, int $userid): array {
    try {
        $progress = $unit['unitcode'] === 'U038' ?
            \local_flwcupkp\local\student_report::u038_progress((int)$unit['courseid'], $userid) :
            \local_flwcupkp\local\unit_report::student_progress((int)$unit['courseid'], $unit['unitcode'], $userid);
        $summary = $progress['summary'];
        $next = $progress['next_recommendation'] ?? null;
        $nexttext = '';
        if ($next) {
            $externalid = (string)($next['kp_externalid'] ?? $next['externalid'] ?? '');
            $title = (string)($next['kp_title'] ?? $next['title'] ?? '');
            $nexttext = trim($externalid . ' - ' . $title, ' -');
        }
        return [
            'percent' => (int)($summary['percent'] ?? 0),
            'gaps' => (int)($summary['gaps'] ?? 0),
            'next' => $nexttext,
        ];
    } catch (Throwable $e) {
        return ['percent' => 0, 'gaps' => 0, 'next' => get_string('studentunitdetailunavailable', 'local_flwcupkp')];
    }
}

/**
 * Count non-mastered learner KP rows across visible operational units.
 *
 * @param array $units
 * @return int
 */
function local_flwcupkp_home_gap_count(array $units): int {
    $count = 0;
    foreach ($units as $unit) {
        if (empty($unit['canreport'])) {
            continue;
        }
        try {
            $report = $unit['unitcode'] === 'U038' ?
                \local_flwcupkp\local\teacher_report::u038_report((int)$unit['courseid']) :
                \local_flwcupkp\local\unit_report::kp_report((int)$unit['courseid'], $unit['unitcode']);
            foreach ($report['rows'] as $row) {
                if ((string)($row['state'] ?? '') !== 'mastered') {
                    $count++;
                }
            }
        } catch (Throwable $e) {
            continue;
        }
    }
    return $count;
}

/**
 * URL helpers.
 *
 * @param array $unit
 * @return moodle_url
 */
function local_flwcupkp_home_student_url(array $unit): moodle_url {
    return $unit['unitcode'] === 'U038' ?
        new moodle_url('/local/flwcupkp/student_u038.php', ['courseid' => $unit['courseid']]) :
        new moodle_url('/local/flwcupkp/student.php', [
            'courseid' => $unit['courseid'],
            'unitcode' => $unit['unitcode'],
        ]);
}

/**
 * @param array $unit
 * @return moodle_url
 */
function local_flwcupkp_home_teacher_url(array $unit): moodle_url {
    return $unit['unitcode'] === 'U038' ?
        new moodle_url('/local/flwcupkp/teacher_u038.php', ['courseid' => $unit['courseid'], 'evidence' => 'review']) :
        new moodle_url('/local/flwcupkp/teacher.php', [
            'courseid' => $unit['courseid'],
            'unitcode' => $unit['unitcode'],
            'evidence' => 'review',
        ]);
}

/**
 * @param array $unit
 * @return moodle_url
 */
function local_flwcupkp_home_evaluation_url(array $unit): moodle_url {
    return new moodle_url('/local/flwcupkp/evaluation.php', [
        'courseid' => $unit['courseid'],
        'unitcode' => $unit['unitcode'],
    ]);
}

/**
 * @param array $unit
 * @return moodle_url
 */
function local_flwcupkp_home_performance_url(array $unit): moodle_url {
    return $unit['unitcode'] === 'U038' ?
        new moodle_url('/local/flwcupkp/performance_u038.php', ['courseid' => $unit['courseid']]) :
        new moodle_url('/local/flwcupkp/performance.php', [
            'courseid' => $unit['courseid'],
            'unitcode' => $unit['unitcode'],
        ]);
}

/**
 * @param array $unit
 * @param int $userid
 * @return moodle_url
 */
function local_flwcupkp_home_initial_path_url(array $unit, int $userid = 0): moodle_url {
    $params = [
        'courseid' => $unit['courseid'],
        'unitcode' => $unit['unitcode'],
    ];
    if ($userid > 0) {
        $params['userid'] = $userid;
    }
    return new moodle_url('/local/flwcupkp/initial_path.php', $params);
}

/**
 * @param array $unit
 * @param int $userid
 * @return moodle_url
 */
function local_flwcupkp_home_activity_resolution_url(array $unit, int $userid = 0): moodle_url {
    $params = [
        'courseid' => $unit['courseid'],
        'unitcode' => $unit['unitcode'],
    ];
    if ($userid > 0) {
        $params['userid'] = $userid;
    }
    return new moodle_url('/local/flwcupkp/activity_resolution.php', $params);
}

/**
 * @param array $unit
 * @param int $userid
 * @return moodle_url
 */
function local_flwcupkp_home_adaptive_path_url(array $unit, int $userid = 0): moodle_url {
    $params = [
        'courseid' => $unit['courseid'],
        'unitcode' => $unit['unitcode'],
    ];
    if ($userid > 0) {
        $params['userid'] = $userid;
    }
    return new moodle_url('/local/flwcupkp/adaptive_path.php', $params);
}

/**
 * @param array $unit
 * @return moodle_url
 */
function local_flwcupkp_home_trajectory_url(array $unit): moodle_url {
    return new moodle_url('/local/flwcupkp/trajectory_simulation.php', [
        'courseid' => $unit['courseid'],
        'unitcode' => $unit['unitcode'],
    ]);
}

/**
 * @param array $unit
 * @param int $userid
 * @return moodle_url
 */
function local_flwcupkp_home_progress_readiness_url(array $unit, int $userid = 0): moodle_url {
    $params = [
        'courseid' => $unit['courseid'],
        'unitcode' => $unit['unitcode'],
    ];
    if ($userid > 0) {
        $params['userid'] = $userid;
    }
    return new moodle_url('/local/flwcupkp/progress_readiness.php', $params);
}

/**
 * @param array $unit
 * @param int $userid
 * @return moodle_url
 */
function local_flwcupkp_home_learning_timeline_url(array $unit, int $userid = 0): moodle_url {
    $params = [
        'courseid' => $unit['courseid'],
        'unitcode' => $unit['unitcode'],
    ];
    if ($userid > 0) {
        $params['userid'] = $userid;
    }
    return new moodle_url('/local/flwcupkp/learning_timeline.php', $params);
}

/**
 * @param array $units
 * @return moodle_url|null
 */
function local_flwcupkp_home_first_teacher_review_url(array $units): ?moodle_url {
    foreach ($units as $unit) {
        if (!empty($unit['canreport'])) {
            return local_flwcupkp_home_teacher_url($unit);
        }
    }
    return null;
}

/**
 * @param array $units
 * @return moodle_url|null
 */
function local_flwcupkp_home_first_student_url(array $units): ?moodle_url {
    foreach ($units as $unit) {
        if (!empty($unit['canview'])) {
            return local_flwcupkp_home_student_url($unit);
        }
    }
    return null;
}
