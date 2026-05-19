@local @local_groupmerge
Feature: Group merge member synchronisation
  As an editing teacher, when I configure group links,
  members of source groups are automatically added to the target group.

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
      | student1 | Student   | One      |
      | student2 | Student   | Two      |
      | student3 | Student   | Three    |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group A | C1     | GA       |
      | Group B | C1     | GB       |
      | Group C | C1     | GC       |
    And the following "group members" exist:
      | user     | group |
      | student1 | GA    |
      | student2 | GB    |

  @javascript
  Scenario: Adding a user to a source group propagates to target group
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to course participants
    And I set the field "Participants tertiary navigation" to "Merge Groups"
    # Create mapping: Group C <- Group A, Group B (Cover).
    And I click on "Add group link" "button"
    And I set the field "target group" to "Group C"
    And I set the field "source groups" to "Group A,Group B"
    And I set the field "Type" to "Cover"
    And I press "Save changes"
    # The existing members of source groups should now be in target group.
    When I am on the "Course 1" "groups" page
    And I set the field "groups" to "Group C (2)"
    Then the "members" select box should contain "Student One (student1@example.com)"
    And the "members" select box should contain "Student Two (student2@example.com)"

  @javascript
  Scenario: Adding a new member to source group after mapping creation syncs to target
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to course participants
    And I set the field "Participants tertiary navigation" to "Merge Groups"
    # Create mapping: Group C <- Group A (Cover).
    And I click on "Add group link" "button"
    And I set the field "target group" to "Group C"
    And I set the field "source groups" to "Group A"
    And I set the field "Type" to "Cover"
    And I press "Save changes"
    # Now add student3 to Group A via group management.
    And I am on the "Course 1" "groups" page
    And I set the field "groups" to "Group A (1)"
    And I press "Add/remove users"
    And I set the field "addselect" to "Student Three (student3@example.com)"
    And I press "Add"
    And I press "Back to groups"
    # Verify student3 is now also in Group C.
    When I set the field "groups" to "Group C (2)"
    Then the "members" select box should contain "Student Three (student3@example.com)"
