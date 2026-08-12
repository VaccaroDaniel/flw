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

namespace mod_contentdesigner;

/**
 * Generator tests class.
 *
 * @package    mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class generator_test extends \advanced_testcase {
    /**
     * Test test_content_designer_create_instance
     * @covers ::create_instance
     */
    public function test_content_designer_create_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse($DB->record_exists('contentdesigner', ['course' => $course->id]));
        $contentdesigner = $this->getDataGenerator()->create_module('contentdesigner', ['course' => $course->id]);
        $this->assertEquals(1, $DB->count_records('contentdesigner', ['course' => $course->id]));
        $this->assertTrue($DB->record_exists('contentdesigner', ['course' => $course->id]));
        $this->assertTrue($DB->record_exists('contentdesigner', ['id' => $contentdesigner->id]));

        $params = ['course' => $course->id, 'name' => 'One more contentdesigner'];
        $contentdesigner = $this->getDataGenerator()->create_module('contentdesigner', $params);
        $this->assertEquals(2, $DB->count_records('contentdesigner', ['course' => $course->id]));
        $this->assertEquals(
            'One more contentdesigner',
            $DB->get_field_select('contentdesigner',
            'name', 'id = :id',
            ['id' => $contentdesigner->id]
        ));
    }
}
