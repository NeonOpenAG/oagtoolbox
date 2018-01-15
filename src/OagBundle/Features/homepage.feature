Feature: Site is up
  Just check the from page is a 200 and has valid(ish) content.
  As a web user
  I need to be able to see the font page and some DOM elements that are coreect.

Scenario: Check the front page loads.
    Given I am on the homepage
    Then the response status code should be 200
    And I should see an ".header-nav" element
    And I should see an "main" element
    And I should see an ".section" element
