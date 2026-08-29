@mod @mod_streak
Feature: Solin Streaks activity
  In order to motivate regular practice
  As a teacher and learner
  I need to add a Solin Streaks activity, see the leaderboard, and control my visibility

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
      | student1 | Student   | One      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name       | course | idnumber | cadenceperiod | qualifymode   |
      | streak   | Keep it up | C1     | streak1  | daily         | anycompletion |

  Scenario: A learner sees the leaderboard and their own streak inline on the course page
    When I am on the "Course 1" course page logged in as student1
    Then I should see "Streak leaderboard"
    And I should see "Student One"

  Scenario: A learner can opt out of, and back into, the leaderboard
    When I am on the "Course 1" course page logged in as student1
    And I follow "Hide me from the leaderboard"
    Then I should see "You have opted out"
    And I should see "Show me on the leaderboard"
    When I follow "Show me on the leaderboard"
    Then I should see "Hide me from the leaderboard"

  Scenario: The course activity index lists the Solin Streaks activities
    When I am on the "Course 1" "streak index" page logged in as "student1"
    Then I should see "Keep it up"

  Scenario: Viewing the course activity index is logged as module access
    Given I am on the "Course 1" "streak index" page logged in as "student1"
    And I log out
    When I am on the "System logs report" page logged in as "admin"
    And I set the field "id" to "Course 1"
    And I press "Get these logs"
    Then I should see "Course module instance list viewed"

  Scenario: Only one Solin Streaks activity is allowed per course
    Given I log in as "teacher1"
    When I add a "streak" activity to course "Course 1" section "1"
    And I set the following fields to these values:
      | Name | A second streak |
    And I press "Save and return to course"
    Then I should see "Only one Solin Streaks activity is allowed per course"

  @javascript
  Scenario: Every site setting is offered as the default on a new activity
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 2 | C2        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C2     | editingteacher |
    And the following config values are set as admin:
      | cadenceperiod    | weekly  | mod_streak |
      | cadencegoal      | 4       | mod_streak |
      | qualifymode      | login   | mod_streak |
      | modfilterexclude | forum   | mod_streak |
      | enddatemode      | none    | mod_streak |
      | freezerate       | 7       | mod_streak |
      | freezecap        | 3       | mod_streak |
      | rewardbreaks     | 1       | mod_streak |
      | reminderhour     | 21      | mod_streak |
      | earlyheadsup     | 1       | mod_streak |
      | excludestaff     | 0       | mod_streak |
      | activedays       | 1111100 | mod_streak |
    When I am on the "Course 2" course page logged in as teacher1
    And I add a "streak" activity to course "Course 2" section "1"
    And I expand all fieldsets
    Then the field "Streak period" matches value "Weekly"
    And the field "Qualifying days per period" matches value "4"
    And the field "What counts as a day" matches value "Login only"
    And the field "Activities that do not count" matches value "forum"
    And the field "When the streak stops" matches value "Never ends"
    And the field "Freeze accrual rate" matches value "7"
    And the field "Maximum freezes" matches value "3"
    And the field "Let practice during a break still count" matches value "1"
    And the field "Reminder hour" matches value "21:00"
    And the field "Send an early heads-up" matches value "1"
    And the field "Leave staff off the leaderboard" matches value "0"
    And the field "activeday5" matches value "1"
    And the field "activeday6" matches value "0"
    And the field "activeday7" matches value "0"

  @javascript
  Scenario: An activity value overrides the site setting and survives a save
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 3 | C3        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C3     | editingteacher |
    And the following config values are set as admin:
      | freezerate   | 7       | mod_streak |
      | reminderhour | 21      | mod_streak |
      | activedays   | 1111111 | mod_streak |
    When I am on the "Course 3" course page logged in as teacher1
    And I add a "streak" activity to course "Course 3" section "1"
    And I expand all fieldsets
    And I set the following fields to these values:
      | Name                | Overridden streak |
      | Freeze accrual rate | 2                 |
      | Reminder hour       | 08:00             |
    And I set the field "id_activeday6" to "0"
    And I set the field "id_activeday7" to "0"
    And I press "Save and return to course"
    And I am on the "Overridden streak" "streak activity editing" page
    And I expand all fieldsets
    Then the field "Freeze accrual rate" matches value "2"
    And the field "Reminder hour" matches value "08:00"
    And the field "activeday6" matches value "0"
    And the field "activeday7" matches value "0"
    And the field "activeday1" matches value "1"

  @javascript
  Scenario: Excluded roles are stored and read back through the form
    When I am on the "Keep it up" "streak activity editing" page logged in as teacher1
    And I expand all fieldsets
    And I set the field "Roles to leave off the leaderboard" to "Non-editing teacher"
    And I press "Save and display"
    And I am on the "Keep it up" "streak activity editing" page
    And I expand all fieldsets
    Then the field "Roles to leave off the leaderboard" matches value "Non-editing teacher"

  @javascript
  Scenario: Days that count survive a save and reopen
    When I am on the "Keep it up" "streak activity editing" page logged in as teacher1
    And I expand all fieldsets
    And I set the field "id_activeday7" to "0"
    And I press "Save and display"
    And I am on the "Keep it up" "streak activity editing" page
    And I expand all fieldsets
    Then the field "activeday7" matches value "0"
    And the field "activeday1" matches value "1"
