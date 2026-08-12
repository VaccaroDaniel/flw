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
 * Reports view page.
 *
 * @package     report_lmsace_reports
 * @copyright  2023 LMSACE <https://lmsace.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot. '/report/lmsace_reports/lib.php');

// Set external page admin.

use report_lmsace_reports\report_helper;

$defaultcourse = report_helper::get_first_course();
$defaultuser = report_helper::get_first_user();
$courseaction = optional_param('courseinfo', $defaultcourse, PARAM_INT);
$useraction = optional_param('userinfo', $defaultuser, PARAM_INT);
$report = optional_param('report', '', PARAM_TEXT);

// Page URL.
$pageurl = new moodle_url($CFG->wwwroot."/report/lmsace_reports/index.php");

if ($report == 'coursereport') {
    $context = context_course::instance($courseaction);
    require_capability("report/lmsace_reports:viewcoursereports", $context);
    $pageurl->param('courseinfo', $courseaction);

} else if ($report == 'userreport') {
    if ($USER->id == $useraction) {
        $context = context_user::instance($useraction);
        require_capability("report/lmsace_reports:viewuserreports", $context);
    } else {
        $context = context_user::instance($useraction);
        require_capability("report/lmsace_reports:viewotheruserreports", $context);
    }

    // Prevent generating the reports for admin users.
    if (is_siteadmin($useraction)) {
        core\notification::info(get_string('noadminreports', 'report_lmsace_reports'));
    }
    $pageurl->param('userinfo', $useraction);
} else {
    $context = context_system::instance();
    require_capability("report/lmsace_reports:viewsitereports", $context);
}

list($context, $course, $cm) = get_context_info_array($context->id);
require_login($course, false, $cm);

if ($report) {
    $pageurl->param('report', $report);
}

// Set page context.
$PAGE->set_context($context);

// Set page URL.
$PAGE->set_url($pageurl);

// Set Page layout.
$PAGE->set_pagelayout('standard');

// Set page heading.
if ($context->id == SYSCONTEXTID) {
    admin_externalpage_setup('lmsacesitereports', '', $PAGE->url->params(),
        $PAGE->url->out(false), ['pagelayout' => 'standard']);
    $PAGE->set_heading(get_string('reports', 'report_lmsace_reports'));
} else {
    $PAGE->set_heading($context->get_context_name(false));
}

$PAGE->set_title($SITE->shortname.": ".get_string("lmsacereports", "report_lmsace_reports"));

$PAGE->add_body_class('lmsace-reports-body');
$output = $PAGE->get_renderer('report_lmsace_reports');
$output->courseaction = $courseaction;
$output->useraction = $useraction;
$output->report = $report;

// Print output in page.
echo $output->header();
$renderable = new \report_lmsace_reports\output\lmsace_reports();

// Load js.
$PAGE->requires->js_call_amd('report_lmsace_reports/main', 'init');
echo $output->render($renderable);
echo $output->footer();
