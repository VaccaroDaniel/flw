<?php
// PHPUnit tests for local_flwhistory source identity.

namespace local_flwhistory;

defined('MOODLE_INTERNAL') || die();

/**
 * Source identity tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwhistory\local\source_identity::class)]
class source_identity_test extends \advanced_testcase {
    public function test_make_key_is_stable_and_cleaned(): void {
        $key = \local_flwhistory\local\source_identity::make_key(
            'moodle',
            'quiz attempt',
            'Attempt #9',
            '20260827',
            '\mod_quiz\event\attempt_submitted'
        );

        $this->assertSame('moodle:quiz-attempt:Attempt-9:20260827:mod_quiz-event-attempt_submitted', $key);
        $this->assertSame($key, \local_flwhistory\local\source_identity::make_key(
            'moodle',
            'quiz attempt',
            'Attempt #9',
            '20260827',
            '\mod_quiz\event\attempt_submitted'
        ));
    }

    public function test_long_key_is_shortened_with_hash(): void {
        $key = \local_flwhistory\local\source_identity::make_key(
            'moodle',
            'very_long_source_type',
            str_repeat('source-', 80),
            'version',
            'event'
        );

        $this->assertLessThanOrEqual(191, strlen($key));
        $this->assertStringStartsWith('moodle:very_long_source_type:', $key);
    }

    public function test_payload_hash_uses_stable_json_ordering(): void {
        $one = \local_flwhistory\local\source_identity::payload_hash(['b' => 2, 'a' => 1]);
        $two = \local_flwhistory\local\source_identity::payload_hash(['a' => 1, 'b' => 2]);

        $this->assertSame($one, $two);
    }

    public function test_source_identity_parts_are_required(): void {
        $this->expectException(\invalid_parameter_exception::class);
        \local_flwhistory\local\source_identity::make_key('moodle', '', '9');
    }
}

