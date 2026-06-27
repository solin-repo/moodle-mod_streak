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
 * Presentation-data helpers for one leaderboard row.
 *
 * These return plain data (a medal name, avatar fields), never markup, so all rendering decisions
 * live in the templates and CSS and a theme can restyle the board without forking the plugin.
 * See docs/streaks-theming-contract.md for the override surface.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class boardrow {

    /** @var int Number of avatar color tokens defined in styles.css (.mod-streak-avatar-c0..c7). */
    private const AVATAR_COLORS = 8;

    /**
     * Medal name for a rank, or null for ranks outside the top three.
     *
     * Returns a semantic name only ('gold'/'silver'/'bronze'); the glyph and color are decided in the
     * template and CSS keyed on that name, so a theme can restyle or replace the medal without any
     * plugin change. See docs/streaks-theming-contract.md.
     *
     * @param int $rank One-based rank.
     * @return string|null 'gold', 'silver', 'bronze', or null.
     */
    public static function medal_name(int $rank): ?string {
        switch ($rank) {
            case 1:
                return 'gold';
            case 2:
                return 'silver';
            case 3:
                return 'bronze';
            default:
                return null;
        }
    }

    /**
     * Avatar data (no markup) for a user row: a real-photo URL when they have one, otherwise the
     * initials and a deterministic palette index. The template assembles the markup (see the
     * mod_streak/avatar template), so a theme can fully restyle the avatar.
     *
     * @param \stdClass $user A row carrying the user-picture fields (see leaderboard::fetch).
     * @param \renderer_base $output The active renderer (used to resolve the picture URL).
     * @return array Avatar data: haspicture, pictureurl, initials, colorindex.
     */
    public static function avatar_data(\stdClass $user, \renderer_base $output): array {
        global $PAGE;

        if (!empty($user->picture)) {
            $userpicture = new \user_picture($user);
            $userpicture->size = 100; // Request a crisp image; CSS controls the displayed size.
            return [
                'haspicture' => true,
                'pictureurl' => $userpicture->get_url($PAGE, $output)->out(false),
                'initials'   => '',
                'colorindex' => 0,
            ];
        }

        $initials = \core_text::strtoupper(\core_text::substr(trim((string) $user->firstname), 0, 1)
            . \core_text::substr(trim((string) $user->lastname), 0, 1));

        return [
            'haspicture' => false,
            'pictureurl' => '',
            'initials'   => $initials,
            'colorindex' => (int) $user->id % self::AVATAR_COLORS,
        ];
    }
}
