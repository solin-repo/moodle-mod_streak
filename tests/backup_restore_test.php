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

/**
 * Backup/restore preserves streaks and the leaderboard (the §16 guarantee).
 *
 * @coversNothing
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_restore_test extends \advanced_testcase {

    public function test_backup_restore_preserves_user_streaks(): void {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();

        daily_ledger::credit($module->id, (int) $user->id, 20260601);
        $st = state::get_or_create($module->id, (int) $user->id);
        $st->currentstreak = 7;
        $st->longeststreak = 9;
        state::save($st);

        // Back up the activity, including user info.
        $bc = new \backup_controller(\backup::TYPE_1ACTIVITY, $module->cmid, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, (int) $USER->id);
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->execute_plan();
        $results = $bc->get_results();
        $backupfile = $results['backup_destination'];
        $bc->destroy();

        // Extract the backup and restore it into a different course.
        $target = $this->getDataGenerator()->create_course();
        $restoreid = 'streak_restore_' . $target->id;
        $packer = get_file_packer('application/vnd.moodle.backup');
        $backupfile->extract_to_pathname($packer, make_backup_temp_directory($restoreid));

        $rc = new \restore_controller($restoreid, $target->id, \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL, (int) $USER->id, \backup::TARGET_EXISTING_ADDING);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // The restored instance carries the user's streak exactly.
        $restored = $DB->get_record('streak', ['course' => $target->id], '*', MUST_EXIST);
        $state = $DB->get_record('streak_state', ['streakid' => $restored->id, 'userid' => $user->id], '*', MUST_EXIST);
        $this->assertSame(7, (int) $state->currentstreak);
        $this->assertSame(9, (int) $state->longeststreak);
        $this->assertSame(1, $DB->count_records('streak_day', ['streakid' => $restored->id, 'userid' => $user->id]));
    }

    public function test_backup_restore_preserves_activity_settings(): void {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        /** @var \mod_streak_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_streak');
        $module = $gen->create_instance([
            'course'        => $course->id,
            'name'          => 'Daily practice',
            'cadenceperiod' => 'weekly',
            'cadencegoal'   => 3,
            'qualifymode'   => 'anycompletion',
            'rewardbreaks'  => 1,
            'excludestaff'  => 0,
            'reminderhour'  => 20,
        ]);

        $restored = $this->backup_then_restore((int) $module->cmid, true);

        // Every configured setting survives the round-trip.
        $this->assertSame('Daily practice', $restored->name);
        $this->assertSame('weekly', $restored->cadenceperiod);
        $this->assertSame(3, (int) $restored->cadencegoal);
        $this->assertSame('anycompletion', $restored->qualifymode);
        $this->assertSame(1, (int) $restored->rewardbreaks);
        $this->assertSame(0, (int) $restored->excludestaff);
        $this->assertSame(20, (int) $restored->reminderhour);
    }

    public function test_restore_without_user_data_keeps_a_clean_instance(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        daily_ledger::credit((int) $module->id, (int) $user->id, 20260601);
        $st = state::get_or_create((int) $module->id, (int) $user->id);
        $st->currentstreak = 5;
        state::save($st);

        // Back up WITHOUT user info -> the activity restores, but no per-user streak data follows.
        $restored = $this->backup_then_restore((int) $module->cmid, false);

        $this->assertSame(0, $DB->count_records('streak_state', ['streakid' => $restored->id]));
        $this->assertSame(0, $DB->count_records('streak_day', ['streakid' => $restored->id]));
    }

    /**
     * Back up one activity and restore it into a fresh course, returning the restored streak record.
     *
     * @param int $cmid Source course-module id.
     * @param bool $withusers Whether to include user info in the backup.
     * @return \stdClass The restored streak instance.
     */
    private function backup_then_restore(int $cmid, bool $withusers): \stdClass {
        global $DB, $USER;

        $bc = new \backup_controller(\backup::TYPE_1ACTIVITY, $cmid, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, (int) $USER->id);
        $bc->get_plan()->get_setting('users')->set_value($withusers);
        $bc->execute_plan();
        $results = $bc->get_results();
        $backupfile = $results['backup_destination'];
        $bc->destroy();

        $target = $this->getDataGenerator()->create_course();
        $restoreid = 'streak_restore_' . $target->id;
        $packer = get_file_packer('application/vnd.moodle.backup');
        $backupfile->extract_to_pathname($packer, make_backup_temp_directory($restoreid));

        $rc = new \restore_controller($restoreid, $target->id, \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL, (int) $USER->id, \backup::TARGET_EXISTING_ADDING);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $DB->get_record('streak', ['course' => $target->id], '*', MUST_EXIST);
    }
}
