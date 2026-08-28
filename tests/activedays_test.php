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

use mod_streak\local\breaks;
use mod_streak\local\engine;

/**
 * The activedays mask: which weekdays count toward a streak.
 *
 * A switched-off weekday behaves exactly like a holiday. Nothing is expected of the learner and not
 * practising cannot cost them the streak, which is what makes a working-week streak possible without
 * everyone losing it every Monday morning.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class activedays_test extends \advanced_testcase {
    /** Monday 2026-08-24 through Sunday 2026-08-30. */
    private const MON = 20260824;
    private const FRI = 20260828;
    private const SAT = 20260829;
    private const SUN = 20260830;

    /**
     * Weekdays resolve to the right position in the mask, Monday first.
     *
     * @covers \mod_streak\local\breaks::is_active_weekday
     */
    public function test_weekday_positions_are_iso_monday_first(): void {
        $this->assertTrue(breaks::is_active_weekday('1000000', self::MON), 'Monday is position 1');
        $this->assertFalse(breaks::is_active_weekday('1000000', self::SUN), 'Sunday is position 7');
        $this->assertTrue(breaks::is_active_weekday('0000001', self::SUN));
        $this->assertFalse(breaks::is_active_weekday('0000001', self::MON));
    }

    /**
     * The working-week mask switches the weekend off and leaves weekdays on.
     *
     * @covers \mod_streak\local\breaks::is_active_weekday
     */
    public function test_working_week_mask(): void {
        $workweek = '1111100';
        $this->assertTrue(breaks::is_active_weekday($workweek, self::FRI));
        $this->assertFalse(breaks::is_active_weekday($workweek, self::SAT));
        $this->assertFalse(breaks::is_active_weekday($workweek, self::SUN));
    }

    /**
     * A malformed or empty mask must never make a streak unearnable.
     *
     * @covers \mod_streak\local\breaks::is_active_weekday
     */
    public function test_a_broken_mask_falls_back_to_every_day(): void {
        foreach (['', 'nonsense', '11111', '111111111', '1111112'] as $bad) {
            $this->assertTrue(breaks::is_active_weekday($bad, self::SAT),
                "mask '{$bad}' should fall back to every day counting");
        }
    }

    /**
     * The default mask preserves the behaviour that existed before this feature.
     *
     * @covers \mod_streak\local\breaks::nonbreak_days
     */
    public function test_default_mask_counts_every_day(): void {
        $this->assertSame(7, breaks::nonbreak_days([], self::MON, self::SUN));
        $this->assertSame(7, breaks::nonbreak_days([], self::MON, self::SUN, breaks::ALL_DAYS));
    }

    /**
     * A working-week mask reduces the days that count in a week.
     *
     * @covers \mod_streak\local\breaks::nonbreak_days
     */
    public function test_working_week_reduces_the_days_that_count(): void {
        $this->assertSame(5, breaks::nonbreak_days([], self::MON, self::SUN, '1111100'));
    }

    /**
     * The mask and the breaks calendar are a union, and an overlap is not double-counted.
     *
     * @covers \mod_streak\local\breaks::is_off_day
     * @covers \mod_streak\local\breaks::nonbreak_days
     */
    public function test_mask_and_breaks_calendar_combine_as_a_union(): void {
        // Wednesday and Thursday are a holiday; the weekend is already off.
        $ranges = breaks::parse("2026-08-26, 2026-08-27");

        $this->assertTrue(breaks::is_off_day($ranges, '1111100', 20260826), 'holiday');
        $this->assertTrue(breaks::is_off_day($ranges, '1111100', self::SAT), 'weekend');
        $this->assertFalse(breaks::is_off_day($ranges, '1111100', self::MON), 'ordinary working day');

        // Mon, Tue, Fri remain.
        $this->assertSame(3, breaks::nonbreak_days($ranges, self::MON, self::SUN, '1111100'));

        // A holiday that lands on an already-off Saturday must not remove the day twice.
        $satonly = breaks::parse("2026-08-29, 2026-08-29");
        $this->assertSame(5, breaks::nonbreak_days($satonly, self::MON, self::SUN, '1111100'));
    }

    /**
     * A period made entirely of off days holds the streak: it neither grows nor breaks.
     *
     * @covers \mod_streak\local\engine::evaluate_period
     */
    public function test_an_all_off_period_holds_the_streak(): void {
        $state = (object) ['currentstreak' => 9, 'longeststreak' => 12, 'freezesavailable' => 0, 'freezesused' => 0];
        // Zero non-break days is what a weekend day looks like to a daily cadence.
        $result = engine::evaluate_period($state, 0, 0, 1, false, 4, 2);

        $this->assertSame(engine::OUTCOME_HOLD, $result->outcome);
        $this->assertSame(9, $result->currentstreak, 'an off day must not break the streak');
        $this->assertSame(0, $result->freezesused, 'an off day must not consume a freeze');
    }

    /**
     * Practising on an off day grows the streak only when work-to-win is switched on.
     *
     * @covers \mod_streak\local\engine::evaluate_period
     */
    public function test_work_to_win_on_an_off_day(): void {
        $state = (object) ['currentstreak' => 9, 'longeststreak' => 12, 'freezesavailable' => 0, 'freezesused' => 0];

        $off = engine::evaluate_period($state, 1, 0, 1, false, 4, 2);
        $this->assertSame(9, $off->currentstreak, 'without rewardbreaks an off day cannot grow the streak');
        $this->assertSame(engine::OUTCOME_HOLD, $off->outcome);

        $on = engine::evaluate_period($state, 1, 0, 1, true, 4, 2);
        $this->assertSame(10, $on->currentstreak, 'with rewardbreaks practice on an off day should count');
        $this->assertSame(engine::OUTCOME_INCREMENT, $on->outcome);
    }

    /**
     * Off days reduce the goal, so a learner is never asked for more days than the period offers.
     *
     * @covers \mod_streak\local\engine::evaluate_period
     */
    public function test_off_days_reduce_the_effective_goal(): void {
        $state = (object) ['currentstreak' => 3, 'longeststreak' => 3, 'freezesavailable' => 0, 'freezesused' => 0];

        // A weekly goal of 5, but a holiday week leaves only 3 days that count. Three days is enough.
        $result = engine::evaluate_period($state, 3, 3, 5, false, 4, 2);
        $this->assertSame(engine::OUTCOME_INCREMENT, $result->outcome,
            'meeting every available day should satisfy the goal');
        $this->assertSame(4, $result->currentstreak);

        // Falling short of the reduced goal still breaks it.
        $short = engine::evaluate_period($state, 2, 3, 5, false, 4, 2);
        $this->assertSame(engine::OUTCOME_RESET, $short->outcome);
    }

    /**
     * The evaluator reads the instance mask, and falls back safely when it is missing or malformed.
     *
     * @covers \mod_streak\local\evaluator::mask
     */
    public function test_evaluator_reads_the_instance_mask(): void {
        $this->assertSame('1111100', local\evaluator::mask((object) ['activedays' => '1111100']));
        $this->assertSame(breaks::ALL_DAYS, local\evaluator::mask((object) ['activedays' => 'rubbish']));
        $this->assertSame(breaks::ALL_DAYS, local\evaluator::mask((object) []));
    }

    /**
     * A working-week activity created from the site default carries the mask through to the engine.
     *
     * @covers ::streak_add_instance
     */
    public function test_a_working_week_activity_is_created_end_to_end(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('activedays', '1111100', 'mod_streak');
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);
        $streak = $DB->get_record('streak', ['id' => $cm->id], '*', MUST_EXIST);

        $this->assertSame('1111100', $streak->activedays);
        $this->assertSame(5, breaks::nonbreak_days(
            local\evaluator::ranges($streak), self::MON, self::SUN, local\evaluator::mask($streak)));
    }
}
