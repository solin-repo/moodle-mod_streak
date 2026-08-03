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

/**
 * Every source file carries the GPL boilerplate and licence markers.
 *
 * phpcs runs with --extensions=php and local_moodlecheck parses PHP only, so nothing in the
 * standard toolchain ever looks at a .mustache, .js or .scss file. A moodle.org reviewer does
 * (solin-repo/moodle-mod_streak issue #2, 2026-08-01: four templates shipped without headers).
 * This test is the regression guard those tools cannot provide.
 *
 * @package    mod_streak
 * @copyright  2026 Solin (Onno Schuit) <o.schuit@solin.nl>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class boilerplate_test extends \advanced_testcase {
    /**
     * Extensions Moodle expects to carry the boilerplate. Core does NOT put it on styles.css,
     * db/install.xml, pix/*.svg or tests/behat/*.feature, so those are deliberately absent.
     *
     * @var string[]
     */
    private const EXTENSIONS = ['php', 'mustache', 'js', 'scss'];

    /**
     * Every source file in the plugin.
     *
     * @return string[] Absolute paths.
     */
    private function source_files(): array {
        global $CFG;

        $root = $CFG->dirroot . '/mod/streak';
        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if (!in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)) {
                continue;
            }
            // Built AMD output is generated, not authored.
            if (strpos($file->getPathname(), '/amd/build/') !== false) {
                continue;
            }
            $found[] = $file->getPathname();
        }
        sort($found);
        return $found;
    }

    /**
     * The sweep must actually be looking at files, or the assertions below pass vacuously.
     *
     * @covers \mod_streak\boilerplate_test
     */
    public function test_the_sweep_finds_the_expected_files(): void {
        $files = $this->source_files();

        $this->assertGreaterThan(40, count($files), 'Source sweep found suspiciously few files.');
        $names = array_map('basename', $files);
        // One of each extension we care about must be in the set, or the check is not covering it.
        $this->assertContains('lib.php', $names);
        $this->assertContains('widget.mustache', $names);
        $this->assertContains('mobile_coursepage.mustache', $names);
    }

    /**
     * Every source file opens with the GPL boilerplate block.
     *
     * @covers \mod_streak\boilerplate_test
     */
    public function test_every_source_file_has_the_gpl_boilerplate(): void {
        global $CFG;

        $missing = [];
        foreach ($this->source_files() as $file) {
            $head = (string) file_get_contents($file, false, null, 0, 2048);
            if (strpos($head, 'This file is part of Moodle') === false) {
                $missing[] = str_replace($CFG->dirroot . '/', '', $file);
            }
        }

        $this->assertSame([], $missing, "Files missing the GPL boilerplate block:\n" . implode("\n", $missing));
    }

    /**
     * Every source file declares its copyright and licence tags explicitly.
     *
     * @covers \mod_streak\boilerplate_test
     */
    public function test_every_source_file_declares_copyright_and_licence(): void {
        global $CFG;

        $missing = [];
        foreach ($this->source_files() as $file) {
            $content = (string) file_get_contents($file);
            $relative = str_replace($CFG->dirroot . '/', '', $file);
            foreach (['@copyright', '@license'] as $tag) {
                if (strpos($content, $tag) === false) {
                    $missing[] = "$relative ($tag)";
                }
            }
        }

        $this->assertSame([], $missing, "Files missing licence markers:\n" . implode("\n", $missing));
    }
}
