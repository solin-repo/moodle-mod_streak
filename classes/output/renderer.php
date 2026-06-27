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

/**
 * Plugin renderer for mod_streak.
 *
 * Themes can subclass this (theme_<name>\output\mod_streak\renderer) to change how the widget data
 * is turned into HTML, in addition to overriding the templates, pix icons, or CSS tokens.
 * See docs/streaks-theming-contract.md.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * Render the inline course-page streak widget.
     *
     * @param widget $widget The widget renderable.
     * @return string HTML.
     */
    protected function render_widget(widget $widget): string {
        return $this->render_from_template('mod_streak/widget', $widget->export_for_template($this));
    }
}
