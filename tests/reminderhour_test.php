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
use mod_streak\local\reminder;
use mod_streak\local\state;

/**
 * The reminder hour, the early heads-up, and reminders on days that do not count.
 *
 * Before 0.10.0 the reminder hour was declared as a setting and read by nothing at all: reminders
 * went out on whichever hourly cron run first found a learner at risk.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reminderhour_test extends \advanced_testcase {
    /**
     * Create a streak instance with the given overrides.
     *
     * @param array $overrides Field overrides.
     * @return \stdClass
     */
    private function make_streak(array $overrides = []): \stdClass {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $record = (object) array_merge([
            'course' => $course->id, 'name' => 'S', 'intro' => '', 'introformat' => 0,
            'cadenceperiod' => 'daily', 'cadencegoal' => 1, 'enddatemode' => 'none',
            'reminderhour' => 18, 'activedays' => '1111111', 'timemodified' => time(),
        ], $overrides);
        $id = $DB->insert_record('streak', $record);
        return $DB->get_record('streak', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Put a learner at risk today: credited yesterday, nothing yet today.
     *
     * @param \stdClass $streak The instance.
     * @param int $userid The learner.
     */
    private function put_at_risk(\stdClass $streak, int $userid): void {
        $yesterday = (new \DateTimeImmutable('yesterday 10:00', new \DateTimeZone('UTC')))->getTimestamp();
        evaluator::credit($streak, $userid, $yesterday);
    }

    /**
     * Nothing is sent before the configured hour, and it is sent at or after it.
     *
     * @covers \mod_streak\local\reminder::process
     */
    public function test_nothing_before_the_hour_and_sent_after(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['timezone' => 'UTC']);
        $streak = $this->make_streak(['reminderhour' => 18]);
        $this->put_at_risk($streak, (int) $user->id);

        $tz = new \DateTimeZone('UTC');
        $sink = $this->redirectMessages();

        foreach ([9, 17] as $hour) {
            $now = (new \DateTimeImmutable("today {$hour}:00", $tz))->getTimestamp();
            $state = state::get_or_create($streak->id, (int) $user->id);
            $this->assertFalse(reminder::process($streak, $state, $now),
                "a reminder went out at {$hour}:00, before the configured 18:00");
        }
        $this->assertCount(0, $sink->get_messages());

        $now = (new \DateTimeImmutable('today 18:00', $tz))->getTimestamp();
        $state = state::get_or_create($streak->id, (int) $user->id);
        $this->assertTrue(reminder::process($streak, $state, $now), 'nothing sent at the configured hour');
        $this->assertCount(1, $sink->get_messages());

        $sink->close();
    }

    /**
     * A different configured hour moves the delivery time.
     *
     * @covers \mod_streak\local\reminder::process
     */
    public function test_the_configured_hour_is_honoured(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['timezone' => 'UTC']);
        $streak = $this->make_streak(['reminderhour' => 8]);
        $this->put_at_risk($streak, (int) $user->id);

        $tz = new \DateTimeZone('UTC');
        $sink = $this->redirectMessages();

        $state = state::get_or_create($streak->id, (int) $user->id);
        $this->assertFalse(reminder::process($streak, $state,
            (new \DateTimeImmutable('today 07:00', $tz))->getTimestamp()));

        $state = state::get_or_create($streak->id, (int) $user->id);
        $this->assertTrue(reminder::process($streak, $state,
            (new \DateTimeImmutable('today 08:00', $tz))->getTimestamp()));

        $sink->close();
    }

    /**
     * The hour is the learner's own local hour, not the server's.
     *
     * @covers \mod_streak\local\reminder::process
     */
    public function test_the_hour_is_evaluated_in_the_learner_timezone(): void {
        $this->resetAfterTest();
        // 18:00 in Sydney is well before 18:00 UTC.
        $user = $this->getDataGenerator()->create_user(['timezone' => 'Australia/Sydney']);
        $streak = $this->make_streak(['reminderhour' => 18]);

        $sydney = new \DateTimeZone('Australia/Sydney');
        $yesterday = (new \DateTimeImmutable('yesterday 10:00', $sydney))->getTimestamp();
        evaluator::credit($streak, (int) $user->id, $yesterday);

        $sink = $this->redirectMessages();
        $now = (new \DateTimeImmutable('today 18:30', $sydney))->getTimestamp();
        $state = state::get_or_create($streak->id, (int) $user->id);
        $this->assertTrue(reminder::process($streak, $state, $now),
            'the learner local hour was not used');
        $sink->close();
    }

    /**
     * Still deduped to one reminder a day once the hour has passed.
     *
     * @covers \mod_streak\local\reminder::process
     */
    public function test_still_only_one_reminder_a_day(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['timezone' => 'UTC']);
        $streak = $this->make_streak(['reminderhour' => 18]);
        $this->put_at_risk($streak, (int) $user->id);

        $tz = new \DateTimeZone('UTC');
        $sink = $this->redirectMessages();

        foreach ([18, 19, 20] as $hour) {
            $state = state::get_or_create($streak->id, (int) $user->id);
            reminder::process($streak, $state, (new \DateTimeImmutable("today {$hour}:00", $tz))->getTimestamp());
        }
        $this->assertCount(1, $sink->get_messages(), 'the daily dedupe stopped working');
        $sink->close();
    }

    /**
     * No reminder on a weekday that does not count.
     *
     * @covers \mod_streak\local\evaluator::reminder_status
     */
    public function test_no_reminder_on_a_day_that_does_not_count(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['timezone' => 'UTC']);
        // Every weekday switched off: today, whatever it is, does not count.
        $streak = $this->make_streak(['reminderhour' => 0, 'activedays' => '0000000']);
        $this->put_at_risk($streak, (int) $user->id);

        $now = (new \DateTimeImmutable('today 23:00', new \DateTimeZone('UTC')))->getTimestamp();
        $state = state::get_or_create($streak->id, (int) $user->id);
        $this->assertNull(evaluator::reminder_status($streak, $state, $now),
            'a reminder was raised on a day that does not count');
    }

    /**
     * The early heads-up never applies to a daily cadence, which has no earlier day.
     *
     * @covers \mod_streak\local\evaluator::reminder_status
     */
    public function test_early_headsup_does_not_apply_to_a_daily_cadence(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['timezone' => 'UTC']);
        $streak = $this->make_streak(['earlyheadsup' => 1, 'cadenceperiod' => 'daily', 'reminderhour' => 0]);
        $this->put_at_risk($streak, (int) $user->id);

        $now = (new \DateTimeImmutable('today 12:00', new \DateTimeZone('UTC')))->getTimestamp();
        $state = state::get_or_create($streak->id, (int) $user->id);
        $status = evaluator::reminder_status($streak, $state, $now);

        // A daily period is make-or-break by definition; it must never be flagged as "early".
        if ($status !== null) {
            $this->assertFalse((bool) $status->isearly, 'a daily cadence produced an early heads-up');
        } else {
            $this->assertNull($status);
        }
    }
}
