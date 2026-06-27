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

use mod_streak\local\breaks;

/**
 * Unit tests for the breaks (holiday) calendar.
 *
 * @package    mod_streak
 * @covers     \mod_streak\local\breaks
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class breaks_test extends \advanced_testcase {

    public function test_parse_and_sort(): void {
        $text = "# Term breaks\n2026-07-06, 2026-07-10\n\n2026-05-01, 2026-05-01\n";
        $ranges = breaks::parse($text);
        $this->assertCount(2, $ranges);
        // Sorted by start date.
        $this->assertSame(20260501, $ranges[0]->startday);
        $this->assertSame(20260501, $ranges[0]->endday);
        $this->assertSame(20260706, $ranges[1]->startday);
        $this->assertSame(20260710, $ranges[1]->endday);
    }

    public function test_validate(): void {
        $this->assertNull(breaks::validate("2026-07-06, 2026-07-10"));
        $this->assertNotNull(breaks::validate("2026-07-06")); // Missing second date.
        $this->assertNotNull(breaks::validate("2026-02-30, 2026-03-01")); // Not a real date.
        $this->assertNotNull(breaks::validate("2026-07-10, 2026-07-06")); // End before start.
    }

    public function test_day_in_ranges(): void {
        $ranges = breaks::parse("2026-07-06, 2026-07-10");
        $this->assertTrue(breaks::day_in_ranges($ranges, 20260706));
        $this->assertTrue(breaks::day_in_ranges($ranges, 20260708));
        $this->assertTrue(breaks::day_in_ranges($ranges, 20260710));
        $this->assertFalse(breaks::day_in_ranges($ranges, 20260705));
        $this->assertFalse(breaks::day_in_ranges($ranges, 20260711));
    }

    public function test_nonbreak_days(): void {
        // A Mon-Sun week (15th-21st) with a two-day break on the 17th-18th -> 5 non-break days.
        $ranges = breaks::parse("2026-06-17, 2026-06-18");
        $this->assertSame(5, breaks::nonbreak_days($ranges, 20260615, 20260621));

        // No breaks -> all 7 days.
        $this->assertSame(7, breaks::nonbreak_days([], 20260615, 20260621));

        // Whole period inside a break -> 0.
        $all = breaks::parse("2026-06-15, 2026-06-21");
        $this->assertSame(0, breaks::nonbreak_days($all, 20260615, 20260621));
    }
}
