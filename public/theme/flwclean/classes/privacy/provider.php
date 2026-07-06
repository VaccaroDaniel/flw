<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Privacy provider for FLW Clean Mode v1.
 *
 * @package    theme_flwclean
 * @copyright  2026 Foreign Language World
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_flwclean\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * FLW Clean Mode v1 stores no personal data.
 */
class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * Return a reason why this plugin stores no personal data.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
