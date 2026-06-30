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

namespace mod_streak;

use mod_streak\local\streak;
use mod_streak\local\qualifier;
use mod_streak\local\evaluator;
use mod_streak\local\state;

/**
 * Event observers that feed qualifying actions into the streak engine.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {

    /**
     * Credit a day when a learner completes an activity (any-completion / course-progress modes).
     *
     * @param \core\event\course_module_completion_updated $event The event.
     */
    public static function completion_updated(\core\event\course_module_completion_updated $event): void {
        global $DB;

        $courseid = (int) $event->courseid;
        $instance = streak::for_course($courseid);
        if ($instance === null || $instance->qualifymode === qualifier::MODE_LOGIN) {
            return;
        }

        $userid = (int) $event->relateduserid;
        if (empty($userid)) {
            return;
        }
        $when = (int) $event->timecreated;

        if ($instance->qualifymode === qualifier::MODE_ANYCOMPLETION) {
            $completionstate = (int) ($event->other['completionstate'] ?? COMPLETION_INCOMPLETE);
            if (qualifier::completion_qualifies($instance, $courseid, (int) $event->contextinstanceid, $completionstate)) {
                evaluator::credit($instance, $userid, $when);
            }
            return;
        }

        // Course-progress mode: credit only when the completed-criteria count advances.
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $newcount = qualifier::course_progress_count($course, $userid);
        $current = state::get_or_create($instance->id, $userid);
        if ($newcount > (int) $current->lastcourseprogress) {
            evaluator::credit($instance, $userid, $when);
            $current = state::get_or_create($instance->id, $userid);
            $current->lastcourseprogress = $newcount;
            state::save($current);
        }
    }

    /**
     * Credit a day on login for any login-mode streak in the user's enrolled courses.
     *
     * @param \core\event\user_loggedin $event The event.
     */
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        global $DB;

        $userid = (int) $event->objectid;
        if (empty($userid)) {
            return;
        }

        // Fetch every login-mode streak across the user's enrolled courses in ONE query, rather than
        // looking the streak up per course (which on the login hot path is an N+1 that scales with the
        // learner's enrolment count).
        $courseids = array_keys(enrol_get_all_users_courses($userid, true, 'id'));
        if (empty($courseids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $params['qmode'] = qualifier::MODE_LOGIN;
        $instances = $DB->get_records_select('streak', "course $insql AND qualifymode = :qmode", $params);

        $when = (int) $event->timecreated;
        foreach ($instances as $instance) {
            evaluator::credit($instance, $userid, $when);
        }
    }
}
