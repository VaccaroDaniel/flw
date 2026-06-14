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
 * Theme almondb renderers.
 *
 * @package   theme_almondb
 * @copyright 2022 ThemesAlmond  - http://themesalmond.com
 * @author    ThemesAlmond - Developer Team
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/renderer.php');

/**
 *  Theme almond course render.
 */
class theme_almondb_core_course_renderer extends core_course_renderer {
    /**
     * Returns HTML to display course content (summary, course contacts and optionally category name)
     *
     * This method is called from coursecat_coursebox() and may be re-used in AJAX
     *
     * @param coursecat_helper $chelper various display options
     * @param stdClass|core_course_list_element $course
     * @return string
     */
    protected function coursecat_coursebox_content(coursecat_helper $chelper, $course) {
        if ($chelper->get_show_courses() < self::COURSECAT_SHOW_COURSES_EXPANDED) {
            return '';
        }
        if ($course instanceof stdClass) {
            $course = new core_course_list_element($course);
        }
        $content = \html_writer::start_tag('div', ['class' => 'row']);

        $content .= \html_writer::start_tag('div', ['class' => 'col-md-6 col-lg-5 col-sm-12']);
        $content .= $this->course_overview_files($course);
        $content .= \html_writer::end_tag('div');

        $content .= \html_writer::start_tag('div', ['class' => 'col-md-6 col-lg-7']);
        $content .= $this->course_summary($chelper, $course);
        $content .= \html_writer::end_tag('div');

        $content .= \html_writer::end_tag('div');
        $content .= $this->course_category_name($chelper, $course);
        $content .= $this->course_custom_fields($course);
        return $content;
    }
    /**
     * Returns HTML to display course overview files.
     *
     * @param core_course_list_element $course
     * @return string
     */
    protected function course_overview_files(core_course_list_element $course): string {
        global $CFG;
        $contentimages = $contentfiles = '';
        foreach ($course->get_course_overviewfiles() as $file) {
            $isimage = $file->is_valid_image();
            $url = moodle_url::make_file_url(
                "$CFG->wwwroot/pluginfile.php",
                '/' . $file->get_contextid() . '/' . $file->get_component() . '/' .
                $file->get_filearea() . $file->get_filepath() . $file->get_filename(),
                !$isimage
            );
            if ($isimage) {
                $contentimages .= html_writer::start_tag(
                    'div',
                    ['class' => 'courseimage single-course', 'style' => 'background-image: url(' . $url . ');']
                );
                // Teacher img.
                $contentimages .= html_writer::start_tag('ul', ['class' => 'teachers']);
                foreach ($course->get_course_contacts() as $coursecontact) {
                    $rolenames = array_map(function ($role) {
                        return $role->displayname;
                    }, $coursecontact['roles']);
                    $namesrole = implode(", ", $rolenames);
                    $user = \core_user::get_user($coursecontact['user']->id);
                    $userpicture = new user_picture($user);
                    $picture = $userpicture->get_url($this->page)->out(false);
                    $name = " <div class='chip h6'><img src='{$picture}'";
                    $name .= " class='border border-secondary' title='{$namesrole}' data-bs-toggle='tooltip'";
                    $name .= " alt='{$coursecontact['username']}'/>" . html_writer::link(
                        new moodle_url(
                            '/user/view.php',
                            ['id' => $coursecontact['user']->id, 'course' => SITEID]
                        ),
                        " " . $coursecontact['username']
                    ) . "</div>";
                    $contentimages .= html_writer::tag('li', $name);
                }
                $contentimages .= html_writer::end_tag('ul');

                $contentimages .= html_writer::end_tag('div');
            } else {
                $image = $this->output->pix_icon(file_file_icon($file), $file->get_filename(), 'moodle');
                $filename = html_writer::tag('span', $image, ['class' => 'fp-icon']) .
                    html_writer::tag('span', $file->get_filename(), ['class' => 'fp-filename']);
                $contentfiles .= html_writer::tag(
                    'span',
                    html_writer::link($url, $filename),
                    ['class' => 'coursefile fp-filename-icon text-break']
                );
            }
        }
        // If the course image is empty, add a teacher image.
        if (empty($contentimages)) {
            $contentimages .= html_writer::start_tag('ul', ['class' => 'teachers']);
            foreach ($course->get_course_contacts() as $coursecontact) {
                $rolenames = array_map(function ($role) {
                    return $role->displayname;
                }, $coursecontact['roles']);
                $namesrole = implode(", ", $rolenames);
                $user = \core_user::get_user($coursecontact['user']->id);
                $userpicture = new user_picture($user);
                $picture = $userpicture->get_url($this->page)->out(false);
                $name = " <div class='chip h6'><img src='{$picture}'";
                $name .= " class='border border-secondary' title='{$namesrole}' data-bs-toggle='tooltip'";
                $name .= " alt='{$coursecontact['username']}'/>" . html_writer::link(
                    new moodle_url(
                        '/user/view.php',
                        ['id' => $coursecontact['user']->id,
                        'course' => SITEID]
                    ),
                    " " . $coursecontact['username']
                ) . "</div>";
                $contentimages .= html_writer::tag('li', $name);
            }
            $contentimages .= html_writer::end_tag('ul');
        }
        return $contentimages . $contentfiles;
    }
}
require('renderers_blog.php');
