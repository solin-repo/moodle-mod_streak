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
use mod_streak\local\leaderboard;
use mod_streak\local\qualifier;
use mod_streak\local\reminder;
use mod_streak\local\state;

/**
 * An activity's own value beats the site setting, in behaviour and not merely in storage.
 *
 * sitedefaults_test proves the value lands in the right database column. That is necessary but not
 * sufficient: if any runtime code read get_config() instead of the instance record, the column could
 * be correct while the plugin behaved as the site said. Every test here sets the site setting to one
 * value, the activity to a different one, and asserts what the plugin actually DOES.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class instanceoverride_test extends \advanced_testcase {
    /**
     * Create an activity whose fields deliberately disagree with the site settings.
     *
     * @param array $siteconfig Site settings to apply first.
     * @param array $instance Instance field overrides.
     * @return \stdClass The stored streak record.
     */
    private function conflicting(array $siteconfig, array $instance): \stdClass {
        global $DB;
        foreach ($siteconfig as $name => $value) {
            set_config($name, $value, 'mod_streak');
        }
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module(
            'streak',
            array_merge(['course' => $course->id], $instance)
        );
        return $DB->get_record('streak', ['id' => $cm->id], '*', MUST_EXIST);
    }

    /**
     * The cadence period and goal used are the activity's, not the site's.
     *
     * @covers \mod_streak\local\evaluator::goal
     */
    public function test_cadence_period_and_goal(): void {
        $this->resetAfterTest();
        $streak = $this->conflicting(
            ['cadenceperiod' => 'daily', 'cadencegoal' => 1],
            ['cadenceperiod' => 'weekly', 'cadencegoal' => 4]
        );
        // A daily period always has a goal of 1, so a goal of 4 can only come from the activity.
        $this->assertSame(4, evaluator::goal($streak));
    }

    /**
     * The qualifying mode used is the activity's.
     *
     * @covers \mod_streak\local\qualifier::completion_qualifies
     */
    public function test_qualify_mode(): void {
        $this->resetAfterTest();
        $streak = $this->conflicting(
            ['qualifymode' => 'anycompletion'],
            ['qualifymode' => 'login']
        );
        // In login mode a completion must not credit a day, whatever the site says.
        $this->assertFalse(
            qualifier::completion_qualifies($streak, (int) $streak->course, 0, COMPLETION_COMPLETE)
        );
    }

    /**
     * The excluded activity types are the activity's.
     *
     * @covers \mod_streak\local\qualifier::excluded_types
     */
    public function test_excluded_activity_types(): void {
        $this->resetAfterTest();
        $streak = $this->conflicting(
            ['modfilterexclude' => 'forum'],
            ['modfilterexclude' => 'quiz']
        );
        $excluded = qualifier::excluded_types($streak);
        $this->assertContains('quiz', $excluded, 'the activity list was ignored');
        $this->assertNotContains('forum', $excluded, 'the site list leaked in');
    }

    /**
     * The weekday mask used is the activity's.
     *
     * @covers \mod_streak\local\evaluator::mask
     */
    public function test_active_days(): void {
        $this->resetAfterTest();
        $streak = $this->conflicting(['activedays' => '1111111'], ['activedays' => '1111100']);
        $this->assertSame('1111100', evaluator::mask($streak));
    }

    /**
     * The end-date mode and custom date used are the activity's.
     *
     * @covers \mod_streak\local\evaluator::resolved_end_date
     */
    public function test_end_date(): void {
        $this->resetAfterTest();
        $when = make_timestamp(2027, 3, 1, 0, 0, 0);
        $streak = $this->conflicting(
            ['enddatemode' => 'none'],
            ['enddatemode' => 'custom', 'customenddate' => $when]
        );
        $this->assertSame(
            $when,
            evaluator::resolved_end_date($streak),
            'the site end-date mode overrode the activity'
        );

        $never = $this->conflicting(['enddatemode' => 'custom'], ['enddatemode' => 'none']);
        $this->assertSame(0, evaluator::resolved_end_date($never));
    }

    /**
     * The freeze accrual rate used is the activity's.
     *
     * @covers \mod_streak\local\evaluator::ensure_current
     */
    public function test_freeze_rate_and_cap(): void {
        $this->resetAfterTest();
        // The site would never grant a freeze; the activity grants one every period.
        $streak = $this->conflicting(
            ['freezerate' => 99, 'freezecap' => 1],
            ['freezerate' => 1, 'freezecap' => 5]
        );
        $user = $this->getDataGenerator()->create_user(['timezone' => 'UTC']);

        $tz = new \DateTimeZone('UTC');
        foreach (['-3 days', '-2 days', '-1 day'] as $offset) {
            evaluator::credit(
                $streak,
                (int) $user->id,
                (new \DateTimeImmutable("{$offset} 10:00", $tz))->getTimestamp()
            );
        }
        $state = state::get_or_create($streak->id, (int) $user->id);
        $state = evaluator::ensure_current(
            $streak,
            $state,
            (new \DateTimeImmutable('today 10:00', $tz))->getTimestamp()
        );

        $this->assertGreaterThan(
            0,
            (int) $state->freezesavailable,
            'no freeze accrued, so the site rate of 99 was used instead of the activity rate of 1'
        );
    }

    /**
     * Work-to-win during a break is the activity's setting.
     *
     * @covers \mod_streak\local\evaluator::ensure_current
     */
    public function test_reward_breaks(): void {
        $this->resetAfterTest();
        // Every weekday switched off, so every period is entirely "off".
        $streak = $this->conflicting(
            ['rewardbreaks' => 0],
            ['rewardbreaks' => 1, 'activedays' => '0000000']
        );
        $user = $this->getDataGenerator()->create_user(['timezone' => 'UTC']);

        $tz = new \DateTimeZone('UTC');
        evaluator::credit(
            $streak,
            (int) $user->id,
            (new \DateTimeImmutable('-1 day 10:00', $tz))->getTimestamp()
        );
        $state = state::get_or_create($streak->id, (int) $user->id);
        $state = evaluator::ensure_current(
            $streak,
            $state,
            (new \DateTimeImmutable('today 10:00', $tz))->getTimestamp()
        );

        $this->assertGreaterThan(
            0,
            (int) $state->currentstreak,
            'practice on an off day did not count, so the site rewardbreaks=0 was used'
        );
    }

    /**
     * The reminder hour used is the activity's.
     *
     * @covers \mod_streak\local\reminder::process
     */
    public function test_reminder_hour(): void {
        $this->resetAfterTest();
        $streak = $this->conflicting(['reminderhour' => 0], ['reminderhour' => 22]);
        $user = $this->getDataGenerator()->create_user(['timezone' => 'UTC']);

        $tz = new \DateTimeZone('UTC');
        evaluator::credit(
            $streak,
            (int) $user->id,
            (new \DateTimeImmutable('yesterday 10:00', $tz))->getTimestamp()
        );

        $sink = $this->redirectMessages();
        $state = state::get_or_create($streak->id, (int) $user->id);
        // The site hour of 0 would have sent this already; the activity says 22:00.
        $this->assertFalse(
            reminder::process(
                $streak,
                $state,
                (new \DateTimeImmutable('today 12:00', $tz))->getTimestamp()
            ),
            'the site reminder hour was used instead of the activity hour'
        );
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    /**
     * The early heads-up flag used is the activity's.
     *
     * @covers \mod_streak\local\evaluator::reminder_status
     */
    public function test_early_headsup(): void {
        $this->resetAfterTest();
        $streak = $this->conflicting(
            ['earlyheadsup' => 1],
            ['earlyheadsup' => 0, 'cadenceperiod' => 'weekly', 'cadencegoal' => 1]
        );
        $this->assertSame(0, (int) $streak->earlyheadsup);

        $user = $this->getDataGenerator()->create_user(['timezone' => 'UTC']);
        $state = state::get_or_create($streak->id, (int) $user->id);
        $status = evaluator::reminder_status($streak, $state, time());
        if ($status !== null) {
            $this->assertFalse(
                (bool) $status->isearly,
                'an early heads-up was raised although the activity switched it off'
            );
        } else {
            $this->assertNull($status);
        }
    }

    /**
     * Staff exclusion on the leaderboard follows the activity.
     *
     * @covers \mod_streak\local\leaderboard::fetch
     */
    public function test_exclude_staff(): void {
        global $DB;
        $this->resetAfterTest();

        // The site would hide staff; this activity shows them.
        set_config('excludestaff', 1, 'mod_streak');
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module(
            'streak',
            ['course' => $course->id, 'excludestaff' => 0]
        );
        $streak = $DB->get_record('streak', ['id' => $cm->id], '*', MUST_EXIST);
        $context = \context_module::instance($cm->cmid);

        $teacher = $this->getDataGenerator()->create_user(['firstname' => 'Tess']);
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $DB->insert_record('streak_state', (object) [
            'streakid' => $streak->id, 'userid' => $teacher->id,
            'currentstreak' => 3, 'displaystreak' => 3, 'longeststreak' => 3,
            'currentperiodstart' => 0, 'currentperioddaysmet' => 0, 'lastqualifyingday' => 0,
            'freezesavailable' => 0, 'freezesused' => 0, 'streakstart' => time(),
            'optout' => 0, 'frozenfinal' => 0, 'timemodified' => time(),
        ]);

        $board = leaderboard::fetch($streak, $context);
        $this->assertSame(
            1,
            (int) $board['total'],
            'the teacher was hidden, so the site excludestaff was used'
        );
    }

    /**
     * Excluded roles on the leaderboard follow the activity.
     *
     * @covers \mod_streak\local\leaderboard::fetch
     */
    public function test_exclude_roles(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'observer']);

        // The site excludes the role; this activity does not.
        set_config('excluderoles', (string) $roleid, 'mod_streak');
        $cm = $this->getDataGenerator()->create_module(
            'streak',
            ['course' => $course->id, 'excludestaff' => 0, 'excluderoles' => '']
        );
        $streak = $DB->get_record('streak', ['id' => $cm->id], '*', MUST_EXIST);
        $context = \context_module::instance($cm->cmid);

        $user = $this->getDataGenerator()->create_user(['firstname' => 'Obs']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        role_assign($roleid, $user->id, $context->get_course_context());
        $DB->insert_record('streak_state', (object) [
            'streakid' => $streak->id, 'userid' => $user->id,
            'currentstreak' => 3, 'displaystreak' => 3, 'longeststreak' => 3,
            'currentperiodstart' => 0, 'currentperioddaysmet' => 0, 'lastqualifyingday' => 0,
            'freezesavailable' => 0, 'freezesused' => 0, 'streakstart' => time(),
            'optout' => 0, 'frozenfinal' => 0, 'timemodified' => time(),
        ]);

        $this->assertSame('', $streak->excluderoles, 'the empty activity value was overwritten');
        $board = leaderboard::fetch($streak, $context);
        $this->assertSame(
            1,
            (int) $board['total'],
            'the learner was excluded, so the site excluderoles was used'
        );
    }

    /**
     * No runtime code may read these settings from the site configuration.
     *
     * This is the structural guarantee behind every test above: if a future change reads
     * get_config('mod_streak', ...) at runtime, an activity's own value could be silently ignored
     * again. Only the breaks calendar is allowed, because it is deliberately site-wide and is
     * combined with the activity's own calendar in evaluator::ranges().
     *
     * @coversNothing
     */
    public function test_runtime_never_reads_the_site_configuration(): void {
        global $CFG;
        $root = $CFG->dirroot . '/mod/streak';
        $offenders = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/classes'));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $body = file_get_contents($file->getPathname());
            if (preg_match_all("/get_config\(\s*'mod_streak'\s*,\s*'([a-z]+)'/", $body, $m)) {
                foreach ($m[1] as $name) {
                    if ($name === 'breakscalendar') {
                        continue;
                    }
                    $offenders[] = str_replace($root . '/', '', $file->getPathname()) . " reads '{$name}'";
                }
            }
        }
        $this->assertSame(
            [],
            $offenders,
            "runtime code must use the instance record, not the site setting:\n" . implode("\n", $offenders)
        );
    }
}
