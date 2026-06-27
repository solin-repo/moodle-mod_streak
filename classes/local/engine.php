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

namespace mod_streak\local;

/**
 * Streak evaluation logic: what happens to a streak when a period closes.
 *
 * Pure decision logic (no DB, no globals): callers compute the period's met-days,
 * total days and non-break days (via {@see daily_ledger}, {@see cadence} and breaks),
 * then ask the engine for the resulting state. This keeps the rules fully unit-testable.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class engine {

    /** @var string The period goal was met: streak grows. */
    public const OUTCOME_INCREMENT = 'increment';
    /** @var string Period failed but a freeze was spent: streak preserved. */
    public const OUTCOME_FREEZE = 'freeze';
    /** @var string Period failed with no freeze: streak resets. */
    public const OUTCOME_RESET = 'reset';
    /** @var string Period was entirely break and not won: streak unchanged. */
    public const OUTCOME_HOLD = 'hold';

    /**
     * Evaluate a single closed period and return the resulting streak figures.
     *
     * @param \stdClass $state Snapshot with currentstreak, longeststreak, freezesavailable, freezesused.
     * @param int $metdays Distinct qualifying days credited within the period.
     * @param int $nonbreakdays Non-break days available in the period (breaks subtracted).
     * @param int $goal Configured qualifying-days goal for the period.
     * @param bool $rewardbreaks Whether "reward practice during breaks" is on (work-to-win).
     * @param int $freezerate Grant one freeze per N successful periods (0 = never).
     * @param int $freezecap Maximum freezes a learner may bank.
     * @return \stdClass {currentstreak, longeststreak, freezesavailable, freezesused, outcome}
     */
    public static function evaluate_period(\stdClass $state, int $metdays, int $nonbreakdays, int $goal,
            bool $rewardbreaks, int $freezerate, int $freezecap): \stdClass {

        $result = (object) [
            'currentstreak'    => (int) $state->currentstreak,
            'longeststreak'    => (int) $state->longeststreak,
            'freezesavailable' => (int) $state->freezesavailable,
            'freezesused'      => (int) $state->freezesused,
            'outcome'          => self::OUTCOME_HOLD,
        ];

        $effectivegoal = min($goal, max(0, $nonbreakdays));

        if ($nonbreakdays === 0) {
            // Entire period was a break. Resting holds; only work-to-win can grow it (FR-BREAK-5/8).
            if ($rewardbreaks && $metdays >= $goal && $goal > 0) {
                self::increment($result, $freezerate, $freezecap);
            }
            return $result;
        }

        if ($metdays >= $effectivegoal) {
            self::increment($result, $freezerate, $freezecap);
            return $result;
        }

        if ($result->freezesavailable > 0) {
            // Goal missed but a freeze forgives the whole period: streak preserved, not incremented.
            $result->freezesavailable--;
            $result->freezesused++;
            $result->outcome = self::OUTCOME_FREEZE;
            return $result;
        }

        // No tolerance left: the streak breaks.
        $result->currentstreak = 0;
        $result->outcome = self::OUTCOME_RESET;
        return $result;
    }

    /**
     * Grow the streak by one period and accrue a freeze if the cadence is hit.
     *
     * @param \stdClass $result Result object, mutated in place.
     * @param int $freezerate Grant one freeze per N successful periods (0 = never).
     * @param int $freezecap Maximum freezes a learner may bank.
     */
    private static function increment(\stdClass $result, int $freezerate, int $freezecap): void {
        $result->currentstreak++;
        if ($result->currentstreak > $result->longeststreak) {
            $result->longeststreak = $result->currentstreak;
        }
        if ($freezerate > 0 && ($result->currentstreak % $freezerate) === 0) {
            $result->freezesavailable = min($freezecap, $result->freezesavailable + 1);
        }
        $result->outcome = self::OUTCOME_INCREMENT;
    }
}
