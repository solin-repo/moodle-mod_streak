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

namespace mod_streak\local;

/**
 * Decides whether a given signal counts toward the streak, per the qualifying mode.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class qualifier {

    /** @var string Any completion of a (non-excluded) activity counts. */
    public const MODE_ANYCOMPLETION = 'anycompletion';
    /** @var string Only advancing course-completion progress counts. */
    public const MODE_COURSEPROGRESS = 'courseprogress';
    /** @var string Any login counts (vanity). */
    public const MODE_LOGIN = 'login';

    /**
     * Module types excluded by default when a course has set no explicit filter.
     *
     * @return string[]
     */
    public static function default_excluded(): array {
        return ['label', 'resource'];
    }

    /**
     * The set of module types that do NOT count for this instance.
     *
     * @param \stdClass $streak The streak instance.
     * @return string[]
     */
    public static function excluded_types(\stdClass $streak): array {
        $raw = trim((string) ($streak->modfilterexclude ?? ''));
        if ($raw === '') {
            return self::default_excluded();
        }
        return array_filter(array_map('trim', explode(',', $raw)));
    }

    /**
     * Whether a completion state value is a "completed" one (engagement-based: a fail still counts).
     *
     * @param int $state COMPLETION_* value.
     * @return bool
     */
    public static function is_completed_state(int $state): bool {
        return in_array($state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS, COMPLETION_COMPLETE_FAIL], true);
    }

    /**
     * Whether an activity completion qualifies under any-completion mode.
     *
     * @param \stdClass $streak The streak instance.
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     * @param int $completionstate The new completion state.
     * @return bool
     */
    public static function completion_qualifies(\stdClass $streak, int $courseid, int $cmid, int $completionstate): bool {
        if (!self::is_completed_state($completionstate)) {
            return false;
        }
        $modname = self::modname_for_cmid($courseid, $cmid);
        if ($modname === null) {
            return false;
        }
        return !in_array($modname, self::excluded_types($streak), true);
    }

    /**
     * Count of satisfied course-completion criteria for a user (course-progress mode).
     *
     * @param \stdClass $course Course record.
     * @param int $userid User id.
     * @return int
     */
    public static function course_progress_count(\stdClass $course, int $userid): int {
        $info = new \completion_info($course);
        if (!$info->is_enabled()) {
            return 0;
        }
        $count = 0;
        foreach ($info->get_completions($userid) as $completion) {
            if ($completion->is_complete()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Resolve a course module's type (modname) from its id.
     *
     * @param int $courseid Course id.
     * @param int $cmid Course module id.
     * @return string|null The modname, or null if it cannot be resolved.
     */
    private static function modname_for_cmid(int $courseid, int $cmid): ?string {
        try {
            $modinfo = get_fast_modinfo($courseid);
            return $modinfo->get_cm($cmid)->modname;
        } catch (\moodle_exception $e) {
            return null;
        }
    }
}
