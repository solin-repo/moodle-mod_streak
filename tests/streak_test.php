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

use mod_streak\local\streak;

/**
 * Tests for the streak instance repository (for_course / instance / memo / weekstart).
 *
 * @package    mod_streak
 * @covers     \mod_streak\local\streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class streak_test extends \advanced_testcase {
    public function test_for_course_instance_and_memo(): void {
        global $DB;
        $this->resetAfterTest();
        streak::reset_memo();

        $course = $this->getDataGenerator()->create_course();
        $this->assertNull(streak::for_course((int) $course->id));

        // Insert directly (bypassing the lib reset_memo) to prove the per-request memo is honored
        // until it is explicitly cleared.
        $id = (int) $DB->insert_record('streak', (object) [
            'course' => $course->id, 'name' => 'S', 'intro' => '', 'introformat' => 0, 'timemodified' => time(),
        ]);
        $this->assertNull(streak::for_course((int) $course->id)); // Still the memoized null.
        streak::reset_memo();
        $rec = streak::for_course((int) $course->id);
        $this->assertNotNull($rec);
        $this->assertSame($id, (int) $rec->id);

        // The instance() accessor fetches by id.
        $this->assertSame($id, (int) streak::instance($id)->id);

        // A missing id throws.
        $this->expectException(\dml_missing_record_exception::class);
        streak::instance($id + 999);
    }

    public function test_weekstart_reads_calendar_setting(): void {
        global $CFG;
        $CFG->calendar_startwday = 0;
        $this->assertSame(0, streak::weekstart());
        $CFG->calendar_startwday = 6;
        $this->assertSame(6, streak::weekstart());
    }
}
