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

use mod_streak\local\milestone;

/**
 * Tests for course-anchored streak progress and the weekly fallback.
 *
 * @package    mod_streak
 * @covers     \mod_streak\local\milestone
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class milestone_test extends \advanced_testcase {

    public function test_course_progress_fills_toward_the_goal(): void {
        // A 60-day course, 39-day streak: the ring is 39/60, not a band fraction.
        $p = milestone::progress(39, 60);
        $this->assertSame('course', $p['mode']);
        $this->assertSame(60, $p['goal']);
        $this->assertSame(39, $p['value']);
        $this->assertSame(21, $p['remaining']);
        $this->assertSame(65, $p['percent']); // 39 / 60.
        // Weekly markers sit strictly inside the ring (7, 14, ... 56 of 60).
        $this->assertSame(8, count($p['markers']));
        $this->assertSame(12, $p['markers'][0]); // 12 = round(7 / 60 * 100).
    }

    public function test_course_progress_caps_at_one_hundred(): void {
        // A streak longer than the course (course extended) caps at the goal, never overflows.
        $p = milestone::progress(70, 60);
        $this->assertSame(60, $p['value']);
        $this->assertSame(0, $p['remaining']);
        $this->assertSame(100, $p['percent']);
    }

    public function test_weekly_fallback_cycles_each_week(): void {
        // No goal (no course end date): the ring shows the current rolling week, 1..7 repeating.
        $this->assertSame('weekly', milestone::progress(39, 0)['mode']);
        $this->assertSame(4, milestone::progress(39, 0)['value']);   // 4 = ((39 - 1) % 7) + 1.
        $this->assertSame(57, milestone::progress(39, 0)['percent']); // 4 / 7.
        $this->assertSame(7, milestone::progress(7, 0)['value']);    // A full week reads 7/7.
        $this->assertSame(1, milestone::progress(8, 0)['value']);    // The next day starts a new week.
        $this->assertSame(0, milestone::progress(0, 0)['value']);    // Not started: empty.
        $this->assertSame([], milestone::progress(39, 0)['markers']);
    }

    public function test_goal_periods_from_course_end_date(): void {
        global $DB;
        $this->resetAfterTest();

        $start = gmmktime(0, 0, 0, 6, 1, 2026);
        $course = $this->getDataGenerator()->create_course(['startdate' => $start, 'enddate' => $start + 60 * DAYSECS]);
        /** @var \mod_streak_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_streak');
        $created = $gen->create_instance([
            'course' => $course->id, 'cadenceperiod' => 'daily', 'enddatemode' => 'course',
        ]);
        $streak = $DB->get_record('streak', ['id' => $created->id], '*', MUST_EXIST);

        $this->assertSame(60, milestone::goal_periods($streak));
    }

    public function test_goal_periods_zero_without_end_date(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        /** @var \mod_streak_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_streak');
        $created = $gen->create_instance([
            'course' => $course->id, 'cadenceperiod' => 'daily', 'enddatemode' => 'none',
        ]);
        $streak = $DB->get_record('streak', ['id' => $created->id], '*', MUST_EXIST);

        $this->assertSame(0, milestone::goal_periods($streak));
    }
}
