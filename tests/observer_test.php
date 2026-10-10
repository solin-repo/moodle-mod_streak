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
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the login event observer: it credits only login-mode streaks, and does so with a bounded
 * number of queries that does not scale with the learner's enrolment count (no per-course N+1).
 *
 * @package    mod_streak
 * @covers     \mod_streak\observer
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\mod_streak\observer::class)]
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

    /**
     * Build a completion-updated event for a learner and an activity.
     *
     * @param \stdClass $cm Course module (as returned by create_module, with ->cmid).
     * @param int $userid The learner.
     * @param int $state The new completion state.
     * @param int $when Event time.
     * @return \core\event\course_module_completion_updated
     */
    private function completion_event(
        \stdClass $cm,
        int $userid,
        int $state,
        int $when
    ): \core\event\course_module_completion_updated {
        $event = \core\event\course_module_completion_updated::create([
            'objectid'      => 1,
            'context'       => \context_module::instance($cm->cmid),
            'relateduserid' => $userid,
            'other'         => ['relateduserid' => $userid, 'overrideby' => null, 'completionstate' => $state],
        ]);
        // Event properties are read-only once created; restoring from the logged form is how the log
        // stores rebuild a past event, and lets the test place it on a chosen day.
        $data = $event->get_data();
        $data['timecreated'] = $when;
        return \core\event\base::restore($data, []);
    }

    /**
     * Completions in a course without a streak, in a login-mode course, or without a learner credit nothing.
     */
    public function test_completion_updated_ignores_what_it_cannot_credit(): void {
        global $DB;
        $this->resetAfterTest();
        streak::reset_memo();
        $user = $this->getDataGenerator()->create_user(['timezone' => 'UTC']);
        $when = gmmktime(10, 0, 0, 6, 15, 2026);

        // No streak in the course at all.
        $bare = $this->getDataGenerator()->create_course();
        $barepage = $this->getDataGenerator()->create_module('page', ['course' => $bare->id]);
        observer::completion_updated($this->completion_event($barepage, (int) $user->id, COMPLETION_COMPLETE, $when));

        // A login-mode streak is fed by logins, not by completions.
        $logincourse = $this->getDataGenerator()->create_course();
        /** @var \mod_streak_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_streak');
        $login = $gen->create_instance(['course' => $logincourse->id, 'qualifymode' => 'login']);
        $loginpage = $this->getDataGenerator()->create_module('page', ['course' => $logincourse->id]);
        observer::completion_updated($this->completion_event($loginpage, (int) $user->id, COMPLETION_COMPLETE, $when));

        // An event without a learner.
        $course = $this->getDataGenerator()->create_course();
        $any = $gen->create_instance(['course' => $course->id, 'qualifymode' => 'anycompletion']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        observer::completion_updated($this->completion_event($page, 0, COMPLETION_COMPLETE, $when));

        $this->assertSame(0, $DB->count_records('streak_day', ['streakid' => $login->id]));
        $this->assertSame(0, $DB->count_records('streak_day', ['streakid' => $any->id]));
        $this->assertSame(0, $DB->count_records('streak_day'));
    }

    /**
     * Any-completion mode credits a completed counting activity, and nothing else.
     */
    public function test_completion_updated_credits_any_completion(): void {
        global $DB;
        $this->resetAfterTest();
        streak::reset_memo();
        $user = $this->getDataGenerator()->create_user(['timezone' => 'UTC']);
        $course = $this->getDataGenerator()->create_course();
        /** @var \mod_streak_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_streak');
        $instance = $gen->create_instance(['course' => $course->id, 'qualifymode' => 'anycompletion']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);

        // Excluded type and an un-completion: no credit.
        observer::completion_updated(
            $this->completion_event($label, (int) $user->id, COMPLETION_COMPLETE, gmmktime(10, 0, 0, 6, 15, 2026))
        );
        observer::completion_updated(
            $this->completion_event($page, (int) $user->id, COMPLETION_INCOMPLETE, gmmktime(10, 0, 0, 6, 15, 2026))
        );
        $this->assertSame(0, $DB->count_records('streak_day', ['streakid' => $instance->id]));

        // A completed page counts, on the learner's day of the event.
        observer::completion_updated(
            $this->completion_event($page, (int) $user->id, COMPLETION_COMPLETE, gmmktime(10, 0, 0, 6, 15, 2026))
        );
        $this->assertTrue(
            $DB->record_exists('streak_day', ['streakid' => $instance->id, 'userid' => $user->id, 'day' => 20260615])
        );
    }

    /**
     * Course-progress mode credits only when the number of satisfied completion criteria goes up.
     */
    public function test_completion_updated_course_progress_credits_only_on_advance(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');
        $this->resetAfterTest();
        streak::reset_memo();
        $CFG->enablecompletion = 1;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $tracked = ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL];
        $one = $this->getDataGenerator()->create_module('page', $tracked);
        $two = $this->getDataGenerator()->create_module('page', $tracked);
        $criterion = new \completion_criteria_activity();
        $config = (object) [
            'id' => $course->id,
            'criteria_activity' => [$one->cmid => 1, $two->cmid => 1],
        ];
        $criterion->update_config($config);
        /** @var \mod_streak_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_streak');
        $instance = $gen->create_instance(['course' => $course->id, 'qualifymode' => 'courseprogress']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['timezone' => 'UTC']);

        // An activity completion that satisfies no criterion yet: progress stays at 0, no credit.
        observer::completion_updated(
            $this->completion_event($one, (int) $user->id, COMPLETION_COMPLETE, gmmktime(10, 0, 0, 6, 15, 2026))
        );
        $this->assertSame(0, $DB->count_records('streak_day', ['streakid' => $instance->id]));

        // One criterion satisfied: progress advances to 1 and the day is credited.
        $info = new \completion_info($course);
        $completions = array_values($info->get_completions((int) $user->id));
        $completions[0]->mark_complete();
        observer::completion_updated(
            $this->completion_event($one, (int) $user->id, COMPLETION_COMPLETE, gmmktime(10, 0, 0, 6, 16, 2026))
        );
        $this->assertTrue($DB->record_exists('streak_day', ['streakid' => $instance->id, 'day' => 20260616]));
        $this->assertSame(1, (int) state::get_or_create($instance->id, (int) $user->id)->lastcourseprogress);

        // Another completion event on a later day with no new criterion met: no credit.
        observer::completion_updated(
            $this->completion_event($two, (int) $user->id, COMPLETION_COMPLETE, gmmktime(10, 0, 0, 6, 17, 2026))
        );
        $this->assertFalse($DB->record_exists('streak_day', ['streakid' => $instance->id, 'day' => 20260617]));
        $this->assertSame(1, (int) state::get_or_create($instance->id, (int) $user->id)->lastcourseprogress);
    }

    /**
     * A login event without a user, or by a user without enrolments, credits nothing.
     */
    public function test_user_loggedin_ignores_users_without_streaks(): void {
        global $DB;
        $this->resetAfterTest();
        streak::reset_memo();

        $course = $this->getDataGenerator()->create_course();
        /** @var \mod_streak_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_streak');
        $gen->create_instance(['course' => $course->id, 'qualifymode' => 'login']);
        $loner = $this->getDataGenerator()->create_user();

        $event = \core\event\user_loggedin::create([
            'userid'   => $loner->id,
            'objectid' => $loner->id,
            'other'    => ['username' => $loner->username],
        ]);
        observer::user_loggedin($event);

        $anonymous = \core\event\user_loggedin::create([
            'userid'   => 0,
            'objectid' => 0,
            'other'    => ['username' => ''],
        ]);
        observer::user_loggedin($anonymous);

        $this->assertSame(0, $DB->count_records('streak_day'));
        $this->assertSame(0, $DB->count_records('streak_state'));
    }
}
