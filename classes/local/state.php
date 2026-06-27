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
 * Repository for per-learner streak state (the streak_state table).
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class state {

    /**
     * Fetch the learner's state row for a streak instance, creating a fresh
     * "not started" row (all zero, streakstart = 0) if none exists.
     *
     * @param int $streakid The streak activity instance id.
     * @param int $userid The user id.
     * @return \stdClass The streak_state record.
     */
    public static function get_or_create(int $streakid, int $userid): \stdClass {
        global $DB;

        $existing = $DB->get_record('streak_state', ['streakid' => $streakid, 'userid' => $userid]);
        if ($existing) {
            return $existing;
        }

        $record = (object) [
            'streakid'     => $streakid,
            'userid'       => $userid,
            'timemodified' => time(),
        ];
        // Remaining columns are NOT NULL with sensible DB defaults (0 = "not started").
        $record->id = $DB->insert_record('streak_state', $record);
        return $DB->get_record('streak_state', ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * Persist a streak_state record (stamps timemodified).
     *
     * @param \stdClass $state The record to save; must carry a valid id.
     */
    public static function save(\stdClass $state): void {
        global $DB;

        $state->timemodified = time();
        $DB->update_record('streak_state', $state);
    }

    /**
     * Whether the learner has started a streak in this instance.
     *
     * @param \stdClass $state A streak_state record.
     * @return bool
     */
    public static function has_started(\stdClass $state): bool {
        return (int) $state->streakstart > 0;
    }
}
