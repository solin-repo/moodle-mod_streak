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

use mod_streak\local\qualifier;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\mod_streak\local\qualifier::class)]
/**
 * Unit tests for the qualifier helpers.
 *
 * @package    mod_streak
 * @covers     \mod_streak\local\qualifier
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class qualifier_test extends \advanced_testcase {
    public function test_is_completed_state(): void {
        $this->assertTrue(qualifier::is_completed_state(COMPLETION_COMPLETE));
        $this->assertTrue(qualifier::is_completed_state(COMPLETION_COMPLETE_PASS));
        $this->assertTrue(qualifier::is_completed_state(COMPLETION_COMPLETE_FAIL));
        $this->assertFalse(qualifier::is_completed_state(COMPLETION_INCOMPLETE));
    }

    public function test_excluded_types_default_and_custom(): void {
        $default = qualifier::excluded_types((object) ['modfilterexclude' => '']);
        $this->assertSame(['label', 'resource'], array_values($default));

        $custom = qualifier::excluded_types((object) ['modfilterexclude' => 'quiz, page ,']);
        $this->assertSame(['quiz', 'page'], array_values($custom));
    }

    public function test_completion_qualifies_by_state_and_type(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $label = $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $streak = (object) ['modfilterexclude' => ''];

        // A completed counting activity qualifies, a failed attempt included (engagement, not mastery).
        $this->assertTrue(qualifier::completion_qualifies($streak, (int) $course->id, (int) $page->cmid, COMPLETION_COMPLETE));
        $this->assertTrue(
            qualifier::completion_qualifies($streak, (int) $course->id, (int) $page->cmid, COMPLETION_COMPLETE_FAIL)
        );
        // Un-completing never qualifies.
        $this->assertFalse(
            qualifier::completion_qualifies($streak, (int) $course->id, (int) $page->cmid, COMPLETION_INCOMPLETE)
        );
        // A label is excluded by default.
        $this->assertFalse(
            qualifier::completion_qualifies($streak, (int) $course->id, (int) $label->cmid, COMPLETION_COMPLETE)
        );
        // A course module that does not exist in the course cannot be resolved and does not qualify.
        $this->assertFalse(qualifier::completion_qualifies($streak, (int) $course->id, 999999, COMPLETION_COMPLETE));
    }

    public function test_course_progress_count_is_zero_without_completion_tracking(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 0]);
        $user = $this->getDataGenerator()->create_and_enrol($course);

        $this->assertSame(0, qualifier::course_progress_count($course, (int) $user->id));
    }

    public function test_course_progress_count_counts_completed_criteria(): void {
        global $CFG;
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');
        $this->resetAfterTest();
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
        $user = $this->getDataGenerator()->create_and_enrol($course);

        $this->assertSame(0, qualifier::course_progress_count($course, (int) $user->id));

        // Satisfy one of the two criteria.
        $info = new \completion_info($course);
        $completions = array_values($info->get_completions((int) $user->id));
        $completions[0]->mark_complete();

        $this->assertSame(1, qualifier::course_progress_count($course, (int) $user->id));
    }
}
