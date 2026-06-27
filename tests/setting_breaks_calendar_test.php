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

use mod_streak\admin\setting_breaks_calendar;
use mod_streak\local\breaks;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php'); // For the admin_setting_configtextarea parent class.

/**
 * Tests for the breaks-calendar admin setting validator.
 *
 * @package    mod_streak
 * @covers     \mod_streak\admin\setting_breaks_calendar
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class setting_breaks_calendar_test extends \advanced_testcase {

    public function test_validate_accepts_valid_and_rejects_invalid(): void {
        $this->resetAfterTest();
        $setting = new setting_breaks_calendar('mod_streak/breakscalendar', 'Breaks', '', '');

        // A well-formed date range validates (true), matching breaks::validate returning null.
        $this->assertTrue($setting->validate('2026-07-06, 2026-07-10'));

        // A malformed line (missing the second date) is rejected with the breaks::validate message.
        $bad = '2026-07-06';
        $expected = breaks::validate($bad);
        $this->assertIsString($expected);
        $this->assertSame($expected, $setting->validate($bad));
    }
}
