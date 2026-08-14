<?php
// Course navigation callbacks for local_flwcupkp.

defined('MOODLE_INTERNAL') || die();

/**
 * Add U038 C-UP-KP links to the mapped course navigation.
 *
 * @param navigation_node $navigation Course navigation node.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 */
function local_flwcupkp_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context): void {
    global $DB;

    $units = $DB->get_records_sql(
        "SELECT DISTINCT unitcode
           FROM {flwcupkp_object}
          WHERE courseid = :courseid
            AND unitcode IS NOT NULL
            AND unitcode <> ''
       ORDER BY unitcode ASC",
        ['courseid' => $course->id]
    );
    if (!$units) {
        return;
    }

    foreach ($units as $unit) {
        $unitcode = (string)$unit->unitcode;
        if (has_capability('local/flwcupkp:viewlearnerpath', $context)) {
            $studenturl = $unitcode === 'U038' ?
                new moodle_url('/local/flwcupkp/student_u038.php', ['courseid' => $course->id]) :
                new moodle_url('/local/flwcupkp/student.php', ['courseid' => $course->id, 'unitcode' => $unitcode]);
            $studentnode = $navigation->add(
                get_string('unitprogressnav', 'local_flwcupkp', $unitcode),
                $studenturl,
                navigation_node::TYPE_SETTING,
                null,
                'local_flwcupkp_student_' . clean_param($unitcode, PARAM_ALPHANUMEXT)
            );
            local_flwcupkp_mark_navigation_node_active($studentnode, $studenturl);
            $evaluationurl = new moodle_url('/local/flwcupkp/evaluation.php', [
                'courseid' => $course->id,
                'unitcode' => $unitcode,
            ]);
            $evaluationnode = $navigation->add(
                get_string('evaluationnav', 'local_flwcupkp', $unitcode),
                $evaluationurl,
                navigation_node::TYPE_SETTING,
                null,
                'local_flwcupkp_evaluation_' . clean_param($unitcode, PARAM_ALPHANUMEXT)
            );
            local_flwcupkp_mark_navigation_node_active($evaluationnode, $evaluationurl);
        }

        if (has_capability('local/flwcupkp:viewreports', $context)) {
            $teacherurl = $unitcode === 'U038' ?
                new moodle_url('/local/flwcupkp/teacher_u038.php', ['courseid' => $course->id]) :
                new moodle_url('/local/flwcupkp/teacher.php', ['courseid' => $course->id, 'unitcode' => $unitcode]);
            $teachernode = $navigation->add(
                get_string('unitteachernav', 'local_flwcupkp', $unitcode),
                $teacherurl,
                navigation_node::TYPE_SETTING,
                null,
                'local_flwcupkp_teacher_' . clean_param($unitcode, PARAM_ALPHANUMEXT)
            );
            local_flwcupkp_mark_navigation_node_active($teachernode, $teacherurl);
            if (has_capability('local/flwcupkp:override', $context) &&
                    \local_flwcupkp\local\performance_service::has_tasks((int)$course->id, $unitcode)) {
                $performanceurl = $unitcode === 'U038' ?
                    new moodle_url('/local/flwcupkp/performance_u038.php', ['courseid' => $course->id]) :
                    new moodle_url('/local/flwcupkp/performance.php', [
                        'courseid' => $course->id,
                        'unitcode' => $unitcode,
                    ]);
                $performancenode = $navigation->add(
                    get_string('unitperformancenav', 'local_flwcupkp', $unitcode),
                    $performanceurl,
                    navigation_node::TYPE_SETTING,
                    null,
                    'local_flwcupkp_performance_' . clean_param($unitcode, PARAM_ALPHANUMEXT)
                );
                local_flwcupkp_mark_navigation_node_active($performancenode, $performanceurl);
            }
        }
    }
}

/**
 * Mark a Moodle navigation node active when it points to the current C-UP-KP page.
 *
 * @param navigation_node|null $node
 * @param moodle_url $url
 */
function local_flwcupkp_mark_navigation_node_active(?navigation_node $node, moodle_url $url): void {
    if (!$node || !\local_flwcupkp\local\visuals::is_current_url($url)) {
        return;
    }

    if (method_exists($node, 'make_active')) {
        $node->make_active();
    } else {
        $node->isactive = true;
    }
    $node->forceopen = true;
}
