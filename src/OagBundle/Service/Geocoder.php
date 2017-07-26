<?php

namespace OagBundle\Service;

class Geocoder extends AbstractAutoService {

  public function processUri($sometext) {
    return $this->processString();
  }

  public function processString($sometext) {
    $uri = $this->getUri();

    $json = $this->getFixtureData();

    return $json;
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

}
