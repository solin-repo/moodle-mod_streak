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

namespace mod_streak\admin;

/**
 * Admin textarea setting for a breaks (holiday) calendar, validated on save.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_breaks_calendar extends \admin_setting_configtextarea {

    /**
     * Validate the breaks-calendar text.
     *
     * @param string $data Submitted value.
     * @return true|string True if valid, else an error message.
     */
    public function validate($data) {
        $error = \mod_streak\local\breaks::validate((string) $data);
        return $error === null ? true : $error;
    }
}
