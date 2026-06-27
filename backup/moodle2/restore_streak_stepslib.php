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

/**
 * Restore structure step for mod_streak.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the restore structure for a Solin Streaks activity.
 *
 * @package    mod_streak
 */
class restore_streak_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define the restore paths.
     *
     * @return mixed
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('streak', '/activity/streak');
        if ($userinfo) {
            $paths[] = new restore_path_element('streak_state', '/activity/streak/states/state');
            $paths[] = new restore_path_element('streak_day', '/activity/streak/days/day');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the streak instance.
     *
     * @param array $data The instance data.
     */
    protected function process_streak($data) {
        global $DB;
        $data = (object) $data;
        $data->course = $this->get_courseid();
        $newid = $DB->insert_record('streak', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restore a per-user state row.
     *
     * @param array $data The state data.
     */
    protected function process_streak_state($data) {
        global $DB;
        $data = (object) $data;
        $data->streakid = $this->get_new_parentid('streak');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $DB->insert_record('streak_state', $data);
    }

    /**
     * Restore a ledger day row.
     *
     * @param array $data The day data.
     */
    protected function process_streak_day($data) {
        global $DB;
        $data = (object) $data;
        $data->streakid = $this->get_new_parentid('streak');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $DB->insert_record('streak_day', $data);
    }
}
