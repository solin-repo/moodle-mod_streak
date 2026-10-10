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

use PHPUnit\Framework\Attributes\CoversFunction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/upgradelib.php');
require_once($CFG->dirroot . '/mod/streak/db/upgrade.php');

/**
 * Tests for the database upgrade steps.
 *
 * Each test puts the schema back the way the old version had it, runs the upgrade from that version,
 * and checks the result. The upgrade re-adds the dropped columns, so the schema is whole again when
 * the test ends.
 *
 * @package    mod_streak
 * @covers     ::xmldb_streak_upgrade
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('xmldb_streak_upgrade')]
final class upgrade_test extends \advanced_testcase {
    /**
     * Insert a learner state row directly, as the old schema would have held it.
     *
     * @param int $streakid Streak instance id.
     * @param int $userid User id.
     * @param int $current Committed streak.
     * @param int $met Days met in the open period.
     * @return int The row id.
     */
    private function old_state(int $streakid, int $userid, int $current, int $met): int {
        global $DB;
        return (int) $DB->insert_record('streak_state', (object) [
            'streakid'             => $streakid,
            'userid'               => $userid,
            'currentstreak'        => $current,
            'currentperioddaysmet' => $met,
            'streakstart'          => 1,
            'timemodified'         => time(),
        ]);
    }

    public function test_upgrade_from_the_first_beta_adds_both_columns_and_backfills(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        /** @var \mod_streak_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_streak');
        $daily = $gen->create_instance(['course' => $course1->id, 'cadenceperiod' => 'daily']);
        $weekly = $gen->create_instance(['course' => $course2->id, 'cadenceperiod' => 'weekly', 'cadencegoal' => 2]);
        $u1 = (int) $this->getDataGenerator()->create_user()->id;
        $u2 = (int) $this->getDataGenerator()->create_user()->id;

        // Back to the schema of version 2026061800: no displaystreak, no activedays.
        $dbman->drop_field(new \xmldb_table('streak_state'), new \xmldb_field('displaystreak'));
        $dbman->drop_field(new \xmldb_table('streak'), new \xmldb_field('activedays'));
        $dailymet = $this->old_state((int) $daily->id, $u1, 4, 1);
        $dailyopen = $this->old_state((int) $daily->id, $u2, 4, 0);
        $weeklyshort = $this->old_state((int) $weekly->id, $u1, 2, 1);
        $weeklymet = $this->old_state((int) $weekly->id, $u2, 2, 2);
        set_config('version', 2026061800, 'mod_streak');

        $this->assertTrue(xmldb_streak_upgrade(2026061800));

        $this->assertTrue($dbman->field_exists('streak_state', 'displaystreak'));
        $this->assertTrue($dbman->field_exists('streak', 'activedays'));
        // Committed periods, plus the open one when its goal (daily 1, weekly as configured) is met.
        $this->assertSame(5, (int) $DB->get_field('streak_state', 'displaystreak', ['id' => $dailymet]));
        $this->assertSame(4, (int) $DB->get_field('streak_state', 'displaystreak', ['id' => $dailyopen]));
        $this->assertSame(2, (int) $DB->get_field('streak_state', 'displaystreak', ['id' => $weeklyshort]));
        $this->assertSame(3, (int) $DB->get_field('streak_state', 'displaystreak', ['id' => $weeklymet]));
        // Existing activities keep counting every weekday.
        $this->assertSame('1111111', $DB->get_field('streak', 'activedays', ['id' => $daily->id]));
        $this->assertEquals(2026082900, get_config('mod_streak', 'version'));
    }

    public function test_upgrade_tolerates_columns_that_already_exist(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('streak', ['course' => $course->id, 'activedays' => '1111100']);
        set_config('version', 2026061800, 'mod_streak');

        $this->assertTrue(xmldb_streak_upgrade(2026061800));

        // A half-applied earlier run is completed without touching the configured mask.
        $this->assertSame('1111100', $DB->get_field('streak', 'activedays', ['id' => $module->id]));
        $this->assertEquals(2026082900, get_config('mod_streak', 'version'));
    }

    public function test_upgrade_from_the_current_version_changes_nothing(): void {
        $this->resetAfterTest();
        $before = get_config('mod_streak', 'version');

        $this->assertTrue(xmldb_streak_upgrade(2026082900));

        $this->assertSame($before, get_config('mod_streak', 'version'));
    }
}
