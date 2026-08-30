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

$string['activedays'] = 'Days that count';
$string['activedays_help'] = 'The weekdays on which a learner is expected to practise. A day that is switched off behaves exactly like a holiday: nothing is expected, and doing nothing cannot cost the learner their streak. Untick Saturday and Sunday for a working-week streak. At least one day must stay switched on.';
$string['boardtruncated'] = 'Showing the top {$a->shown} of {$a->total}.';
$string['cadencegoal'] = 'Qualifying days per period';
$string['cadencegoal_help'] = 'The minimum number of days within each period on which the learner must do a qualifying activity. Ignored for the Daily period (always 1).';
$string['cadenceperiod'] = 'Streak period';
$string['cadenceperiod_help'] = 'How often a learner must practice to keep the streak: every day, or a number of days within each week, fortnight, or month.';
$string['currentstreak'] = 'Current streak';
$string['customenddate'] = 'Streak end date';
$string['earlyheadsup'] = 'Send an early heads-up';
$string['earlyheadsup_help'] = 'Send one extra reminder the day before the last possible day, so the learner still has room to act. Only applies to weekly, fortnightly and monthly periods; a daily period has no earlier day.';
$string['enddate:course'] = 'Follow the course end date';
$string['enddate:custom'] = 'A fixed date';
$string['enddate:none'] = 'Never ends';
$string['enddatemode'] = 'When the streak stops';
$string['enddatemode_help'] = 'When streaks stop being counted. After the end date each learner keeps their final standing, which is what the leaderboard then shows.';
$string['excluderoles'] = 'Roles to leave off the leaderboard';
$string['excluderoles_help'] = 'Anyone holding one of these roles in the course is not listed on the leaderboard and their streak is not visible to others. They still earn a streak and can see it themselves. This is on top of the staff exclusion above.';
$string['excludestaff'] = 'Leave staff off the leaderboard';
$string['excludestaff_help'] = 'Anyone who can edit the course is not listed on the leaderboard. They still earn a streak and can see it themselves.';
$string['freezecap'] = 'Maximum freezes';
$string['freezecap_help'] = 'The largest number of unused freezes a learner can hold at once. Once they reach this cap, no further freezes accrue until one is used.';
$string['freezerate'] = 'Freeze accrual rate';
$string['freezerate_help'] = 'A learner earns one streak freeze for every this many successful periods, up to the maximum below. A freeze automatically forgives a single missed period, so an occasional gap does not reset the streak. Enter 0 to switch freezes off.';
$string['headlinerank'] = '#{$a->rank} of {$a->total}';
$string['leaderboard'] = 'Streak leaderboard';
$string['lifecycle'] = 'Lifecycle';
$string['longeststreak'] = 'Longest streak';
$string['messageprovider:streakreminder'] = 'Streak reminders';
$string['milestonecourse'] = '{$a->value} of {$a->goal} days';
$string['milestoneweekly'] = 'Day {$a->value} of 7 this week';
$string['mode:anycompletion'] = 'Any activity completion';
$string['mode:courseprogress'] = 'Course progress advanced';
$string['mode:login'] = 'Login only';
$string['modfilterexclude'] = 'Activities that do not count';
$string['modfilterexclude_help'] = 'One activity-module name per line (for example "forum") to leave out when deciding whether a learner practised. Only applies when a day is earned by any activity completion.';
$string['modulename'] = 'Solin Streaks';
$string['modulename_help'] = 'Solin Streaks adds a daily-practice learning streak to a course: a per-learner streak counter, streak freeze, reminders, and a per-course leaderboard.';
$string['modulenameplural'] = 'Solin Streaks activities';
$string['nobodyyet'] = 'No streaks yet. Be the first!';
$string['notstarted'] = 'Streak not started yet';
$string['onlyoneinstance'] = 'Only one Solin Streaks activity is allowed per course.';
$string['optedoutnotice'] = 'You have opted out: you are not listed, and others cannot see your streak. Your own streak is visible only to you.';
$string['optin'] = 'Show me on the leaderboard';
$string['optout'] = 'Hide me from the leaderboard';
$string['participant'] = 'Participant';
$string['period:daily'] = 'Daily';
$string['period:fortnightly'] = 'Fortnightly';
$string['period:monthly'] = 'Monthly';
$string['period:weekly'] = 'Weekly';
$string['pluginadministration'] = 'Solin Streaks administration';
$string['pluginname'] = 'Solin Streaks';

