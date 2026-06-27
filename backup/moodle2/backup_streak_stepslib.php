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
 * Backup structure step for mod_streak.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the backup structure for a Solin Streaks activity.
 *
 * @package    mod_streak
 */
class backup_streak_activity_structure_step extends backup_activity_structure_step {

    /**
     * Build the backup tree.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $streak = new backup_nested_element('streak', ['id'], [
            'name', 'intro', 'introformat', 'cadenceperiod', 'cadencegoal', 'qualifymode',
            'modfilterexclude', 'freezerate', 'freezecap', 'enddatemode', 'customenddate',
            'reminderhour', 'earlyheadsup', 'breakscalendar', 'rewardbreaks', 'excludestaff',
            'excluderoles', 'timemodified',
        ]);

        $states = new backup_nested_element('states');
        $state = new backup_nested_element('state', ['id'], [
            'userid', 'currentstreak', 'displaystreak', 'longeststreak', 'currentperiodstart',
            'currentperioddaysmet',
            'lastqualifyingday', 'lastreminderday', 'lastearlyheadsup', 'lastcourseprogress',
            'freezesavailable', 'freezesused', 'streakstart', 'optout', 'frozenfinal', 'finalrank',
            'timemodified',
        ]);

        $days = new backup_nested_element('days');
        $day = new backup_nested_element('day', ['id'], ['userid', 'day', 'timecreated']);

        $streak->add_child($states);
        $states->add_child($state);
        $streak->add_child($days);
        $days->add_child($day);

        $streak->set_source_table('streak', ['id' => backup::VAR_ACTIVITYID]);
        if ($userinfo) {
            $state->set_source_table('streak_state', ['streakid' => backup::VAR_PARENTID]);
            $day->set_source_table('streak_day', ['streakid' => backup::VAR_PARENTID]);
        }

        $state->annotate_ids('user', 'userid');
        $day->annotate_ids('user', 'userid');

        return $this->prepare_activity_structure($streak);
    }
}
