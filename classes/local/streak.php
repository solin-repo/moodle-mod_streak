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
 * Repository / accessor for Solin Streaks activity instances (the streak table).
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class streak {

    /** @var array Per-request memo of course id => streak record (or null). */
    private static array $coursememo = [];

    /**
     * The (single) Solin Streaks instance in a course, or null if none.
     *
     * @param int $courseid Course id.
     * @return \stdClass|null
     */
    public static function for_course(int $courseid): ?\stdClass {
        global $DB;
        if (!array_key_exists($courseid, self::$coursememo)) {
            $record = $DB->get_record('streak', ['course' => $courseid], '*', IGNORE_MULTIPLE);
            self::$coursememo[$courseid] = $record ?: null;
        }
        return self::$coursememo[$courseid];
    }

    /**
     * Fetch an instance by id.
     *
     * @param int $streakid Instance id.
     * @return \stdClass
     */
    public static function instance(int $streakid): \stdClass {
        global $DB;
        return $DB->get_record('streak', ['id' => $streakid], '*', MUST_EXIST);
    }

    /**
     * Clear the per-request memo (after add/update/delete).
     */
    public static function reset_memo(): void {
        self::$coursememo = [];
    }

    /**
     * The site week-start day (0=Sunday .. 6=Saturday).
     *
     * @return int
     */
    public static function weekstart(): int {
        global $CFG;
        return isset($CFG->calendar_startwday) ? (int) $CFG->calendar_startwday : 1;
    }
}
