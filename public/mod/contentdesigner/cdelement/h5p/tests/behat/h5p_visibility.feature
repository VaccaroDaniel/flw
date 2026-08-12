@mod @mod_contentdesigner @cdelement_h5p  @javascript @core_h5p @_file_upload @_switch_iframe
Feature: Check content designer h5p element settings
  In order to content elements settings of multiple responses
  As a teacher
  I need to add contentdesigner activities to courses
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And the following "permission overrides" exist:
      | capability                 | permission | role           | contextlevel | reference |
      | moodle/h5p:updatelibraries | Allow      | editingteacher | System       |           |
    And the following "activity" exists:
      | activity    | contentdesigner              |
      | name        | Demo content                 |
      | intro       | Contentdesigner Description  |
      | course      | C1                           |
    And the following "contentbank content" exist:
      | contextlevel | reference | contenttype     | user     | contentname         | filepath                           |
      | Course       | C1        | contenttype_h5p | admin    | ipsums.h5p          | /h5p/tests/fixtures/ipsums.h5p     |
    And I log out

  Scenario: Add a h5p element
    Given I am on the "Demo content" "contentdesigner activity" page logged in as teacher1
    And I click on "Content editor" "link"
    And I click on ".contentdesigner-addelement .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=h5p]" "css_element" in the ".modal-body" "css_element"
    And I click on "Add..." "button" in the "Package file" "form_row"
    And I select "Content bank" repository in file picker
    And I click on "ipsums.h5p" "file" in repository content area
    And I click on "Link to the file" "radio"
    And I click on "Select this file" "button"
    And I set the following fields to these values:
      | Mandatory     | No                 |
      | Title         | Test H5p           |
    And I press "Create element"
    And I click on ".contentdesigner-addelement[data-position=\"bottom\"] .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=heading]" "css_element" in the ".modal-body" "css_element"
    And I set the following fields to these values:
      | Heading text  | Heading 01        |
      | Title         | Heading 01         |
    And I press "Create element"
    And I click on "Content Designer" "link"
    Then I should see "Heading 01"
    And I wait "5" seconds
    Then ".h5p-element-instance" "css_element" should exist
    And I click on "Content editor" "link"
    And I click on ".chapters-list:nth-child(1) li.element-item:nth-child(1) .action-item[data-action=edit] a" "css_element"
    And I set the following fields to these values:
    | Mandatory     | Yes                 |
    And I press "Update element"
    And I click on "Content Designer" "link"
    Then I should not see "Heading 01"
