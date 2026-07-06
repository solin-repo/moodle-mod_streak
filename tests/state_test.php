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

use mod_streak\local\state;

/**
 * Tests for the streak_state repository.
 *
 * @package    mod_streak
 * @covers     \mod_streak\local\state
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class state_test extends \advanced_testcase {
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

    public function test_get_or_create_makes_not_started_row(): void {
        global $DB;
        $this->resetAfterTest();
        $streakid = $this->make_streak();

        $state = state::get_or_create($streakid, 7);
        $this->assertSame(0, (int) $state->currentstreak);
        $this->assertSame(0, (int) $state->streakstart);
        $this->assertFalse(state::has_started($state));
        $this->assertSame(1, $DB->count_records('streak_state', ['streakid' => $streakid, 'userid' => 7]));
    }

    public function test_get_or_create_is_stable_and_save_persists(): void {
        global $DB;
        $this->resetAfterTest();
        $streakid = $this->make_streak();

        $state = state::get_or_create($streakid, 7);
        $state->currentstreak = 5;
        $state->streakstart = 1700000000;
        state::save($state);

        // No duplicate row, and the change persisted.
        $reloaded = state::get_or_create($streakid, 7);
        $this->assertSame(1, $DB->count_records('streak_state', ['streakid' => $streakid, 'userid' => 7]));
        $this->assertSame(5, (int) $reloaded->currentstreak);
        $this->assertTrue(state::has_started($reloaded));
    }
}
