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
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\mod_streak\local\evaluator::class)]
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

    public function test_nothing_happens_before_the_first_qualifying_action(): void {
        $s = $this->make_streak('daily');
        $state = $this->reload($s->id);
        $now = $this->ts('2026-06-10 12:00');

        $this->assertSame(0, evaluator::display_streak($s, $state, $now));
        $this->assertSame($state, evaluator::ensure_current($s, $state, $now));
        $this->assertNull(evaluator::reminder_status($s, $state, $now));
        $this->assertSame($state, evaluator::apply_lifecycle($s, $state, $now, 0));
    }

    public function test_a_frozen_streak_takes_no_more_credit(): void {
        global $DB;
        $s = $this->make_streak('daily');
        evaluator::credit($s, $this->userid, $this->ts('2026-06-01 10:00'));
        $state = $this->reload($s->id);
        $state->frozenfinal = 1;
        state::save($state);

        evaluator::credit($s, $this->userid, $this->ts('2026-06-02 10:00'));

        $this->assertFalse($DB->record_exists('streak_day', ['streakid' => $s->id, 'day' => 20260602]));
        $state = $this->reload($s->id);
        $this->assertSame(20260601, (int) $state->lastqualifyingday);
        // Nor does it roll over any more periods.
        $this->assertSame($state, evaluator::ensure_current($s, $state, $this->ts('2026-06-09 10:00')));
    }

    public function test_lifecycle_resolves_the_end_date_itself_when_not_given(): void {
        global $DB;
        $s = $this->make_streak('daily');
        $DB->update_record('streak', (object) [
            'id' => $s->id,
            'enddatemode' => 'custom',
            'customenddate' => $this->ts('2026-06-03 00:00'),
        ]);
        $s = $DB->get_record('streak', ['id' => $s->id], '*', MUST_EXIST);
        evaluator::credit($s, $this->userid, $this->ts('2026-06-01 10:00'));
        evaluator::credit($s, $this->userid, $this->ts('2026-06-02 10:00'));

        $state = evaluator::apply_lifecycle($s, $this->reload($s->id), $this->ts('2026-06-05 10:00'));

        $this->assertSame(1, (int) $state->frozenfinal);
        $this->assertSame(1, (int) $this->reload($s->id)->frozenfinal);
    }

    public function test_reminder_is_not_due_once_the_goal_is_met(): void {
        $s = $this->make_streak('weekly', 2);
        evaluator::credit($s, $this->userid, $this->ts('2026-06-15 10:00'));
        evaluator::credit($s, $this->userid, $this->ts('2026-06-16 10:00'));

        $this->assertNull(evaluator::reminder_status($s, $this->reload($s->id), $this->ts('2026-06-20 19:00')));
    }

    public function test_reminder_is_not_due_after_acting_today(): void {
        $s = $this->make_streak('weekly', 7);
        evaluator::credit($s, $this->userid, $this->ts('2026-06-15 10:00'));

        // Every day of the week is needed, so Monday is make-or-break, but the learner already acted.
        $this->assertNull(evaluator::reminder_status($s, $this->reload($s->id), $this->ts('2026-06-15 19:00')));
    }

    public function test_reminder_is_not_due_while_there_is_slack(): void {
        $s = $this->make_streak('weekly', 1);
        evaluator::credit($s, $this->userid, $this->ts('2026-06-15 10:00'));

        // Monday of the next week: one day needed, seven left.
        $this->assertNull(evaluator::reminder_status($s, $this->reload($s->id), $this->ts('2026-06-22 19:00')));
        // Sunday of that week: the last chance.
        $status = evaluator::reminder_status($s, $this->reload($s->id), $this->ts('2026-06-28 19:00'));
        $this->assertSame(1, $status->needed);
        $this->assertSame(1, $status->remaining);
        $this->assertFalse($status->isearly);
    }

    public function test_today_is_the_learners_local_day(): void {
        $tokyo = (int) $this->getDataGenerator()->create_user(['timezone' => 'Asia/Tokyo'])->id;
        $at = $this->ts('2026-06-15 20:00');

        $this->assertSame(20260615, evaluator::today($this->userid, $at));
        $this->assertSame(20260616, evaluator::today($tokyo, $at));
    }

    public function test_a_broken_breaks_calendar_counts_as_no_breaks(): void {
        $s = $this->make_streak('daily');
        set_config('breakscalendar', "2026-06-01, 2026-06-07\nnot a date", 'mod_streak');

        $this->assertSame([], evaluator::ranges($s));

        set_config('breakscalendar', '2026-06-01, 2026-06-07', 'mod_streak');
        $this->assertCount(1, evaluator::ranges($s));
    }

    public function test_the_display_of_an_unstarted_state_is_zero(): void {
        // The public paths only reach recompute_display() for a started streak; its guard for
        // an unstarted state is exercised directly so a future caller cannot inherit a stale value.
        $s = $this->make_streak('daily');
        $state = $this->reload($s->id);
        $state->displaystreak = 5;

        $method = new \ReflectionMethod(evaluator::class, 'recompute_display');
        $method->invoke(null, $s, $state);

        $this->assertSame(0, $state->displaystreak);
    }
}
