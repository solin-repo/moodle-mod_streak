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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/streak/lib.php');

/**
 * Site-level defaults, and per-activity overrides of them.
 *
 * Until 0.10.0 the settings page presented defaults that nothing read: streak_add_instance() inserted
 * the submitted form data unchanged, so every activity took the install.xml column defaults and the
 * settings had no effect at all. These tests pin both halves of the contract: a site setting seeds a
 * new activity, and the activity's own value wins from then on.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sitedefaults_test extends \advanced_testcase {
    /**
     * Every configurable field, with a site value that differs from the install.xml default.
     *
     * @return array<string, array{0: string, 1: mixed, 2: mixed}> name => [field, sitevalue, columndefault]
     */
    public static function setting_provider(): array {
        return [
            'cadenceperiod'    => ['cadenceperiod', 'weekly', 'daily'],
            'cadencegoal'      => ['cadencegoal', 5, 1],
            'qualifymode'      => ['qualifymode', 'login', 'anycompletion'],
            'freezerate'       => ['freezerate', 7, 4],
            'freezecap'        => ['freezecap', 3, 2],
            'reminderhour'     => ['reminderhour', 21, 18],
            'earlyheadsup'     => ['earlyheadsup', 1, 0],
            'rewardbreaks'     => ['rewardbreaks', 1, 0],
            'excludestaff'     => ['excludestaff', 0, 1],
            'enddatemode'      => ['enddatemode', 'none', 'course'],
            'activedays'       => ['activedays', '1111100', '1111111'],
        ];
    }

    /**
     * A site setting supplies the value for a newly created activity.
     *
     * @dataProvider setting_provider
     * @param string $field Instance field name.
     * @param mixed $sitevalue Value configured at site level.
     * @param mixed $columndefault The install.xml default the field would otherwise take.
     * @covers ::streak_add_instance
     * @covers ::streak_apply_site_defaults
     */
    public function test_site_setting_seeds_a_new_activity(string $field, $sitevalue, $columndefault): void {
        global $DB;
        $this->resetAfterTest();

        set_config($field, $sitevalue, 'mod_streak');
        $course = $this->getDataGenerator()->create_course();

        // Deliberately NOT the module generator: it supplies its own defaults for several of these
        // fields, which would mask whether the site setting was applied. This is the minimal record
        // the activity form submits, so every unset field must come from the site settings.
        $id = streak_add_instance((object) [
            'course'      => $course->id,
            'name'        => 'Your daily streak',
            'intro'       => '',
            'introformat' => FORMAT_HTML,
        ]);

        $got = $DB->get_field('streak', $field, ['id' => $id], MUST_EXIST);
        $this->assertEquals($sitevalue, $got, "site setting for {$field} did not reach the new activity");
        $this->assertNotEquals($columndefault, $got, "{$field} fell back to the column default");
    }

    /**
     * A value supplied for the activity beats the site setting.
     *
     * @dataProvider setting_provider
     * @param string $field Instance field name.
     * @param mixed $sitevalue Value configured at site level.
     * @param mixed $columndefault Unused here.
     * @covers ::streak_apply_site_defaults
     */
    public function test_activity_value_overrides_the_site_setting(string $field, $sitevalue, $columndefault): void {
        global $DB;
        $this->resetAfterTest();

        set_config($field, $sitevalue, 'mod_streak');
        // Pick something that is neither the site value nor the column default.
        $override = is_numeric($sitevalue) ? ((int) $sitevalue + 1) : ($field === 'activedays' ? '1010100' : 'none');
        if ($field === 'cadenceperiod') {
            $override = 'monthly';
        } else if ($field === 'qualifymode') {
            $override = 'courseprogress';
        } else if ($field === 'enddatemode') {
            $override = 'custom';
        }

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('streak', ['course' => $course->id, $field => $override]);

        $this->assertEquals($override, $DB->get_field('streak', $field, ['id' => $cm->id], MUST_EXIST),
            "the activity's own {$field} did not win over the site setting");
    }

    /**
     * Changing a site setting leaves activities that already exist alone.
     *
     * @covers ::streak_apply_site_defaults
     */
    public function test_changing_a_site_setting_does_not_touch_existing_activities(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('freezerate', 7, 'mod_streak');
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('streak', ['course' => $course->id]);
        $this->assertSame(7, (int) $DB->get_field('streak', 'freezerate', ['id' => $cm->id], MUST_EXIST));

        set_config('freezerate', 2, 'mod_streak');
        $this->assertSame(7, (int) $DB->get_field('streak', 'freezerate', ['id' => $cm->id], MUST_EXIST),
            'an existing activity followed a later site-setting change');
    }

    /**
     * With nothing configured at site level the documented column defaults apply.
     *
     * @covers ::streak_apply_site_defaults
     */
    public function test_unset_config_falls_back_to_the_column_defaults(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $id = streak_add_instance((object) [
            'course'      => $course->id,
            'name'        => 'Your daily streak',
            'intro'       => '',
            'introformat' => FORMAT_HTML,
        ]);
        $row = $DB->get_record('streak', ['id' => $id], '*', MUST_EXIST);

        $this->assertSame('daily', $row->cadenceperiod);
        $this->assertSame(4, (int) $row->freezerate);
        $this->assertSame(2, (int) $row->freezecap);
        $this->assertSame(18, (int) $row->reminderhour);
        $this->assertSame('1111111', $row->activedays);
    }

    /**
     * The two text settings seed a new activity too.
     *
     * They are kept out of the data provider because their "different" value is a string rather than
     * a number, but they are settings like any other and must not be exempt from the contract.
     *
     * @covers ::streak_apply_site_defaults
     */
    public function test_text_settings_are_seeded(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('excluderoles', '3,5', 'mod_streak');
        set_config('modfilterexclude', "forum\nchoice", 'mod_streak');

        $course = $this->getDataGenerator()->create_course();
        $id = streak_add_instance((object) [
            'course' => $course->id, 'name' => 'S', 'intro' => '', 'introformat' => FORMAT_HTML,
        ]);
        $row = $DB->get_record('streak', ['id' => $id], '*', MUST_EXIST);

        $this->assertSame('3,5', $row->excluderoles);
        $this->assertSame("forum\nchoice", $row->modfilterexclude);
    }

    /**
     * An explicitly empty choice is respected rather than replaced by the site default.
     *
     * This is the difference between a setting that can be overridden and one that only looks like
     * it can: a teacher who clears "roles to leave off the leaderboard" means no roles, not
     * "whatever the site says".
     *
     * @covers ::streak_apply_site_defaults
     */
    public function test_an_explicitly_empty_value_is_not_overwritten(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('excluderoles', '3,5', 'mod_streak');
        set_config('modfilterexclude', 'forum', 'mod_streak');

        $course = $this->getDataGenerator()->create_course();
        $id = streak_add_instance((object) [
            'course' => $course->id, 'name' => 'S', 'intro' => '', 'introformat' => FORMAT_HTML,
            'excluderoles' => '',
            'modfilterexclude' => '',
        ]);
        $row = $DB->get_record('streak', ['id' => $id], '*', MUST_EXIST);

        $this->assertSame('', $row->excluderoles, 'the site default overrode a deliberate empty choice');
        $this->assertSame('', $row->modfilterexclude, 'the site default overrode a deliberate empty choice');
    }

    /**
     * The site-wide breaks calendar reaches the evaluator, and unions with an activity's own.
     *
     * breakscalendar is the one setting that always worked, but nothing proved it: no test had ever
     * set the site-level value, only the instance column.
     *
     * @covers \mod_streak\local\evaluator::ranges
     */
    public function test_the_site_breaks_calendar_reaches_the_evaluator(): void {
        $this->resetAfterTest();

        set_config('breakscalendar', "2026-12-24, 2026-12-26", 'mod_streak');

        $ranges = local\evaluator::ranges((object) ['breakscalendar' => '']);
        $this->assertCount(1, $ranges, 'the site calendar was not read');
        $this->assertTrue(local\breaks::day_in_ranges($ranges, 20261225));
        $this->assertFalse(local\breaks::day_in_ranges($ranges, 20261227));

        // An activity calendar adds to the site one rather than replacing it.
        $both = local\evaluator::ranges((object) ['breakscalendar' => "2026-07-01, 2026-07-05"]);
        $this->assertCount(2, $both, 'the two calendars were not combined');
        $this->assertTrue(local\breaks::day_in_ranges($both, 20261225), 'site range lost');
        $this->assertTrue(local\breaks::day_in_ranges($both, 20260703), 'activity range lost');
    }

    /**
     * The seeded freeze values genuinely drive the engine, not just the stored row.
     *
     * @covers \mod_streak\local\evaluator::apply_lifecycle
     */
    public function test_seeded_freeze_values_change_behaviour(): void {
        $this->resetAfterTest();

        // One freeze every two successful periods rather than every four.
        $state = (object) ['currentstreak' => 0, 'longeststreak' => 0, 'freezesavailable' => 0, 'freezesused' => 0];
        for ($i = 0; $i < 2; $i++) {
            $state = local\engine::evaluate_period($state, 1, 1, 1, false, 2, 3);
        }
        $this->assertSame(1, $state->freezesavailable, 'accrual did not follow the configured rate');

        $state = (object) ['currentstreak' => 0, 'longeststreak' => 0, 'freezesavailable' => 0, 'freezesused' => 0];
        for ($i = 0; $i < 2; $i++) {
            $state = local\engine::evaluate_period($state, 1, 1, 1, false, 4, 2);
        }
        $this->assertSame(0, $state->freezesavailable, 'accrual ignored the configured rate');
    }
}
