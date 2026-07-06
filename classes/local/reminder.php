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
 * Sends the adaptive "make-or-break" streak reminder via the Message API.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reminder {
    /**
     * The pure make-or-break rule: due when acting today is now required to keep the goal.
     *
     * @param int $daysremaining Days left in the period including today.
     * @param int $needed Qualifying days still required this period.
     * @return bool
     */
    public static function is_make_or_break(int $daysremaining, int $needed): bool {
        return $needed > 0 && $daysremaining === $needed;
    }

    /**
     * Evaluate and, if due, send the at-risk reminder for one learner (deduped per day).
     *
     * @param \stdClass $streak The streak instance.
     * @param \stdClass $state The learner's state.
     * @param int $now Reference time.
     * @return bool True if a reminder was sent.
     */
    public static function process(\stdClass $streak, \stdClass $state, int $now): bool {
        $status = evaluator::reminder_status($streak, $state, $now);
        if ($status === null) {
            return false;
        }
        $today = evaluator::today((int) $state->userid, $now);
        $fresh = state::get_or_create($streak->id, (int) $state->userid);
        if ((int) $fresh->lastreminderday === $today) {
            return false; // Already reminded today.
        }
        self::send($streak, $fresh, $status);
        $fresh->lastreminderday = $today;
        state::save($fresh);
        return true;
    }

    /**
     * Build and send the reminder notification.
     *
     * @param \stdClass $streak The streak instance.
     * @param \stdClass $state The learner's state.
     * @param \stdClass $status {needed, remaining}.
     */
    private static function send(\stdClass $streak, \stdClass $state, \stdClass $status): void {
        $user = \core_user::get_user((int) $state->userid, '*', MUST_EXIST);
        $a = (object) [
            'needed'    => $status->needed,
            'remaining' => $status->remaining,
            'name'      => format_string($streak->name),
        ];
        $body = get_string('reminder:body', 'mod_streak', $a);

        $message = new \core\message\message();
        $message->component = 'mod_streak';
        $message->name = 'streakreminder';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->notification = 1;
        $message->courseid = (int) $streak->course;
        $message->subject = get_string('reminder:subject', 'mod_streak');
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = \html_writer::tag('p', s($body));
        $message->smallmessage = $body;
        $message->contexturl = (new \moodle_url('/course/view.php', ['id' => $streak->course]))->out(false);
        $message->contexturlname = format_string($streak->name);

        message_send($message);
    }
}
