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
 * Extended class of elements for Paragraph.
 *
 * @package   cdelement_paragraph
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace cdelement_paragraph;

use html_writer;

/**
 * Paragraph element instance extended the base elements.
 */
class element extends \mod_contentdesigner\elements {
    /**
     * Shortname of the element.
     */
    const SHORTNAME = 'paragraph';

    /**
     * Element name which is visbile for the users
     *
     * @return string
     */
    public function element_name() {
        return get_string('pluginname', 'cdelement_paragraph');
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
        return $output->pix_icon('e/styleparagraph', get_string('pluginname', 'cdelement_paragraph'));
    }

    /**
     * Search area definition.
     *
     * @return array Table and fields to search.
     */
    public function search_area_list(): array {
        return ['cdelement_paragraph' => 'content'];
    }

    /**
     * Element form element definition.
     *
     * @param moodle_form $mform
     * @param genreal_element_form $formobj
     * @return void
     */
    public function element_form(&$mform, $formobj) {

        $mform->addElement('textarea', 'content', get_string('content', 'mod_contentdesigner'), ['rows' => 15, 'cols' => 40]);
        $mform->addRule('content', null, 'required');
        $mform->addHelpButton('content', 'content', 'mod_contentdesigner');
        $horizontalalign = [
            'left' => get_string('strleft', 'mod_contentdesigner'),
            'center' => get_string('strcenter', 'mod_contentdesigner'),
            'right' => get_string('strright', 'mod_contentdesigner'),
        ];
        $default = get_config('cdelement_paragraph', 'horizontal');
        $mform->addElement('select', 'horizontal', get_string('horizontalalign', 'mod_contentdesigner'), $horizontalalign);
        $mform->addHelpButton('horizontal', 'horizontalalign', 'mod_contentdesigner');
        $mform->setDefault('horizontal', $default ?: 'left');

        $verticalalign = [
            'top' => get_string('strtop', 'mod_contentdesigner'),
            'middle' => get_string('strmiddle', 'mod_contentdesigner'),
            'bottom' => get_string('strbottom', 'mod_contentdesigner'),
        ];
        $default = get_config('cdelement_paragraph', 'vertical');
        $mform->addElement('select', 'vertical', get_string('verticalalign', 'mod_contentdesigner'), $verticalalign);
        $mform->addHelpButton('vertical', 'verticalalign', 'mod_contentdesigner');
        $mform->setDefault('vertical', $default ?: 'top');
    }

    /**
     * Load the classes to parent div.
     *
     * @param stdclass $instance Instance record
     * @param stdclass $option General options
     * @return void
     */
    public function generate_element_classes(&$instance, $option) {
        $instance = $this->load_option_classes($instance, $option);
        $hozclass = "hl-" . $instance->horizontal;
        $vertclass = "vl-" . $instance->vertical;
        $instance->classes .= ' ' . $hozclass . ' ' . $vertclass;
    }

    /**
     * Render the view of element instance, Which is displayed in the student view.
     *
     * @param stdclass $instance
     * @return string
     */
    public function render($instance) {
        return html_writer::tag('p', format_string($instance->content), ['class' => "element-paragraph"]);
    }
}
