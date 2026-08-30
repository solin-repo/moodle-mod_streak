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
        $mform->setDefault('cadenceperiod', self::sitedefault('cadenceperiod', 'daily'));
        $mform->addHelpButton('cadenceperiod', 'cadenceperiod', 'mod_streak');

        $mform->addElement('text', 'cadencegoal', get_string('cadencegoal', 'mod_streak'), ['size' => 4]);
        $mform->setType('cadencegoal', PARAM_INT);
        $mform->setDefault('cadencegoal', self::sitedefault('cadencegoal', 1));
        $mform->addHelpButton('cadencegoal', 'cadencegoal', 'mod_streak');
        $mform->disabledIf('cadencegoal', 'cadenceperiod', 'eq', 'daily');

        $modes = [
            'anycompletion'  => get_string('mode:anycompletion', 'mod_streak'),
            'courseprogress' => get_string('mode:courseprogress', 'mod_streak'),
            'login'          => get_string('mode:login', 'mod_streak'),
        ];
        $mform->addElement('select', 'qualifymode', get_string('qualifymode', 'mod_streak'), $modes);
        $mform->setDefault('qualifymode', self::sitedefault('qualifymode', 'anycompletion'));
        $mform->addHelpButton('qualifymode', 'qualifymode', 'mod_streak');

        // Everything from here on has a site-level default, so most courses never touch it.
        // It sits behind the form's "Show more..." toggle to keep the section short.
        $mform->addElement(
            'textarea',
            'modfilterexclude',
            get_string('modfilterexclude', 'mod_streak'),
            ['rows' => 3, 'cols' => 40]
        );
        $mform->setType('modfilterexclude', PARAM_RAW);
        $mform->setDefault('modfilterexclude', self::sitedefault('modfilterexclude', ''));
        $mform->addHelpButton('modfilterexclude', 'modfilterexclude', 'mod_streak');
        $mform->hideIf('modfilterexclude', 'qualifymode', 'neq', 'anycompletion');
        $mform->setAdvanced('modfilterexclude');

        // Which weekdays count toward the streak.
        $daynames = self::day_names();
        $checkboxes = [];
        foreach ($daynames as $i => $label) {
            $checkboxes[] = $mform->createElement('advcheckbox', 'activeday' . $i, null, $label);
        }
        $mform->addGroup($checkboxes, 'activedaysgroup', get_string('activedays', 'mod_streak'), ' ', false);
        $mform->addHelpButton('activedaysgroup', 'activedays', 'mod_streak');
        $mform->setAdvanced('activedaysgroup');

        // How much a learner may miss before the streak resets.
        $this->add_subheading('streaktolerance', get_string('tolerance', 'mod_streak'));

        $mform->addElement('text', 'freezerate', get_string('settings:freezerate', 'mod_streak'), ['size' => 4]);
        $mform->setType('freezerate', PARAM_INT);
        $mform->setDefault('freezerate', self::sitedefault('freezerate', 4));
        $mform->addHelpButton('freezerate', 'freezerate', 'mod_streak');
        $mform->setAdvanced('freezerate');

        $mform->addElement('text', 'freezecap', get_string('settings:freezecap', 'mod_streak'), ['size' => 4]);
        $mform->setType('freezecap', PARAM_INT);
        $mform->setDefault('freezecap', self::sitedefault('freezecap', 2));
        $mform->addHelpButton('freezecap', 'freezecap', 'mod_streak');
        $mform->hideIf('freezecap', 'freezerate', 'eq', 0);
        $mform->setAdvanced('freezecap');

        $mform->addElement('advcheckbox', 'rewardbreaks', get_string('rewardbreaks', 'mod_streak'));
        $mform->setDefault('rewardbreaks', self::sitedefault('rewardbreaks', 0));
        $mform->addHelpButton('rewardbreaks', 'rewardbreaks', 'mod_streak');
        $mform->setAdvanced('rewardbreaks');

        // When the streak stops being counted.
        $this->add_subheading('streaklifecycle', get_string('lifecycle', 'mod_streak'));

        $endmodes = [
            'course' => get_string('enddate:course', 'mod_streak'),
            'none'   => get_string('enddate:none', 'mod_streak'),
            'custom' => get_string('enddate:custom', 'mod_streak'),
        ];
        $mform->addElement('select', 'enddatemode', get_string('enddatemode', 'mod_streak'), $endmodes);
        $mform->setDefault('enddatemode', self::sitedefault('enddatemode', 'course'));
        $mform->addHelpButton('enddatemode', 'enddatemode', 'mod_streak');
        $mform->setAdvanced('enddatemode');

        $mform->addElement('date_selector', 'customenddate', get_string('customenddate', 'mod_streak'), ['optional' => false]);
        $mform->hideIf('customenddate', 'enddatemode', 'neq', 'custom');
        $mform->setAdvanced('customenddate');

        // When and whether the learner is nudged.
        $this->add_subheading('streakreminders', get_string('reminders', 'mod_streak'));

        $hours = [];
        for ($h = 0; $h <= 23; $h++) {
            $hours[$h] = sprintf('%02d:00', $h);
        }
        $mform->addElement('select', 'reminderhour', get_string('settings:reminderhour', 'mod_streak'), $hours);
        $mform->setDefault('reminderhour', self::sitedefault('reminderhour', 18));
        $mform->addHelpButton('reminderhour', 'reminderhour', 'mod_streak');
        $mform->setAdvanced('reminderhour');

        $mform->addElement('advcheckbox', 'earlyheadsup', get_string('earlyheadsup', 'mod_streak'));
        $mform->setDefault('earlyheadsup', self::sitedefault('earlyheadsup', 0));
        $mform->addHelpButton('earlyheadsup', 'earlyheadsup', 'mod_streak');
        $mform->hideIf('earlyheadsup', 'cadenceperiod', 'eq', 'daily');
        $mform->setAdvanced('earlyheadsup');

        // Who appears on the board.
        $this->add_subheading('streakboard', get_string('leaderboard', 'mod_streak'));

        $mform->addElement('advcheckbox', 'excludestaff', get_string('excludestaff', 'mod_streak'));
        $mform->setDefault('excludestaff', self::sitedefault('excludestaff', 1));
        $mform->addHelpButton('excludestaff', 'excludestaff', 'mod_streak');
        $mform->setAdvanced('excludestaff');

        $roles = [];
        foreach (role_get_names(\context_course::instance($this->get_course()->id)) as $role) {
            $roles[$role->id] = $role->localname;
        }
        $rolesel = $mform->addElement('select', 'excluderoles', get_string('excluderoles', 'mod_streak'), $roles);
        $rolesel->setMultiple(true);
        $mform->addHelpButton('excluderoles', 'excluderoles', 'mod_streak');
        $mform->setAdvanced('excluderoles');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * A sub-heading inside the Solin Streaks section, hidden along with the fields it introduces.
     *
     * @param string $name Element name.
     * @param string $label Heading text.
     */
    private function add_subheading(string $name, string $label) {
        $mform = $this->_form;
        $mform->addElement('static', $name, '', html_writer::tag('h5', $label, ['class' => 'mb-0']));
        $mform->setAdvanced($name);
    }

    /**
     * Weekday labels in ISO order, Monday first.
     *
     * @return array<int, string> Keyed 1-7.
     */
    private static function day_names(): array {
        $names = [];
        // 2026-01-05 was a Monday; walking seven days from it gives ISO order in the site language.
        $monday = make_timestamp(2026, 1, 5, 12, 0, 0, 'UTC');
        for ($i = 1; $i <= 7; $i++) {
            $names[$i] = userdate($monday + ($i - 1) * DAYSECS, '%a', 'UTC');
        }
        return $names;
    }

    /**
     * The site-level default for a field, falling back to the plugin's own default.
     *
     * @param string $name Setting name.
     * @param mixed $fallback Value to use when the site has no setting stored.
     * @return mixed
     */
    private static function sitedefault(string $name, $fallback) {
        $value = get_config('mod_streak', $name);
        if ($value === false || $value === '') {
            return $fallback;
        }
        return is_int($fallback) ? (int) $value : $value;
    }

    /**
     * Split the stored activedays mask into the seven checkbox elements.
     *
     * @param array $defaultvalues Form defaults, mutated in place.
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        $mask = (string) ($defaultvalues['activedays'] ?? self::sitedefault('activedays', '1111111'));
        if (!preg_match('/^[01]{7}$/', $mask)) {
            $mask = '1111111';
        }
        for ($i = 1; $i <= 7; $i++) {
            $defaultvalues['activeday' . $i] = (int) $mask[$i - 1];
        }

        if (!empty($defaultvalues['excluderoles'])) {
            $defaultvalues['excluderoles'] = explode(',', $defaultvalues['excluderoles']);
        }
    }

    /**
     * Rebuild the activedays mask and the excluded-role list from the submitted form.
     *
     * @param stdClass $data Submitted data, mutated in place.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);

        $mask = '';
        for ($i = 1; $i <= 7; $i++) {
            $field = 'activeday' . $i;
            $mask .= empty($data->$field) ? '0' : '1';
            unset($data->$field);
        }
        // Refuse an empty week: with no active day nothing could ever qualify.
        $data->activedays = ($mask === '0000000') ? '1111111' : $mask;

        if (isset($data->excluderoles) && is_array($data->excluderoles)) {
            $data->excluderoles = implode(',', array_map('intval', $data->excluderoles));
        }
    }

    /**
     * Enforce a single Solin Streaks activity per course (§2 of the spec).
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        global $CFG;

        require_once($CFG->dirroot . '/mod/streak/lib.php');

        $errors = parent::validation($data, $files);

        $courseid = (int) $this->_course->id;
        $instanceid = empty($this->_instance) ? 0 : (int) $this->_instance;
        if (streak_course_has_instance($courseid, $instanceid)) {
            $errors['name'] = get_string('onlyoneinstance', 'mod_streak');
        }

        return $errors;
    }
}
