Feature: Dockers are running correctly
  Check the 4 dockers are started and process data correctly
  As a shell user
  I need to be able to see the dockers are running and process data correctly.

@dockers
Scenario: Check the four dockers are running
    Given I am in the group docker
    When check if the docker is running with the following:
      | openag_cove      |
      | openag_nerserver |
      | openag_geocoder  |
      | openag_dportal   |
    Then I should get "true"

@dockers-cove
Scenario: Upload a good xml file
    Given I am in the group docker
    # When I run "cat src/OagBundle/XMLTestFiles/ | docker exec -i --env FILENAME='test.xml' openag_cove /usr/local/bin/process.sh"
    When I pass the cove docker the following:
        | src/OagBundle/XMLTestFiles/test-all.xml                                                     |
        | src/OagBundle/XMLTestFiles/test-multiple-existing-classification.xml                        |
        | src/OagBundle/XMLTestFiles/test-multiple-existing-location-multiple-existing-categories.xml |
        | src/OagBundle/XMLTestFiles/test-multiple-existing-location.xml                              |
        | src/OagBundle/XMLTestFiles/test-multiple-suggested-classification.xml                       |
        | src/OagBundle/XMLTestFiles/test-multiple-suggested-location.xml                             |
        | src/OagBundle/XMLTestFiles/test-no-suggested-or-existing.xml                                |
        | src/OagBundle/XMLTestFiles/test-one-existing-classification.xml                             |
        | src/OagBundle/XMLTestFiles/test-one-existing-location.xml                                   |
        | src/OagBundle/XMLTestFiles/test-one-suggested-classification.xml                            |
        | src/OagBundle/XMLTestFiles/test-one-suggested-location.xml                                  |
    Then I should get "</iati-activities>"
    # Senario: Upload a bad file

# vim: set expandtab tabstop=4 shiftwidth=4 autoindent smartindent:
