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
 * Behat Designer course format steps definitions.
 *
 * @package    cdelement_outro
 * @category   test
 * @copyright  2020 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 require_once(__DIR__ . '/../../../../../../lib/behat/behat_base.php');

/**
 * Designer course format steps definitions.
 *
 * @package    cdelement_outro
 * @category   test
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_cdelement_outro extends behat_base {
    /**
     * Go to editing section layout for specified section number and layout type.
     * You need to be in the course page and on editing mode.
     *
     * @Given /^I check outro image$/
     */
    public function i_check_outro_image() {
        $this->execute('behat_general::the_image_at_should_be_identical_to', [
        "//div[contains(@class, 'element-outro')]//img[contains(@src, 'pluginfile.php')
        and contains(@src, '/mod_contentdesigner/cdelement_outro_outroimage/')]",
        "xpath_element",
        "mod/contentdesigner/cdelement/outro/tests/behat/assets/c1.jpg",
        ]);
    }
}
