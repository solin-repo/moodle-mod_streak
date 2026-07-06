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

use mod_streak\local\daily_ledger;

/**
 * Tests for the credited-day ledger.
 *
 * @package    mod_streak
 * @covers     \mod_streak\local\daily_ledger
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class daily_ledger_test extends \advanced_testcase {
    /**
     * Create a streak instance and return its id.
     *
     * @return int
     */
    private function make_streak(): int {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        return (int) $DB->insert_record('streak', (object) [
            'course'       => $course->id,
            'name'         => 'Test streak',
            'intro'        => '',
            'introformat'  => 0,
            'timemodified' => time(),
        ]);
    }

    public function test_credit_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $streakid = $this->make_streak();

        $this->assertTrue(daily_ledger::credit($streakid, 7, 20260617));
        // Same day again: no new row, returns false.
        $this->assertFalse(daily_ledger::credit($streakid, 7, 20260617));
        $this->assertSame(1, $DB->count_records('streak_day', ['streakid' => $streakid, 'userid' => 7]));
    }

    public function test_count_days_in_range(): void {
        $this->resetAfterTest();
        $streakid = $this->make_streak();

        daily_ledger::credit($streakid, 7, 20260615);
        daily_ledger::credit($streakid, 7, 20260617);
        daily_ledger::credit($streakid, 7, 20260620);
        daily_ledger::credit($streakid, 7, 20260622); // Outside the range below.
        daily_ledger::credit($streakid, 8, 20260617); // Different user.

        // Range 15th-21st for user 7 -> 3 days (15, 17, 20).
        $this->assertSame(3, daily_ledger::count_days_in_range($streakid, 7, 20260615, 20260621));
        // Different user is independent.
        $this->assertSame(1, daily_ledger::count_days_in_range($streakid, 8, 20260615, 20260621));
    }
}
