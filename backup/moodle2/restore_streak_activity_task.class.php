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
 * Restore activity task for mod_streak.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/streak/backup/moodle2/restore_streak_stepslib.php');

/**
 * Restore task that provides the steps to restore a Solin Streaks activity.
 *
 * @package    mod_streak
 */
class restore_streak_activity_task extends restore_activity_task {

    /**
     * No particular settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Define the restore steps.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_streak_activity_structure_step('streak_structure', 'streak.xml'));
    }

    /**
     * Define content areas to decode (none).
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [];
    }

    /**
     * Define decode rules (none).
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [];
    }

    /**
     * Define restore log rules (none).
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        return [];
    }
}
