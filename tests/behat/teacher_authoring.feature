@mod @mod_aibranchedscenario
Feature: A teacher can add a branching scenario and open the authoring wizard
  In order to build a branching scenario from my own source material
  As a teacher
  I need to add the activity and reach the six step authoring wizard

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  Scenario: A teacher adds the activity to a course
    Given I am on the "Course 1" course page logged in as teacher1
    When I add a "AI Branched Scenario" activity to course "Course 1" section "1" and I fill the form with:
      | Activity name | Handling a hazard report |
      | Description   | Report the fault fast.   |
    Then I should see "Handling a hazard report"

  Scenario: The authoring wizard offers all six steps
    Given the following "activities" exist:
      | activity            | course | name                     | intro                  |
      | aibranchedscenario  | C1     | Handling a hazard report | Report the fault fast. |
    And I am on the "Handling a hazard report" "aibranchedscenario activity" page logged in as teacher1
    When I follow "Edit scenario"
    Then I should see "Source"
    And I should see "The scene"
    And I should see "The challenge"
    And I should see "The people"
    And I should see "Design"
    And I should see "Build and publish"

  Scenario: An unpublished scenario tells a learner there is nothing to play yet
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity            | course | name                     |
      | aibranchedscenario  | C1     | Handling a hazard report |
    When I am on the "Handling a hazard report" "aibranchedscenario activity" page logged in as student1
    Then I should see "This scenario has not been published yet."
