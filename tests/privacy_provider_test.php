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
use mod_streak\privacy\provider;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tests for the privacy provider.
 *
 * @package    mod_streak
 * @covers     \mod_streak\privacy\provider
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    public function test_export_and_delete(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);
        $context = \context_module::instance($module->cmid);
        $user = $this->getDataGenerator()->create_user();

        daily_ledger::credit($module->id, (int) $user->id, 20260601);
        $st = state::get_or_create($module->id, (int) $user->id);
        $st->currentstreak = 9;
        state::save($st);

        // The context appears for this user.
        $contexts = array_map('intval', provider::get_contexts_for_userid((int) $user->id)->get_contextids());
        $this->assertContains((int) $context->id, $contexts);

        // Export produces data.
        $approved = new approved_contextlist($user, 'mod_streak', [$context->id]);
        provider::export_user_data($approved);
        $this->assertTrue(writer::with_context($context)->get_data([])->currentstreak == 9);

        // Delete for the user removes the rows.
        provider::delete_data_for_user($approved);
        $this->assertSame(0, $DB->count_records('streak_state', ['streakid' => $module->id, 'userid' => $user->id]));
        $this->assertSame(0, $DB->count_records('streak_day', ['streakid' => $module->id, 'userid' => $user->id]));
    }

    public function test_metadata_userlist_and_bulk_delete(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);
        $context = \context_module::instance($module->cmid);
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $this->seed_streak_data((int) $module->id, (int) $u1->id);
        $this->seed_streak_data((int) $module->id, (int) $u2->id);

        // The metadata describes both stored tables.
        $collection = provider::get_metadata(new collection('mod_streak'));
        $tables = array_map(static function ($item) {
            return $item->get_name();
        }, $collection->get_collection());
        $this->assertContains('streak_state', $tables);
        $this->assertContains('streak_day', $tables);

        // The userlist holds both users for the module context, and nobody for a course context.
        $userlist = new userlist($context, 'mod_streak');
        provider::get_users_in_context($userlist);
        $found = array_map('intval', $userlist->get_userids());
        $this->assertContains((int) $u1->id, $found);
        $this->assertContains((int) $u2->id, $found);

        $coursecontextlist = new userlist(\context_course::instance($course->id), 'mod_streak');
        provider::get_users_in_context($coursecontextlist);
        $this->assertEmpty($coursecontextlist->get_userids());

        // Deleting the approved user removes only their rows; the other user is untouched (isolation).
        provider::delete_data_for_users(new approved_userlist($context, 'mod_streak', [(int) $u1->id]));
        $this->assertSame(0, $DB->count_records('streak_state', ['streakid' => $module->id, 'userid' => $u1->id]));
        $this->assertSame(0, $DB->count_records('streak_day', ['streakid' => $module->id, 'userid' => $u1->id]));
        $this->assertSame(1, $DB->count_records('streak_state', ['streakid' => $module->id, 'userid' => $u2->id]));
        $this->assertSame(1, $DB->count_records('streak_day', ['streakid' => $module->id, 'userid' => $u2->id]));

        // The context-wide delete clears whatever remains in the context.
        provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(0, $DB->count_records('streak_state', ['streakid' => $module->id]));
        $this->assertSame(0, $DB->count_records('streak_day', ['streakid' => $module->id]));
    }

    /**
     * Give a user one qualifying day and a streak_state row in an instance.
     *
     * @param int $streakid Streak instance id.
     * @param int $userid User id.
     */
    private function seed_streak_data(int $streakid, int $userid): void {
        daily_ledger::credit($streakid, $userid, 20260601);
        $st = state::get_or_create($streakid, $userid);
        $st->currentstreak = 4;
        state::save($st);
    }
}
