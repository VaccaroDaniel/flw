<?php
// Behat steps for local_flwcupkp.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat page resolver for C-UP-KP pages.
 *
 * @package    local_flwcupkp
 * @category   test
 */
class behat_local_flwcupkp extends behat_base {
    /**
     * Convert page names to URLs for steps like
     * 'When I am on the "site" "local_flwcupkp > [page type]" page'.
     *
     * @param string $type
     * @param string $identifier
     * @return moodle_url
     * @throws Exception
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'admin':
            case 'index':
                return new moodle_url('/local/flwcupkp/index.php');
            case 'curriculum':
                return new moodle_url('/local/flwcupkp/curriculum.php');
            case 'setup':
            case 'unit setup':
                return new moodle_url('/local/flwcupkp/setup.php');
            case 'traceability':
            case 'trace':
                return new moodle_url('/local/flwcupkp/trace.php');
            case 'calibration':
                return new moodle_url('/local/flwcupkp/calibration.php');
            case 'calibration proposals':
            case 'threshold proposals':
                return new moodle_url('/local/flwcupkp/calibration_proposal.php');
            default:
                throw new Exception("Unrecognised local_flwcupkp page '{$type}'");
        }
    }
}
