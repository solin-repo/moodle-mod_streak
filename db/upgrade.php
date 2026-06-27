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
 * Database upgrade steps for mod_streak.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Apply mod_streak database upgrades.
 *
 * @param int $oldversion The currently installed plugin version.
 * @return bool
 */
function xmldb_streak_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026061808) {
        // Cached displayed streak, so the headline and the leaderboard read the same value.
        $table = new xmldb_table('streak_state');
        $field = new xmldb_field('displaystreak', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
            'currentstreak');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Backfill: committed periods plus the open one when its goal is met, per streak so the goal is
        // correct (daily = 1, else the configured per-period goal). Column arithmetic cannot be expressed
        // via the typed DML API, so a parameterised UPDATE is used. The break-day goal reduction is not
        // applied in the backfill; the next credit/roll-over refreshes the value exactly.
        $streaks = $DB->get_recordset('streak', null, '', 'id, cadenceperiod, cadencegoal');
        foreach ($streaks as $streak) {
            $goal = ($streak->cadenceperiod === 'daily') ? 1 : max(1, (int) $streak->cadencegoal);
            $DB->execute(
                "UPDATE {streak_state}
                    SET displaystreak = currentstreak
                        + CASE WHEN currentperioddaysmet >= ? THEN 1 ELSE 0 END
                  WHERE streakid = ?",
                [$goal, $streak->id]);
        }
        $streaks->close();

        upgrade_mod_savepoint(true, 2026061808, 'streak');
    }

    return true;
}
