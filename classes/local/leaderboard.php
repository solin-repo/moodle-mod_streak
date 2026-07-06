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
 * Per-course streak leaderboard query.
 *
 * One ranked, paginated query (no per-row user lookups), ranking by the committed
 * streak that cron keeps fresh. Honors opt-out and (optionally) staff exclusion.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class leaderboard {
    /**
     * Fetch a page of the leaderboard.
     *
     * @param \stdClass $streak The streak instance.
     * @param \context $context The module context.
     * @param int $page Zero-based page number.
     * @param int $perpage Rows per page.
     * @return array{rows: array, total: int}
     */
    public static function fetch(\stdClass $streak, \context $context, int $page = 0, int $perpage = 50): array {
        global $DB;

        [$esql, $params] = get_enrolled_sql($context, 'mod/streak:view', 0, true);
        $params['streakid'] = $streak->id;

        $staffwhere = '';
        if (!empty($streak->excludestaff)) {
            $coursecontext = $context->get_course_context();
            $staffids = array_keys(get_users_by_capability($coursecontext, 'moodle/course:update', 'u.id'));
            if ($staffids) {
                [$notin, $ninparams] = $DB->get_in_or_equal($staffids, SQL_PARAMS_NAMED, 'st', false);
                $staffwhere = " AND u.id $notin";
                $params += $ninparams;
            }
        }

        // Pull the user-picture fields (name, picture, imagealt, ...) in this one ranked query so the
        // leaderboard can render real avatars without a per-row user lookup (no N+1). Exclude id here
        // because it is already selected explicitly as the leading (keying) column below.
        $userfields = \core_user\fields::for_userpic()->excluding('id')->get_sql('u', true);
        $params += $userfields->params;

        $from = "FROM {user} u
                 JOIN ($esql) je ON je.id = u.id
                 $userfields->joins
            LEFT JOIN {streak_state} ss ON ss.streakid = :streakid AND ss.userid = u.id
                WHERE (ss.optout IS NULL OR ss.optout = 0) $staffwhere";

        // Rank by and return the cached displayed streak (streak_state.displaystreak), the same value
        // the widget headline reads, so the board and a learner's own number always agree. The rule
        // that derives it lives solely in evaluator::recompute_display(); there is no streak logic here.
        $select = "SELECT u.id $userfields->selects,
                          COALESCE(ss.displaystreak, 0) AS displaystreak,
                          COALESCE(ss.longeststreak, 0) AS longeststreak";
        $order = " ORDER BY displaystreak DESC, longeststreak DESC, u.lastname ASC, u.firstname ASC";

        $rows = $DB->get_records_sql($select . ' ' . $from . $order, $params, $page * $perpage, $perpage);
        $total = $DB->count_records_sql("SELECT COUNT(1) $from", $params);

        return ['rows' => $rows, 'total' => $total];
    }
}
