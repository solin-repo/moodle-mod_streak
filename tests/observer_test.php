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

use mod_streak\local\evaluator;
use mod_streak\local\state;
use mod_streak\local\streak;

/**
 * Tests for the login event observer: it credits only login-mode streaks, and does so with a bounded
 * number of queries that does not scale with the learner's enrolment count (no per-course N+1).
 *
 * @package    mod_streak
 * @covers     \mod_streak\observer
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer_test extends \advanced_testcase {
    /**
     * A login credits the login-mode streak in an enrolled course, and leaves a non-login streak alone.
     */
    public function test_user_loggedin_credits_only_login_mode_streaks(): void {
        global $DB;
        $this->resetAfterTest();
        streak::reset_memo();

        $user = $this->getDataGenerator()->create_user(['timezone' => 'UTC']);

        $logincourse = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $logincourse->id);
        $completioncourse = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $completioncourse->id);
        // A third enrolled course with no streak at all, to prove it is simply ignored.
        $bare = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $bare->id);

        /** @var \mod_streak_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_streak');
        $login = $gen->create_instance([
            'course' => $logincourse->id, 'cadenceperiod' => 'daily', 'qualifymode' => 'login',
        ]);
        $completion = $gen->create_instance([
            'course' => $completioncourse->id, 'cadenceperiod' => 'daily', 'qualifymode' => 'anycompletion',
        ]);
        $loginrec = $DB->get_record('streak', ['id' => $login->id], '*', MUST_EXIST);
        $completionrec = $DB->get_record('streak', ['id' => $completion->id], '*', MUST_EXIST);

        $event = \core\event\user_loggedin::create([
            'userid'   => $user->id,
            'objectid' => $user->id,
            'other'    => ['username' => $user->username],
        ]);
        observer::user_loggedin($event);

        $now = time();
        // The login-mode streak is credited: today counts, so the daily display is 1.
        $loginstate = state::get_or_create($loginrec->id, (int) $user->id);
        $this->assertSame(1, evaluator::display_streak($loginrec, $loginstate, $now));
        // The any-completion streak in another enrolled course must NOT be credited by a login.
        $completionstate = state::get_or_create($completionrec->id, (int) $user->id);
        $this->assertSame(0, evaluator::display_streak($completionrec, $completionstate, $now));
    }

    /**
     * Harden-to-fail regression guard for the login-observer N+1: adding streak-less courses to a
     * learner's enrolment must not add DB reads to login handling. Pre-fix this grew by one read per
     * extra course (a per-course streak lookup); the single-query form keeps it flat.
     */
    public function test_user_loggedin_reads_do_not_scale_with_enrolment_count(): void {
        $this->resetAfterTest();

        $measure = function (int $extracourses): int {
            global $DB;
            streak::reset_memo();
            $user = $this->getDataGenerator()->create_user(['timezone' => 'UTC']);

            // One course that does carry a login-mode streak (the real work the observer must do).
            $logincourse = $this->getDataGenerator()->create_course();
            $this->getDataGenerator()->enrol_user($user->id, $logincourse->id);
            /** @var \mod_streak_generator $gen */
            $gen = $this->getDataGenerator()->get_plugin_generator('mod_streak');
            $gen->create_instance([
                'course' => $logincourse->id, 'cadenceperiod' => 'daily', 'qualifymode' => 'login',
            ]);

            // Plus N enrolled courses that have no streak — pure noise the observer must shrug off cheaply.
            for ($i = 0; $i < $extracourses; $i++) {
                $c = $this->getDataGenerator()->create_course();
                $this->getDataGenerator()->enrol_user($user->id, $c->id);
            }

            $event = \core\event\user_loggedin::create([
                'userid'   => $user->id,
                'objectid' => $user->id,
                'other'    => ['username' => $user->username],
            ]);

            streak::reset_memo();
            $before = $DB->perf_get_reads();
            observer::user_loggedin($event);
            return $DB->perf_get_reads() - $before;
        };

        $few = $measure(1);
        $many = $measure(8);

        // Seven extra streak-less courses would have meant seven extra reads pre-fix; assert the cost
        // stays essentially flat (small slack for unrelated per-call variance).
        $this->assertLessThanOrEqual(
            $few + 2,
            $many,
            'login-observer DB reads scale with enrolment count — the per-course N+1 has regressed'
        );
    }
}
