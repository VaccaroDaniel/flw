<?php
// This file is part of Moodle - http://moodle.org/

namespace local_flwexam\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;

/**
 * Privacy metadata provider for FLW Exam.
 */
class provider implements metadata_provider {
    /**
     * Describe stored user data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_flwexam_attempts', [
            'userid' => 'privacy:metadata:attempts:userid',
            'metadatajson' => 'privacy:metadata:attempts:metadatajson',
        ], 'privacy:metadata:attempts');
        $collection->add_database_table('local_flwexam_results', [
            'userid' => 'privacy:metadata:results:userid',
            'overallscore' => 'privacy:metadata:results:overallscore',
            'decisionjson' => 'privacy:metadata:results:decisionjson',
        ], 'privacy:metadata:results');
        $collection->add_database_table('local_flwexam_certificates', [
            'userid' => 'privacy:metadata:certificates:userid',
            'certificatecode' => 'privacy:metadata:certificates:certificatecode',
        ], 'privacy:metadata:certificates');
        return $collection;
    }
}
