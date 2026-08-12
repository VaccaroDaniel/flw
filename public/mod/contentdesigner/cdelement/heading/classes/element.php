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
 * Heading element instance class.
 *
 * @package   cdelement_heading
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace cdelement_heading;

use html_writer;

/**
 * Heading element instance class.
 */
class element extends \mod_contentdesigner\elements {
    /**
     * Shortname of the element.
     */
    const SHORTNAME = 'heading';

    /**
     * Element name which is visbile for the users
     *
     * @return string
     */
    public function element_name() {
        return get_string('pluginname', 'cdelement_heading');
    }

    /**
     * Element shortname which is used as identical purpose.
     *
     * @return string
     */
    public function element_shortname() {
        return self::SHORTNAME;
    }

    /**
     * Icon of the element.
     *
     * @param renderer $output
     * @return string HTML fragment
     */
    public function icon($output) {
        return html_writer::tag('i', '', ['class' => 'fa fa-header icon pluginicon']);
    }

    /**
     * Search area definition.
     *
     * @return array Table and fields to search.
     */
    public function search_area_list(): array {
        return ['cdelement_heading' => 'heading'];
    }

    /**
     * Element form element definition.
     *
     * @param moodle_form $mform
     * @param genreal_element_form $formobj
     * @return void
     */
    public function element_form(&$mform, $formobj) {

        $mform->addElement('text', 'heading', get_string('headingtext', 'mod_contentdesigner'), 'maxlength="100" size="30"');
        $mform->setType('heading', PARAM_NOTAGS);
        $mform->addRule('heading', null, 'required');
        $mform->addHelpButton('heading', 'headingtext', 'mod_contentdesigner');

        $mform->addElement('url', 'headingurl', get_string('headingurl', 'mod_contentdesigner'), ['size' => '60'], [
        'usefilepicker' => true]);
        $mform->setType('headingurl', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('headingurl', 'headingurl', 'mod_contentdesigner');

        $headings = [
            'h2' => get_string('mainheading', 'mod_contentdesigner'),
            'h3' => get_string('subheading', 'mod_contentdesigner'),
        ];
        $default = get_config('cdelement_heading', 'headingtype');
        $mform->addElement('select', 'headingtype', get_string('strheading', 'mod_contentdesigner'), $headings);
        $mform->addHelpButton('headingtype', 'strheading', 'mod_contentdesigner');
        $mform->setDefault('headingtype', $default ?: 'h2');

        $targets = [
            '_blank' => get_string('strblank', 'mod_contentdesigner'),
            '_self' => get_string('strself', 'mod_contentdesigner'),
        ];
        $default = get_config('cdelement_heading', 'target');
        $mform->addElement('select', 'target', get_string('target', 'mod_contentdesigner'), $targets);
        $mform->addHelpButton('target', 'target', 'mod_contentdesigner');
        $mform->setDefault('target', $default ?: '_blank');

        $horizontalalign = [
            'left' => get_string('strleft', 'mod_contentdesigner'),
            'center' => get_string('strcenter', 'mod_contentdesigner'),
            'right' => get_string('strright', 'mod_contentdesigner'),
        ];
        $default = get_config('cdelement_heading', 'horizontal');
        $mform->addElement('select', 'horizontal', get_string('horizontalalign', 'mod_contentdesigner'), $horizontalalign);
        $mform->addHelpButton('horizontal', 'horizontalalign', 'mod_contentdesigner');
        $mform->setDefault('horizontal', $default ?: 'left');

        $verticalalign = [
            'top' => get_string('strtop', 'mod_contentdesigner'),
            'middle' => get_string('strmiddle', 'mod_contentdesigner'),
            'bottom' => get_string('strbottom', 'mod_contentdesigner'),
        ];
        $default = get_config('cdelement_heading', 'vertical');
        $mform->addElement('select', 'vertical', get_string('verticalalign', 'mod_contentdesigner'), $verticalalign);
        $mform->addHelpButton('vertical', 'verticalalign', 'mod_contentdesigner');
        $mform->setDefault('vertical', $default ?: 'top');
    }

    /**
     * Render the view of element instance, Which is displayed in the student view.
     *
     * @param stdclass $instance
     * @return string
     */
    public function render($instance) {
        $content = '';
        if ($instance->visible && $instance->heading && $instance->headingtype) {
            $hozclass = "hl-" . $instance->horizontal;
            $vertclass = "vl-" . $instance->vertical;
            $heading = html_writer::tag($instance->headingtype, format_string($instance->heading), [
            'class' => "element-heading $hozclass $vertclass"]);
            $content .= ($instance->headingurl) ?
            html_writer::link($instance->headingurl, $heading, ['target' => $instance->target]) : $heading;
        }
        return $content;
    }
}
