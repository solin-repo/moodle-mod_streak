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

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/streak/mod_form.php');

/**
 * Tests for the activity settings form: defaults, the weekday mask, excluded roles and validation.
 *
 * Behat drives the same form through a browser (tests/behat/streak.feature); these tests pin the
 * conversions between the stored columns and the form fields, which a browser cannot observe.
 *
 * @package    mod_streak
 * @covers     \mod_streak_mod_form
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\mod_streak_mod_form::class)]
final class mod_form_test extends \advanced_testcase {
    /**
     * The "add activity" form for a course.
     *
     * @param \stdClass $course The course.
     * @return \mod_streak_mod_form
     */
    private function add_form(\stdClass $course): \mod_streak_mod_form {
        // Module forms read the course from the page, as modedit.php sets it.
        $this->use_course($course);
        [, , $cw, $cm, $data] = prepare_new_moduleinfo_data($course, 'streak', 0);
        return new \mod_streak_mod_form($data, $cw->section, $cm, $course);
    }

    /**
     * The "edit settings" form for an existing activity.
     *
     * @param \stdClass $course The course.
     * @param int $cmid The course module id.
     * @return array{0: \mod_streak_mod_form, 1: \stdClass} The form and the data it was built from.
     */
    private function edit_form(\stdClass $course, int $cmid): array {
        $this->use_course($course);
        $cm = get_coursemodule_from_id('streak', $cmid, 0, false, MUST_EXIST);
        [$cm, , , $data, $cw] = get_moduleinfo_data($cm, $course);
        $form = new \mod_streak_mod_form($data, $cw->section, $cm, $course);
        $form->set_data($data);
        return [$form, $data];
    }

    /**
     * Put the course on the page, once; the page refuses a second set once output is prepared.
     *
     * @param \stdClass $course The course.
     */
    private function use_course(\stdClass $course): void {
        global $PAGE;
        if ((int) $PAGE->course->id !== (int) $course->id) {
            $PAGE->set_course($course);
        }
    }

    /**
     * The QuickForm behind a moodleform.
     *
     * @param \mod_streak_mod_form $form The form.
     * @return \MoodleQuickForm
     */
    private function quickform(\mod_streak_mod_form $form): \MoodleQuickForm {
        return (new \ReflectionProperty($form, '_form'))->getValue($form);
    }

    public function test_a_new_activity_starts_from_the_site_defaults(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('cadenceperiod', 'weekly', 'mod_streak');
        set_config('cadencegoal', '3', 'mod_streak');
        set_config('reminderhour', '20', 'mod_streak');
        set_config('modfilterexclude', '', 'mod_streak');
        $course = $this->getDataGenerator()->create_course();

        $mform = $this->quickform($this->add_form($course));

        // Configured site values win; the int ones arrive as ints.
        $this->assertSame(['weekly'], $mform->getElement('cadenceperiod')->getValue());
        $this->assertSame(3, $mform->getElementValue('cadencegoal'));
        $this->assertSame([20], $mform->getElement('reminderhour')->getValue());
        // An unset or empty site value falls back to the plugin's own default.
        $this->assertSame(['anycompletion'], $mform->getElement('qualifymode')->getValue());
        $this->assertSame('', $mform->getElementValue('modfilterexclude'));
        $this->assertSame(4, $mform->getElementValue('freezerate'));
        $this->assertSame(1, $mform->getElementValue('excludestaff'));
    }

    public function test_the_form_offers_every_field(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $mform = $this->quickform($this->add_form($course));

        $fields = [
            'name', 'cadenceperiod', 'cadencegoal', 'qualifymode', 'modfilterexclude', 'activedaysgroup',
            'streaktolerance', 'freezerate', 'freezecap', 'rewardbreaks', 'streaklifecycle', 'enddatemode',
            'customenddate', 'streakreminders', 'reminderhour', 'earlyheadsup', 'streakboard', 'excludestaff',
            'excluderoles',
        ];
        foreach ($fields as $field) {
            $this->assertTrue($mform->elementExists($field), $field);
        }

        // Seven weekday checkboxes, Monday first, each labelled once.
        $boxes = $mform->getElement('activedaysgroup')->getElements();
        $this->assertCount(7, $boxes);
        $this->assertSame('activeday1', $boxes[0]->getName());
        $this->assertSame(userdate(make_timestamp(2026, 1, 5, 12, 0, 0, 'UTC'), '%a', 'UTC'), $boxes[0]->getText());
        $this->assertSame(userdate(make_timestamp(2026, 1, 11, 12, 0, 0, 'UTC'), '%a', 'UTC'), $boxes[6]->getText());

        // The excluded-roles list is a multi-select over the course's roles.
        $roles = $mform->getElement('excluderoles');
        $this->assertTrue($roles->getMultiple());
        $studentid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
        $labels = [];
        foreach ($roles->_options as $option) {
            $labels[(int) $option['attr']['value']] = $option['text'];
        }
        $this->assertArrayHasKey($studentid, $labels);
    }

    public function test_editing_splits_the_stored_mask_and_roles_into_fields(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('streak', [
            'course'       => $course->id,
            'activedays'   => '1111100',
            'excluderoles' => '3,5',
        ]);

        [$form] = $this->edit_form($course, (int) $module->cmid);
        $mform = $this->quickform($form);

        $this->assertSame(1, (int) $mform->getElement('activedaysgroup')->getElements()[0]->getValue());
        $this->assertSame(0, (int) $mform->getElement('activedaysgroup')->getElements()[5]->getValue());
        $this->assertEquals(['3', '5'], $mform->getElement('excluderoles')->getValue());
    }

    public function test_preprocessing_repairs_or_fills_the_mask(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $form = $this->add_form($course);

        // A malformed stored mask is shown as "every day".
        $values = ['activedays' => '12x', 'excluderoles' => ''];
        $form->data_preprocessing($values);
        for ($i = 1; $i <= 7; $i++) {
            $this->assertSame(1, $values['activeday' . $i]);
        }
        $this->assertSame('', $values['excluderoles']);

        // No stored mask at all (a new activity) takes the site default.
        set_config('activedays', '0111110', 'mod_streak');
        $values = [];
        $form->data_preprocessing($values);
        $this->assertSame(0, $values['activeday1']);
        $this->assertSame(1, $values['activeday2']);
        $this->assertSame(0, $values['activeday7']);
    }

    public function test_postprocessing_builds_the_mask_and_role_list(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $form = $this->add_form($course);

        $data = (object) [
            'activeday1' => 1, 'activeday2' => 1, 'activeday3' => 0, 'activeday4' => 1,
            'activeday5' => 1, 'activeday6' => 0, 'activeday7' => 0,
            'excluderoles' => ['3', '5'],
        ];
        $form->data_postprocessing($data);
        $this->assertSame('1101100', $data->activedays);
        $this->assertSame('3,5', $data->excluderoles);
        $this->assertFalse(property_exists($data, 'activeday1'));

        // An empty week cannot be saved: nothing could ever qualify, so it becomes every day.
        $empty = (object) [];
        $form->data_postprocessing($empty);
        $this->assertSame('1111111', $empty->activedays);
        $this->assertFalse(property_exists($empty, 'excluderoles'));
    }

    public function test_validation_allows_one_activity_per_course(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $submitted = [
            'name'                       => 'Streak',
            'course'                     => $course->id,
            'modulename'                 => 'streak',
            'instance'                   => 0,
            'coursemodule'               => 0,
            'cmidnumber'                 => '',
            'availabilityconditionsjson' => '',
        ];

        // The first activity in the course is fine.
        $errors = $this->add_form($course)->validation($submitted, []);
        $this->assertArrayNotHasKey('name', $errors);

        // Once one exists, adding a second is refused on the name field.
        $module = $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);
        $errors = $this->add_form($course)->validation($submitted, []);
        $this->assertSame(get_string('onlyoneinstance', 'mod_streak'), $errors['name']);

        // Editing the existing activity does not count it against itself.
        [$form] = $this->edit_form($course, (int) $module->cmid);
        $errors = $form->validation(['instance' => $module->id, 'coursemodule' => $module->cmid] + $submitted, []);
        $this->assertArrayNotHasKey('name', $errors);
    }

    public function test_a_submitted_form_returns_storable_data(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->use_course($course);
        [, , $cw, $cm, $data] = prepare_new_moduleinfo_data($course, 'streak', 0);

        \mod_streak_mod_form::mock_submit((array) $data + [
            'cmidnumber'    => '',
            'availabilityconditionsjson' => '',
            'name'          => 'Daily practice',
            'introeditor'   => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'cadenceperiod' => 'weekly',
            'cadencegoal'   => 2,
            'qualifymode'   => 'anycompletion',
            'activeday1'    => 1, 'activeday2' => 1, 'activeday3' => 1, 'activeday4' => 1,
            'activeday5'    => 1, 'activeday6' => 0, 'activeday7' => 0,
            'excluderoles'  => ['5'],
            'visible'       => 1,
        ]);
        $form = new \mod_streak_mod_form($data, $cw->section, $cm, $course);
        $result = $form->get_data();

        $this->assertNotNull($result);
        $this->assertSame('Daily practice', $result->name);
        $this->assertSame('1111100', $result->activedays);
        $this->assertSame('5', $result->excluderoles);
    }
}
