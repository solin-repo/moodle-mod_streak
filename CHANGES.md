# Changelog

All notable changes to Solin Streaks (`mod_streak`) are documented here.

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
