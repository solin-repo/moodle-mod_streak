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
 * Site-level admin settings for mod_streak.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    global $OUTPUT;

    $settings->add(new admin_setting_heading('mod_streak/defaultsheading',
        get_string('settings:defaults', 'mod_streak'), ''));

    $settings->add(new admin_setting_configselect('mod_streak/cadenceperiod',
        get_string('cadenceperiod', 'mod_streak') . $OUTPUT->help_icon('cadenceperiod', 'mod_streak'),
        '', 'daily', [
            'daily'       => get_string('period:daily', 'mod_streak'),
            'weekly'      => get_string('period:weekly', 'mod_streak'),
            'fortnightly' => get_string('period:fortnightly', 'mod_streak'),
            'monthly'     => get_string('period:monthly', 'mod_streak'),
        ]));

    $settings->add(new admin_setting_configtext('mod_streak/cadencegoal',
        get_string('cadencegoal', 'mod_streak') . $OUTPUT->help_icon('cadencegoal', 'mod_streak'),
        '', 3, PARAM_INT, 4));

    $settings->add(new admin_setting_configtext('mod_streak/freezerate',
        get_string('settings:freezerate', 'mod_streak') . $OUTPUT->help_icon('settings:freezerate', 'mod_streak'),
        get_string('settings:freezerate_desc', 'mod_streak'), 4, PARAM_INT, 4));

    $settings->add(new admin_setting_configtext('mod_streak/freezecap',
        get_string('settings:freezecap', 'mod_streak') . $OUTPUT->help_icon('settings:freezecap', 'mod_streak'),
        '', 2, PARAM_INT, 4));

    $settings->add(new admin_setting_configtext('mod_streak/reminderhour',
        get_string('settings:reminderhour', 'mod_streak') . $OUTPUT->help_icon('settings:reminderhour', 'mod_streak'),
        get_string('settings:reminderhour_desc', 'mod_streak'), 18, PARAM_INT, 4));

    $settings->add(new \mod_streak\admin\setting_breaks_calendar('mod_streak/breakscalendar',
        get_string('settings:breakscalendar', 'mod_streak') . $OUTPUT->help_icon('settings:breakscalendar', 'mod_streak'),
        get_string('settings:breakscalendar_desc', 'mod_streak'), ''));
}
