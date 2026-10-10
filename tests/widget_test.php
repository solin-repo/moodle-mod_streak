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
use mod_streak\output\widget;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the inline widget renderable.
 *
 * @package    mod_streak
 * @covers     \mod_streak\output\widget
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\mod_streak\output\widget::class)]
final class widget_test extends \advanced_testcase {
    public function test_rank_in_returns_board_position(): void {
        // Ordered board page keyed by user id, as leaderboard::fetch returns it.
        $rows = [
            7  => (object) ['id' => 7],
            3  => (object) ['id' => 3],
            9  => (object) ['id' => 9],
        ];

        // The headline rank must match where the viewer sits on the board: first, middle, last.
        $this->assertSame(1, widget::rank_in($rows, 7));
        $this->assertSame(2, widget::rank_in($rows, 3));
        $this->assertSame(3, widget::rank_in($rows, 9));
    }

    public function test_rank_in_returns_null_when_off_board(): void {
        $rows = [
            7 => (object) ['id' => 7],
            3 => (object) ['id' => 3],
        ];

        // A viewer not on the page (beyond the cap, opted out, or excluded) has no rank to show.
        $this->assertNull(widget::rank_in($rows, 99));
        $this->assertNull(widget::rank_in([], 7));
    }

    public function test_export_for_template_returns_pure_data(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', ['timezone' => 'UTC']);

        /** @var \mod_streak_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_streak');
        $created = $gen->create_instance([
            'course'        => $course->id,
            'cadenceperiod' => 'daily',
            'excludestaff'  => 0,
        ]);
        $streak = $DB->get_record('streak', ['id' => $created->id], '*', MUST_EXIST);

        // Give the learner a streak so they appear (ranked first) on the board.
        $now = gmmktime(12, 0, 0, 6, 15, 2026);
        evaluator::credit($streak, (int) $student->id, $now);

        $PAGE->set_url('/');
        $output = $PAGE->get_renderer('mod_streak');
        $widget = new widget($streak, (int) $student->id, $now, (int) $created->cmid);
        $data = $widget->export_for_template($output);

        // The headline avatar is structured data (not HTML) and carries the large-variant flag.
        $this->assertIsArray($data['myavatar']);
        $this->assertFalse($data['myavatar']['haspicture']);
        $this->assertSame(true, $data['myavatar']['large']);
        $this->assertNotEmpty($data['myavatar']['initials']);

        // Milestone progress is exposed. This course has no end date, so it uses the weekly fallback:
        // a 1-day streak is day 1 of 7 (1/7 ≈ 14%), with no ring markers.
        $this->assertTrue($data['hasmilestone']);
        $this->assertSame('weekly', $data['milestonemode']);
        $this->assertSame(14, $data['milestonepercent']);
        $this->assertIsArray($data['milestonemarkers']);
        $this->assertSame([], $data['milestonemarkers']);

        // The first board row is the learner: rank 1 -> gold medal, avatar is data, value matches.
        $this->assertTrue($data['hasrows']);
        $row = $data['rows'][0];
        $this->assertTrue($row['isme']);
        $this->assertTrue($row['ismedal']);
        $this->assertSame('gold', $row['medalname']);
        $this->assertIsArray($row['avatar']);
        $this->assertArrayHasKey('initials', $row['avatar']);
        $this->assertSame(1, $row['streak']);

        // No raw HTML leaked into the context: avatar values are arrays, not strings.
        $this->assertIsNotString($data['myavatar']);
    }

    /**
     * Create a streak activity in a course and return its record and cmid.
     *
     * @param \stdClass $course The course.
     * @param array $settings Instance settings.
     * @return array{0: \stdClass, 1: int} The streak record and the cmid.
     */
    private function make_streak(\stdClass $course, array $settings): array {
        global $DB;
        /** @var \mod_streak_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_streak');
        $created = $gen->create_instance(['course' => $course->id] + $settings);
        return [$DB->get_record('streak', ['id' => $created->id], '*', MUST_EXIST), (int) $created->cmid];
    }

    public function test_inline_renders_through_the_plugin_renderer(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', ['timezone' => 'UTC']);
        [$streak, $cmid] = $this->make_streak($course, ['excludestaff' => 0]);
        $now = gmmktime(12, 0, 0, 6, 15, 2026);
        evaluator::credit($streak, (int) $student->id, $now);
        $PAGE->set_url('/');

        $html = widget::inline($streak, (int) $student->id, $now, $cmid);

        $this->assertStringContainsString('streak', $html);
        $this->assertStringContainsString(get_string('unitdays', 'mod_streak', 1), $html);
        $this->assertStringContainsString(fullname($student), $html);
    }

    public function test_a_course_with_an_end_date_shows_progress_toward_the_course_length(): void {
        global $PAGE;
        $this->resetAfterTest();
        $start = gmmktime(0, 0, 0, 6, 1, 2026);
        $course = $this->getDataGenerator()->create_course([
            'startdate' => $start,
            'enddate'   => $start + 28 * DAYSECS,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', ['timezone' => 'UTC']);
        [$streak, $cmid] = $this->make_streak($course, ['enddatemode' => 'course']);
        $now = gmmktime(12, 0, 0, 6, 15, 2026);
        evaluator::credit($streak, (int) $student->id, $now);
        $PAGE->set_url('/');

        $data = (new widget($streak, (int) $student->id, $now, $cmid))->export_for_template($PAGE->get_renderer('mod_streak'));

        // A 28-day course: one day of 28, with a marker at every week (7, 14 and 21 days in).
        $this->assertSame('course', $data['milestonemode']);
        $this->assertSame(4, $data['milestonepercent']);
        $caption = get_string('milestonecourse', 'mod_streak', (object) ['value' => 1, 'goal' => 28]);
        $this->assertSame($caption, $data['milestonecaption']);
        $this->assertSame([['angle' => 90.0], ['angle' => 180.0], ['angle' => 270.0]], $data['milestonemarkers']);
        // Daily cadence has no per-period progress line.
        $this->assertSame('', $data['progress']);
    }

    public function test_a_non_daily_cadence_shows_progress_in_the_open_period(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', ['timezone' => 'UTC']);
        [$streak, $cmid] = $this->make_streak($course, ['cadenceperiod' => 'weekly', 'cadencegoal' => 3]);
        $now = gmmktime(12, 0, 0, 6, 15, 2026);
        evaluator::credit($streak, (int) $student->id, $now);
        $PAGE->set_url('/');

        $data = (new widget($streak, (int) $student->id, $now, $cmid))->export_for_template($PAGE->get_renderer('mod_streak'));

        $this->assertSame(get_string('progressthisperiod', 'mod_streak', (object) ['met' => 1, 'goal' => 3]), $data['progress']);
        $this->assertSame(get_string('unitweeks', 'mod_streak', 0), $data['unit']);
    }

    public function test_the_unit_label_follows_the_cadence(): void {
        global $PAGE;
        $this->resetAfterTest();
        $PAGE->set_url('/');
        $expected = [
            'daily'       => 'unitdays',
            'weekly'      => 'unitweeks',
            'fortnightly' => 'unitfortnights',
            'monthly'     => 'unitmonths',
        ];
        foreach ($expected as $period => $stringid) {
            $course = $this->getDataGenerator()->create_course();
            $student = $this->getDataGenerator()->create_and_enrol($course, 'student', ['timezone' => 'UTC']);
            [$streak, $cmid] = $this->make_streak($course, ['cadenceperiod' => $period, 'cadencegoal' => 1]);

            $widget = new widget($streak, (int) $student->id, time(), $cmid);
            $data = $widget->export_for_template($PAGE->get_renderer('mod_streak'));

            $this->assertSame(get_string($stringid, 'mod_streak', 0), $data['unit'], $period);
        }
    }

    public function test_a_long_board_is_truncated_with_a_note(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $students = [];
        for ($i = 0; $i < 51; $i++) {
            $students[] = $this->getDataGenerator()->create_and_enrol($course, 'student', ['timezone' => 'UTC']);
        }
        [$streak, $cmid] = $this->make_streak($course, []);
        $PAGE->set_url('/');

        $viewer = end($students);
        $data = (new widget($streak, (int) $viewer->id, time(), $cmid))->export_for_template($PAGE->get_renderer('mod_streak'));

        $this->assertTrue($data['hasboard']);
        $this->assertCount(50, $data['rows']);
        $this->assertTrue($data['hasmore']);
        $this->assertSame(get_string('boardtruncated', 'mod_streak', (object) ['shown' => 50, 'total' => 51]), $data['boardnote']);
    }

    public function test_no_board_without_the_leaderboard_capability(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', ['timezone' => 'UTC']);
        [$streak, $cmid] = $this->make_streak($course, []);
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('mod/streak:viewleaderboard', CAP_PROHIBIT, $studentrole, \context_module::instance($cmid));
        $PAGE->set_url('/');

        $data = (new widget($streak, (int) $student->id, time(), $cmid))->export_for_template($PAGE->get_renderer('mod_streak'));

        $this->assertFalse($data['hasboard']);
        $this->assertSame([], $data['rows']);
        $this->assertFalse($data['hasmyrank']);
    }
}
