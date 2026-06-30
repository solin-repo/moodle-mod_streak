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

namespace mod_streak\task;

use mod_streak\local\evaluator;
use mod_streak\local\reminder;

/**
 * Scheduled task: roll over closed periods, freeze ended streaks, and fire reminders.
 *
 * Streams state rows with a recordset (no all-in-memory load). The per-user evaluation
 * keeps the leaderboard's committed values fresh even for learners who never log in.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rollover_task extends \core\task\scheduled_task {

    /**
     * Task name shown in the admin UI.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:rollover', 'mod_streak');
    }

    /**
     * Run the roll-over and reminders.
     */
    public function execute() {
        global $DB;

        $now = time();
        $streaks = $DB->get_recordset('streak');
        foreach ($streaks as $streak) {
            // Resolve the lifecycle end date once per streak: every learner of this streak shares the
            // same course, so resolving it inside the per-learner loop would re-query the same course
            // row once per learner (an N+1). Pass the resolved value into apply_lifecycle() instead.
            $end = evaluator::resolved_end_date($streak);
            $states = $DB->get_recordset('streak_state', ['streakid' => $streak->id]);
            foreach ($states as $state) {
                // The row apply_lifecycle() returns is already current, so reuse it directly rather
                // than re-reading the same record from the database on every iteration.
                $updated = evaluator::apply_lifecycle($streak, $state, $now, $end);
                reminder::process($streak, $updated, $now);
            }
            $states->close();
        }
        $streaks->close();
    }
}
