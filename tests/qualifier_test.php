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

use mod_streak\local\qualifier;

/**
 * Unit tests for the qualifier helpers.
 *
 * @package    mod_streak
 * @covers     \mod_streak\local\qualifier
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class qualifier_test extends \advanced_testcase {
    public function test_is_completed_state(): void {
        $this->assertTrue(qualifier::is_completed_state(COMPLETION_COMPLETE));
        $this->assertTrue(qualifier::is_completed_state(COMPLETION_COMPLETE_PASS));
        $this->assertTrue(qualifier::is_completed_state(COMPLETION_COMPLETE_FAIL));
        $this->assertFalse(qualifier::is_completed_state(COMPLETION_INCOMPLETE));
    }

    public function test_excluded_types_default_and_custom(): void {
        $default = qualifier::excluded_types((object) ['modfilterexclude' => '']);
        $this->assertSame(['label', 'resource'], array_values($default));

        $custom = qualifier::excluded_types((object) ['modfilterexclude' => 'quiz, page ,']);
        $this->assertSame(['quiz', 'page'], array_values($custom));
    }
}
