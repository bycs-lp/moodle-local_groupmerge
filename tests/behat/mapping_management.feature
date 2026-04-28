@local @local_groupmerge
Feature: Group merge mapping management
  As an editing teacher I can create, edit and delete group merge mappings
  so that group memberships are synchronised automatically.

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
  Scenario: Teacher can access the groupmerge config page
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I navigate to course participants
    And I set the field "Participants tertiary navigation" to "Merge groups"
    Then I should see "Group links"
    And I should see "Add group link"

  @javascript
  Scenario: User without manage capability cannot see groupmerge navigation
    Given the following "users" exist:
      | username  | firstname    | lastname |
      | neteacher | Non-editing  | Teacher  |
    And the following "course enrolments" exist:
      | user      | course | role    |
      | neteacher | C1     | teacher |
    When I log in as "neteacher"
    And I am on "Course 1" course homepage
    And I navigate to course participants
    Then the "Participants tertiary navigation" select box should not contain "Merge groups"

  @javascript
  Scenario: Teacher creates a new cover mapping
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to course participants
    And I set the field "Participants tertiary navigation" to "Merge groups"
    When I click on "Add group link" "button"
    And I set the field "target group" to "Group C"
    And I set the field "source groups" to "Group A,Group B"
    And I set the field "Type" to "Cover"
    And I press "Save changes"
    Then I should see "Group A" in the "#local_groupmerge-mapping-table" "css_element"
    And I should see "Group B" in the "#local_groupmerge-mapping-table" "css_element"
    And I should see "Group C" in the "#local_groupmerge-mapping-table" "css_element"

  @javascript
  Scenario: Teacher deletes a group mapping
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to course participants
    And I set the field "Participants tertiary navigation" to "Merge groups"
    # First create a mapping.
    And I click on "Add group link" "button"
    And I set the field "target group" to "Group C"
    And I set the field "source groups" to "Group A"
    And I set the field "Type" to "Cover"
    And I press "Save changes"
    And I should see "Group C" in the "#local_groupmerge-mapping-table" "css_element"
    # Now delete it.
    When I click on "Delete" "link" in the "#local_groupmerge-mapping-table" "css_element"
    And I click on "Delete" "button" in the "Delete group link" "dialogue"
    Then I should see "No group links defined yet."

  @javascript
  Scenario: Not enough groups shows warning message
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 2 | C2        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C2     | editingteacher |
    And the following "groups" exist:
      | name       | course | idnumber |
      | Only Group | C2     | OG       |
    When I log in as "teacher1"
    And I am on "Course 2" course homepage
    And I navigate to course participants
    And I set the field "Participants tertiary navigation" to "Merge groups"
    Then I should see "requires at least 2 groups"
    And "Add group link" "button" should not exist

  @javascript
  Scenario: Teacher edits an existing group mapping
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to course participants
    And I set the field "Participants tertiary navigation" to "Merge groups"
    # First create a mapping: Group C <- Group A (Cover).
    And I click on "Add group link" "button"
    And I set the field "target group" to "Group C"
    And I set the field "source groups" to "Group A"
    And I set the field "Type" to "Cover"
    And I press "Save changes"
    And I should see "Group A" in the "#local_groupmerge-mapping-table" "css_element"
    And I should see "Group C" in the "#local_groupmerge-mapping-table" "css_element"
    # Now edit the mapping: add Group B as additional source and change type to Subset.
    When I click on "Edit" "link" in the "#local_groupmerge-mapping-table" "css_element"
    And I set the field "source groups" to "Group A,Group B"
    And I set the field "Type" to "Subset"
    And I press "Save changes"
    Then I should see "Group A" in the "#local_groupmerge-mapping-table" "css_element"
    And I should see "Group B" in the "#local_groupmerge-mapping-table" "css_element"
    And I should see "Group C" in the "#local_groupmerge-mapping-table" "css_element"
