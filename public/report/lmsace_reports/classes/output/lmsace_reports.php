<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Config report renderer.
 *
 * @package    report_lmsace_reports
 * @copyright  2023 LMSACE <https://lmsace.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_lmsace_reports\output;

use report_lmsace_reports\report_helper;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;
use moodle_url;

require_once($CFG->dirroot.'/report/lmsace_reports/lib.php');

/**
 * Config report renderer.
 *
 * @package    report_lmsace_reports
 * @copyright  2023 LMSACE <https://lmsace.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lmsace_reports implements renderable, templatable {

    /**
     * Report.
     *
     * @var [type]
     */
    public $report;

    /**
     * Function to export the renderer data in a format that is suitable for a
     * edit mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return stdClass|array
     */
    public function export_for_template(renderer_base $output) {
        global $CFG, $PAGE, $USER;

        $data = new stdClass();

        $data->enablecourseblock = !empty(report_helper::get_course()) ? true : false;
        $data->lastmonthsinfo = report_helper::get_current_last_12months();
        $data->currentmonth = $data->lastmonthsinfo[0]['month'];
        $data->courses = report_helper::get_course($output->courseaction);

        // Get the widgets list.
        $widgets = \report_lmsace_reports\widgets::get_widgets();
        $reportwidgets = new \report_lmsace_reports\output\report_widgets($widgets, $output);
        $widgetslist = $reportwidgets->get_widgets();

        // Load the reports js data variables.
        report_helper::load_js_data($widgetslist);
        $data->widgets = $widgetslist;

        $purgeurl = new moodle_url('/report/lmsace_reports/cache.php', ['confirm' => 1,
                'sesskey' => sesskey(),
                'returnurl' => $PAGE->url->out_as_local_url(false),
            ]);

        $data->contextid = $PAGE->context->id;
        $data->users = report_helper::get_users($output->useraction);

        $data->enableuserblock = !empty(report_helper::get_users()) ? true : false;
        $data->pageurl = new \moodle_url('/report/lmsace_reports/index.php');
        if (isset($output->report) && !empty($output->report)) {
            $data->{$output->report} = true;
            $data->reportbase = true;
            $purgeurl->param('purge', $output->report);
            $purgeurl->param('courseinfo', $output->courseaction);
            $purgeurl->param('userinfo', $output->useraction);
        }
        $data->purgeurl = $purgeurl->out(false);

        $availableteachercourses = false;
        if ($output->report == 'coursereport' && !has_capability("report/lmsace_reports:viewsitereports",
            \context_system::instance())) {
            if (report_helper::is_currentuser_has_teacherrole()) {
                $teachercourses = report_helper::get_teacher_courses($USER->id);
                $teachercourses = array_column($teachercourses, 'courseid');
                $data->courses = report_helper::generate_course_chooser_data($teachercourses, $output->courseaction);
                $availableteachercourses = true;
            }
        }

        $data->availableteachercourses = $availableteachercourses;
        $data->reporttype = $output->report;
        $data->courseaction = $output->courseaction;

        // Chooser form.
        require_once($CFG->dirroot . '/report/lmsace_reports/form/chooser_form.php');

        if (has_capability("report/lmsace_reports:viewsitereports", \context_system::instance()) && $data->enablecourseblock) {

            if ($PAGE->context->contextlevel == CONTEXT_SYSTEM) {
                // Course selectors form.
                $courseform = new \course_selector_form(null, ['courseinfo' => $output->courseaction]);
                if ($courseform->get_data()) {
                    $data->showcoursereport = true;
                }
                $courseform->set_data(['courseinfo' => $output->courseaction]);
                $data->courseform = $courseform->render();
            } else {
                $data->courseform = '';
            }
        }

        if ($PAGE->context->contextlevel == CONTEXT_SYSTEM) {
            // Users selectors form.
            $form = (new \user_selector_form(null, ['userinfo' => $output->useraction]));
            if ($form->get_data()) {
                $data->showuserreport = true;
            }
            $form->set_data(['userinfo' => $output->useraction]);
            $data->userform = $form->render();
        } else {
            $data->userform = '';
        }
        // Moodle 5.0 uses Bootstrap 5, so we need to set the correct data attribute for tab toggling.
        $data->datatabtoggle = $CFG->branch >= 500 ? 'data-bs-toggle=tab' : 'data-toggle=tab';
        $data->datatargetsitereport = $CFG->branch >= 500 ? 'data-bs-target=#site-report' : 'href=#site-report';
        $data->datatargetcoursereport = $CFG->branch >= 500 ? 'data-bs-target=#course-report' : 'href=#course-report';
        $data->datatargetuserreport = $CFG->branch >= 500 ? 'data-bs-target=#user-report' : 'href=#user-report';
        $data->datatoggle = $CFG->branch >= 500 ? 'data-bs-toggle=dropdown' : 'data-toggle=dropdown';
        $data->showsitereport = !isset($data->showcoursereport) && !isset($data->showuserreport);

        return $data;
    }
}
