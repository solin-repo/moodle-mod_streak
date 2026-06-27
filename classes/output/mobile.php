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

namespace mod_streak\output;

use mod_streak\local\state;
use mod_streak\local\evaluator;
use mod_streak\local\leaderboard;

/**
 * Moodle App output handler for mod_streak.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {

    /**
     * Render the streak view for the Moodle App (CoreCourseModuleDelegate).
     *
     * @param array $args App args, including courseid and cmid.
     * @return array Templates + data for the app.
     */
    public static function mobile_course_view(array $args): array {
        global $OUTPUT, $DB, $USER;

        $args = (object) $args;
        $cm = get_coursemodule_from_id('streak', (int) $args->cmid, 0, false, MUST_EXIST);
        require_login($cm->course, false, $cm);
        $context = \context_module::instance($cm->id);
        require_capability('mod/streak:view', $context);

        $streak = $DB->get_record('streak', ['id' => $cm->instance], '*', MUST_EXIST);
        $state = state::get_or_create($streak->id, (int) $USER->id);
        $display = evaluator::display_streak($streak, $state, time());
        $state = state::get_or_create($streak->id, (int) $USER->id);

        $rows = [];
        if (has_capability('mod/streak:viewleaderboard', $context)) {
            $board = leaderboard::fetch($streak, $context, 0, 10);
            $rank = 0;
            foreach ($board['rows'] as $row) {
                $rank++;
                $rows[] = [
                    'rank'   => $rank,
                    'name'   => fullname($row),
                    'streak' => (int) $row->displaystreak,
                    'isme'   => ((int) $row->id === (int) $USER->id),
                ];
            }
        }

        $data = [
            'name'    => format_string($streak->name),
            'display' => $display,
            'started' => state::has_started($state),
            'rows'    => $rows,
            'hasrows' => !empty($rows),
        ];

        return [
            'templates' => [
                [
                    'id'   => 'main',
                    'html' => $OUTPUT->render_from_template('mod_streak/mobileapp/mobile_view', $data),
                ],
            ],
            'javascript' => '',
            'otherdata'  => [],
            'files'      => [],
        ];
    }
}
