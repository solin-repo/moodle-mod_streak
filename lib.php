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
            // The streak and leaderboard render inline on the course page, so the activity
            // has no separate view page (just like a label).
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
 * Add a new Solin Streaks instance.
 *
 * @param stdClass $data Form data (matches the streak table columns).
 * @param mod_streak_mod_form|null $mform The form.
 * @return int The new instance id.
 */
function streak_add_instance($data, $mform = null) {
    global $DB;

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
}
