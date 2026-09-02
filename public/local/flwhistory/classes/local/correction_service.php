<?php
// Correction service for local_flwhistory.

namespace local_flwhistory\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Service boundary for correction and supersession links.
 */
class correction_service {
    /**
     * Record a generic supersession relation.
     *
     * @param string $recordtable New record table.
     * @param int $recordid New record id.
     * @param string $correctedtable Corrected record table.
     * @param int $correctedid Corrected record id.
     * @param string $reason Reason.
     * @param int|null $userid Actor id.
     * @return int Correction id.
     */
    public static function record_supersession(
        string $recordtable,
        int $recordid,
        string $correctedtable,
        int $correctedid,
        string $reason = '',
        ?int $userid = null
    ): int {
        return repository::record_correction([
            'recordtable' => $recordtable,
            'recordid' => $recordid,
            'correctedtable' => $correctedtable,
            'correctedid' => $correctedid,
            'correctiontype' => 'supersession',
            'reason' => $reason,
            'userid' => $userid,
        ]);
    }
}

