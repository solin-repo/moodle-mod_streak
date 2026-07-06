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
 * Institutional break (holiday) calendars, adapted from block_atrisk's "breaks" mechanism.
 *
 * Breaks are whole calendar dates in the course/site timezone. For streaks we work in
 * calendar-date membership (not timestamp overlap), which avoids the off-by-one a learner
 * in another timezone would otherwise hit at break edges (see functional spec §8.7).
 *
 * Format: one range per line, "YYYY-MM-DD, YYYY-MM-DD"; blank lines and lines starting
 * with "#" are ignored.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class breaks {
    /**
     * Parse a multiline breaks calendar into a sorted list of {startday, endday} (YYYYMMDD) ranges.
     *
     * @param string $text Stored breaks-calendar config.
     * @return array List of stdClass {startday, endday}.
     * @throws \invalid_parameter_exception When a line is malformed.
     */
    public static function parse(string $text): array {
        $ranges = [];
        $lines = preg_split('/\R/', $text);
        foreach ($lines as $lineno => $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '#') === 0) {
                continue;
            }
            $parts = array_map('trim', explode(',', $trimmed));
            if (count($parts) !== 2) {
                throw new \invalid_parameter_exception(
                    'Line ' . ($lineno + 1) . ': expected "YYYY-MM-DD, YYYY-MM-DD".'
                );
            }
            $start = self::iso_to_ymd($parts[0], $lineno + 1);
            $end = self::iso_to_ymd($parts[1], $lineno + 1);
            if ($start > $end) {
                throw new \invalid_parameter_exception(
                    'Line ' . ($lineno + 1) . ': start date is after end date.'
                );
            }
            $ranges[] = (object) ['startday' => $start, 'endday' => $end];
        }
        usort($ranges, static function ($a, $b) {
            return $a->startday <=> $b->startday;
        });
        return $ranges;
    }

    /**
     * Validate a breaks-calendar string. Null on success, else a one-line message.
     *
     * @param string $text Calendar text.
     * @return string|null
     */
    public static function validate(string $text): ?string {
        try {
            self::parse($text);
            return null;
        } catch (\invalid_parameter_exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Whether a calendar day falls inside any break range.
     *
     * @param array $ranges Output of {@see self::parse()}.
     * @param int $day Day as YYYYMMDD.
     * @return bool
     */
    public static function day_in_ranges(array $ranges, int $day): bool {
        foreach ($ranges as $range) {
            if ($day >= $range->startday && $day <= $range->endday) {
                return true;
            }
        }
        return false;
    }

    /**
     * Count the calendar dates in [startday, endday] that are NOT inside any break.
     *
     * @param array $ranges Parsed ranges.
     * @param int $startday Inclusive range start, YYYYMMDD.
     * @param int $endday Inclusive range end, YYYYMMDD.
     * @return int Number of non-break days.
     */
    public static function nonbreak_days(array $ranges, int $startday, int $endday): int {
        $count = 0;
        $utc = new \DateTimeZone('UTC');
        $cursor = \DateTimeImmutable::createFromFormat('!Ymd', (string) $startday, $utc);
        $end = \DateTimeImmutable::createFromFormat('!Ymd', (string) $endday, $utc);
        while ($cursor <= $end) {
            $day = (int) $cursor->format('Ymd');
            if (!self::day_in_ranges($ranges, $day)) {
                $count++;
            }
            $cursor = $cursor->modify('+1 day');
        }
        return $count;
    }

    /**
     * Parse and validate a single ISO date into a YYYYMMDD integer.
     *
     * @param string $iso ISO 8601 date (YYYY-MM-DD).
     * @param int $lineno One-based line number for error reporting.
     * @return int YYYYMMDD.
     * @throws \invalid_parameter_exception On malformed input.
     */
    private static function iso_to_ymd(string $iso, int $lineno): int {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) {
            throw new \invalid_parameter_exception("Line {$lineno}: '{$iso}' is not a valid YYYY-MM-DD date.");
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new \invalid_parameter_exception("Line {$lineno}: '{$iso}' is not a real calendar date.");
        }
        return (int) ($m[1] . $m[2] . $m[3]);
    }
}
