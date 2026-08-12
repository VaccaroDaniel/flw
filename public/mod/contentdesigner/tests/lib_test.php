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
 * Unit tests for (some of) mod/book/lib.php.
 *
 * @package    mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_contentdesigner;

defined('MOODLE_INTERNAL') || die();

use mod_contentdesigner\editor;
use stdClass;

global $CFG;

/**
 * Unit tests for (some of) mod/book/lib.php.
 *
 * @package    mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends \advanced_testcase {
    /**
     * Course.
     * @var stdclass
     */
    public $course;

    /**
     * User.
     * @var stdclass
     */
    public $user;

    /**
     * Contentdesigner instance.
     * @var stdclass
     */
    public $contentdesigner;

    /**
     * Heading element info.
     * @var stdclass
     */
    public $headingelementinfo;

    /**
     * Heading element.
     * @var stdclass
     */
    public $headingelement;

    /**
     * Setup the test.
     */
    public function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);
        $this->contentdesigner = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $this->course->id]);
        $this->headingelementinfo = $DB->get_record('contentdesigner_elements', ['shortname' => 'heading']);
        $this->headingelement = $this->get_element($this->headingelementinfo->id, $this->contentdesigner->cmid);
    }

    /**
     * Test update_element.
     * @covers ::update_element
     */
    public function test_element_update_element(): void {
        global $DB;
        // Create element.
        $this->create_heading_element();
        $this->assertEquals($DB->count_records('cdelement_heading'), 2);
        $this->assertEquals($DB->count_records('contentdesigner_content'), 2);
    }

    /**
     * Create a heading element.
     */
    public function create_heading_element() {
        global $DB;
        $data = new stdClass();
        $data->heading = "Heading 01";
        $data->title = "Heading 01";
        $data->headingtype = "h2";
        $data->course = $this->course->id;
        $data->cmid = $this->contentdesigner->cmid;
        $data->element = $this->headingelement->element_id();
        $data->instanceid = 0;
        $data->contentdesignerid = $this->contentdesigner->id;
        $this->headingelement->update_element($data);
        $this->assertEquals($DB->count_records('cdelement_heading'), 1);
        $data->heading = "Heading 02";
        $data->title = "Heading 02";
        $this->headingelement->update_element($data);
    }

    /**
     * Test get_instance.
     * @covers ::get_instance
     */
    public function test_element_get_instance(): void {
        $this->create_heading_element();
        $element = $this->get_heading_element();
        $result = $this->headingelement->get_instance($element->id);
        $this->assertEquals('Heading 01', $result->title);
    }

    /**
     * Test info.
     * @covers ::info
     */
    public function test_element_info(): void {
        $this->create_heading_element();
        $result = $this->headingelement->info();
        $this->assertEquals($this->headingelementinfo->id, $result->elementid);
        $this->assertEquals(get_string('pluginname', 'cdelement_heading'), $result->name);
        $this->assertEquals($this->headingelementinfo->shortname, $result->shortname);
    }

    /**
     * Test get_contentdesigner.
     * @covers ::get_contentdesigner
     */
    public function test_get_contentdesigner(): void {
        $result = $this->headingelement->get_contentdesigner();
        $this->assertEquals($this->contentdesigner->id, $result->id);
    }

    /**
     * Test get_cm_from_modinstance.
     * @covers ::get_cm_from_modinstance
     */
    public function test_get_cm_from_modinstance(): void {
        $result = $this->headingelement->get_cm_from_modinstance($this->contentdesigner->id);
        $this->assertEquals($this->contentdesigner->cmid, $result->id);
    }

    /**
     * Test delete_element.
     * @covers ::delete_element
     */
    public function test_delete_element(): void {
        global $DB;
        $this->create_heading_element();
        $this->assertEquals($DB->count_records('cdelement_heading'), 2);
        $element = $this->get_heading_element();
        $instance = $this->headingelement->get_instance($element->id);
        $this->headingelement->delete_element($instance->id);
        $this->assertEquals($DB->count_records('cdelement_heading'), 1);
        $this->assertEquals($DB->count_records('contentdesigner_content'), 1);
    }

    /**
     * Test update_visibility.
     * @covers ::update_visibility
     */
    public function test_update_visibility(): void {
        global $DB;
        $this->create_heading_element();
        $element = $this->get_heading_element();
        $instance = $this->headingelement->get_instance($element->id);
        $this->headingelement->update_visibility($instance->id, 0);
        $this->assertEquals(0, $DB->get_field($this->headingelement->tablename(), 'visible', ['id' => $instance->id]));
        $this->headingelement->update_visibility($instance->id, 1);
        $this->assertEquals(1, $DB->get_field($this->headingelement->tablename(), 'visible', ['id' => $instance->id]));
    }

    /**
     * Get the editor.
     * @param int $cmid
     */
    public function get_editor($cmid) {
        return editor::get_editor($cmid);
    }

    /**
     * Get the element.
     * @param int $elementid
     * @param int $cmid
     */
    public function get_element($elementid, $cmid) {
        return editor::get_element($elementid, $cmid);
    }

    /**
     * Get the heading element.
     */
    public function get_heading_element() {
        global $DB;
        return $DB->get_record('cdelement_heading', ['title' => 'Heading 01']);
    }
}
