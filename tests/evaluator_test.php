<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_streak;

use mod_streak\local\evaluator;
use mod_streak\local\state;

/**
 * Integration tests for the streak evaluator (crediting + roll-over + display).
 *
 * @package    mod_streak
 * @covers     \mod_streak\local\evaluator
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class evaluator_test extends \advanced_testcase {
    /** @var int A UTC user's id, for predictable day boundaries. */
    private int $userid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        global $CFG;
        $CFG->calendar_startwday = 1; // Monday, for the weekly case.
        $this->userid = (int) $this->getDataGenerator()->create_user(['timezone' => 'UTC'])->id;
    }

    /**
     * Create a streak instance.
     *
     * @param string $period Cadence period.
     * @param int $goal Goal days per period.
     * @return \stdClass The streak record.
     */
    private function make_streak(string $period = 'daily', int $goal = 1): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $id = $DB->insert_record('streak', (object) [
            'course'        => $course->id,
            'name'          => 'Test',
            'intro'         => '',
            'introformat'   => 0,
            'cadenceperiod' => $period,
            'cadencegoal'   => $goal,
            'freezerate'    => 4,
            'freezecap'     => 2,
            'rewardbreaks'  => 0,
            'breakscalendar' => '',
            'timemodified'  => time(),
        ]);
        return $DB->get_record('streak', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Epoch for a UTC datetime string.
     *
     * @param string $datetime e.g. '2026-06-01 10:00'.
     * @return int
     */
    private function ts(string $datetime): int {
        return (new \DateTimeImmutable($datetime, new \DateTimeZone('UTC')))->getTimestamp();
    }

    /**
     * Reload the learner's state.
     *
     * @param int $streakid Streak id.
     * @return \stdClass
     */
    private function reload(int $streakid): \stdClass {
        return state::get_or_create($streakid, $this->userid);
    }

    public function test_daily_consecutive_days_grow_streak(): void {
        $s = $this->make_streak('daily');

        evaluator::credit($s, $this->userid, $this->ts('2026-06-01 10:00'));
        // Period not closed yet: committed 0, but display shows today's met day.
        $state = $this->reload($s->id);
        $this->assertSame(0, (int) $state->currentstreak);
        $this->assertSame(1, evaluator::display_streak($s, $state, $this->ts('2026-06-01 12:00')));

        evaluator::credit($s, $this->userid, $this->ts('2026-06-02 10:00'));
        $state = $this->reload($s->id);
        $this->assertSame(1, (int) $state->currentstreak); // Day 1 closed and counted.
        $this->assertSame(2, evaluator::display_streak($s, $state, $this->ts('2026-06-02 12:00')));

        evaluator::credit($s, $this->userid, $this->ts('2026-06-03 10:00'));
        $state = $this->reload($s->id);
        $this->assertSame(2, (int) $state->currentstreak);
        $this->assertSame(3, evaluator::display_streak($s, $state, $this->ts('2026-06-03 12:00')));
    }

    public function test_gap_without_freeze_resets(): void {
        $s = $this->make_streak('daily');
        evaluator::credit($s, $this->userid, $this->ts('2026-06-01 10:00'));
        evaluator::credit($s, $this->userid, $this->ts('2026-06-02 10:00'));

        // Jump to the 5th with no activity on the 3rd/4th: those periods reset the streak.
        $state = $this->reload($s->id);
        $display = evaluator::display_streak($s, $state, $this->ts('2026-06-05 12:00'));
        $state = $this->reload($s->id);
        $this->assertSame(0, (int) $state->currentstreak);
        $this->assertSame(0, $display);
    }

    public function test_freeze_accrues_then_saves_a_missed_day(): void {
        $s = $this->make_streak('daily');
        // Five consecutive days: streak commits up to 4, and reaching 4 accrues one freeze.
        foreach (['01', '02', '03', '04', '05'] as $d) {
            evaluator::credit($s, $this->userid, $this->ts("2026-06-{$d} 10:00"));
        }
        $state = $this->reload($s->id);
        $this->assertSame(4, (int) $state->currentstreak);
        $this->assertSame(1, (int) $state->freezesavailable);

        // Skip the 6th, then act on the 7th: the freeze should rescue the streak.
        evaluator::credit($s, $this->userid, $this->ts('2026-06-07 10:00'));
        $state = $this->reload($s->id);
        $this->assertSame(5, (int) $state->currentstreak); // 5th closed (+1), 6th frozen (held).
        $this->assertSame(0, (int) $state->freezesavailable);
        $this->assertSame(1, (int) $state->freezesused);
    }

    public function test_weekly_goal_rolls_over(): void {
        $s = $this->make_streak('weekly', 2);
        // Week of Mon 2026-06-15: two qualifying days meet the goal of 2.
        evaluator::credit($s, $this->userid, $this->ts('2026-06-15 10:00'));
        evaluator::credit($s, $this->userid, $this->ts('2026-06-17 10:00'));

        // Into the next week: the first week closes as a success.
        $state = $this->reload($s->id);
        evaluator::credit($s, $this->userid, $this->ts('2026-06-22 10:00'));
        $state = $this->reload($s->id);
        $this->assertSame(1, (int) $state->currentstreak);
    }

    public function test_displayed_rule(): void {
        // The open period not yet met: the committed value stands.
        $this->assertSame(5, evaluator::displayed(5, 0, 1));
        // Daily goal met: the open period counts provisionally (+1).
        $this->assertSame(6, evaluator::displayed(5, 1, 1));
        // A multi-day goal: only counts once the goal is reached.
        $this->assertSame(5, evaluator::displayed(5, 2, 3));
        $this->assertSame(6, evaluator::displayed(5, 3, 3));
        // A zero effective goal (e.g. a fully-break period) never adds the open period.
        $this->assertSame(5, evaluator::displayed(5, 4, 0));
    }
}
