<?php
// Privacy metadata for FLW VR Room.

namespace mod_flwvrroom\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements \core_privacy\local\metadata\provider {
    public static function get_metadata(\core_privacy\local\metadata\collection $collection): \core_privacy\local\metadata\collection {
        $collection->add_database_table('flwvrroom_attempts', [
            'userid' => 'privacy:metadata:flwvrroom_attempts:userid',
            'score' => 'privacy:metadata:flwvrroom_attempts:score',
            'completedobjects' => 'privacy:metadata:flwvrroom_attempts:completedobjects',
            'completed' => 'privacy:metadata:flwvrroom_attempts:completed',
            'timefinished' => 'privacy:metadata:flwvrroom_attempts:timefinished',
        ], 'privacy:metadata:flwvrroom_attempts');
        return $collection;
    }
}
