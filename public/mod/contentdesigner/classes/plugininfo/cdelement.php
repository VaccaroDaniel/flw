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
 * Subplugin info class.
 *
 * @package   mod_contentdesigner
 * @copyright  2022 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_contentdesigner\plugininfo;

use part_of_admin_tree;
use admin_settingpage;

/**
 * Element subplugin define classes.
 */
class cdelement extends \core\plugininfo\base {
    /**
     * Returns the information about plugin availability
     *
     * True means that the plugin is enabled. False means that the plugin is
     * disabled. Null means that the information is not available, or the
     * plugin does not support configurable availability or the availability
     * can not be changed.
     *
     * @return null|bool
     */
    public function is_enabled() {
        return true;
    }

    /**
     * Should there be a way to uninstall the plugin via the administration UI.
     *
     * By default uninstallation is not allowed, plugin developers must enable it explicitly!
     *
     * @return bool
     */
    public function is_uninstall_allowed() {
        return true;
    }

    /**
     * Returns the node name used in admin settings menu for this plugin settings (if applicable)
     *
     * @return null|string node name or null if plugin does not create settings node (default)
     */
    public function get_settings_section_name() {
        return 'cdelement' . $this->name . 'settings';
    }
    /**
     * Loads plugin settings to the settings tree
     *
     * This function usually includes settings.php file in plugins folder.
     * Alternatively it can create a link to some settings page (instance of admin_externalpage)
     *
     * @param \part_of_admin_tree $adminroot
     * @param string $parentnodename
     * @param bool $hassiteconfig whether the current user has moodle/site:config capability
     */
    public function load_settings(part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {

        $ADMIN = $adminroot; // May be used in settings.php.
        if (!$this->is_installed_and_upgraded()) {
            return;
        }

        if (!$hassiteconfig || !file_exists($this->full_path('settings.php'))) {
            return;
        }

        $section = $this->get_settings_section_name();
        $page = new admin_settingpage($section, $this->displayname, 'moodle/site:config', $this->is_enabled() === false);
        include($this->full_path('settings.php')); // This may also set $settings to null.

        if ($page) {
            $ADMIN->add($parentnodename, $page);
        }
    }

    /**
     * Update the plugins config, move the files from previous fileareas. remove the exising plugin from moodle DB.
     *
     * @param string $pluginname
     * @param string $newpluginname
     * @param array $areafiles
     * @return void
     */
    public static function update_plugins_config(string $pluginname, $newpluginname, $areafiles = []) {
        global $DB, $CFG;

        $records = $DB->get_records('config_plugins', ['plugin' => $pluginname]);
        $existingnewpluginrecords = $DB->get_records_menu('config_plugins', ['plugin' => $newpluginname], 'id', 'id,name');
        if ($records) {
            foreach ($records as $record) {
                if (!in_array($record->name, $existingnewpluginrecords)) {
                    $record->plugin = $newpluginname;
                    $DB->update_record('config_plugins', $record);
                }
            }
        }

        // Get the list of contexts created for content designer instance.
        $sql = "SELECT ctx.id, ctx.contextlevel, ctx.instanceid
            FROM {context} ctx
            JOIN {course_modules} cm ON cm.id = ctx.instanceid
            JOIN {modules} m ON m.id = cm.module
            WHERE m.name = :modulename";
        $contexts = $DB->get_records_sql($sql, ['modulename' => 'contentdesigner']);

        // Copy file area files from old plugin to new plugin.
        if (!empty($areafiles) && !empty($contexts)) {
            $fs = get_file_storage();
            foreach ($areafiles as $filearea) {
                foreach ($contexts as $context) {
                    $oldfilearea = $pluginname . '_' . $filearea;
                    $newfilearea = $newpluginname . '_' . $filearea;
                    $component = 'mod_contentdesigner';

                    // H5P maintain its own file area.
                    if ($filearea == 'package') {
                        $oldfilearea = $filearea;
                        $newfilearea = $filearea;
                        $component = $pluginname;
                    }

                    $files = $fs->get_area_files($context->id, $component, $oldfilearea);
                    foreach ($files as $file) {
                        if ($file->is_directory()) {
                            continue;
                        }

                        $filerecord = [
                            'contextid'    => $file->get_contextid(),
                            'component'    => $filearea == 'package' ? $newpluginname : 'mod_contentdesigner',
                            'filearea'     => $newfilearea,
                            'itemid'       => $file->get_itemid(),
                            'filepath'     => $file->get_filepath(),
                            'filename'     => $file->get_filename(),
                            'timecreated'  => $file->get_timecreated(),
                            'timemodified' => $file->get_timemodified(),
                        ];
                        $fs->create_file_from_storedfile($filerecord, $file);
                        // Now delete the original file.
                        $file->delete();
                    }
                }
            }
        }

        if (class_exists('\core\output\progress_trace\progress_trace_buffer')) {
            // Uninstall the plugin addon_report, this will remove the missing from the disk issue.
            $progress = new \core\output\progress_trace\progress_trace_buffer(
            new \core\output\progress_trace\text_progress_trace(), false);
        } else {
            require_once($CFG->libdir . '/weblib.php');
            $progress = new \progress_trace_buffer(new \text_progress_trace(), false);
        }

        if (class_exists('\core\plugin_manager')) {
            // Uninstall the plugin element_chapter using Moodle's default method.
            \core\plugin_manager::instance()->uninstall_plugin($pluginname, $progress);
        } else {
            require_once($CFG->libdir . '/adminlib.php');
            core_plugin_manager::instane()->uninstall_plugin($pluginname, $progress);
        }
    }
}
