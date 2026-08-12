@mod @mod_contentdesigner @cdelement_heading  @javascript
Feature: Check content designer header element settings
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
    And the following "activity" exists:
      | activity    | contentdesigner              |
      | name        | Demo content                 |
      | intro       | Contentdesigner Description  |
      | course      | C1                           |
    And I log out

  Scenario: Add a heading element workflow
    Given I am on the "Demo content" "contentdesigner activity" page logged in as teacher1
    And I click on "Content editor" "link"
    And I click on ".contentdesigner-addelement .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=heading]" "css_element" in the ".modal-body" "css_element"
    And I set the following fields to these values:
      | Heading URL   | https://www.example.com |
      | Heading       | Main heading (h2)  |
      | Heading text  | Heading 01        |
      | Title         | Heading 01         |
      | Target        | Open a same window |
      | Horizontal Alignment |  Right      |
      | Vertical Alignment   |  Middle     |
    And I press "Create element"
    And I click on "Content Designer" "link"
    Then I should see "Heading 01" in the ".chapter-elements-list li.element-item" "css_element"
    Then ".chapter-elements-list li.element-item h2.element-heading.hl-right.vl-middle" "css_element" should exist
    Then ".chapter-elements-list li.element-item a[target=_self]" "css_element" should exist
    And I click on "Content editor" "link"
    And I click on ".chapters-list:nth-child(1) li.element-item:nth-child(1) .action-item[data-action=edit] a" "css_element"
    And I set the following fields to these values:
      | Heading URL   | https://www.example.com |
      | Heading text  | Heading First           |
      | Title         | Heading First           |
      | Target        | Open a new window     |
      | Horizontal Alignment |  Center          |
      | Vertical Alignment   |  Top         |
    And I press "Update element"
    And I click on "Content Designer" "link"
    Then I should not see "Heading 01" in the ".chapter-elements-list li.element-item" "css_element"
    Then I should see "Heading First" in the ".chapter-elements-list li.element-item" "css_element"
    Then ".chapter-elements-list li.element-item h2.element-heading.hl-center.vl-top" "css_element" should exist
    Then ".chapter-elements-list li.element-item a[target=_blank]" "css_element" should exist
