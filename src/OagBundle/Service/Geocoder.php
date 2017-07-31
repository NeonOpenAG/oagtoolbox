<?php

namespace OagBundle\Service;

class Geocoder extends AbstractAutoService {

  public function processUri($sometext) {
    // TODO implement non-fixture process
    return json_decode($this->getFixtureData(), true);
  }

  public function processString($sometext) {
    // TODO implement non-fixture process
    $uri = $this->getUri();

    $json = $this->getFixtureData();

    return json_decode($json, true);
  }

  public function processXML($contents) {
    // TODO implement non-fixture process
    return json_decode($this->getFixtureData(), true);
  }

  public function getName() {
    return 'geocoder';
  }

  public function getFixtureData() {
    $kernel = $this->getContainer()->get('kernel');
    $path = $kernel->locateResource('@OagBundle/Resources/fixtures/geocoder.json');
    $contents = file_get_contents($path);

    return $contents;
  }

  public function extractLocations($json) {
    $locations = array();
    foreach ($json as $projectObject) {
      $id = $projectObject['project_id'];
      $projLocations = $projectObject['locations'];
      $locations[$id] = $projLocations;
    }
    return $locations;
  }

}
