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
 * Contentdesigner module base render to update the core components.
 *
 * @package    mod_contentdesigner
 * @copyright  2024 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_contentdesigner\output;

use core_renderer;
use moodle_url;

/**
 * Contentdesigner render to create custom navigation of previous and next modules.
 */
class renderer extends core_renderer {
    /**
     * Activity navigation to fetch the next and previous modules.
     *
     * * Removed the verification condition of theme is uses the course index.
     *
     * @copyright Modified version of moodle core output\activity_navigation by 2009 Tim Hunt
     * @param int $contentdesigner
     * @param \context $context
     *
     * @return string|\core_course\output\activity_navigation
     */
    public function activity_navigation($contentdesigner = 0, $context = null) {
        // First we should check if we want to add navigation.
        $context = $context ?: $this->page->context;
        if (($this->page->pagelayout !== 'incourse' && $this->page->pagelayout !== 'frametop') ||
        $context->contextlevel != CONTEXT_MODULE) {
            return '';
        }

        if ($contentdesigner) {
            [$course, $cm] = get_course_and_cm_from_instance($contentdesigner, 'contentdesigner');
        } else {
            $course = $this->page->cm->get_course();
            $cm = $this->page->cm;
        }

        // If the activity is in stealth mode, show no links.
        if ($cm->is_stealth()) {
            return '';
        }

        // Get a list of all the activities in the course.
        $modules = get_fast_modinfo($course->id)->get_cms();

        // Put the modules into an array in order by the position they are shown in the course.
        $mods = [];
        $activitylist = [];
        foreach ($modules as $module) {
            // Only add activities the user can access, aren't in stealth mode and have a url (eg. mod_label does not).
            if (!$module->uservisible || $module->is_stealth() || empty($module->url)) {
                continue;
            }
            $mods[$module->id] = $module;

            // No need to add the current module to the list for the activity dropdown menu.
            if ($module->id == $this->page->cm->id) {
                continue;
            }
            // Module name.
            $modname = $module->get_formatted_name();
            // Display the hidden text if necessary.
            if (!$module->visible) {
                $modname .= ' ' . get_string('hiddenwithbrackets');
            }
            // Module URL.
            $linkurl = new moodle_url($module->url, ['forceview' => 1]);
            // Add module URL (as key) and name (as value) to the activity list array.
            $activitylist[$linkurl->out(false)] = $modname;
        }

        $nummods = count($mods);

        // If there is only one mod then do nothing.
        if ($nummods == 1) {
            return '';
        }

        // Get an array of just the course module ids used to get the cmid value based on their position in the course.
        $modids = array_keys($mods);

        // Get the position in the array of the course module we are viewing.
        $position = array_search($this->page->cm->id, $modids);

        $prevmod = null;
        $nextmod = null;

        // Check if we have a previous mod to show.
        if ($position > 0) {
            $prevmod = $mods[$modids[$position - 1]];
        }

        // Check if we have a next mod to show.
        if ($position < ($nummods - 1)) {
            $nextmod = $mods[$modids[$position + 1]];
        }

        $activitynav = new \core_course\output\activity_navigation($prevmod, $nextmod, $activitylist);
        return $activitynav;
    }
}
