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
 * Get report widgets
 *
 * @package     report_lmsace_reports
 * @copyright  2023 LMSACE <https://lmsace.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace report_lmsace_reports\output;

/**
 * Define widget reports.
 *
 * @package     report_lmsace_reports
 * @copyright  2023 LMSACE <https://lmsace.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_widgets {

    /**
     * @var array $instance
     */
    private $instance;

    /**
     * Constructor to get report widgets
     * @param array $widgets Widgets
     * @param object $output
     */
    public function __construct($widgets, $output) {
        global $CFG, $DB;

        $visiblesitewidgets = explode(",", get_config('reports_lmsace_reports', 'visiblesitereports'));
        $visiblecoursewidgets = explode(",", get_config('reports_lmsace_reports', 'visiblecoursereports'));
        $visibleuserwidgets = explode(",", get_config('reports_lmsace_reports', 'visibleuserreports'));
        $visiblewidgets = array_merge($visiblesitewidgets, $visiblecoursewidgets, $visibleuserwidgets);

        if (empty(array_filter($visiblesitewidgets))) {
            $this->instance['nositereports'] = true;
        }

        if (empty(array_filter($visiblecoursewidgets))) {
            $this->instance['nocoursereports'] = true;
        }

        if (empty(array_filter($visibleuserwidgets))) {
            $this->instance['nouserreports'] = true;
        } else if (is_siteadmin($output->useraction)) {
            $this->instance['enableuserblock'] = false;
            $this->instance['isforadmin'] = true;
        }

        foreach ($widgets as $widget) {
            // Check the widget instance visible or not.
            if (!$widget->visible || !in_array($widget->widget, $visiblewidgets)) {
                continue;
            }
            // Check if class file exist.
            $classname = $widget->instance;
            $classfile = $CFG->dirroot . '/report/lmsace_reports/classes/local/widgets/' . $classname . '.php';
            if (!file_exists($classfile)) {
                debugging("Class file dosn't exist " . $classname);
            }
            require_once($classfile);
            $widgetinstance = null;
            $classname = '\\report_lmsace_reports\\local\\widgets\\' . $classname;
            if ($widget->context == "course") {
                if ($DB->record_exists('course', ['id' => $output->courseaction])) {
                    $widgetinstance = new $classname($output->courseaction);
                }
            } else if ($widget->context == "user") {
                if ($DB->record_exists('user', ['id' => $output->useraction])) {
                    $widgetinstance = new $classname($output->useraction);
                }
            } else {
                $widgetinstance = new $classname();
            }

            $data = $widgetinstance ? $widgetinstance->get_data() : [];
            // Data is empty not need to show the report.
            $this->instance[$widget->widget] = $data;
        }
    }

    /**
     * Get the widgets.
     */
    public function get_widgets() {
        return $this->instance;
    }
}
