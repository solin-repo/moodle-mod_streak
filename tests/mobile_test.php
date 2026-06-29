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
use mod_streak\output\mobile;

/**
 * Tests for the Moodle App output handler.
 *
 * @package    mod_streak
 * @covers     \mod_streak\output\mobile
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mobile_test extends \advanced_testcase {

    public function test_mobile_course_view_returns_widget_template(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', ['timezone' => 'UTC']);
        $module = $this->getDataGenerator()->create_module('streak',
            ['course' => $course->id, 'name' => 'Daily streak']);

        // Give the learner a streak so the board has a row.
        $streak = $DB->get_record('streak', ['id' => $module->id], '*', MUST_EXIST);
        evaluator::credit($streak, (int) $student->id, gmmktime(12, 0, 0, 6, 15, 2026));

        $this->setUser($student);
        $PAGE->set_url('/');

        $result = mobile::mobile_course_view(['cmid' => $module->cmid, 'courseid' => $course->id]);

        // The handler returns the App's expected shape: one 'main' template plus the standard keys.
        $this->assertArrayHasKey('templates', $result);
        $this->assertSame('main', $result['templates'][0]['id']);
        $this->assertNotEmpty($result['templates'][0]['html']);
        $this->assertStringContainsString('Daily streak', $result['templates'][0]['html']);
        $this->assertArrayHasKey('javascript', $result);
        $this->assertArrayHasKey('otherdata', $result);
        $this->assertArrayHasKey('files', $result);
    }

    public function test_mobile_coursepage_view_is_read_only(): void {
        global $PAGE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', ['timezone' => 'UTC']);
        $module = $this->getDataGenerator()->create_module('streak',
            ['course' => $course->id, 'name' => 'Daily streak']);

        $this->setUser($student);
        $PAGE->set_url('/');

        $result = mobile::mobile_coursepage_view(['cmid' => $module->cmid, 'courseid' => $course->id]);
        $html = $result['templates'][0]['html'];

        // The app view shows the streak read-only; leaderboard opt-out is web-only, so the
        // inline app view must NOT carry an opt-out control or web service call.
        $this->assertStringContainsString('Daily streak', $html);
        $this->assertStringNotContainsString('action=optout', $html);
        $this->assertStringNotContainsString('mod_streak_set_optout', $html);
    }
}
