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

use mod_streak\local\evaluator;
use mod_streak\local\leaderboard;
use mod_streak\local\state;

/**
 * Day roll-over behavior: the displayed streak holds across midnight and only advances
 * once the learner completes a qualifying activity on the new day.
 *
 * @package    mod_streak
 * @covers     \mod_streak\observer
 * @covers     \mod_streak\local\evaluator
 * @covers     \mod_streak\task\rollover_task
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rollover_test extends \advanced_testcase {

    public function test_new_day_holds_until_completion_then_flips(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();

        // A daily streak in a completion-enabled course; any activity completion qualifies.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', ['timezone' => 'UTC']);
        $context = \context_course::instance($course->id);

        /** @var \mod_streak_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_streak');
        $created = $gen->create_instance([
            'course'        => $course->id,
            'cadenceperiod' => 'daily',
            'qualifymode'   => 'anycompletion',
            'excludestaff'  => 0,
        ]);
        $streak = $DB->get_record('streak', ['id' => $created->id], '*', MUST_EXIST);

        // The activity the learner will complete on the new day (manual completion so we can tick it).
        $page = $this->getDataGenerator()->create_module('page', [
            'course'     => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        // Build a 38-day streak ending yesterday: credit 38 consecutive prior days (oldest first), so
        // "today" opens on a committed 38 with nothing done yet today. UTC keeps the day math exact.
        $now = time();
        for ($daysago = 38; $daysago >= 1; $daysago--) {
            evaluator::credit($streak, (int) $student->id, $now - ($daysago * DAYSECS));
        }

        // New day, before completing anything today: the number holds at 38 (yesterday is committed;
        // today's open period is not yet met). Headline value and board row agree.
        $state = state::get_or_create($streak->id, (int) $student->id);
        $this->assertSame(38, evaluator::display_streak($streak, $state, $now));
        $board = leaderboard::fetch($streak, $context);
        $this->assertSame(38, (int) $board['rows'][$student->id]->displaystreak);

        // Complete an activity today: the completion event feeds the observer, which credits the day.
        $completion = new \completion_info($course);
        $cm = get_coursemodule_from_instance('page', $page->id, $course->id, false, MUST_EXIST);
        $completion->update_state($cm, COMPLETION_COMPLETE, (int) $student->id);

        // It flips to 39, and both surfaces read it from the one cached column.
        $state = state::get_or_create($streak->id, (int) $student->id);
        $this->assertSame(39, evaluator::display_streak($streak, $state, $now));
        $board = leaderboard::fetch($streak, $context);
        $this->assertSame(39, (int) $board['rows'][$student->id]->displaystreak);
    }

    public function test_scheduled_task_freezes_streak_at_course_end(): void {
        global $DB;
        $this->resetAfterTest();

        // A course-date streak whose course has already ended: the rollover task must freeze it.
        $now = time();
        $course = $this->getDataGenerator()->create_course([
            'startdate' => $now - (30 * DAYSECS),
            'enddate'   => $now - DAYSECS,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', ['timezone' => 'UTC']);

        /** @var \mod_streak_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_streak');
        $created = $gen->create_instance([
            'course'        => $course->id,
            'cadenceperiod' => 'daily',
            'qualifymode'   => 'anycompletion',
            'enddatemode'   => 'course',
            'excludestaff'  => 0,
        ]);
        $streak = $DB->get_record('streak', ['id' => $created->id], '*', MUST_EXIST);

        // Start an active streak before the course end date.
        evaluator::credit($streak, (int) $student->id, $now - (5 * DAYSECS));
        $this->assertSame(0, (int) state::get_or_create($streak->id, (int) $student->id)->frozenfinal);

        // Running the task resolves the course end date once per streak and freezes the ended streak.
        (new \mod_streak\task\rollover_task())->execute();

        $state = state::get_or_create($streak->id, (int) $student->id);
        $this->assertSame(1, (int) $state->frozenfinal);
    }
}
