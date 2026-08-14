<?php
// PHPUnit tests for local_flwcupkp mastery engine.

namespace local_flwcupkp;

defined('MOODLE_INTERNAL') || die();

/**
 * Mastery engine tests.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_flwcupkp\local\mastery_engine::class)]
class mastery_engine_test extends \advanced_testcase {
    public function test_competency_requires_direct_evidence(): void {
        $events = [
            (object)[
                'normalizedscore' => 1.0,
                'confidence' => 0.9,
                'evidencestrength' => 'recognition',
                'timecreated' => time(),
            ],
        ];

        $state = \local_flwcupkp\local\mastery_engine::calculate('competency', $events);
        $this->assertSame('developing', $state['masterystate']);
    }

    public function test_direct_performance_can_achieve_competency(): void {
        $events = [
            (object)[
                'normalizedscore' => 0.88,
                'confidence' => 0.85,
                'evidencestrength' => 'independent_performance',
                'timecreated' => time(),
            ],
        ];

        $state = \local_flwcupkp\local\mastery_engine::calculate('competency', $events);
        $this->assertSame('achieved', $state['masterystate']);
    }
}
