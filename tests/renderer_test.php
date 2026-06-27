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

/**
 * Tests for the plugin renderer (renders the widget renderable through its template).
 *
 * @package    mod_streak
 * @covers     \mod_streak\output\renderer
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class renderer_test extends \advanced_testcase {

    public function test_render_widget_outputs_html(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', ['timezone' => 'UTC']);
        /** @var \mod_streak_generator $gen */
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_streak');
        $created = $gen->create_instance(['course' => $course->id, 'excludestaff' => 0]);
        $streak = $DB->get_record('streak', ['id' => $created->id], '*', MUST_EXIST);

        $now = gmmktime(12, 0, 0, 6, 15, 2026);
        evaluator::credit($streak, (int) $student->id, $now);

        $this->setUser($student);
        $PAGE->set_url('/');
        $output = $PAGE->get_renderer('mod_streak');

        // The render() call dispatches to the protected render_widget(), which renders the mod_streak/widget template.
        $html = $output->render(new widget($streak, (int) $student->id, $now, (int) $created->cmid));

        $this->assertIsString($html);
        $this->assertStringContainsString('mod-streak-widget', $html);
    }
}
