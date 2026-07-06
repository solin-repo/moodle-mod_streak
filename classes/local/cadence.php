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
 * Period (cadence) math for Solin Streaks: given a moment, the window it falls in.
 *
 * Pure logic with no Moodle/global dependencies (timezone, week-start and anchor are
 * passed in) so it is fully unit-testable. Day arithmetic is done on calendar dates in
 * UTC to stay correct across DST transitions; only the period boundary timestamps are
 * computed in the learner's timezone.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cadence {
    /** @var string Daily cadence. */
    public const DAILY = 'daily';
    /** @var string Weekly cadence. */
    public const WEEKLY = 'weekly';
    /** @var string Fortnightly cadence. */
    public const FORTNIGHTLY = 'fortnightly';
    /** @var string Monthly cadence. */
    public const MONTHLY = 'monthly';

    /**
     * Return the period window containing $now.
     *
     * @param string $type One of the cadence constants.
     * @param int $now Reference timestamp (epoch seconds).
     * @param \DateTimeZone $tz The learner's timezone.
     * @param int $anchor Streak-start epoch, used to anchor rolling fortnightly/monthly windows.
     *                    Falls back to $now (the window starts today) when 0.
     * @param int $weekstart First day of week, 0=Sunday .. 6=Saturday (Moodle convention).
     * @return \stdClass {start, end (epoch, inclusive), startday, endday (YYYYMMDD), days}
     */
    public static function period(string $type, int $now, \DateTimeZone $tz, int $anchor = 0, int $weekstart = 1): \stdClass {
        $mid = self::local_midnight($now, $tz);

        switch ($type) {
            case self::WEEKLY:
                $dow = (int) $mid->format('w');
                $back = ($dow - $weekstart + 7) % 7;
                $start = $mid->modify('-' . $back . ' days');
                $endexcl = $start->modify('+7 days');
                break;

            case self::FORTNIGHTLY:
                $anchormid = self::local_midnight($anchor > 0 ? $anchor : $now, $tz);
                $since = self::days_between(self::ymd($anchormid), self::ymd($mid));
                if ($since < 0) {
                    $since = 0;
                }
                $windowindex = intdiv($since, 14);
                $start = $anchormid->modify('+' . ($windowindex * 14) . ' days');
                $endexcl = $start->modify('+14 days');
                break;

            case self::MONTHLY:
                // NOTE: PHP "+N months" overflows for end-of-month anchors (e.g. Jan 31 -> Mar 3).
                // v1 expects mid-month-safe anchors; clamping EOM anchors is a TODO.
                $anchormid = self::local_midnight($anchor > 0 ? $anchor : $now, $tz);
                $months = ((int) $mid->format('Y') - (int) $anchormid->format('Y')) * 12
                    + ((int) $mid->format('n') - (int) $anchormid->format('n'));
                if ($months < 0) {
                    $months = 0;
                }
                $start = $anchormid->modify('+' . $months . ' months');
                if ($start > $mid) {
                    $months--;
                    $start = $anchormid->modify('+' . $months . ' months');
                }
                $endexcl = $start->modify('+1 month');
                break;

            case self::DAILY:
            default:
                $start = $mid;
                $endexcl = $mid->modify('+1 day');
                break;
        }

        $end = $endexcl->modify('-1 second');

        $period = new \stdClass();
        $period->start = $start->getTimestamp();
        $period->end = $end->getTimestamp();
        $period->startday = self::ymd($start);
        $period->endday = self::ymd($end);
        $period->days = self::days_between(self::ymd($start), self::ymd($endexcl));
        return $period;
    }

    /**
     * Days remaining in the period, counting the day of $now itself.
     *
     * @param \stdClass $period A window from {@see self::period()}.
     * @param int $now Reference timestamp.
     * @param \DateTimeZone $tz The learner's timezone.
     * @return int Days from today through the period's last day, inclusive (>= 1 inside the window).
     */
    public static function days_remaining(\stdClass $period, int $now, \DateTimeZone $tz): int {
        return self::days_between(self::day_number($now, $tz), $period->endday) + 1;
    }

    /**
     * The calendar day of a timestamp, as a YYYYMMDD integer in the given timezone.
     *
     * @param int $ts Epoch seconds.
     * @param \DateTimeZone $tz Timezone.
     * @return int YYYYMMDD.
     */
    public static function day_number(int $ts, \DateTimeZone $tz): int {
        return (int) (new \DateTimeImmutable('@' . $ts))->setTimezone($tz)->format('Ymd');
    }

    /**
     * Midnight (start of day) of a timestamp in the given timezone.
     *
     * @param int $ts Epoch seconds.
     * @param \DateTimeZone $tz Timezone.
     * @return \DateTimeImmutable
     */
    private static function local_midnight(int $ts, \DateTimeZone $tz): \DateTimeImmutable {
        return (new \DateTimeImmutable('@' . $ts))->setTimezone($tz)->setTime(0, 0, 0);
    }

    /**
     * YYYYMMDD integer for a DateTimeImmutable.
     *
     * @param \DateTimeImmutable $date Date.
     * @return int YYYYMMDD.
     */
    private static function ymd(\DateTimeImmutable $date): int {
        return (int) $date->format('Ymd');
    }

    /**
     * Whole calendar days from date A to date B (both YYYYMMDD), DST-safe (computed in UTC).
     *
     * @param int $daya Start date YYYYMMDD.
     * @param int $dayb End date YYYYMMDD.
     * @return int Day count (B - A); negative if B precedes A.
     */
    private static function days_between(int $daya, int $dayb): int {
        $utc = new \DateTimeZone('UTC');
        $a = \DateTimeImmutable::createFromFormat('!Ymd', (string) $daya, $utc);
        $b = \DateTimeImmutable::createFromFormat('!Ymd', (string) $dayb, $utc);
        $diff = $a->diff($b);
        return (int) $diff->days * ($diff->invert ? -1 : 1);
    }
}
