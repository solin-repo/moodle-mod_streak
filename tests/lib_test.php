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
use mod_streak\local\state;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/streak/lib.php');

/**
 * Tests for the mod_streak lib.php interface functions.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends \advanced_testcase {
    /**
     * The feature flags describe an inline, no-view-link, ungraded interactive activity.
     *
     * @covers ::streak_supports
     */
    public function test_supports_declares_inline_no_view_module(): void {
        $this->assertTrue(streak_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(streak_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(streak_supports(FEATURE_SHOW_DESCRIPTION));
        $this->assertTrue(streak_supports(FEATURE_NO_VIEW_LINK));
        $this->assertFalse(streak_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertFalse(streak_supports(FEATURE_GROUPS));
        $this->assertFalse(streak_supports(FEATURE_COMPLETION_TRACKS_VIEWS));
        $this->assertSame(MOD_PURPOSE_INTERACTIVECONTENT, streak_supports(FEATURE_MOD_PURPOSE));
        $this->assertNull(streak_supports('some_unknown_feature'));
    }

    /**
     * The flame icon keeps its own colors (not tinted by the purpose filter).
     *
     * @covers ::streak_is_branded
     */
    public function test_icon_is_branded(): void {
        $this->assertTrue(streak_is_branded());
    }

    /**
     * Add, update, then delete an instance through the lib API; delete must remove per-user data too.
     *
     * @covers ::streak_add_instance
     * @covers ::streak_update_instance
     * @covers ::streak_delete_instance
     */
    public function test_add_update_delete_instance(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $data = (object) [
            'course' => $course->id, 'name' => 'Streak A', 'intro' => '', 'introformat' => FORMAT_HTML,
            'cadenceperiod' => 'daily', 'cadencegoal' => 1, 'qualifymode' => 'anycompletion',
            'enddatemode' => 'none', 'rewardbreaks' => 0, 'excludestaff' => 1,
        ];
        $id = streak_add_instance($data);
        $this->assertGreaterThan(0, $id);
        $rec = $DB->get_record('streak', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('Streak A', $rec->name);
        $this->assertGreaterThan(0, (int) $rec->timemodified);

        // Update (the form passes ->instance; non-column fields are dropped by update_record).
        $update = (object) [
            'instance' => $id, 'course' => $course->id, 'name' => 'Streak B',
            'cadencegoal' => 2, 'intro' => '', 'introformat' => FORMAT_HTML,
        ];
        $this->assertTrue(streak_update_instance($update));
        $rec = $DB->get_record('streak', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('Streak B', $rec->name);
        $this->assertSame(2, (int) $rec->cadencegoal);

        // Seed per-user rows, then delete the instance.
        $user = $this->getDataGenerator()->create_user();
        daily_ledger::credit($id, (int) $user->id, 20260601);
        state::save(state::get_or_create($id, (int) $user->id));
        $this->assertTrue($DB->record_exists('streak_state', ['streakid' => $id]));

        $this->assertTrue(streak_delete_instance($id));
        $this->assertFalse($DB->record_exists('streak', ['id' => $id]));
        $this->assertSame(0, $DB->count_records('streak_state', ['streakid' => $id]));
        $this->assertSame(0, $DB->count_records('streak_day', ['streakid' => $id]));

        // Deleting a non-existent instance is a no-op returning false.
        $this->assertFalse(streak_delete_instance($id));
    }

    /**
     * cm_info_view injects the inline widget HTML as the activity's course-page content.
     *
     * @covers ::streak_cm_info_view
     */
    public function test_cm_info_view_sets_inline_widget(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module = $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);

        $this->setUser($student);
        $PAGE->set_url('/');

        $cm = get_fast_modinfo($course)->get_cm($module->cmid);
        streak_cm_info_view($cm);

        $this->assertStringContainsString('mod-streak-widget', (string) $cm->content);
    }
}
