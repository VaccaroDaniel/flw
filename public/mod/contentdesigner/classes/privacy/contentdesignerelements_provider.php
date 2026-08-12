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
 * This file contains the contentdesignerelements_provider interface.
 *
 * Content designer Sub plugins should implement this if they store personal information.
 *
 * @package   mod_contentdesigner
 * @copyright 2022, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_contentdesigner\privacy;

/**
 * Content designer elements privacy provider.
 *
 * @package   mod_contentdesigner
 * @copyright 2022, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface contentdesignerelements_provider extends \core_privacy\local\request\plugin\subplugin_provider {
    /**
     * Export all relevant user elements information which match the combination of userid.
     *
     * @param array $contentdesignerids The subcontext within the context to export this information
     * @param stdclass $user
     */
    public static function export_element_user_data(array $contentdesignerids, \stdclass $user);
}
