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

/**
 * Keeping named roles off the leaderboard.
 *
 * excluderoles was a column that nothing read until 0.10.0. It sits alongside excludestaff: staff are
 * excluded by capability, these by explicit role.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class excluderoles_test extends \advanced_testcase {
    /**
     * Build a course with three enrolled students who all have a streak.
     *
     * @param array $streakoverrides Field values to override on the streak instance.
     * @return array{0: \stdClass, 1: \context, 2: array<string, \stdClass>}
     */
    private function make_board(array $streakoverrides = []): array {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module(
            'streak',
            array_merge(['course' => $course->id, 'excludestaff' => 0], $streakoverrides)
        );
        $streak = $DB->get_record('streak', ['id' => $cm->id], '*', MUST_EXIST);
        $context = \context_module::instance($cm->cmid);

        $users = [];
        foreach (['alice' => 'student', 'bob' => 'student', 'carol' => 'student'] as $name => $role) {
            $u = $this->getDataGenerator()->create_user(['firstname' => ucfirst($name), 'lastname' => 'T']);
            $this->getDataGenerator()->enrol_user($u->id, $course->id, $role);
            $DB->insert_record('streak_state', (object) [
                'streakid' => $streak->id, 'userid' => $u->id,
                'currentstreak' => 5, 'displaystreak' => 5, 'longeststreak' => 5,
                'currentperiodstart' => 0, 'currentperioddaysmet' => 0, 'lastqualifyingday' => 0,
                'freezesavailable' => 0, 'freezesused' => 0, 'streakstart' => time(),
                'optout' => 0, 'frozenfinal' => 0, 'timemodified' => time(),
            ]);
            $users[$name] = $u;
        }
        return [$streak, $context, $users];
    }

    /**
     * With nothing excluded everybody is listed.
     *
     * @covers \mod_streak\local\leaderboard::fetch
     */
    public function test_nobody_excluded_by_default(): void {
        [$streak, $context] = $this->make_board();
        $board = leaderboard::fetch($streak, $context);
        $this->assertSame(3, (int) $board['total']);
    }

    /**
     * Members holding an excluded role are removed, and only them.
     *
     * @covers \mod_streak\local\leaderboard::fetch
     */
    public function test_an_excluded_role_is_removed(): void {
        global $DB;
        [$streak, $context, $users] = $this->make_board();

        // Give Bob an extra role and exclude that role.
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'observer']);
        role_assign($roleid, $users['bob']->id, $context->get_course_context());
        $DB->set_field('streak', 'excluderoles', (string) $roleid, ['id' => $streak->id]);
        $streak = $DB->get_record('streak', ['id' => $streak->id], '*', MUST_EXIST);

        $board = leaderboard::fetch($streak, $context);
        $names = array_map(static function ($row) {
            return $row->firstname;
        }, $board['rows']);

        $this->assertSame(2, (int) $board['total'], 'the excluded role was not removed');
        $this->assertNotContains('Bob', $names);
        $this->assertContains('Alice', $names);
        $this->assertContains('Carol', $names);
    }

    /**
     * Several excluded roles are honoured together.
     *
     * @covers \mod_streak\local\leaderboard::fetch
     */
    public function test_several_excluded_roles(): void {
        global $DB;
        [$streak, $context, $users] = $this->make_board();

        $one = $this->getDataGenerator()->create_role(['shortname' => 'observer']);
        $two = $this->getDataGenerator()->create_role(['shortname' => 'auditor']);
        role_assign($one, $users['alice']->id, $context->get_course_context());
        role_assign($two, $users['carol']->id, $context->get_course_context());
        $DB->set_field('streak', 'excluderoles', "$one,$two", ['id' => $streak->id]);
        $streak = $DB->get_record('streak', ['id' => $streak->id], '*', MUST_EXIST);

        $board = leaderboard::fetch($streak, $context);
        $this->assertSame(1, (int) $board['total']);
        $this->assertSame('Bob', $board['rows'][array_key_first($board['rows'])]->firstname);
    }

    /**
     * An empty or malformed value excludes nobody rather than everybody.
     *
     * @covers \mod_streak\local\leaderboard::fetch
     */
    public function test_an_empty_value_excludes_nobody(): void {
        global $DB;
        foreach (['', ',', '0'] as $value) {
            [$streak, $context] = $this->make_board();
            $DB->set_field('streak', 'excluderoles', $value, ['id' => $streak->id]);
            $streak = $DB->get_record('streak', ['id' => $streak->id], '*', MUST_EXIST);
            $board = leaderboard::fetch($streak, $context);
            $this->assertSame(3, (int) $board['total'], "value '{$value}' wrongly excluded people");
        }
    }
}
