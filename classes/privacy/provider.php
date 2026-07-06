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

namespace mod_streak\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context;
use context_module;

/**
 * Privacy provider for mod_streak.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection The metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('streak_state', [
            'userid'        => 'privacy:metadata:streak_state:userid',
            'currentstreak' => 'privacy:metadata:streak_state:currentstreak',
        ], 'privacy:metadata:streak_state');

        $collection->add_database_table('streak_day', [
            'userid' => 'privacy:metadata:streak_day:userid',
            'day'    => 'privacy:metadata:streak_day:day',
        ], 'privacy:metadata:streak_day');

        return $collection;
    }

    /**
     * Module contexts where the user has streak data.
     *
     * @param int $userid The user id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {streak} s
                  JOIN {modules} m ON m.name = 'streak'
                  JOIN {course_modules} cm ON cm.instance = s.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                  JOIN {streak_state} ss ON ss.streakid = s.id
                 WHERE ss.userid = :userid";
        $contextlist->add_from_sql($sql, ['modlevel' => CONTEXT_MODULE, 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * Users with streak data in a context.
     *
     * @param userlist $userlist The userlist to populate.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $streakid = self::streakid_from_context($context);
        if ($streakid === null) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {streak_state} WHERE streakid = :sid', ['sid' => $streakid]);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {streak_day} WHERE streakid = :sid', ['sid' => $streakid]);
    }

    /**
     * Export a user's streak data for the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $streakid = self::streakid_from_context($context);
            if ($streakid === null) {
                continue;
            }
            $state = $DB->get_record('streak_state', ['streakid' => $streakid, 'userid' => $userid]);
            if (!$state) {
                continue;
            }
            $days = $DB->get_fieldset_select(
                'streak_day',
                'day',
                'streakid = :sid AND userid = :uid',
                ['sid' => $streakid, 'uid' => $userid]
            );

            writer::with_context($context)->export_data([], (object) [
                'currentstreak'    => $state->currentstreak,
                'displaystreak'    => $state->displaystreak,
                'longeststreak'    => $state->longeststreak,
                'freezesavailable' => $state->freezesavailable,
                'freezesused'      => $state->freezesused,
                'optout'           => $state->optout,
                'frozenfinal'      => $state->frozenfinal,
                'qualifyingdays'   => $days,
            ]);
        }
    }

    /**
     * Delete all streak data in a context.
     *
     * @param context $context The context.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if (!$context instanceof context_module) {
            return;
        }
        $streakid = self::streakid_from_context($context);
        if ($streakid === null) {
            return;
        }
        $DB->delete_records('streak_day', ['streakid' => $streakid]);
        $DB->delete_records('streak_state', ['streakid' => $streakid]);
    }

    /**
     * Delete a user's streak data across the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $streakid = self::streakid_from_context($context);
            if ($streakid === null) {
                continue;
            }
            $DB->delete_records('streak_day', ['streakid' => $streakid, 'userid' => $userid]);
            $DB->delete_records('streak_state', ['streakid' => $streakid, 'userid' => $userid]);
        }
    }

    /**
     * Delete data for the approved users in a context.
     *
     * @param approved_userlist $userlist Approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $streakid = self::streakid_from_context($context);
        if ($streakid === null) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['sid'] = $streakid;
        $DB->delete_records_select('streak_day', "streakid = :sid AND userid $insql", $params);
        $DB->delete_records_select('streak_state', "streakid = :sid AND userid $insql", $params);
    }

    /**
     * Resolve the streak instance id behind a module context.
     *
     * @param context_module $context The module context.
     * @return int|null
     */
    private static function streakid_from_context(context_module $context): ?int {
        $cm = get_coursemodule_from_id('streak', $context->instanceid, 0, false, IGNORE_MISSING);
        return $cm ? (int) $cm->instance : null;
    }
}
