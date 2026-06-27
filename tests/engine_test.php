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

use mod_streak\local\engine;

/**
 * Unit tests for the streak period-evaluation engine.
 *
 * @package    mod_streak
 * @covers     \mod_streak\local\engine
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class engine_test extends \advanced_testcase {

    /**
     * Build a state snapshot.
     *
     * @param int $current Current streak.
     * @param int $longest Longest streak.
     * @param int $freezes Freezes available.
     * @param int $used Freezes used.
     * @return \stdClass
     */
    private function state(int $current, int $longest, int $freezes, int $used = 0): \stdClass {
        return (object) [
            'currentstreak'    => $current,
            'longeststreak'    => $longest,
            'freezesavailable' => $freezes,
            'freezesused'      => $used,
        ];
    }

    public function test_increment_on_goal_met(): void {
        $r = engine::evaluate_period($this->state(4, 4, 0), 3, 7, 3, false, 4, 2);
        $this->assertSame(engine::OUTCOME_INCREMENT, $r->outcome);
        $this->assertSame(5, $r->currentstreak);
        $this->assertSame(5, $r->longeststreak);
        $this->assertSame(0, $r->freezesavailable); // 5 % 4 != 0.
    }

    public function test_longest_streak_not_reduced(): void {
        // Current behind longest; an increment must not lower longest.
        $r = engine::evaluate_period($this->state(2, 9, 0), 3, 7, 3, false, 0, 2);
        $this->assertSame(3, $r->currentstreak);
        $this->assertSame(9, $r->longeststreak);
    }

    public function test_freeze_accrual_and_cap(): void {
        // Reaching streak 8 grants a freeze (8 % 4 == 0), up to the cap.
        $r = engine::evaluate_period($this->state(7, 7, 1), 3, 7, 3, false, 4, 2);
        $this->assertSame(8, $r->currentstreak);
        $this->assertSame(2, $r->freezesavailable);

        // Reaching 12 would grant another, but the cap of 2 holds.
        $r2 = engine::evaluate_period($this->state(11, 11, 2), 3, 7, 3, false, 4, 2);
        $this->assertSame(12, $r2->currentstreak);
        $this->assertSame(2, $r2->freezesavailable);
    }

    public function test_freeze_consumed_on_miss(): void {
        $r = engine::evaluate_period($this->state(5, 6, 1), 1, 7, 3, false, 4, 2);
        $this->assertSame(engine::OUTCOME_FREEZE, $r->outcome);
        $this->assertSame(5, $r->currentstreak);   // Preserved, not incremented.
        $this->assertSame(0, $r->freezesavailable);
        $this->assertSame(1, $r->freezesused);
    }

    public function test_reset_when_no_freeze(): void {
        $r = engine::evaluate_period($this->state(5, 6, 0), 1, 7, 3, false, 4, 2);
        $this->assertSame(engine::OUTCOME_RESET, $r->outcome);
        $this->assertSame(0, $r->currentstreak);
        $this->assertSame(6, $r->longeststreak);
    }

    public function test_effective_goal_prorates_with_breaks(): void {
        // Goal 3, but only 2 non-break days available -> effective goal 2; meeting 2 succeeds.
        $r = engine::evaluate_period($this->state(2, 2, 0), 2, 2, 3, false, 0, 2);
        $this->assertSame(engine::OUTCOME_INCREMENT, $r->outcome);
        $this->assertSame(3, $r->currentstreak);
    }

    public function test_all_break_period_holds(): void {
        // Entire period is a break, reward off: streak holds, no freeze spent.
        $r = engine::evaluate_period($this->state(5, 5, 1), 0, 0, 3, false, 4, 2);
        $this->assertSame(engine::OUTCOME_HOLD, $r->outcome);
        $this->assertSame(5, $r->currentstreak);
        $this->assertSame(1, $r->freezesavailable);
    }

    public function test_all_break_work_to_win(): void {
        // Reward on + goal met during the break -> grows; not met -> holds.
        $win = engine::evaluate_period($this->state(5, 5, 0), 3, 0, 3, true, 0, 2);
        $this->assertSame(engine::OUTCOME_INCREMENT, $win->outcome);
        $this->assertSame(6, $win->currentstreak);

        $hold = engine::evaluate_period($this->state(5, 5, 0), 2, 0, 3, true, 0, 2);
        $this->assertSame(engine::OUTCOME_HOLD, $hold->outcome);
        $this->assertSame(5, $hold->currentstreak);
    }
}
