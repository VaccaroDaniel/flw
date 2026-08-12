@mod @mod_contentdesigner @element_appearance @javascript
Feature: Check content designer element options
  In order to create content elements of multiple responses
  As a teacher
  I need to add contentdesigner activities to courses
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And the following "activity" exists:
      | activity    | contentdesigner              |
      | name        | Demo content                 |
      | intro       | Contentdesigner Description  |
      | course      | C1                           |
    And I log out

  Scenario: Add contentdesigner elements.
    Given I am on the "Demo content" "contentdesigner activity" page logged in as teacher1
    Then I should see "Content editor"
    And I click on "Content editor" "link"
    Then ".contentdesigner-addelement .fa-plus" "css_element" should exist
    And I click on ".contentdesigner-addelement .fa-plus" "css_element"
    And I should see "Insert Element" in the ".modal-header" "css_element"
    And I should see "Chapter" in the ".modal-body" "css_element"
    And I should see "Heading" in the ".modal-body" "css_element"
    And I click on ".elements-list li[data-element=chapter]" "css_element" in the ".modal-body" "css_element"
    Then I should see "Chapter element settings"
    And I set the following fields to these values:
      | Title     | First chapter |
      | Visibility| Visible       |
    And I press "Create element"
    Then ".course-content-list .chapters-list" "css_element" should exist

    And I click on ".contentdesigner-addelement .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=heading]" "css_element" in the ".modal-body" "css_element"
    Then I should see "Heading element settings"
    And I set the following fields to these values:
      | Heading text  | Demo Url |
      | Heading URL   | https://example.com/|
      | Title         | First heading |
    And I press "Create element"
    Then I should see "First heading"
    Then ".course-content-list .chapters-list .chapter-elements-list .element-item" "css_element" should exist
    And I log out
    And I am on the "Demo content" "contentdesigner activity" page logged in as student1
    Then ".course-content-list .chapter-elements-list .element-item" "css_element" should exist
    Then I should see "Demo Url"

  Scenario: Edit contentdesigner element actions.
    Given I am on the "Demo content" "contentdesigner activity" page logged in as teacher1
    And I click on "Content editor" "link"
    And I click on ".contentdesigner-addelement .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=chapter]" "css_element" in the ".modal-body" "css_element"
    And I set the following fields to these values:
      | Title  | First chapter |
    And I press "Create element"
    And I click on ".contentdesigner-addelement .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=chapter]" "css_element" in the ".modal-body" "css_element"
    And I set the following fields to these values:
      | Title  | Second chapter |
    And I press "Create element"
    And I click on ".chapters-list:nth-child(1) .contentdesigner-addelement .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=heading]" "css_element" in the ".modal-body" "css_element"
    And I set the following fields to these values:
      | Heading text  | Heading 01 |
      | Title         | Heading 01 |
    And I press "Create element"
    And I click on ".chapters-list:nth-child(1) .contentdesigner-addelement[data-position=\"bottom\"] .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=heading]" "css_element" in the ".modal-body" "css_element"
    And I set the following fields to these values:
      | Heading text  | Heading 02 |
      | Title         | Heading 02 |
    And I press "Create element"
    And I click on ".chapters-list:nth-child(2) .contentdesigner-addelement .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=heading]" "css_element" in the ".modal-body" "css_element"
    And I set the following fields to these values:
      | Heading text  | Heading 03 |
      | Title         | Heading 03 |
    And I press "Create element"
    And I click on ".chapters-list:nth-child(2) .contentdesigner-addelement[data-position=\"bottom\"] .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=heading]" "css_element" in the ".modal-body" "css_element"
    And I set the following fields to these values:
      | Heading text  | Heading 04 |
      | Title         | Heading 04 |
    And I press "Create element"
    And I should see "Heading 01" in the ".course-content-list .chapters-list:nth-child(1) li.element-item:nth-child(1)" "css_element"
    And I should see "Heading 02" in the ".course-content-list .chapters-list:nth-child(1) li.element-item:nth-child(2)" "css_element"
    And I should see "Heading 03" in the ".course-content-list .chapters-list:nth-child(2) li.element-item:nth-child(1)" "css_element"
    And I should see "Heading 04" in the ".course-content-list .chapters-list:nth-child(2) li.element-item:nth-child(2)" "css_element"
    And I click on ".chapters-list:nth-child(1) li.element-item:nth-child(1) .action-item[data-action=edit] a" "css_element"
    Then I should see "Heading element settings"
    And I set the following fields to these values:
      | Heading text  | Heading one |
      | Title         | Heading one |
    And I click on "Update element" "button"
    And I should not see "Heading 01" in the ".chapters-list:nth-child(1) li.element-item:nth-child(1)" "css_element"
    And I should see "Heading one" in the ".course-content-list .chapters-list:nth-child(1) li.element-item:nth-child(1)" "css_element"
    And I click on ".chapters-list:nth-child(1) li.element-item:nth-child(1) .action-item[data-action=delete]" "css_element"
    Then I should see "Are you sure that you want to delete this heading element?" in the ".modal-body" "css_element"
    Then I click on "Yes" "button" in the ".modal-footer" "css_element"
    And I should not see "Heading one" in the ".chapters-list:nth-child(1) li.element-item:nth-child(1)" "css_element"
