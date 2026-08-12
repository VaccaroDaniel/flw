@mod @mod_contentdesigner @cdelement_chapter @chapter_notes  @javascript
Feature: Check content designer chapter element notes tool support
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
  Scenario: Use notes tool for chapter element content.
    Given I am on the "Demo content" "contentdesigner activity" page logged in as student1
    Then I should see "Notes" in the ".toolbar-block" "css_element"
    # Take a note.
    And I click on "Notes" "button" in the ".toolbar-block" "css_element"
    And I should see "Take notes" in the ".modal-title" "css_element"
    And I set the field "ltnoteeditor" to "Test note 1"
    And I press "Save changes"
    Then I should see "Notes added successfully and you can view the note under profile / learning tools / note."
    # Add second note.
    Then I should see "Notes" in the ".toolbar-block .content-designer-learningtool-note" "css_element"
    Then I should see "(1)" in the ".toolbar-block .content-designer-learningtool-note .badge-light" "css_element"
    And I click on "Notes" "button" in the ".toolbar-block" "css_element"
    And I should see "Take notes" in the ".modal-title" "css_element"
    And I set the field "ltnoteeditor" to "Test note 2"
    And I press "Save changes"
    Then I should see "Notes added successfully and you can view the note under profile / learning tools / note."
    Then I should see "Notes" in the ".toolbar-block" "css_element"
    Then I should see "(2)" in the ".toolbar-block .content-designer-learningtool-note .badge-light" "css_element"
    # Notes list page.
    Then I follow "Profile" in the user menu
    And I click on "Notes" "link"
    Then I should see "Course 1 / General / C1: Demo content | Acceptance test site | First chapter" in the ".card-body:nth-of-type(1) .title-block" "css_element"
    Then I should see "Course 1 / General / C1: Demo content | Acceptance test site | First chapter" in the ".card-body:nth-of-type(2) .title-block" "css_element"
    And I should see "Test note 1" in the ".card-body:nth-of-type(1)" "css_element"
    And I should see "Test note 2" in the ".card-body:nth-of-type(2)" "css_element"
    # Edit note on notes list page.
    And I click on ".edit-block a" "css_element" in the ".card-body:nth-of-type(1) .details-block" "css_element"
    And I set the field "noteeditor[text]" to "Test note 1 is edited"
    And I press "Save changes"
    Then I should see "Successfully edited"
    Then I should see "Test note 1 is edited" in the ".card-body:nth-of-type(1)" "css_element"
    # Delete note on notes list page.
    And I click on ".delete-block a" "css_element" in the ".card-body:nth-of-type(2) .details-block" "css_element"
    And "Confirm" "dialogue" should be visible
    Then I should see "Are you absolutely sure you want to completely delete the Note, including their Note and data?"
    And "Delete" "button" should exist in the "Confirm" "dialogue"
    When I click on "Delete" "button" in the "Confirm" "dialogue"
    And I wait until the page is ready
    Then I should see "Successfully deleted"
    And I should not see "Test note 2"
    And I click on "View Chapter" "button" in the ".btn-block" "css_element"
    Then I should see "Notes" in the ".toolbar-block .content-designer-learningtool-note" "css_element"
    Then I should see "(1)" in the ".toolbar-block .content-designer-learningtool-note .badge-light" "css_element"

  @javascript
  Scenario: Add notes tool for chapter element content.
    Given I am on the "Demo content" "contentdesigner activity" page logged in as student1
    Then I should see "Notes" in the ".toolbar-block" "css_element"
    # Take a note.
    And I click on "Notes" "button" in the ".toolbar-block" "css_element"
    And I should see "Take notes" in the ".modal-title" "css_element"
    And I set the field "ltnoteeditor" to "Test note 1"
    And I press "Save changes"
    Then I should see "Notes added successfully and you can view the note under profile / learning tools / note."
    # Add second note.
    Then I should see "Notes" in the ".toolbar-block .content-designer-learningtool-note" "css_element"
    Then I should see "(1)" in the ".toolbar-block .content-designer-learningtool-note .badge-light" "css_element"
    And I click on "Notes" "button" in the ".toolbar-block" "css_element"
    And I should see "Take notes" in the ".modal-title" "css_element"
    And I set the field "ltnoteeditor" to "Test note 2"
    And I press "Save changes"
    Then I should see "Notes added successfully and you can view the note under profile / learning tools / note."
    Then I should see "Notes" in the ".toolbar-block" "css_element"
    Then I should see "(2)" in the ".toolbar-block .content-designer-learningtool-note .badge-light" "css_element"
    Then I follow "Profile" in the user menu
    And I click on "Notes" "link"
    And I should see "Test note 1" in the ".card-body:nth-of-type(1)" "css_element"
    And I should see "Test note 2" in the ".card-body:nth-of-type(2)" "css_element"
    And I am on the "Demo content" "contentdesigner activity" page
    And I click on "Notes" "button" in the ".toolbar-block" "css_element"
    And I should see "Test note 2" in the ".modal-body .card-body:nth-of-type(1)" "css_element"
    And I should see "Test note 1" in the ".modal-body .card-body:nth-of-type(2)" "css_element"
    # Edit note on modal.
    And I click on ".edit-action a" "css_element" in the ".modal-body .card-body:nth-of-type(1) .action-block" "css_element"
    And I set the field "noteeditor[text]" to "Test note 2 is edited"
    And I press "Save changes"
    Then I should see "Successfully edited"
    And I click on "Notes" "button" in the ".toolbar-block" "css_element"
    Then I should see "Test note 2 is edited" in the ".modal-body .card-body:nth-of-type(1)" "css_element"
    # Delete note on modal.
    And I click on ".delete-action a" "css_element" in the ".modal-body .card-body:nth-of-type(2) .action-block" "css_element"
    And "Confirm" "dialogue" should be visible
    Then I should see "Are you absolutely sure you want to completely delete the Note, including their Note and data?"
    And "Delete" "button" should exist in the "Confirm" "dialogue"
    When I click on "Delete" "button" in the "Confirm" "dialogue"
    And I wait until the page is ready
    Then I should see "Successfully deleted"
    Then I should see "Notes" in the ".toolbar-block .content-designer-learningtool-note" "css_element"
    Then I should see "(1)" in the ".toolbar-block .content-designer-learningtool-note .badge-light" "css_element"
    And I click on "Notes" "button" in the ".toolbar-block" "css_element"
    And I should not see "Test note 1"
    And I should see "Test note 2 is edited" in the ".modal-body .card-body:nth-of-type(1)" "css_element"
    And I log out
