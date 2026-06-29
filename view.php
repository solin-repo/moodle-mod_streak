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
 * Solin Streaks has no separate view page: the streak and leaderboard render inline on the course
 * page (FEATURE_NO_VIEW_LINK). This script only handles the leaderboard opt-out/opt-in toggle and
 * then returns the learner to the course page.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_streak\local\state;

$id = required_param('id', PARAM_INT); // Course module id.
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('streak', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$streak = $DB->get_record('streak', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/streak:view', $context);

// Toggle the viewer's leaderboard visibility (the web opt-out link), then send them back to
// the inline widget. The Moodle App shows the streak read-only, so opt-out is web-only.
if ($action !== '' && confirm_sesskey()) {
    $mystate = state::get_or_create($streak->id, (int) $USER->id);
    $mystate->optout = ($action === 'optout') ? 1 : 0;
    state::save($mystate);
}

redirect(new moodle_url('/course/view.php', ['id' => $course->id], 'module-' . $cm->id));
