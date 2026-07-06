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
 * The credited-day ledger (the streak_day table): one row per learner-local
 * qualifying day, giving idempotent crediting and break-aware period counting.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class daily_ledger {
    /**
     * Credit a qualifying day, idempotently.
     *
     * Relies on the unique (streakid, userid, day) key and catches the duplicate-key
     * exception, so it is portable (no MySQL-only INSERT IGNORE) and race-safe against
     * two simultaneous events for the same day.
     *
     * @param int $streakid The streak activity instance id.
     * @param int $userid The user id.
     * @param int $day The learner-local calendar day, YYYYMMDD.
     * @param int|null $time Creation time (defaults to now).
     * @return bool True if newly credited, false if the day was already credited.
     */
    public static function credit(int $streakid, int $userid, int $day, ?int $time = null): bool {
        global $DB;

        $record = (object) [
            'streakid'    => $streakid,
            'userid'      => $userid,
            'day'         => $day,
            'timecreated' => $time ?? time(),
        ];

        try {
            $DB->insert_record('streak_day', $record);
            return true;
        } catch (\dml_write_exception $e) {
            // Unique-key violation: the day is already credited. Idempotent no-op.
            return false;
        }
    }

    /**
     * Count distinct credited days within an inclusive YYYYMMDD range.
     *
     * Each day is one row (unique key), so a row count is the distinct-day count.
     *
     * @param int $streakid The streak activity instance id.
     * @param int $userid The user id.
     * @param int $startday Range start, YYYYMMDD inclusive.
     * @param int $endday Range end, YYYYMMDD inclusive.
     * @return int Number of credited days in the range.
     */
    public static function count_days_in_range(int $streakid, int $userid, int $startday, int $endday): int {
        global $DB;

        $select = 'streakid = :streakid AND userid = :userid AND day >= :startday AND day <= :endday';
        return $DB->count_records_select('streak_day', $select, [
            'streakid' => $streakid,
            'userid'   => $userid,
            'startday' => $startday,
            'endday'   => $endday,
        ]);
    }
}
