@local @local_flwcupkp
Feature: C-UP-KP admin pages
  In order to manage C-UP-KP curriculum and evidence quality
  As an administrator
  I need the core C-UP-KP admin pages to be reachable in Moodle

  Scenario: Admin opens the C-UP-KP landing page
    When I am on the "site" "local_flwcupkp > admin" page logged in as "admin"
    Then I should see "FLW C-UP-KP"
    And I should see "Unit Setup Wizard"
    And I should see "Curriculum Manager"
    And I should see "Traceability report"

  Scenario: Admin opens the unit setup wizard
    When I am on the "site" "local_flwcupkp > setup" page logged in as "admin"
    Then I should see "Unit Setup Wizard"
    And I should see "Select course and unit"
    And I should see "Import package"
    And I should see "Activate unit"

  Scenario: Admin opens the curriculum relationship view
    When I am on the "site" "local_flwcupkp > curriculum" page logged in as "admin"
    Then I should see "Curriculum Manager"
    And I should see "Relationship view"
    And I should see "Bulk operations"
    And I should see "Curriculum graph"

  @javascript @accessibility
  Scenario: Admin curriculum page supports accessibility and keyboard navigation
    When I am on the "site" "local_flwcupkp > curriculum" page logged in as "admin"
    Then the "region-main" "region" should meet accessibility standards
    And I click on "Search" "field"
    And the focused element is "Search" "field"
    When I press the tab key
    Then the focused element is "Filter" "button"

  Scenario: Admin opens traceability and calibration reports
    When I am on the "site" "local_flwcupkp > traceability" page logged in as "admin"
    Then I should see "Traceability report"
    And I should see "Learner state"
    When I am on the "site" "local_flwcupkp > calibration" page logged in as "admin"
    Then I should see "Evidence calibration"
    And I should see "Mastery states"
