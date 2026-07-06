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
 * Test/Behat generator for mod_streak.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Solin Streaks module generator.
 *
 * @package    mod_streak
 */
class mod_streak_generator extends testing_module_generator {
    /**
     * Create a Solin Streaks instance.
     *
     * @param array|stdClass|null $record Instance data.
     * @param array|null $options Generator options.
     * @return stdClass The created instance record (with ->cmid).
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;
        $defaults = [
            'name'          => 'Solin Streaks',
            'cadenceperiod' => 'daily',
            'cadencegoal'   => 1,
            'qualifymode'   => 'anycompletion',
            'enddatemode'   => 'none',
            'rewardbreaks'  => 0,
            'excludestaff'  => 1,
        ];
        foreach ($defaults as $key => $value) {
            if (!isset($record->{$key})) {
                $record->{$key} = $value;
            }
        }
        return parent::create_instance($record, (array) $options);
    }
}
