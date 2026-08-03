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
 * Library of interface functions and constants for mod_streak.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Declare which features the module supports.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed True/false for supported features, or null for unknown.
 */
function streak_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_INTERACTIVECONTENT;
        case FEATURE_NO_VIEW_LINK:
            // The streak and leaderboard render inline on the course page, so the activity has
            // no separate view page (like a label). The Moodle App shows the same inline view,
            // read-only.
            return true;
        default:
            return null;
    }
}

/**
 * Whether the activity icon is branded (keeps its own colors instead of being tinted
 * with the purpose color). The flame is a fixed-orange brand mark, so it must not be
 * recolored by the purpose filter.
 *
 * @return bool
 */
function streak_is_branded(): bool {
    return true;
}

/**
 * Whether the course already holds a Solin Streaks instance.
 *
 * One instance per course is a hard rule, not a preference: the crediting engine resolves a
 * course's streak with streak::for_course(), so only one instance can ever be credited. A second
 * one would sit at zero for every learner forever. See the spec §7.
 *
 * @param int $courseid The course id.
 * @param int $excludeid Instance id to ignore (when editing an existing instance).
 * @return bool
 */
function streak_course_has_instance(int $courseid, int $excludeid = 0): bool {
    global $DB;

    if ($courseid <= 0) {
        return false;
    }
    return $DB->record_exists_select(
        'streak',
        'course = :course AND id <> :id',
        ['course' => $courseid, 'id' => $excludeid]
    );
}

/**
 * Add a new Solin Streaks instance.
 *
 * Refuses to create a second instance in a course. mod_form::validation() already blocks this in
 * the UI with a field error; this is the backstop for every other creation path (web services,
 * CLI, generators). The restore path does not come through here — it inserts directly — and is
 * handled separately under the spec §16 policy.
 *
 * @param stdClass $data Form data (matches the streak table columns).
 * @param mod_streak_mod_form|null $mform The form.
 * @return int The new instance id.
 * @throws moodle_exception When the course already has a Solin Streaks instance.
 */
function streak_add_instance($data, $mform = null) {
    global $DB;

    if (streak_course_has_instance((int) $data->course)) {
        throw new moodle_exception('onlyoneinstance', 'mod_streak');
    }

    $data->timemodified = time();
    $id = $DB->insert_record('streak', $data);
    \mod_streak\local\streak::reset_memo();
    return $id;
}

/**
 * Update an existing Solin Streaks instance.
 *
 * @param stdClass $data Form data; $data->instance is the instance id.
 * @param mod_streak_mod_form|null $mform The form.
 * @return bool
 */
function streak_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;
    $ok = $DB->update_record('streak', $data);
    \mod_streak\local\streak::reset_memo();
    return $ok;
}

/**
 * Delete a Solin Streaks instance and its per-user data.
 *
 * @param int $id Instance id.
 * @return bool
 */
function streak_delete_instance($id) {
    global $DB;

    if (!$DB->record_exists('streak', ['id' => $id])) {
        return false;
    }
    // Per-user data is keyed by the activity instance (single-instance-per-course is enforced).
    $DB->delete_records('streak_day', ['streakid' => $id]);
    $DB->delete_records('streak_state', ['streakid' => $id]);
    $DB->delete_records('streak', ['id' => $id]);
    \mod_streak\local\streak::reset_memo();
    return true;
}

/**
 * Mark the activity as viewed: trigger the course module viewed event so module access is logged.
 *
 * Solin Streaks has no separate view page (FEATURE_NO_VIEW_LINK), so this is called from the
 * inline course-page renderer and from the Moodle App view — the two places where a learner
 * actually sees their streak — rather than from a view.php page load.
 *
 * @param stdClass $streak The streak instance record.
 * @param stdClass|null $course The course record, when the caller already has it.
 * @param stdClass|cm_info $cm The course module.
 * @param context_module $context The module context.
 */
function streak_view($streak, $course, $cm, $context) {
    $event = \mod_streak\event\course_module_viewed::create([
        'context'  => $context,
        'objectid' => $streak->id,
    ]);
    $event->add_record_snapshot('course_modules', $cm);
    if ($course) {
        $event->add_record_snapshot('course', $course);
    }
    $event->add_record_snapshot('streak', $streak);
    $event->trigger();
}

/**
 * Log that the course activity index (index.php) was viewed.
 *
 * Kept here rather than inline in index.php so the trigger is unit-testable: a script-level
 * trigger cannot be exercised by PHPUnit, so core's own instance-list event tests only check
 * the event object (see mod/h5pactivity). Behat covers index.php actually calling this.
 *
 * @param stdClass $course The course record.
 * @param context_course $context The course context.
 */
function streak_index_view($course, $context) {
    $event = \mod_streak\event\course_module_instance_list_viewed::create(['context' => $context]);
    $event->add_record_snapshot('course', $course);
    $event->trigger();
}

/**
 * Render the per-user inline streak widget on the course page (uncached, per request).
 *
 * @param cm_info $cm The course module.
 */
function streak_cm_info_view(cm_info $cm) {
    global $USER, $DB;

    if (during_initial_install()) {
        return;
    }
    $streak = $DB->get_record('streak', ['id' => $cm->instance]);
    if (!$streak) {
        return;
    }
    $context = context_module::instance($cm->id);
    if (!has_capability('mod/streak:view', $context, $USER)) {
        return;
    }
    $html = \mod_streak\output\widget::inline($streak, (int) $USER->id, time(), $cm->id);
    $cm->set_content($html, true);
    $cm->set_custom_cmlist_item(true);

    // This inline render is the activity view, so log it here (there is no view page to log from).
    streak_view($streak, null, $cm, $context);
}
