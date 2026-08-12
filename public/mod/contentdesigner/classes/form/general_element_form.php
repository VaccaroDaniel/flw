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
 * Form for editing a general element.
 *
 * @package    mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_contentdesigner\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * General option form to create elements.
 *
 * @copyright 2022 bdecent gmbh <https://bdecent.de>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class general_element_form extends \moodleform {
    /**
     * Make the custom data as public varaible to access on the elements forms.
     *
     * @var array
     */
    public $_customdata;

    /**
     * Define the form.
     */
    public function definition() {
        global $DB;
        $mform = $this->_form;
        $element = $this->_customdata['element'];
        $instanceid = $this->_customdata['instanceid'];
        $cmid = $this->_customdata['cmid'];
        $element = \mod_contentdesigner\editor::get_element($element, $cmid);

        $mform->addElement('header', 'elementsettings',
        get_string('elementsettings', 'mod_contentdesigner', ucfirst($element->element_name())));
        $element->element_form($mform, $this);

        if ($element->supports_standard_elements()) {
            // Extend the elements own options as mform element.
            $this->standard_element_settings($mform);
        }

        $buttonstr = '';
        if ($instanceid) {
            $buttonstr = get_string('elementupdate', 'mod_contentdesigner');
        } else {
            $buttonstr = get_string('elementcreate', 'mod_contentdesigner');
        }

        $mform->addElement('hidden', 'chapterid', $this->_customdata['chapterid']);
        $mform->setType('chapterid', PARAM_INT);

        $mform->addElement('hidden', 'sesskey', sesskey());
        $mform->setType('sesskey', PARAM_ALPHANUMEXT);

        if (($this->_customdata['element'] == "chapter") && ($record = $DB->get_record('cdelement_chapter',
        ['id' => $instanceid]))) {
            $mform->addElement('hidden', 'position', $record->position);
            $mform->setType('position', PARAM_INT);
        } else {
            $mform->addElement('hidden', 'position', $this->_customdata['position']);
            $mform->setType('position', PARAM_ALPHA);
        }

        $this->add_action_buttons(true, $buttonstr);
    }

    /**
     * Defined the standard general options moodle form elements for content designer elements.
     *
     * @param moodle_form $mform Moodle quick form.
     * @return void
     */
    public function standard_element_settings($mform) {

        // Print the required moodle fields first.
        // Title for General element.
        $mform->addElement('header', 'generalsettings', get_string('generaltitle', 'mod_contentdesigner'));
        $mform->addElement('text', 'title', get_string('elementtitle', 'mod_contentdesigner'), 'maxlength="100" size="30"');
        $mform->setType('title', PARAM_NOTAGS);
        $mform->addHelpButton('title', 'elementtitle', 'mod_contentdesigner');

        // Description for General element.
        $mform->addElement('editor', 'description_editor', get_string('elementdescription', 'mod_contentdesigner'), null, [
            'maxfiles' => 0,
            'maxbytes' => 0,
            'context' => $element->context ?? null,
        ]);
        $mform->setType('description_editor', PARAM_RAW);
        $mform->addHelpButton('description_editor', 'elementdescription', 'mod_contentdesigner');

        // Show description for General element.
        $mform->addElement('advcheckbox', 'showdescription', get_string('showdescription', 'mod_contentdesigner'));
        $mform->addHelpButton('showdescription', 'showdescription', 'mod_contentdesigner');

        // Visibility for General element.
        $visibleoptions = [
            1 => get_string('visible'),
            0 => get_string('hidden', 'mod_contentdesigner'),
        ];
        $default = get_config('mod_contentdesigner', 'visible');
        $mform->addElement('select', 'visible', get_string('visibility', 'mod_contentdesigner'), $visibleoptions);
        $mform->addHelpButton('visible', 'visibility', 'mod_contentdesigner');
        $mform->setDefault('visible', $default ?: 1);

        // Margin for General element.
        $mform->addElement('text', 'margin',  get_string('margin', 'mod_contentdesigner'), 'size="30"');
        $mform->setType('margin', PARAM_RAW);
        $mform->addHelpButton('margin', 'margin', 'mod_contentdesigner');
        $default = get_config('mod_contentdesigner', 'margin');
        $mform->setDefault('margin', $default ?: '');

        // Padding for General element.
        $mform->addElement('text', 'padding',  get_string('padding', 'mod_contentdesigner'), 'size="30"');
        $mform->setType('padding', PARAM_RAW);
        $mform->addHelpButton('padding', 'padding', 'mod_contentdesigner');
        $default = get_config('mod_contentdesigner', 'padding');
        $mform->setDefault('padding', $default ?: '');

        $mform->addElement('header', 'backgroundsettings', get_string('backgroundtitle', 'mod_contentdesigner'));

        // Background for general element.
        $mform->addElement('text', 'abovecolorbg', get_string('abovecolorbg', 'mod_contentdesigner'),
        ['placeholder' => 'linear-gradient(#e66465, #9198e5)', 'size' => "60"]);
        $mform->setType('abovecolorbg', PARAM_RAW);
        $mform->addHelpButton('abovecolorbg', 'abovecolorbg', 'mod_contentdesigner');
        $default = get_config('mod_contentdesigner', 'abovecolorbg');
        $mform->setDefault('abovecolorbg', $default ?: '');

        $options = [
            'accepted_types' => ['image'],
            'maxbytes' => 0,
            'maxfiles' => 1,
            'subdirs' => 0,
        ];
        $mform->addElement('filemanager', 'bgimage', get_string('elementbgimage', 'mod_contentdesigner'), null, $options);
        $mform->addHelpButton('bgimage', 'elementbgimage', 'mod_contentdesigner');

        $mform->addElement('text', 'belowcolorbg', get_string('belowcolorbg', 'mod_contentdesigner'),
        ['placeholder' => 'linear-gradient(#e66465, #9198e5)', 'size' => "60"]);
        $mform->setType('belowcolorbg', PARAM_RAW);
        $mform->addHelpButton('belowcolorbg', 'belowcolorbg', 'mod_contentdesigner');
        $default = get_config('mod_contentdesigner', 'belowcolorbg');
        $mform->setDefault('belowcolorbg', $default ?: '');

        $mform->addElement('header', 'animationsettings', get_string('animationtitle', 'mod_contentdesigner'));

        // Animation for general element.
        $animationtype = [
            0 => get_string('none'),
            'fadeIn' => get_string('fadein', 'mod_contentdesigner'),
            'slideInRight' => get_string('slidefromright', 'mod_contentdesigner'),
            'slideInLeft' => get_string('slidefromleft', 'mod_contentdesigner'),
        ];
        $mform->addElement('select', 'animation', get_string('stranimation', 'mod_contentdesigner'), $animationtype);
        $mform->addHelpButton('animation', 'stranimation', 'mod_contentdesigner');
        $default = get_config('mod_contentdesigner', 'animation');
        $mform->setDefault('animation', $default ?: 0);

        $durations = [
            'slow' => get_string('strslow', 'mod_contentdesigner'),
            'normal' => get_string('strnormal', 'mod_contentdesigner'),
            'fast' => get_string('strfast', 'mod_contentdesigner'),
        ];
        $mform->addElement('select', 'duration', get_string('strduration', 'mod_contentdesigner'), $durations);
        $mform->addHelpButton('duration', 'strduration', 'mod_contentdesigner');
        $default = get_config('mod_contentdesigner', 'duration');
        $mform->setDefault('duration', $default ?: 'slow');

        $mform->addElement('text', 'delay', get_string('strdelay', 'mod_contentdesigner'));
        $mform->setType('delay', PARAM_TEXT);
        $mform->addHelpButton('delay', 'strdelay', 'mod_contentdesigner');
        $default = get_config('mod_contentdesigner', 'delay');
        $mform->setDefault('delay', $default ?: '');

        $mform->addElement('header', 'scrollingsettings', get_string('scrollingeffectstitle', 'mod_contentdesigner'));

        // Animation for general element.
        $scrolldirections = [
            0 => get_string('none'),
            'left' => get_string('toleft', 'mod_contentdesigner'),
            'right' => get_string('toright', 'mod_contentdesigner'),
        ];
        $mform->addElement('select', 'direction', get_string('strdirection', 'mod_contentdesigner'), $scrolldirections);
        $mform->addHelpButton('direction', 'strdirection', 'mod_contentdesigner');
        $default = get_config('mod_contentdesigner', 'direction');
        $mform->setDefault('direction', $default ?: 0);

        $mform->addElement('select', 'speed', get_string('speed', 'mod_contentdesigner'), range(0, 10));
        $mform->addHelpButton('speed', 'speed', 'mod_contentdesigner');
        $default = get_config('mod_contentdesigner', 'speed');
        $mform->setDefault('speed', $default ?: 0);

        $mform->addElement('text', 'viewport', get_string('viewport', 'mod_contentdesigner'));
        $mform->setType('viewport', PARAM_INT);
        $mform->addHelpButton('viewport', 'viewport', 'mod_contentdesigner');
        $default = get_config('mod_contentdesigner', 'viewport');
        $mform->setDefault('viewport', $default ?: '');

        $mform->addElement('header', 'responsivesettings', get_string('responsivetitle', 'mod_contentdesigner'));

        // Responsive for general element.
        $mform->addElement('advcheckbox', 'hidedesktop', get_string('hideondesktop', 'mod_contentdesigner'));
        $mform->addHelpButton('hidedesktop', 'hideondesktop', 'mod_contentdesigner');
        $default = get_config('mod_contentdesigner', 'hidedesktop');
        $mform->setDefault('hidedesktop', $default ?: 0);

        $mform->addElement('advcheckbox', 'hidetablet', get_string('hideontablet', 'mod_contentdesigner'));
        $mform->addHelpButton('hidetablet', 'hideontablet', 'mod_contentdesigner');
        $default = get_config('mod_contentdesigner', 'hidetablet');
        $mform->setDefault('hidetablet', $default ?: 0);

        $mform->addElement('advcheckbox', 'hidemobile', get_string('hideonmobile', 'mod_contentdesigner'));
        $mform->addHelpButton('hidemobile', 'hideonmobile', 'mod_contentdesigner');
        $default = get_config('mod_contentdesigner', 'hidemobile');
        $mform->setDefault('hidemobile', $default ?: 0);
    }
}
