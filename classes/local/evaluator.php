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
 * The streak service: ties cadence + ledger + breaks + engine together.
 *
 * Credits qualifying days, performs lazy period roll-over (so values are correct
 * between cron runs), and computes the displayed streak.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class evaluator {

    /** @var array Per-request memo of userid => DateTimeZone. */
    private static array $tzmemo = [];

    /**
     * Credit a qualifying action for a learner at a given time.
     *
     * @param \stdClass $streak The streak instance record.
     * @param int $userid The user id.
     * @param int $when Epoch of the qualifying action.
     */
    public static function credit(\stdClass $streak, int $userid, int $when): void {
        $tz = self::user_tz($userid);
        $state = state::get_or_create($streak->id, $userid);

        if ((int) $state->frozenfinal === 1) {
            return; // Lifecycle ended; no more crediting.
        }

        if ((int) $state->streakstart === 0) {
            // First qualifying action starts the streak and anchors the first period.
            $period = cadence::period($streak->cadenceperiod, $when, $tz, $when, streak::weekstart());
            $state->streakstart = $period->start;
            $state->currentperiodstart = $period->start;
        } else {
            $state = self::ensure_current($streak, $state, $when);
        }

        $day = cadence::day_number($when, $tz);
        daily_ledger::credit($streak->id, $userid, $day, $when);

        $period = self::current_period($streak, $state, $tz);
        $state->currentperioddaysmet =
            daily_ledger::count_days_in_range($streak->id, $userid, $period->startday, $period->endday);
        if ($day > (int) $state->lastqualifyingday) {
            $state->lastqualifyingday = $day;
        }
        self::recompute_display($streak, $state);
        state::save($state);
    }

    /**
     * Close any periods that have elapsed up to $now, applying the engine rules.
     *
     * @param \stdClass $streak The streak instance.
     * @param \stdClass $state The learner's state.
     * @param int $now Reference time.
     * @return \stdClass The (possibly updated) state.
     */
    public static function ensure_current(\stdClass $streak, \stdClass $state, int $now): \stdClass {
        if ((int) $state->streakstart === 0 || (int) $state->frozenfinal === 1) {
            return $state;
        }

        $tz = self::user_tz((int) $state->userid);
        $ranges = self::ranges($streak);
        $anchor = (int) $state->streakstart;
        $weekstart = streak::weekstart();
        $changed = false;
        $guard = 0;

        while ($guard++ < 1000) {
            $period = cadence::period($streak->cadenceperiod, (int) $state->currentperiodstart, $tz, $anchor, $weekstart);
            if ($now <= $period->end) {
                break; // Still inside the open period.
            }

            $met = daily_ledger::count_days_in_range($streak->id, (int) $state->userid,
                $period->startday, $period->endday);
            $nonbreak = breaks::nonbreak_days($ranges, $period->startday, $period->endday);
            $result = engine::evaluate_period($state, $met, $nonbreak, self::goal($streak),
                (bool) $streak->rewardbreaks, (int) $streak->freezerate, (int) $streak->freezecap);

            $state->currentstreak = $result->currentstreak;
            $state->longeststreak = $result->longeststreak;
            $state->freezesavailable = $result->freezesavailable;
            $state->freezesused = $result->freezesused;

            $next = cadence::period($streak->cadenceperiod, $period->end + 1, $tz, $anchor, $weekstart);
            $state->currentperiodstart = $next->start;
            if ($result->outcome === engine::OUTCOME_RESET) {
                // Re-anchor rolling cadences to the restart.
                $state->streakstart = $next->start;
                $anchor = $next->start;
            }
            $state->currentperioddaysmet = daily_ledger::count_days_in_range($streak->id,
                (int) $state->userid, $next->startday, $next->endday);
            $changed = true;
        }

        if ($changed) {
            self::recompute_display($streak, $state);
            state::save($state);
        }
        return $state;
    }

    /**
     * The streak value to display: committed periods, plus the open one if its goal is met.
     *
     * @param \stdClass $streak The streak instance.
     * @param \stdClass $state The learner's state.
     * @param int $now Reference time.
     * @return int
     */
    public static function display_streak(\stdClass $streak, \stdClass $state, int $now): int {
        if ((int) $state->streakstart === 0) {
            return 0;
        }
        $state = self::ensure_current($streak, $state, $now);
        return (int) $state->displaystreak;
    }

    /**
     * Recompute the cached displayed streak on the state object (the caller persists it via state::save).
     *
     * This is the single place the displayed value is derived. Both the widget headline (which reads
     * the value back after ensure_current()) and the leaderboard (which selects the stored column)
     * read streak_state.displaystreak, so the number a learner sees at the top and the number on the
     * board can never disagree. It is recomputed on every state mutation that can change it: crediting
     * a qualifying action, rolling a period over, and lifecycle freezing.
     *
     * @param \stdClass $streak The streak instance.
     * @param \stdClass $state The learner's state (mutated: displaystreak is set).
     */
    private static function recompute_display(\stdClass $streak, \stdClass $state): void {
        if ((int) $state->streakstart === 0) {
            $state->displaystreak = 0;
            return;
        }
        $tz = self::user_tz((int) $state->userid);
        $period = self::current_period($streak, $state, $tz);
        $met = daily_ledger::count_days_in_range($streak->id, (int) $state->userid,
            $period->startday, $period->endday);
        $nonbreak = breaks::nonbreak_days(self::ranges($streak), $period->startday, $period->endday);
        $effectivegoal = min(self::goal($streak), max(0, $nonbreak));
        $state->displaystreak = self::displayed((int) $state->currentstreak, $met, $effectivegoal);
    }

    /**
     * The displayed streak from already-known figures: committed periods, plus the open one when its
     * goal is met. The single definition of the rule, used by recompute_display() (and unit-tested).
     *
     * @param int $committed The committed streak (streak_state.currentstreak).
     * @param int $met Days met in the current open period.
     * @param int $effectivegoal The period goal that must be met to count the open period.
     * @return int
     */
    public static function displayed(int $committed, int $met, int $effectivegoal): int {
        if ($effectivegoal > 0 && $met >= $effectivegoal) {
            return $committed + 1;
        }
        return $committed;
    }

    /**
     * Whether an at-risk reminder is due now, with the figures for the message.
     *
     * @param \stdClass $streak The streak instance.
     * @param \stdClass $state The learner's state.
     * @param int $now Reference time.
     * @return \stdClass|null {needed, remaining} when the make-or-break reminder is due, else null.
     */
    public static function reminder_status(\stdClass $streak, \stdClass $state, int $now): ?\stdClass {
        if ((int) $state->streakstart === 0 || (int) $state->frozenfinal === 1) {
            return null;
        }
        $state = self::ensure_current($streak, $state, $now);
        $tz = self::user_tz((int) $state->userid);
        $ranges = self::ranges($streak);
        $today = cadence::day_number($now, $tz);
        if (breaks::day_in_ranges($ranges, $today)) {
            return null; // No reminders during an active break.
        }
        $period = self::current_period($streak, $state, $tz);
        $met = daily_ledger::count_days_in_range($streak->id, (int) $state->userid, $period->startday, $period->endday);
        $nonbreak = breaks::nonbreak_days($ranges, $period->startday, $period->endday);
        $needed = min(self::goal($streak), max(0, $nonbreak)) - $met;
        if ($needed <= 0) {
            return null; // Goal already met this period.
        }
        if (daily_ledger::count_days_in_range($streak->id, (int) $state->userid, $today, $today) > 0) {
            return null; // Already acted today.
        }
        $remaining = cadence::days_remaining($period, $now, $tz);
        if ($remaining !== $needed) {
            return null; // Not the make-or-break day.
        }
        return (object) ['needed' => $needed, 'remaining' => $remaining];
    }

    /**
     * The learner's local day (YYYYMMDD) at a given time.
     *
     * @param int $userid The user id.
     * @param int $now Reference time.
     * @return int
     */
    public static function today(int $userid, int $now): int {
        return cadence::day_number($now, self::user_tz($userid));
    }

    /**
     * Freeze the streak at its lifecycle end date (FR-LIFE): close periods up to the end and lock.
     *
     * @param \stdClass $streak The streak instance.
     * @param \stdClass $state The learner's state.
     * @param int $now Reference time.
     * @return \stdClass The (possibly frozen) state.
     */
    public static function apply_lifecycle(\stdClass $streak, \stdClass $state, int $now): \stdClass {
        if ((int) $state->frozenfinal === 1 || (int) $state->streakstart === 0) {
            return $state;
        }
        $end = self::resolved_end_date($streak);
        if ($end > 0 && $now >= $end) {
            $state = self::ensure_current($streak, $state, $end);
            $state->frozenfinal = 1;
            self::recompute_display($streak, $state);
            state::save($state);
        }
        return $state;
    }

    /**
     * The resolved lifecycle end date (epoch), or 0 for none.
     *
     * @param \stdClass $streak The streak instance.
     * @return int
     */
    public static function resolved_end_date(\stdClass $streak): int {
        global $DB;
        if ($streak->enddatemode === 'none') {
            return 0;
        }
        if ($streak->enddatemode === 'custom') {
            return (int) $streak->customenddate;
        }
        return (int) $DB->get_field('course', 'enddate', ['id' => $streak->course]);
    }

    /**
     * The window currently open for a learner (anchored at streakstart).
     *
     * @param \stdClass $streak The streak instance.
     * @param \stdClass $state The learner's state.
     * @param \DateTimeZone $tz The learner's timezone.
     * @return \stdClass A cadence period.
     */
    private static function current_period(\stdClass $streak, \stdClass $state, \DateTimeZone $tz): \stdClass {
        $anchor = (int) $state->streakstart ?: (int) $state->currentperiodstart;
        return cadence::period($streak->cadenceperiod, (int) $state->currentperiodstart, $tz, $anchor, streak::weekstart());
    }

    /**
     * The effective per-period goal (Daily is always 1).
     *
     * @param \stdClass $streak The streak instance.
     * @return int
     */
    public static function goal(\stdClass $streak): int {
        if ($streak->cadenceperiod === cadence::DAILY) {
            return 1;
        }
        return max(1, (int) $streak->cadencegoal);
    }

    /**
     * Parsed effective break ranges = union(site calendar, course calendar).
     *
     * @param \stdClass $streak The streak instance.
     * @return array Parsed break ranges.
     */
    public static function ranges(\stdClass $streak): array {
        $sitecal = (string) get_config('mod_streak', 'breakscalendar');
        $coursecal = (string) ($streak->breakscalendar ?? '');
        $combined = trim($sitecal . "\n" . $coursecal);
        if ($combined === '') {
            return [];
        }
        try {
            return breaks::parse($combined);
        } catch (\invalid_parameter_exception $e) {
            // Misconfigured calendars must never break crediting; treat as no breaks.
            return [];
        }
    }

    /**
     * The learner's timezone (memoised; reads the user record, not an id-as-forced-tz).
     *
     * @param int $userid The user id.
     * @return \DateTimeZone
     */
    private static function user_tz(int $userid): \DateTimeZone {
        global $DB;
        if (!isset(self::$tzmemo[$userid])) {
            $user = $DB->get_record('user', ['id' => $userid], 'id, timezone', MUST_EXIST);
            self::$tzmemo[$userid] = \core_date::get_user_timezone_object($user);
        }
        return self::$tzmemo[$userid];
    }
}
