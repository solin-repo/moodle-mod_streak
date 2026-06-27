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

use mod_streak\local\reminder;
use mod_streak\local\evaluator;
use mod_streak\local\state;

/**
 * Tests for the make-or-break reminder.
 *
 * @package    mod_streak
 * @covers     \mod_streak\local\reminder
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reminder_test extends \advanced_testcase {

    public function test_is_make_or_break_rule(): void {
        $this->assertTrue(reminder::is_make_or_break(2, 2));   // Every remaining day now required.
        $this->assertFalse(reminder::is_make_or_break(3, 2));  // Still a day of buffer.
        $this->assertFalse(reminder::is_make_or_break(2, 0));  // Goal already met.
    }

    /**
     * Create a daily streak instance.
     *
     * @return \stdClass
     */
    private function make_daily_streak(): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $id = $DB->insert_record('streak', (object) [
            'course' => $course->id, 'name' => 'S', 'intro' => '', 'introformat' => 0,
            'cadenceperiod' => 'daily', 'cadencegoal' => 1, 'enddatemode' => 'none', 'timemodified' => time(),
        ]);
        return $DB->get_record('streak', ['id' => $id], '*', MUST_EXIST);
    }

    public function test_process_sends_once_when_at_risk(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['timezone' => 'UTC']);
        $streak = $this->make_daily_streak();

        // Start the streak yesterday; today is then an uncredited make-or-break day.
        $yesterday = (new \DateTimeImmutable('yesterday 10:00', new \DateTimeZone('UTC')))->getTimestamp();
        evaluator::credit($streak, (int) $user->id, $yesterday);
        $now = (new \DateTimeImmutable('today 20:00', new \DateTimeZone('UTC')))->getTimestamp();

        $sink = $this->redirectMessages();

        $state = state::get_or_create($streak->id, (int) $user->id);
        $this->assertTrue(reminder::process($streak, $state, $now));
        $this->assertCount(1, $sink->get_messages());

        // Same day again -> deduped, no second message.
        $state = state::get_or_create($streak->id, (int) $user->id);
        $this->assertFalse(reminder::process($streak, $state, $now));
        $this->assertCount(1, $sink->get_messages());

        $sink->close();
    }

    public function test_no_reminder_when_done_today(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['timezone' => 'UTC']);
        $streak = $this->make_daily_streak();

        $today = (new \DateTimeImmutable('today 09:00', new \DateTimeZone('UTC')))->getTimestamp();
        evaluator::credit($streak, (int) $user->id, $today);
        $now = (new \DateTimeImmutable('today 20:00', new \DateTimeZone('UTC')))->getTimestamp();

        $sink = $this->redirectMessages();
        $state = state::get_or_create($streak->id, (int) $user->id);
        $this->assertFalse(reminder::process($streak, $state, $now));
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }
}
