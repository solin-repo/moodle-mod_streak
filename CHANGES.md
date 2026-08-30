# Changelog

All notable changes to Solin Streaks (`mod_streak`) are documented here.

## 0.10.1 — 2026-08-30

Plugin version `2026083001`.

### Changed

- **One Solin Streaks section on the activity form.** The per-activity settings were spread over six
  headings (Solin Streaks, Days that count, Tolerance, Lifecycle, Reminders, Streak leaderboard),
  which made only the first of them look like it belonged to the plugin: everything down to Common
  module settings read as core Moodle. They are now a single Solin Streaks section. Streak period,
  qualifying days per period and what counts as a day are shown; every field that has a site-level
  default sits behind Moodle's own "Show more..." toggle, with the old headings kept as
  sub-headings. No setting was added, removed or renamed.

### Fixed

- The weekday checkboxes printed each day name twice ("Mon Mon Tue Tue ..."), because the label was
  passed as both the element label and the checkbox text.

### Documentation

- **The site settings help claimed something the plugin does not do.** The reminder-hour help said
  "Delivery also respects quiet hours". There is no quiet-hours mechanism, in this plugin or in core
  Moodle messaging. The same help also described the setting as the hour at which the daily check
  runs; the check runs every hour, and the setting decides how early in the learner's own day a
  reminder may go out. Rewritten to say what actually happens.
- The activity-chooser description called this a "daily-practice" streak, which stopped being the
  whole story when weekly, fortnightly and monthly periods were added.
- Both settings screenshots were retaken: the site settings page was two releases out of date,
  showing six settings where there are now fourteen, with help text that had since been corrected.
- The README's site-settings table listed six of the fourteen settings; it now lists all of them.
  The test counts were also stale (62 tests / 3 scenarios, "CI matrix planned"), and the feature
  list did not mention the weekday mask at all.
- US spelling throughout: "practise" and its forms became "practice".

## 0.10.0 — 2026-08-28

Plugin version `2026082900`.

### Fixed

- **The site-level settings did nothing.** Cadence period, cadence goal, freeze accrual rate,
  maximum freezes and reminder hour were offered on the settings page but nothing read them:
  `streak_add_instance()` inserted the submitted form data unchanged, so every activity took the
  column defaults from `install.xml` and the settings had no effect on any activity, new or
  existing. They are now applied when an activity is created.
- **The reminder hour was never implemented.** `reminder::process()` had no hour gate at all, so a
  learner was nudged on whichever hourly cron run first found their streak at risk. Reminders are
  now held until the configured hour has arrived in the learner's own timezone.
- **`excluderoles` was never implemented.** The column existed and was carried through backup and
  restore, but no code read it. Named roles are now excluded from the leaderboard, alongside the
  existing staff exclusion.
- **`earlyheadsup` was never implemented.** It now sends one extra reminder the day before the last
  possible day, for cadences longer than daily.

### Added

- **Per-activity overrides for every setting.** Freeze accrual rate, maximum freezes, reminder hour,
  early heads-up, work-to-win during breaks, staff exclusion, excluded roles, excluded activity
  types and the streak end date are all editable on the activity itself. A site setting supplies the
  starting value for a new activity; the activity's own value is authoritative from then on.
- **Days that count.** A new per-activity weekday mask decides which days a learner is expected to
  practice. Unticking Saturday and Sunday gives a working-week streak, which is what a corporate
  audience usually wants. A switched-off day behaves exactly like a holiday: nothing is expected and
  missing it cannot cost the learner their streak, it reduces the period's effective goal, and
  work-to-win still applies if that is switched on. It combines with the breaks calendar as a union.
  Defaults to all seven days, so nothing changes for an existing activity.
- Site-level defaults for the settings that previously had none: qualifying mode, excluded activity
  types, end-date mode, work-to-win, early heads-up, staff exclusion, excluded roles and the
  weekday mask.

- **A deliberately empty choice was overwritten by the site default.** `streak_apply_site_defaults()`
  treated an empty string as "not supplied", so a teacher who cleared "roles to leave off the
  leaderboard" or "activities that do not count" silently got the site value back. An explicit empty
  value is now a real choice and is respected.

