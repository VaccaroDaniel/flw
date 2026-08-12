@mod @mod_contentdesigner @cdelement_chapter @chapter_bookmark  @javascript
Feature: Check content designer chapter element bookmark tool support

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
    And I am on the "Demo content" "contentdesigner activity" page logged in as teacher1
    And I click on "Content editor" "link"
    And I click on ".contentdesigner-addelement .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=chapter]" "css_element" in the ".modal-body" "css_element"
    And I set the following fields to these values:
      | Title          | First chapter |
      | Display title  | 1             |
      | Visibility     | Visible       |
      | Learning Tools | Enabled       |
    And I press "Create element"
    And I click on "Content editor" "link"
    And I click on ".contentdesigner-addelement .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=paragraph]" "css_element" in the ".modal-body" "css_element"
    And I set the following fields to these values:
      | Content  | Lorem Ipsum is simply dummy text of the printing and typesetting industry.|
      | Title    | Paragraph 01 |
    And I press "Create element"
    And I log out

  @javascript
  Scenario: Learning tool status checked in chapter element settings.
    Given I am on the "Demo content" "contentdesigner activity" page logged in as teacher1
    And I click on "Content Designer" "link"
    Then I should see "First chapter"
    Then I should see "Bookmark" in the ".toolbar-block" "css_element"
    Then I should see "Notes" in the ".toolbar-block" "css_element"
    And I click on "Content editor" "link"
    And I click on ".element-item[data-elementshortname=chapter] .actions-list li[data-action=edit] a" "css_element"
    And I set the following fields to these values:
      | Title          | First chapter - Learning tool disabled |
      | Learning Tools | Disabled                               |
    And I press "Update element"
    And I click on "Content Designer" "link"
    Then I should see "First chapter - Learning tool disabled"
    Then I should not see "Bookmark" in the ".toolbar-block" "css_element"
    Then I should not see "Notes" in the ".toolbar-block" "css_element"

  @javascript
  Scenario: Learning tool status checked in chapter element general settings.
    Given I log in as "admin"
    And I navigate to "Plugins > Activity modules > Content Designer" in site administration
    And I set the field "Learning Tools" to "Enabled"
    And I press "Save changes"
    And I log out
    And I am on the "Demo content" "contentdesigner activity" page logged in as teacher1
    And I click on "Content editor" "link"
    And I click on ".contentdesigner-addelement .fa-plus" "css_element"
    And I click on ".elements-list li[data-element=chapter]" "css_element" in the ".modal-body" "css_element"
    And I set the following fields to these values:
      | Title          | Second chapter |
      | Display title  | 1              |
      | Visibility     | Visible        |
    And I press "Create element"
    And I am on the "Demo content" "contentdesigner activity" page logged in as teacher1
    And I click on "Content Designer" "link"
    Then I should see "First chapter"
    Then I should see "Bookmark" in the ".chapters-list:nth-child(2) .toolbar-block .content-designer-learningtool-bookmark" "css_element"
    Then I should see "Notes" in the ".chapters-list:nth-child(2) .toolbar-block .content-designer-learningtool-note" "css_element"
    And I click on "Content editor" "link"
    And I click on ".chapters-list:nth-child(2) .element-item[data-elementshortname='chapter']  .actions-list li[data-action=edit] a" "css_element"
    And I set the following fields to these values:
      | Title          | Second chapter - Learning tool disabled |
      | Learning Tools | Disabled                                |
    And I press "Update element"
    And I click on "Content Designer" "link"
    Then I should see "Second chapter - Learning tool disabled"
    Then I should not see "Bookmark" in the ".chapters-list:nth-child(2) .toolbar-block" "css_element"
    Then I should not see "Notes" in the ".chapters-list:nth-child(2) .toolbar-block" "css_element"

  @javascript
  Scenario: Use bookmark tool for chapter element content.
    Given I am on the "Demo content" "contentdesigner activity" page logged in as student1
    Then I should see "Bookmark" in the ".toolbar-block" "css_element"
    # Add a bookmark.
    And I click on "Bookmark" "button" in the ".toolbar-block" "css_element"
    Then I should see "Bookmarked" in the ".toolbar-block" "css_element"
    Then I should see "This chapter bookmarked successfully and you can view the bookmarks under profile / learning tools / bookmarks."
    Then I follow "Profile" in the user menu
    And I click on "Bookmarks" "link"
    Then I should see "C1: Demo content | Acceptance test site | First chapter" in the ".course-item" "css_element"
    Then I should see "View Chapter" in the ".button-block" "css_element"
    Then I should see "Course 1 / General" in the ".category-item" "css_element"
    And I click on "View Chapter" "button" in the ".button-block" "css_element"
    Then I should see "Demo content"
    Then I should see "First chapter"
    Then I should see "Bookmarked" in the ".toolbar-block" "css_element"
    # Remove a bookmark.
    And I click on "Bookmarked" "button" in the ".toolbar-block" "css_element"
    Then I should see "This chapter bookmark removed and you can view the bookmarks under profile / learning tools / bookmarks."
    Then I follow "Profile" in the user menu
    And I click on "Bookmarks" "link"
    Then I should not see "C1: Demo content"
    Then I should not see "C1: Demo content | Acceptance test site | First chapter"
    Then I should not see "View Chapter"
    And I log out
