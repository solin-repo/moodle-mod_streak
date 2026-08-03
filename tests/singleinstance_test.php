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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/streak/lib.php');

/**
 * One Solin Streaks instance per course.
 *
 * This is a hard rule, not a preference: the crediting engine resolves a course's streak with
 * streak::for_course(), so only one instance can ever be credited and a second one would sit at
 * zero for every learner. Enforced in mod_form::validation() for the UI and in
 * streak_add_instance() for every other creation path. Restore is deliberately exempt (spec §16)
 * so incoming user data is never lost, which is why for_course() must be deterministic.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class singleinstance_test extends \advanced_testcase {
    /**
     * The first instance in a course is created normally.
     *
     * @covers ::streak_add_instance
     * @covers ::streak_course_has_instance
     */
    public function test_first_instance_is_allowed(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $module = $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);

        $this->assertTrue(streak_course_has_instance((int) $course->id));
        $this->assertFalse(streak_course_has_instance((int) $course->id, (int) $module->id));
    }

    /**
     * A second instance cannot be created through streak_add_instance().
     *
     * This is the path taken by web services, CLI and generators — everything that never touches
     * mod_form::validation().
     *
     * @covers ::streak_add_instance
     */
    public function test_second_instance_is_refused(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/Only one Solin Streaks activity is allowed per course/');
        $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);
    }

    /**
     * A second instance in a *different* course is fine.
     *
     * @covers ::streak_add_instance
     */
    public function test_other_courses_are_unaffected(): void {
        $this->resetAfterTest();
        $coursea = $this->getDataGenerator()->create_course();
        $courseb = $this->getDataGenerator()->create_course();

        $a = $this->getDataGenerator()->create_module('streak', ['course' => $coursea->id]);
        $b = $this->getDataGenerator()->create_module('streak', ['course' => $courseb->id]);

        $this->assertNotEquals($a->id, $b->id);
        $this->assertEquals($a->id, streak::for_course((int) $coursea->id)->id);
        streak::reset_memo();
        $this->assertEquals($b->id, streak::for_course((int) $courseb->id)->id);
    }

    /**
     * Editing an existing instance does not trip its own guard.
     *
     * @covers ::streak_course_has_instance
     * @covers ::streak_update_instance
     */
    public function test_editing_the_existing_instance_is_allowed(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);

        $data = $DB->get_record('streak', ['id' => $module->id], '*', MUST_EXIST);
        $data->instance = $data->id;
        $data->name = 'Renamed';

        $this->assertTrue(streak_update_instance($data));
        $this->assertSame('Renamed', $DB->get_field('streak', 'name', ['id' => $module->id]));
    }

    /**
     * If a duplicate does land (only restore can do that), the ORIGINAL instance stays the live one.
     *
     * Without an explicit order, for_course() returned whichever row the database produced first,
     * so a restored duplicate could take over crediting and silently stop the existing learners'
     * streaks. Oldest id must win, deterministically.
     *
     * SCOPE OF THIS TEST, honestly stated: it catches a *wrong* explicit order (verified — flipping
     * the query to 'id DESC' turns it red) but it does NOT catch the ordering being dropped
     * altogether. Both MySQL/InnoDB and PostgreSQL happen to return rows in primary-key order for a
     * query this simple, so the pre-fix IGNORE_MULTIPLE version passes here too. That is precisely
     * why the fix is worth having: the old code depended on unspecified behaviour that no test can
     * reliably reproduce on demand. The ORDER BY makes it correct by construction rather than by
     * luck; this test documents the contract and guards the direction.
     *
     * @covers \mod_streak\local\streak::for_course
     */
    public function test_oldest_instance_wins_when_a_duplicate_exists(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $original = $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);

        // Insert a duplicate the way the restore step does — straight to the table, no add_instance.
        $incoming = $DB->get_record('streak', ['id' => $original->id], '*', MUST_EXIST);
        unset($incoming->id);
        $incoming->name = 'Restored copy';
        $duplicateid = $DB->insert_record('streak', $incoming);
        streak::reset_memo();

        $this->assertGreaterThan((int) $original->id, (int) $duplicateid);
        $resolved = streak::for_course((int) $course->id);
        $this->assertEquals(
            (int) $original->id,
            (int) $resolved->id,
            'The pre-existing instance must stay the credited one after a duplicate is restored.'
        );
    }

    /**
     * Restoring an activity backup into a course that ALREADY has a streak.
     *
     * This is the real-world route to a duplicate, and the one the spec §16 policy governs:
     * restore keeps the incoming activity rather than silently merging its user data into the
     * existing instance. It must succeed (nothing lost), and crediting must stay with the
     * original instance. The same code path serves the course UI's "Duplicate" action, which
     * runs backup + restore internally (course/lib.php duplicate_module()).
     *
     * @covers \restore_streak_activity_structure_step::process_streak
     * @covers \mod_streak\local\streak::for_course
     */
    public function test_restore_into_a_course_that_already_has_one(): void {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $original = $this->getDataGenerator()->create_module(
            'streak',
            ['course' => $course->id, 'name' => 'Original streak']
        );

        // Back the activity up.
        $bc = new \backup_controller(
            \backup::TYPE_1ACTIVITY,
            $original->cmid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            (int) $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->execute_plan();
        $results = $bc->get_results();
        $backupfile = $results['backup_destination'];
        $bc->destroy();

        // Restore it back into the SAME course, which already holds an instance.
        $restoreid = 'streak_restore_dup_' . $course->id;
        $packer = get_file_packer('application/vnd.moodle.backup');
        $backupfile->extract_to_pathname($packer, make_backup_temp_directory($restoreid));

        $rc = new \restore_controller(
            $restoreid,
            $course->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            (int) $USER->id,
            \backup::TARGET_EXISTING_ADDING
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        streak::reset_memo();

        // Nothing was lost: the incoming activity is there alongside the original (spec §16).
        $this->assertEquals(2, $DB->count_records('streak', ['course' => $course->id]));

        // And crediting did not move: the original instance is still the live one.
        $resolved = streak::for_course((int) $course->id);
        $this->assertEquals((int) $original->id, (int) $resolved->id);
        $this->assertSame('Original streak', $resolved->name);
    }
}
