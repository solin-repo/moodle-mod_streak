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

  Scenario: Only one Solin Streaks activity is allowed per course
    Given I log in as "teacher1"
    When I add a "streak" activity to course "Course 1" section "1"
    And I set the following fields to these values:
      | Name | A second streak |
    And I press "Save and return to course"
    Then I should see "Only one Solin Streaks activity is allowed per course"
