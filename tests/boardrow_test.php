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

use mod_streak\local\boardrow;

/**
 * Tests for the leaderboard-row presentation helpers (medal name + avatar data).
 *
 * @package    mod_streak
 * @covers     \mod_streak\local\boardrow
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class boardrow_test extends \advanced_testcase {

    public function test_medal_name_for_top_three_only(): void {
        $this->assertSame('gold', boardrow::medal_name(1));
        $this->assertSame('silver', boardrow::medal_name(2));
        $this->assertSame('bronze', boardrow::medal_name(3));
        $this->assertNull(boardrow::medal_name(4));
        $this->assertNull(boardrow::medal_name(0));
    }

    public function test_avatar_data_without_picture_uses_initials(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_url('/');
        $output = $PAGE->get_renderer('core');

        $user = (object) ['id' => 42, 'firstname' => 'Ada', 'lastname' => 'Lovelace', 'picture' => 0];
        $data = boardrow::avatar_data($user, $output);

        $this->assertFalse($data['haspicture']);
        $this->assertSame('', $data['pictureurl']);
        $this->assertSame('AL', $data['initials']);
        $this->assertSame(42 % 8, $data['colorindex']); // Deterministic palette index.
    }

    public function test_avatar_data_with_picture_returns_url(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_url('/');
        $output = $PAGE->get_renderer('core');

        $user = $this->getDataGenerator()->create_user(['picture' => 1]);
        $data = boardrow::avatar_data($user, $output);

        $this->assertTrue($data['haspicture']);
        $this->assertNotEmpty($data['pictureurl']);
        $this->assertSame('', $data['initials']);
    }
}
