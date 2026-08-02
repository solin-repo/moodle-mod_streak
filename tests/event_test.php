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

use mod_streak\output\mobile;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/streak/lib.php');

/**
 * Tests for the mod_streak course module viewed event.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class event_test extends \advanced_testcase {
    /**
     * streak_view() triggers a well-formed course module viewed event.
     *
     * @covers ::streak_view
     * @covers \mod_streak\event\course_module_viewed
     */
    public function test_streak_view_triggers_event(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module = $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);
        $streak = $DB->get_record('streak', ['id' => $module->id], '*', MUST_EXIST);
        $cm = get_coursemodule_from_id('streak', $module->cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $this->setUser($student);

        $sink = $this->redirectEvents();
        streak_view($streak, $course, $cm, $context);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(\mod_streak\event\course_module_viewed::class, $event);
        $this->assertSame('streak', $event->objecttable);
        $this->assertEquals($streak->id, $event->objectid);
        $this->assertEquals($context->id, $event->contextid);
        $this->assertEquals('r', $event->crud);
        $this->assertEquals($event::LEVEL_PARTICIPATING, $event->edulevel);
        $this->assertEquals($student->id, $event->userid);
        $this->assertEquals(
            new \moodle_url('/mod/streak/view.php', ['id' => $cm->id]),
            $event->get_url()
        );
    }

    /**
     * The inline course-page render is the activity view, so it must log module access.
     *
     * @covers ::streak_cm_info_view
     */
    public function test_cm_info_view_logs_module_access(): void {
        global $PAGE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module = $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);

        $this->setUser($student);
        $PAGE->set_url('/');

        $sink = $this->redirectEvents();
        streak_cm_info_view(get_fast_modinfo($course)->get_cm($module->cmid));
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(\mod_streak\event\course_module_viewed::class, reset($events));
    }

    /**
     * Opening the activity in the Moodle App logs the same module access event.
     *
     * @covers \mod_streak\output\mobile
     */
    public function test_mobile_view_logs_module_access(): void {
        global $PAGE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module = $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);

        $this->setUser($student);
        $PAGE->set_url('/');

        $sink = $this->redirectEvents();
        mobile::mobile_course_view(['cmid' => $module->cmid, 'courseid' => $course->id]);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(\mod_streak\event\course_module_viewed::class, reset($events));
    }

    /**
     * The course activity index (index.php) logs an instance list viewed event.
     *
     * @covers \mod_streak\event\course_module_instance_list_viewed
     */
    public function test_instance_list_viewed_event(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);
        $coursecontext = \context_course::instance($course->id);

        $this->setUser($student);

        $sink = $this->redirectEvents();
        $event = \mod_streak\event\course_module_instance_list_viewed::create(['context' => $coursecontext]);
        $event->add_record_snapshot('course', $course);
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $triggered = reset($events);
        $this->assertInstanceOf(\mod_streak\event\course_module_instance_list_viewed::class, $triggered);
        $this->assertEquals($coursecontext->id, $triggered->contextid);
        $this->assertEquals('r', $triggered->crud);
        $this->assertEquals(
            new \moodle_url('/mod/streak/index.php', ['id' => $course->id]),
            $triggered->get_url()
        );
    }

    /**
     * A learner without the view capability never reaches the inline widget, so nothing is logged.
     *
     * @covers ::streak_cm_info_view
     */
    public function test_cm_info_view_logs_nothing_without_capability(): void {
        global $PAGE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module = $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);
        $context = \context_module::instance($module->cmid);
        $studentrole = $this->getDataGenerator()->create_role();
        assign_capability('mod/streak:view', CAP_PROHIBIT, $studentrole, $context->id, true);
        role_assign($studentrole, $student->id, $context->id);

        $this->setUser($student);
        $PAGE->set_url('/');

        $sink = $this->redirectEvents();
        streak_cm_info_view(get_fast_modinfo($course)->get_cm($module->cmid));
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(0, $events);
    }
}