### Tests

- Per-setting coverage of the override contract at the level of behaviour, not storage: for every
  overridable setting the site is configured one way and the activity another, and the assertion is
  on what the plugin does. Storage being correct is not enough, since any runtime call to
  `get_config()` would leave the column right and the behaviour wrong.
- A structural guard asserting that no code under `classes/` reads a site setting at runtime, with
  the breaks calendar as the single documented exception. It names the offending file and setting
  when it trips.
- Behat covers the activity form field by field: every site setting is asserted to arrive as the
  default on a new activity, an override is asserted to survive a save and reopen, and the two
  fields with custom conversion code (the weekday mask and the excluded-role list) get a form
  round-trip. This is the one layer PHPUnit cannot reach: a default read from the wrong
  configuration key would leave the seeding helper correct while the form quietly offered the
  fallback, which is the original defect in a new place.

### Changed

- The site-settings help text now states that a value is the starting point for a new activity, that
  the activity can change it, and that changing the setting does not affect activities that already
  exist.

### Notes

- The breaks calendar stays site-wide only, by design. The per-activity column and the union logic
  already exist, so a per-activity calendar remains possible later without a schema change.
- Upgrade adds one column (`activedays`, default `1111111`). No other schema change.

## 0.9.2 — 2026-08-28

Plugin version `2026082800`. Documentation only, no behavior change.

### Changed

- **Corrected the site-settings help text.** The help for the freeze accrual rate and the maximum
  freezes both claimed "Each activity can override this default", and the breaks calendar help
  claimed "Individual activities can add their own breaks on top of these". None of that is
  possible: the activity form has no fields for the freeze values, the reminder hour or the breaks
  calendar. The reminder-hour help also read as though the hour were a per-learner choice; learners
  can only switch the reminders off altogether, in their own notification preferences. The section
  heading is now "Site-wide streak settings" rather than "Default settings for new Solin Streaks
  activities", which implied a per-activity override that does not exist.

## 0.9.1 — 2026-08-08

Plugin version `2026080302`. Addresses both issues raised in the Moodle plugin review.

### Fixed

- **Required activity-module events.** The module now defines and triggers
  `\mod_streak\event\course_module_viewed` (from the inline course-page render, from the Moodle App
  view, and via a `streak_view()` helper in `lib.php`) and
  `\mod_streak\event\course_module_instance_list_viewed` (from `index.php`). Both were missing.
- **File boilerplate headers.** All four Mustache templates now carry the GPL "This file is part of
  Moodle" block plus explicit `@copyright` and `@license` tags, ahead of the existing `@template`
  docblock.

### Changed

- **One Solin Streaks activity per course is now enforced.** A second instance in the same course
  produced a silently dead widget — only one instance received credit, so the other showed a
  permanently empty streak. Creation is now blocked on every path (activity form validation, direct
  `streak_add_instance()`, duplicate, and import), instance resolution is deterministic
  (oldest wins) rather than arbitrary, and a restore that would introduce a duplicate logs a warning
  in the restore log instead of silently taking over crediting.

### Testing

- PHPUnit 80 tests / 306 assertions, Behat 5 scenarios, green on Moodle 4.5, 5.0, 5.1 and 5.2 across
  PHP 8.1–8.3 and both PostgreSQL and MariaDB.

## 0.9.0 — 2026-07-06

Plugin version `2026070603`. First public release.

- Per-learner streak counter with current streak and personal best, rendered inline on the course
  page.
- Configurable cadence (daily, weekly, fortnightly, monthly) with a per-period goal.
- Streak freezes for forgiven misses, with a configurable accrual rate and cap.
- Make-or-break reminders through Moodle's Message API, sent only when a streak is genuinely at risk.
- Privacy-aware per-course leaderboard with per-learner opt-out.
- Streak lifecycle: course end date, custom end date, or evergreen, with a frozen final standing.
- Configurable qualifying action: any activity completion, course-progress advance, or login.
- Site-wide holiday calendar so institutional breaks do not break streaks.
- Moodle App support (read-only view) and full Privacy API implementation.
- No JavaScript; fully themeable through templates, pix icons and CSS tokens.
