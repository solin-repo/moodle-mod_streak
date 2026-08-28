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
    // Site-level defaults for new activities. Per the core admin-settings convention, each setting's
    // help text is passed as its description (the 3rd argument) and shown inline below the field, the
    // same way core does (e.g. grade settings use "<name>_help" as the description). Admin settings do
    // not use the activity form's "?" help popups.
    $settings->add(new admin_setting_heading(
        'mod_streak/defaultsheading',
        get_string('settings:defaults', 'mod_streak'),
        ''
    ));

    $settings->add(new admin_setting_configselect(
        'mod_streak/cadenceperiod',
        get_string('cadenceperiod', 'mod_streak'),
        get_string('cadenceperiod_help', 'mod_streak'),
        'daily',
        [
            'daily'       => get_string('period:daily', 'mod_streak'),
            'weekly'      => get_string('period:weekly', 'mod_streak'),
            'fortnightly' => get_string('period:fortnightly', 'mod_streak'),
            'monthly'     => get_string('period:monthly', 'mod_streak'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'mod_streak/cadencegoal',
        get_string('cadencegoal', 'mod_streak'),
        get_string('cadencegoal_help', 'mod_streak'),
        3,
        PARAM_INT,
        4
    ));

    $settings->add(new admin_setting_configtext(
        'mod_streak/freezerate',
        get_string('settings:freezerate', 'mod_streak'),
        get_string('settings:freezerate_help', 'mod_streak'),
        4,
        PARAM_INT,
        4
    ));

    $settings->add(new admin_setting_configtext(
        'mod_streak/freezecap',
        get_string('settings:freezecap', 'mod_streak'),
        get_string('settings:freezecap_help', 'mod_streak'),
        2,
        PARAM_INT,
        4
    ));

    $settings->add(new admin_setting_configtext(
        'mod_streak/reminderhour',
        get_string('settings:reminderhour', 'mod_streak'),
        get_string('settings:reminderhour_help', 'mod_streak'),
        18,
        PARAM_INT,
        4
    ));

    $settings->add(new admin_setting_configselect(
        'mod_streak/qualifymode',
        get_string('qualifymode', 'mod_streak'),
        get_string('qualifymode_help', 'mod_streak'),
        'anycompletion',
        [
            'anycompletion'  => get_string('mode:anycompletion', 'mod_streak'),
            'courseprogress' => get_string('mode:courseprogress', 'mod_streak'),
            'login'          => get_string('mode:login', 'mod_streak'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'mod_streak/activedays',
        get_string('activedays', 'mod_streak'),
        get_string('settings:activedays_help', 'mod_streak'),
        '1111111',
        '/^[01]{7}$/',
        7
    ));

    $settings->add(new admin_setting_configselect(
        'mod_streak/enddatemode',
        get_string('enddatemode', 'mod_streak'),
        get_string('enddatemode_help', 'mod_streak'),
        'course',
        [
            'course' => get_string('enddate:course', 'mod_streak'),
            'none'   => get_string('enddate:none', 'mod_streak'),
            'custom' => get_string('enddate:custom', 'mod_streak'),
        ]
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_streak/rewardbreaks',
        get_string('rewardbreaks', 'mod_streak'),
        get_string('rewardbreaks_help', 'mod_streak'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_streak/earlyheadsup',
        get_string('earlyheadsup', 'mod_streak'),
        get_string('earlyheadsup_help', 'mod_streak'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_streak/excludestaff',
        get_string('excludestaff', 'mod_streak'),
        get_string('excludestaff_help', 'mod_streak'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'mod_streak/excluderoles',
        get_string('excluderoles', 'mod_streak'),
        get_string('excluderoles_help', 'mod_streak'),
        '',
        PARAM_SEQUENCE
    ));

    $settings->add(new admin_setting_configtextarea(
        'mod_streak/modfilterexclude',
        get_string('modfilterexclude', 'mod_streak'),
        get_string('modfilterexclude_help', 'mod_streak'),
        '',
        PARAM_RAW
    ));

    $settings->add(new \mod_streak\admin\setting_breaks_calendar(
        'mod_streak/breakscalendar',
        get_string('settings:breakscalendar', 'mod_streak'),
        get_string('settings:breakscalendar_help', 'mod_streak'),
        ''
    ));
}
