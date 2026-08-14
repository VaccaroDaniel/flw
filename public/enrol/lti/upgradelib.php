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
 * This file contains functions used by upgrade and install.
 *
 * Because this is used during install it should not include additional files.
 *
 * @package   enrol_lti
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * This function checks if a private key has been generated for this enrolment instance.
 *
 * If the key does not exist it generates a new one. If the openssl
 * extension is not installed or configured properly it returns a warning message.
 *
 * @return string A warning message if a private key does not exist and cannot be generated.
 */
function enrol_lti_verify_private_key() {
    global $CFG;

    $name = 'lti_13_kid';
    $key = get_config('enrol_lti', $name);

    // If we already generated a valid key, no need to check.
    if (empty($key)) {
        // Create the private key.
        $kid = bin2hex(openssl_random_pseudo_bytes(10));
        set_config($name, $kid, 'enrol_lti');
        $config = array(
            "digest_alg" => "sha256",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        );
        if ($opensslconfig = enrol_lti_find_openssl_config()) {
            $config['config'] = $opensslconfig;
            if (!getenv('OPENSSL_CONF')) {
                putenv('OPENSSL_CONF=' . $opensslconfig);
            }
        }
        $res = @openssl_pkey_new($config);
        if ($res !== false) {
            @openssl_pkey_export($res, $privatekey, null, $config);
        }

        if (!empty($privatekey)) {
            set_config('lti_13_privatekey', $privatekey, 'enrol_lti');
        } else {
            return get_string('opensslconfiginvalid', 'enrol_lti');
        }
    }

    return '';
}

/**
 * Locate the bundled OpenSSL configuration used by the Windows FLW stack.
 *
 * @return string|null
 */
function enrol_lti_find_openssl_config(): ?string {
    global $CFG;

    $candidates = [];
    $default = ini_get('openssl.default_config');
    if (!empty($default)) {
        $candidates[] = $default;
    }
    if (!empty($CFG->dirroot)) {
        $serverroot = dirname($CFG->dirroot, 2);
        $candidates[] = $serverroot . '/apache/conf/openssl.cnf';
        $candidates[] = $serverroot . '/php/windowsXamppPhp/extras/ssl/openssl.cnf';
    }

    foreach ($candidates as $candidate) {
        if ($candidate && is_readable($candidate)) {
            return $candidate;
        }
    }

    return null;
}
