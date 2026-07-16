<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwmedia\external;

defined('MOODLE_INTERNAL') || die();

/**
 * External function for saving reading attempts.
 *
 * @package    local_flwmedia
 */
class save_reading_attempt extends save_attempt_base {
    /** @var string Practice mode handled by this endpoint. */
    protected const MODE = 'read';
}
