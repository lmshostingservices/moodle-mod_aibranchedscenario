@mod @mod_aibranchedscenario @javascript
Feature: A learner can play a published branching scenario
  In order to practise a decision I will have to make at work
  As a learner
  I need to work through the scenario and reach a debrief

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity           | course | name                     | intro                  |
      | aibranchedscenario | C1     | Handling a hazard report | Report the fault fast. |
    And the sample scenario is published in the "Handling a hazard report" activity

  Scenario: A learner works through every decision and reaches the debrief
    Given I am on the "Handling a hazard report" "aibranchedscenario activity" page logged in as student1
    And I should see "The vibrating machine"
    And I should see "Report faults before the next shift"

    When I click on "Begin the scenario" "button"
    Then I should see "Sam waves you over to the shaking packer."

    When I click on "Stop the line and log the fault now." "button"
    Then I should see "The line stops. Sam looks relieved."
    And I should see "Why this mattered"

    When I click on "Continue" "button"
    Then I should see "You are standing at the guard rail with Sam."

    When I click on "Stop the line and log the fault now." "button"
    And I click on "Continue" "button"
    Then I should see "The line lead asks you directly what you decided."

    When I click on "Stop the line and log the fault now." "button"
    And I click on "Continue" "button"
    Then I should see "The night shift supervisor arrives for handover."

    When I click on "Stop the line and log the fault now." "button"
    And I click on "See how it ended" "button"
    Then I should see "The fault is fixed before anyone is hurt"
    And I should see "How you handled it"
    And I should see "Your decisions"
    And I should see "Write it down"

  Scenario: A learner who takes the poor option every time reaches the high risk ending
    Given I am on the "Handling a hazard report" "aibranchedscenario activity" page logged in as student1

    When I click on "Begin the scenario" "button"
    And I click on "Leave it for the next shift to deal with." "button"
    And I click on "Continue" "button"
    And I click on "Leave it for the next shift to deal with." "button"
    And I click on "Continue" "button"
    And I click on "Leave it for the next shift to deal with." "button"
    And I click on "Continue" "button"
    And I click on "Leave it for the next shift to deal with." "button"
    And I click on "See how it ended" "button"
    Then I should see "The fault runs into the night shift"
