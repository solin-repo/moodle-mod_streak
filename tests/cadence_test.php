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

use mod_streak\local\cadence;

/**
 * Unit tests for the cadence (period) engine.
 *
 * @package    mod_streak
 * @covers     \mod_streak\local\cadence
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cadence_test extends \advanced_testcase {

    /** @var \DateTimeZone Amsterdam timezone used across the cases. */
    private \DateTimeZone $ams;

    protected function setUp(): void {
        parent::setUp();
        $this->ams = new \DateTimeZone('Europe/Amsterdam');
    }

    /**
     * Build an epoch from a local datetime string in a timezone.
     *
     * @param string $datetime e.g. '2026-06-17 10:00'.
     * @param \DateTimeZone $tz Timezone.
     * @return int Epoch seconds.
     */
    private function ts(string $datetime, \DateTimeZone $tz): int {
        return (new \DateTimeImmutable($datetime, $tz))->getTimestamp();
    }

    public function test_day_number_respects_timezone(): void {
        // 23:30 UTC on the 17th is 01:30 (CEST) on the 18th in Amsterdam.
        $ts = $this->ts('2026-06-17 23:30', new \DateTimeZone('UTC'));
        $this->assertSame(20260618, cadence::day_number($ts, $this->ams));
        $this->assertSame(20260617, cadence::day_number($ts, new \DateTimeZone('UTC')));
    }

    public function test_daily_period(): void {
        $now = $this->ts('2026-06-17 10:00', $this->ams);
        $p = cadence::period(cadence::DAILY, $now, $this->ams);
        $this->assertSame(20260617, $p->startday);
        $this->assertSame(20260617, $p->endday);
        $this->assertSame(1, $p->days);
        $this->assertSame(1, cadence::days_remaining($p, $now, $this->ams));
    }

    public function test_weekly_monday_start(): void {
        // 2026-06-17 is a Wednesday; Monday-start week is Mon 15 .. Sun 21.
        $now = $this->ts('2026-06-17 10:00', $this->ams);
        $p = cadence::period(cadence::WEEKLY, $now, $this->ams, 0, 1);
        $this->assertSame(20260615, $p->startday);
        $this->assertSame(20260621, $p->endday);
        $this->assertSame(7, $p->days);
        // Wed -> Sun inclusive = 5 days remaining.
        $this->assertSame(5, cadence::days_remaining($p, $now, $this->ams));
    }

    public function test_weekly_sunday_start(): void {
        // Sunday-start week containing Wed 2026-06-17 is Sun 14 .. Sat 20.
        $now = $this->ts('2026-06-17 10:00', $this->ams);
        $p = cadence::period(cadence::WEEKLY, $now, $this->ams, 0, 0);
        $this->assertSame(20260614, $p->startday);
        $this->assertSame(20260620, $p->endday);
        $this->assertSame(7, $p->days);
    }

    public function test_fortnightly_windows(): void {
        $anchor = $this->ts('2026-06-01 00:00', $this->ams);

        // 16 days after the anchor -> second window (15th-28th).
        $now = $this->ts('2026-06-17 10:00', $this->ams);
        $p = cadence::period(cadence::FORTNIGHTLY, $now, $this->ams, $anchor);
        $this->assertSame(20260615, $p->startday);
        $this->assertSame(20260628, $p->endday);
        $this->assertSame(14, $p->days);

        // 9 days after the anchor -> first window (1st-14th).
        $now0 = $this->ts('2026-06-10 10:00', $this->ams);
        $p0 = cadence::period(cadence::FORTNIGHTLY, $now0, $this->ams, $anchor);
        $this->assertSame(20260601, $p0->startday);
        $this->assertSame(20260614, $p0->endday);
    }

    public function test_monthly_windows(): void {
        $anchor = $this->ts('2026-01-15 00:00', $this->ams);

        $now = $this->ts('2026-03-20 10:00', $this->ams);
        $p = cadence::period(cadence::MONTHLY, $now, $this->ams, $anchor);
        $this->assertSame(20260315, $p->startday);
        $this->assertSame(20260414, $p->endday);
        $this->assertSame(31, $p->days);

        // The last day of the window before the anchor day rolls over.
        $boundary = $this->ts('2026-02-14 23:00', $this->ams);
        $pb = cadence::period(cadence::MONTHLY, $boundary, $this->ams, $anchor);
        $this->assertSame(20260115, $pb->startday);
        $this->assertSame(20260214, $pb->endday);
        $this->assertSame(1, cadence::days_remaining($pb, $boundary, $this->ams));
    }

    public function test_weekly_spans_dst_change(): void {
        // EU DST springs forward on Sun 2026-03-29. The Monday-start week Mon 23 .. Sun 29
        // must still be 7 calendar days, and day counting must not lose the missing hour.
        $now = $this->ts('2026-03-25 12:00', $this->ams);
        $p = cadence::period(cadence::WEEKLY, $now, $this->ams, 0, 1);
        $this->assertSame(20260323, $p->startday);
        $this->assertSame(20260329, $p->endday);
        $this->assertSame(7, $p->days);
        // Wed 25 -> Sun 29 inclusive = 5, despite the DST transition in the window.
        $this->assertSame(5, cadence::days_remaining($p, $now, $this->ams));
    }
}
