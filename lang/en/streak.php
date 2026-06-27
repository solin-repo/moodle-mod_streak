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
 * English strings for mod_streak (Solin Streaks).
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Solin Streaks';
$string['modulename'] = 'Solin Streaks';
$string['modulenameplural'] = 'Solin Streaks activities';
$string['modulename_help'] = 'Solin Streaks adds a daily-practice learning streak to a course: a per-learner streak counter, streak freeze, reminders, and a per-course leaderboard.';
$string['pluginadministration'] = 'Solin Streaks administration';

$string['streak:addinstance'] = 'Add a new Solin Streaks activity';
$string['streak:view'] = 'View own streak';
$string['streak:viewleaderboard'] = 'View the streak leaderboard';

// Settings.
$string['cadenceperiod'] = 'Streak period';
$string['cadenceperiod_help'] = 'How often a learner must practice to keep the streak: every day, or a number of days within each week, fortnight, or month.';
$string['cadencegoal'] = 'Qualifying days per period';
$string['cadencegoal_help'] = 'The minimum number of days within each period on which the learner must do a qualifying activity. Ignored for the Daily period (always 1).';
$string['qualifymode'] = 'What counts as a day';
$string['qualifymode_help'] = 'Which learner action counts toward the streak on a given day.';
$string['period:daily'] = 'Daily';
$string['period:weekly'] = 'Weekly';
$string['period:fortnightly'] = 'Fortnightly';
$string['period:monthly'] = 'Monthly';
$string['mode:anycompletion'] = 'Any activity completion';
$string['mode:courseprogress'] = 'Course progress advanced';
$string['mode:login'] = 'Login only';

$string['onlyoneinstance'] = 'Only one Solin Streaks activity is allowed per course.';

// Widget / leaderboard.
$string['notstarted'] = 'Streak not started yet';
$string['unitdays'] = 'day streak';
$string['unitweeks'] = 'week streak';
$string['unitfortnights'] = 'fortnight streak';
$string['unitmonths'] = 'month streak';
$string['progressthisperiod'] = '{$a->met} of {$a->goal} days this period';
$string['headlinerank'] = '#{$a->rank} of {$a->total}';
$string['milestonecourse'] = '{$a->value} of {$a->goal} days';
$string['milestoneweekly'] = 'Day {$a->value} of 7 this week';
$string['youlabel'] = 'You';
$string['boardtruncated'] = 'Showing the top {$a->shown} of {$a->total}.';
$string['leaderboard'] = 'Streak leaderboard';
$string['rank'] = 'Rank';
$string['participant'] = 'Participant';
$string['currentstreak'] = 'Current streak';
$string['longeststreak'] = 'Longest streak';
$string['nobodyyet'] = 'No streaks yet. Be the first!';
$string['optout'] = 'Hide me from the leaderboard';
$string['optin'] = 'Show me on the leaderboard';
$string['optedoutnotice'] = 'You have opted out: you are not listed, and others cannot see your streak. Your own streak is visible only to you.';
$string['yourstreak'] = 'Your streak (visible only to you): {$a}';

// Site settings.
$string['settings:defaults'] = 'Default settings for new Solin Streaks activities';
$string['settings:freezerate'] = 'Freeze accrual rate';
$string['settings:freezerate_desc'] = 'Grant one streak freeze per this many successful periods (0 to disable).';
$string['settings:freezerate_help'] = 'The default for new Solin Streaks activities. A learner earns one streak freeze for every this many successful periods, up to the maximum below. A freeze automatically forgives a single missed period so an occasional gap does not reset the streak. Enter 0 to switch freezes off. Each activity can override this default.';
$string['settings:freezecap'] = 'Maximum freezes';
$string['settings:freezecap_help'] = 'The default for new Solin Streaks activities. The largest number of unused freezes a learner can hold at once. Once they reach this cap, no further freezes accrue until one is used. Each activity can override this default.';
$string['settings:reminderhour'] = 'Reminder hour';
$string['settings:reminderhour_desc'] = 'Local hour of day (0-23) at which the daily at-risk check runs.';
$string['settings:reminderhour_help'] = 'The hour of day (0-23) at which the daily check for at-risk streaks runs and make-or-break reminders are sent. Delivery still respects each learner\'s own notification preferences and quiet hours. This is a single site-wide value.';
$string['settings:breakscalendar'] = 'Site-wide breaks calendar';
$string['settings:breakscalendar_desc'] = 'Holiday/term-break date ranges applied to every course, one per line as "YYYY-MM-DD, YYYY-MM-DD". During a break, learners keep their streak without doing anything. Lines starting with # are ignored.';
$string['settings:breakscalendar_help'] = 'Holiday and term-break date ranges that apply to every course on the site. Enter one range per line as two ISO dates separated by a comma: "YYYY-MM-DD, YYYY-MM-DD". During a break, learners keep their streak without doing anything and reminders pause. Lines starting with # are ignored. Individual activities can add their own breaks on top of these.';

// Scheduled task + reminders.
$string['task:rollover'] = 'Solin Streaks roll-over and reminders';
$string['messageprovider:streakreminder'] = 'Streak reminders';
$string['reminder:subject'] = 'Keep your streak alive';
$string['reminder:body'] = 'You need {$a->needed} more qualifying day(s) and have {$a->remaining} day(s) left to keep your streak in "{$a->name}". Practice today!';

// Privacy.
$string['privacy:metadata:streak_state'] = 'Per-learner streak state (current and longest streak, freezes, progress, opt-out, final standing).';
$string['privacy:metadata:streak_state:userid'] = 'The user the streak belongs to.';
$string['privacy:metadata:streak_state:currentstreak'] = 'The learner\'s current streak.';
$string['privacy:metadata:streak_day'] = 'The ledger of days on which the learner performed a qualifying action.';
$string['privacy:metadata:streak_day:userid'] = 'The user who earned the qualifying day.';
$string['privacy:metadata:streak_day:day'] = 'The calendar day (in the learner timezone) that was credited.';
