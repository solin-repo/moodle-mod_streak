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
 * Streak progress: how far the learner is toward keeping the streak for the whole course.
 *
 * A Moodle course is finite, so the goal is the course's own length (start date to end date), not an
 * arbitrary infinite ladder. The progress ring fills toward that goal and only ever moves backward if
 * the streak itself breaks. Weekly markers sit along the ring as milestones. When a course has no end
 * date, it falls back to a rolling weekly ring. Pure rule (no rendering); see
 * docs/streaks-theming-contract.md.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class milestone {
    /** @var int Periods in the milestone cadence and the no-end-date fallback window (a week). */
    private const WEEK = 7;

    /**
     * The goal streak length (in cadence periods) for a streak: the number of periods the course runs,
     * or 0 when the course has no end date (caller then uses the weekly fallback).
     *
     * @param \stdClass $streak The streak instance.
     * @return int Goal in periods, or 0 if none.
     */
    public static function goal_periods(\stdClass $streak): int {
        global $DB;

        $end = evaluator::resolved_end_date($streak);
        if ($end <= 0) {
            return 0;
        }
        $start = (int) $DB->get_field('course', 'startdate', ['id' => $streak->course]);
        if ($start <= 0 || $end <= $start) {
            return 0;
        }
        $days = (int) floor(($end - $start) / DAYSECS);
        return max(1, (int) floor($days / self::period_days($streak->cadenceperiod)));
    }

    /**
     * Progress toward the goal.
     *
     * With a goal (course has an end date): the ring fills current/goal, capped at 100%, with weekly
     * marker positions along it. With goal 0: a rolling weekly window (the ring shows the current
     * week's progress and repeats each week).
     *
     * @param int $current The displayed streak length.
     * @param int $goal The goal in periods (0 = weekly fallback).
     * @return array{mode: string, goal: int, value: int, remaining: int, percent: int, markers: int[]}
     */
    public static function progress(int $current, int $goal): array {
        $current = max(0, $current);

        if ($goal > 0) {
            $value = min($current, $goal);
            $markers = [];
            for ($p = self::WEEK; $p < $goal; $p += self::WEEK) {
                $markers[] = (int) round($p / $goal * 100);
            }
            return [
                'mode'      => 'course',
                'goal'      => $goal,
                'value'     => $value,
                'remaining' => max(0, $goal - $current),
                'percent'   => (int) min(100, round($value / $goal * 100)),
                'markers'   => $markers,
            ];
        }

        // Weekly fallback: progress within the current rolling week (1..7, repeating).
        $value = $current === 0 ? 0 : (($current - 1) % self::WEEK) + 1;
        return [
            'mode'      => 'weekly',
            'goal'      => self::WEEK,
            'value'     => $value,
            'remaining' => self::WEEK - $value,
            'percent'   => (int) round($value / self::WEEK * 100),
            'markers'   => [],
        ];
    }

    /**
     * Days in one cadence period.
     *
     * @param string $period Cadence period.
     * @return int
     */
    private static function period_days(string $period): int {
        switch ($period) {
            case cadence::WEEKLY:
                return 7;
            case cadence::FORTNIGHTLY:
                return 14;
            case cadence::MONTHLY:
                return 30;
            default:
                return 1;
        }
    }
}
