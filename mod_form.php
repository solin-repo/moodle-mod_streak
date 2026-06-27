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

/**
 * The activity settings form for mod_streak.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Solin Streaks activity settings form.
 *
 * @package    mod_streak
 */
class mod_streak_mod_form extends moodleform_mod {

    /**
     * Define the form.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'streaksettings', get_string('pluginname', 'mod_streak'));

        $periods = [
            'daily'       => get_string('period:daily', 'mod_streak'),
            'weekly'      => get_string('period:weekly', 'mod_streak'),
            'fortnightly' => get_string('period:fortnightly', 'mod_streak'),
            'monthly'     => get_string('period:monthly', 'mod_streak'),
        ];
        $mform->addElement('select', 'cadenceperiod', get_string('cadenceperiod', 'mod_streak'), $periods);
        $mform->setDefault('cadenceperiod', 'daily');
        $mform->addHelpButton('cadenceperiod', 'cadenceperiod', 'mod_streak');

        $mform->addElement('text', 'cadencegoal', get_string('cadencegoal', 'mod_streak'), ['size' => 4]);
        $mform->setType('cadencegoal', PARAM_INT);
        $mform->setDefault('cadencegoal', 1);
        $mform->addHelpButton('cadencegoal', 'cadencegoal', 'mod_streak');
        $mform->disabledIf('cadencegoal', 'cadenceperiod', 'eq', 'daily');

        $modes = [
            'anycompletion'  => get_string('mode:anycompletion', 'mod_streak'),
            'courseprogress' => get_string('mode:courseprogress', 'mod_streak'),
            'login'          => get_string('mode:login', 'mod_streak'),
        ];
        $mform->addElement('select', 'qualifymode', get_string('qualifymode', 'mod_streak'), $modes);
        $mform->setDefault('qualifymode', 'anycompletion');
        $mform->addHelpButton('qualifymode', 'qualifymode', 'mod_streak');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Enforce a single Solin Streaks activity per course (§2 of the spec).
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        $courseid = (int) $this->_course->id;
        $instanceid = empty($this->_instance) ? 0 : (int) $this->_instance;
        if ($courseid && $DB->record_exists_select('streak', 'course = :course AND id <> :id',
                ['course' => $courseid, 'id' => $instanceid])) {
            $errors['name'] = get_string('onlyoneinstance', 'mod_streak');
        }

        return $errors;
    }
}
