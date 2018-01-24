Feature: Site is up
  Just check the from page is a 200 and has valid(ish) content.
  As a web user
  I need to be able to see the font page and some DOM elements that are coreect.

@frontpage
Scenario: Check the front page loads.
    Given I am on the homepage
    And I should see an ".header-nav" element
    And I should see an "main" element
    And I should see an "section" element

@frontpageupload
Scenario: Upload a valid XML file
    Given I am on the homepage
    When I attach the file "src/OagBundle/XMLTestFiles/test-one-suggested-classification.xml" to "oag_file_documentName"
    And I press "Upload"
    Then I should see "IATI File created"
    And I should see an ".nav-options" element
    And I should see an "#current-file" element