$string['privacy:metadata:streak_day'] = 'The ledger of days on which the learner performed a qualifying action.';
$string['privacy:metadata:streak_day:day'] = 'The calendar day (in the learner timezone) that was credited.';
$string['privacy:metadata:streak_day:userid'] = 'The user who earned the qualifying day.';
$string['privacy:metadata:streak_state'] = 'Per-learner streak state (current and longest streak, freezes, progress, opt-out, final standing).';
$string['privacy:metadata:streak_state:currentstreak'] = 'The learner\'s current streak.';
$string['privacy:metadata:streak_state:userid'] = 'The user the streak belongs to.';
$string['progressthisperiod'] = '{$a->met} of {$a->goal} days this period';
$string['qualifymode'] = 'What counts as a day';
$string['qualifymode_help'] = 'Which learner action counts toward the streak on a given day.';
$string['rank'] = 'Rank';
$string['reminder:body'] = 'You need {$a->needed} more qualifying day(s) and have {$a->remaining} day(s) left to keep your streak in "{$a->name}". Practice today!';
$string['reminder:subject'] = 'Keep your streak alive';
$string['reminderhour'] = 'Reminder time';
$string['reminderhour_help'] = 'The learner is reminded at or after this time in their own timezone, on the day their streak is at risk. Reminders are sent at most once a day, and only to learners who have not switched them off in their notification preferences.';
$string['reminders'] = 'Reminders';
$string['restoreduplicatenotice'] = 'This course already contains a Solin Streaks activity. The restored activity has been kept so none of its data is lost, but only the original activity records streaks. Remove one of the two so learners see a single, working streak.';
$string['rewardbreaks'] = 'Let practice during a break still count';
$string['rewardbreaks_help'] = 'Normally a holiday or a day that does not count is simply skipped, and the streak neither grows nor breaks. Switch this on to let a learner who practises anyway grow their streak on such a day.';
$string['settings:activedays_help'] = 'The starting value for the "Days that count" setting on a new activity. Seven characters of 1 or 0 in Monday-to-Sunday order, so 1111100 means the working week only.';
$string['settings:breakscalendar'] = 'Site-wide breaks calendar';
$string['settings:breakscalendar_help'] = 'Holiday and term-break date ranges that apply to every course on the site. Enter one range per line as two ISO dates separated by a comma: "YYYY-MM-DD, YYYY-MM-DD". During a break, learners keep their streak without doing anything and reminders pause. Lines starting with # are ignored. This calendar applies to every Solin Streaks activity on the site.';
$string['settings:defaults'] = 'Default settings for new Solin Streaks activities';
$string['settings:freezecap'] = 'Maximum freezes';
$string['settings:freezecap_help'] = 'The largest number of unused freezes a learner can hold at once. Once they reach this cap, no further freezes accrue until one is used. This is the starting value for a new activity; each activity can change it, and changing this setting does not affect activities that already exist.';
$string['settings:freezerate'] = 'Freeze accrual rate';
$string['settings:freezerate_help'] = 'A learner earns one streak freeze for every this many successful periods, up to the maximum below. A freeze automatically forgives a single missed period, so an occasional gap does not reset the streak. Enter 0 to switch freezes off. This is the starting value for a new activity; each activity can change it, and changing this setting does not affect activities that already exist.';
$string['settings:reminderhour'] = 'Reminder hour';
$string['settings:reminderhour_help'] = 'The hour of day (0-23) at which the daily check for at-risk streaks runs and make-or-break reminders are sent. Learners cannot choose their own hour. They can only turn these reminders off altogether, in their own notification preferences. Delivery also respects quiet hours. This is the starting value for a new activity; each activity can change it, and changing this setting does not affect activities that already exist.';
$string['streak:addinstance'] = 'Add a new Solin Streaks activity';
$string['streak:view'] = 'View own streak';
$string['streak:viewleaderboard'] = 'View the streak leaderboard';
$string['task:rollover'] = 'Solin Streaks roll-over and reminders';
$string['tolerance'] = 'Tolerance';



$string['unitdays'] = 'day streak';
$string['unitfortnights'] = 'fortnight streak';
$string['unitmonths'] = 'month streak';
$string['unitweeks'] = 'week streak';
$string['youlabel'] = 'You';
$string['yourstreak'] = 'Your streak (visible only to you): {$a}';
