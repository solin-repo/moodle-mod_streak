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

namespace mod_streak\output;

use mod_streak\local\boardrow;
use mod_streak\local\cadence;
use mod_streak\local\evaluator;
use mod_streak\local\leaderboard;
use mod_streak\local\milestone;
use mod_streak\local\state;
use renderable;
use renderer_base;
use templatable;

/**
 * The per-learner inline course-page streak widget (the learner's own streak plus the leaderboard).
 *
 * A templatable renderable: export_for_template() returns pure data only, so every visual decision
 * lives in the mod_streak/widget (and mod_streak/avatar) templates and in styles.css. A theme can
 * override the template, the renderer, the pix icons, or the CSS tokens without touching the plugin.
 * See docs/streaks-theming-contract.md for the documented override surface.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class widget implements renderable, templatable {
    /** @var int How many leaderboard rows to show inline on the course page (bounded to avoid
     * rendering an unbounded table; a note is shown when the board is longer than this). */
    private const INLINE_ROWS = 50;

    /** @var \stdClass The streak instance. */
    private \stdClass $streak;

    /** @var int The viewing user's id. */
    private int $userid;

    /** @var int Reference time. */
    private int $now;

    /** @var int Course module id. */
    private int $cmid;

    /**
     * Constructor.
     *
     * @param \stdClass $streak The streak instance.
     * @param int $userid The viewing user's id.
     * @param int $now Reference time.
     * @param int $cmid Course module id.
     */
    public function __construct(\stdClass $streak, int $userid, int $now, int $cmid) {
        $this->streak = $streak;
        $this->userid = $userid;
        $this->now = $now;
        $this->cmid = $cmid;
    }

    /**
     * Convenience: render the inline widget for a learner through the plugin renderer (so theme
     * renderer/template overrides apply).
     *
     * @param \stdClass $streak The streak instance.
     * @param int $userid The viewing user's id.
     * @param int $now Reference time.
     * @param int $cmid Course module id.
     * @return string HTML.
     */
    public static function inline(\stdClass $streak, int $userid, int $now, int $cmid): string {
        global $PAGE;

        $renderer = $PAGE->get_renderer('mod_streak');
        return $renderer->render(new self($streak, $userid, $now, $cmid));
    }

    /**
     * Export the widget data for the template. Pure data only (no HTML).
     *
     * @param renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $streak = $this->streak;
        $userid = $this->userid;
        $context = \context_module::instance($this->cmid);

        $state = state::get_or_create($streak->id, $userid);
        $display = evaluator::display_streak($streak, $state, $this->now);
        $state = state::get_or_create($streak->id, $userid); // Reload after any lazy roll-over.

        // The viewer's own avatar anchors the headline number to a person and visually rhymes with their
        // highlighted row on the board below ("this is me; there I am, ranked Nth").
        $me = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $myavatar = boardrow::avatar_data($me, $output);
        $myavatar['large'] = true;

        // Progress toward keeping the streak for the whole course (goal = course length): the basic widget
        // shows a slim bar; a theme can render the same percent as a ring. Only meaningful once started.
        $started = state::has_started($state);
        $ms = milestone::progress($display, milestone::goal_periods($streak));
        if ($ms['mode'] === 'course') {
            $milestonecaption = get_string(
                'milestonecourse',
                'mod_streak',
                (object) ['value' => $ms['value'], 'goal' => $ms['goal']]
            );
        } else {
            $milestonecaption = get_string('milestoneweekly', 'mod_streak', (object) ['value' => $ms['value']]);
        }
        $milestonemarkers = [];
        foreach ($ms['markers'] as $markerpercent) {
            $milestonemarkers[] = ['angle' => round($markerpercent * 3.6, 2)];
        }

        $progress = '';
        if ($streak->cadenceperiod !== cadence::DAILY) {
            $progress = get_string('progressthisperiod', 'mod_streak', (object) [
                'met'  => (int) $state->currentperioddaysmet,
                'goal' => evaluator::goal($streak),
            ]);
        }

        $optedout = ((int) $state->optout === 1);
        $toggleurl = new \moodle_url('/mod/streak/view.php', [
            'id'      => $this->cmid,
            'action'  => $optedout ? 'optin' : 'optout',
            'sesskey' => sesskey(),
        ]);

        $data = [
            'started'            => $started,
            'display'            => $display,
            'unit'               => self::unit_label($streak->cadenceperiod, $display),
            'progress'           => $progress,
            'myavatar'           => $myavatar,
            'hasmilestone'     => $started,
            'milestonepercent' => $ms['percent'],
            'milestonemode'    => $ms['mode'],
            'milestonecaption' => $milestonecaption,
            'milestonemarkers' => $milestonemarkers,
            'hasmyrank'   => false,
            'myranklabel' => '',
            'hasboard'    => false,
            'hasrows'     => false,
            'hasmore'     => false,
            'boardnote'   => '',
            'rows'        => [],
            'optout'      => $optedout,
            'toggleurl'   => $toggleurl->out(false),
        ];

        if (has_capability('mod/streak:viewleaderboard', $context, $userid)) {
            $data['hasboard'] = true;
            $board = leaderboard::fetch($streak, $context, 0, self::INLINE_ROWS);
            $rank = 0;
            foreach ($board['rows'] as $row) {
                $rank++;
                $medalname = boardrow::medal_name($rank);
                $data['rows'][] = [
                    'rank'      => $rank,
                    'ismedal'   => ($medalname !== null),
                    'medalname' => (string) $medalname,
                    'avatar'    => boardrow::avatar_data($row, $output),
                    'name'      => fullname($row),
                    'streak'    => (int) $row->displaystreak,
                    'isme'      => ((int) $row->id === $userid),
                ];
            }
            $data['hasrows'] = !empty($data['rows']);

            // Show the viewer's standing next to the headline number, using the same ordering as the
            // board so the eyebrow rank and the highlighted row always agree. Null (off this page, opted
            // out, or excluded) simply omits the rank; the avatar and "You" label still anchor the number.
            $myrank = self::rank_in($board['rows'], $userid);
            if ($myrank !== null) {
                $data['hasmyrank'] = true;
                $data['myranklabel'] = get_string('headlinerank', 'mod_streak', (object) [
                    'rank'  => $myrank,
                    'total' => (int) $board['total'],
                ]);
            }

            if ((int) $board['total'] > self::INLINE_ROWS) {
                $data['hasmore'] = true;
                $data['boardnote'] = get_string('boardtruncated', 'mod_streak', (object) [
                    'shown' => count($data['rows']),
                    'total' => (int) $board['total'],
                ]);
            }
        }

        return $data;
    }

    /**
     * The viewer's one-based rank within an ordered board page, or null if they are not on it
     * (beyond the page, opted out, or excluded). The rows are page-0 of leaderboard::fetch, so the
     * position equals the true rank for everyone shown.
     *
     * @param array $boardrows Ordered leaderboard rows keyed by user id (from leaderboard::fetch).
     * @param int $userid The viewer's user id.
     * @return int|null
     */
    public static function rank_in(array $boardrows, int $userid): ?int {
        $rank = 0;
        foreach ($boardrows as $row) {
            $rank++;
            if ((int) $row->id === $userid) {
                return $rank;
            }
        }
        return null;
    }

    /**
     * The "N-unit streak" label for a count and cadence.
     *
     * @param string $period Cadence period.
     * @param int $count Streak count.
     * @return string
     */
    private static function unit_label(string $period, int $count): string {
        switch ($period) {
            case cadence::WEEKLY:
                return get_string('unitweeks', 'mod_streak', $count);
            case cadence::FORTNIGHTLY:
                return get_string('unitfortnights', 'mod_streak', $count);
            case cadence::MONTHLY:
                return get_string('unitmonths', 'mod_streak', $count);
            default:
                return get_string('unitdays', 'mod_streak', $count);
        }
    }
}
