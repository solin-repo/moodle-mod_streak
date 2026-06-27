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

use mod_streak\local\leaderboard;
use mod_streak\local\evaluator;
use mod_streak\local\state;

/**
 * Tests for the leaderboard query (ranking, staff exclusion, opt-out).
 *
 * @package    mod_streak
 * @covers     \mod_streak\local\leaderboard
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class leaderboard_test extends \advanced_testcase {

    public function test_ranking_excludes_staff_and_optouts(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $s1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $s2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $streakid = $DB->insert_record('streak', (object) [
            'course' => $course->id, 'name' => 'S', 'intro' => '', 'introformat' => 0,
            'excludestaff' => 1, 'timemodified' => time(),
        ]);
        $streak = $DB->get_record('streak', ['id' => $streakid], '*', MUST_EXIST);

        $this->set_state($streakid, $s1->id, 5, 5, 0);
        $this->set_state($streakid, $s2->id, 3, 8, 0);
        $this->set_state($streakid, $teacher->id, 10, 10, 0); // Should be excluded as staff.

        $board = leaderboard::fetch($streak, $context);
        $this->assertSame(2, $board['total']);
        $ids = array_keys($board['rows']);
        $this->assertSame([(int) $s1->id, (int) $s2->id], array_map('intval', $ids)); // 5 before 3.

        // Opt s2 out -> only s1 remains.
        $this->set_state($streakid, $s2->id, 3, 8, 1);
        $board = leaderboard::fetch($streak, $context);
        $this->assertSame(1, $board['total']);
        $this->assertSame((int) $s1->id, (int) array_key_first($board['rows']));
    }

    public function test_ranks_by_displayed_streak(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $s1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $s2 = $this->getDataGenerator()->create_and_enrol($course, 'student');

        global $DB;
        $streakid = $DB->insert_record('streak', (object) [
            'course' => $course->id, 'name' => 'S', 'intro' => '', 'introformat' => 0,
            'cadenceperiod' => 'daily', 'cadencegoal' => 1, 'excludestaff' => 0, 'timemodified' => time(),
        ]);
        $streak = $DB->get_record('streak', ['id' => $streakid], '*', MUST_EXIST);

        // The board ranks by and returns the cached displayed streak, not the committed one.
        $this->set_state($streakid, $s1->id, 5, 9, 0, 6); // Committed 5, displays 6.
        $this->set_state($streakid, $s2->id, 4, 9, 0, 4); // Committed 4, displays 4.

        $board = leaderboard::fetch($streak, $context);

        $this->assertSame([(int) $s1->id, (int) $s2->id], array_map('intval', array_keys($board['rows'])));
        $this->assertSame(6, (int) $board['rows'][$s1->id]->displaystreak);
        $this->assertSame(4, (int) $board['rows'][$s2->id]->displaystreak);
    }

    public function test_board_value_matches_display_streak(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', ['timezone' => 'UTC']);

        global $DB;
        $streakid = $DB->insert_record('streak', (object) [
            'course' => $course->id, 'name' => 'S', 'intro' => '', 'introformat' => 0,
            'cadenceperiod' => 'daily', 'cadencegoal' => 1, 'excludestaff' => 0, 'timemodified' => time(),
        ]);
        $streak = $DB->get_record('streak', ['id' => $streakid], '*', MUST_EXIST);

        // A real qualifying action drives the cached value; the board must show exactly what the
        // learner sees at the top of the widget.
        $now = gmmktime(12, 0, 0, 6, 15, 2026);
        evaluator::credit($streak, (int) $student->id, $now);
        $state = state::get_or_create($streakid, (int) $student->id);
        $display = evaluator::display_streak($streak, $state, $now);

        $board = leaderboard::fetch($streak, $context);

        $this->assertSame(1, $display); // One qualifying day = a 1-day streak shown.
        $this->assertSame($display, (int) $board['rows'][$student->id]->displaystreak);
    }

    /**
     * Upsert a streak_state row.
     *
     * @param int $streakid Streak id.
     * @param int $userid User id.
     * @param int $current Current streak.
     * @param int $longest Longest streak.
     * @param int $optout Opt-out flag.
     * @param int|null $display Cached displayed streak (defaults to the committed streak).
     */
    private function set_state(int $streakid, int $userid, int $current, int $longest, int $optout,
            ?int $display = null): void {
        global $DB;
        $existing = $DB->get_record('streak_state', ['streakid' => $streakid, 'userid' => $userid]);
        $record = (object) [
            'streakid' => $streakid, 'userid' => $userid,
            'currentstreak' => $current, 'longeststreak' => $longest,
            'displaystreak' => $display ?? $current,
            'optout' => $optout, 'timemodified' => time(),
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('streak_state', $record);
        } else {
            $DB->insert_record('streak_state', $record);
        }
    }
}
