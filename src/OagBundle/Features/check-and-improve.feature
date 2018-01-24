Feature: The check and improve page
  Test the various cases of the c&i page
  As a web user
  I need to be able to see the dockers are running and process data correctly.

@test-one-existing-classification
Scenario: 
    Given I am on the homepage
        When I attach the file "src/OagBundle/XMLTestFiles/test-one-existing-classification.xml" to "oag_file_documentName"
        And I press "Upload"
        Then I should see "IATI File created"
    Given I am on the homepage
        Then I click on the element with css selector "#checkandimprove"
        And I should see "Check and improve your data"


# @test-one-existing-location.xml
# @test-one-suggested-classification.xml
# @test-one-suggested-location.xml
# @test-multiple-suggested-classification.xml
# @test-multiple-suggested-location.xml
# @test-multiple-existing-classification.xml
# @test-multiple-existing-location.xml
# @test-multiple-existing-location-multiple-existing-categories.xml

# vim: set expandtab tabstop=4 shiftwidth=4 autoindent smartindent:
